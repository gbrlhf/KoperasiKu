"""PIC D->E SKU/day dense feed with zero-sale days and honest stockout unknown.

Read analytics, produce CSV consumable by PIC E; read model only, no forecasting.
"""
import argparse
import csv
from datetime import date, datetime
from pathlib import Path
from zoneinfo import ZoneInfo
import os


def export(dsn, start:date, end:date, output:Path):
    import psycopg
    if start > end:
        raise ValueError('Forecast start must precede end')
    sql="""
      SELECT p.sku, gs.day::date AS local_date,
             COALESCE(s.units,0)::int AS units_sold,
             CASE WHEN stock.snapshot_key IS NULL THEN NULL
                  WHEN stock.available=0 THEN true ELSE false END AS stockout_possible,
             p.lead_time_days
      FROM analytics.dim_product p
      CROSS JOIN LATERAL generate_series(%s::date,%s::date,interval '1 day') AS gs(day)
      LEFT JOIN (SELECT product_key,local_date,SUM(quantity) AS units
                 FROM analytics.fact_sales WHERE local_date BETWEEN %s AND %s
                 GROUP BY product_key,local_date) s
        ON s.product_key=p.product_key AND s.local_date=gs.day::date
      LEFT JOIN analytics.fact_stock_daily stock
        ON stock.product_key=p.product_key AND stock.snapshot_date=gs.day::date
      ORDER BY p.sku,gs.day
    """
    output.parent.mkdir(parents=True,exist_ok=True)
    with psycopg.connect(dsn) as conn,conn.cursor() as cur:
        conn.execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY')
        latest = conn.execute("SELECT data_quality FROM analytics.etl_runs WHERE status='SUCCEEDED' ORDER BY finished_at DESC LIMIT 1").fetchone()
        quality = latest[0] if latest else {}
        if not (quality.get('range_start', '9999') <= str(start) <= str(end) <= quality.get('range_end', '0000')):
            raise ValueError('Forecast range is not covered by a successful ETL; unknown history is not zero sales')
        cur.execute(sql,(start,end,start,end))
        return write_feed(cur, output)


def write_feed(cur, output):
    with output.open('w',encoding='utf-8',newline='') as file:
        writer=csv.writer(file)
        writer.writerow(['sku','local_date','units_sold','stockout_possible','lead_time_days'])
        rows=0
        for sku,day,units,stockout,lead_time in cur:
            writer.writerow([sku,day.isoformat(),units,'' if stockout is None else str(stockout).lower(),
                             '' if lead_time is None else lead_time])
            rows+=1
    return rows


def main():
    parser=argparse.ArgumentParser()
    today=datetime.now(ZoneInfo('Asia/Jakarta')).date()
    parser.add_argument('--start',required=True)
    parser.add_argument('--end',default=today.isoformat())
    parser.add_argument('--output',default='forecast_input_pic_e.csv')
    opts=parser.parse_args()
    start,end=date.fromisoformat(opts.start),date.fromisoformat(opts.end)
    if start>end:parser.error('start > end')
    from dotenv import load_dotenv
    load_dotenv(Path(__file__).parents[1] / '.env')
    if not os.getenv('PICD_WAREHOUSE_DSN'):
        parser.error('Set PICD_WAREHOUSE_DSN')
    rows=export(os.environ['PICD_WAREHOUSE_DSN'],start,end,Path(opts.output))
    print('Wrote',rows,'SKU/day rows to',opts.output)

if __name__=='__main__':main()
