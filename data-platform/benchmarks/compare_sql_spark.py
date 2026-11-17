"""Deterministic grouped sales benchmark. Run with PICD_BENCHMARK_DSN.

Generation is included in query timing for both engines. Memory is the benchmark
process and local Spark process tree sampled by psutil; PostgreSQL server memory
must be measured separately on its host and is explicitly reported as unavailable.
"""
import argparse
import json
import os
import platform
import statistics
import threading
import time
from pathlib import Path
import psutil
import psycopg
from pyspark.sql import SparkSession, functions as F

SQL = '''SELECT id %% 100 AS product, id %% 365 AS day,
 SUM(id %% 5 + 1)::bigint AS units,
 SUM((id %% 5 + 1) * (id %% 90 + 10) * 100)::bigint AS revenue,
 SUM((id %% 5 + 1) * (id %% 90 + 10) * 70)::bigint AS cogs
 FROM generate_series(1,%s) g(id) WHERE id %% 10 != 0
 GROUP BY 1,2 ORDER BY 1,2'''

def measured(call):
    done = threading.Event()
    peak = [0]
    def sample():
        root = psutil.Process()
        while not done.is_set():
            total = 0
            for process in [root, *root.children(recursive=True)]:
                try:
                    total += process.memory_info().rss
                except psutil.Error:
                    pass
            peak[0] = max(peak[0], total)
            done.wait(0.02)
    sampler = threading.Thread(target=sample, daemon=True)
    sampler.start()
    begin = time.perf_counter()
    try:
        value = call()
        return value, time.perf_counter() - begin, peak[0]
    finally:
        done.set()
        sampler.join()

def bench(sizes, dsn, repeats):
    spark = SparkSession.builder.master('local[2]').appName('PICD sales benchmark').config('spark.sql.shuffle.partitions','4').getOrCreate()
    spark.sparkContext.setLogLevel('ERROR')
    results = []
    try:
        with psycopg.connect(dsn) as pg:
            pg.execute('SET TRANSACTION READ ONLY')
            pg_version = pg.execute('SHOW server_version').fetchone()[0]
            spark.range(100).count()
            for size in sizes:
                sql_times, spark_times, samples = [], [], []
                def spark_query():
                    rows = spark.range(1,size+1).where('id % 10 != 0').selectExpr(
                        'id % 100 AS product', 'id % 365 AS day', 'id % 5 + 1 AS qty', 'id % 90 + 10 AS price')
                    grouped = rows.groupBy('product','day').agg(F.sum('qty').alias('units'),
                        F.sum(F.col('qty') * F.col('price') * 100).alias('revenue'),
                        F.sum(F.col('qty') * F.col('price') * 70).alias('cogs')).orderBy('product','day')
                    return [tuple(row) for row in grouped.collect()]
                for iteration in range(repeats):
                    sql_rows, sql_time, sql_memory = measured(lambda: pg.execute(SQL,(size,)).fetchall())
                    spark_rows, spark_time, spark_memory = measured(spark_query)
                    if sql_rows != spark_rows:
                        raise AssertionError(f'Aggregation mismatch for {size} rows')
                    sql_times.append(sql_time)
                    spark_times.append(spark_time)
                    samples.append({'iteration':iteration+1,'sql_seconds':sql_time,'spark_seconds':spark_time,
                        'client_tree_peak_rss_bytes_sql_phase':sql_memory,'client_tree_peak_rss_bytes_spark_phase':spark_memory})
                results.append({'input_rows':size,'accepted_rows':size-size//10,'output_groups':len(sql_rows),
                    'match':True,'sql_median_seconds':statistics.median(sql_times),
                    'spark_median_seconds':statistics.median(spark_times),'samples':samples})
            return {'method':'Grouped synthetic completed sales by product/day; generation included; integer rupiah',
                'repeats':repeats,'warmup':'Spark range count before measured queries',
                'host':platform.platform(),'python':platform.python_version(),'spark':spark.version,
                'postgresql':pg_version,'cpu_count':os.cpu_count(),'spark_threads':2,
                'memory_scope':'Client Python and local Spark process-tree RSS, shared pages may be counted more than once',
                'postgres_server_peak_rss_bytes':None,'results':results}
    finally:
        spark.stop()

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--sizes',default='10000,100000,1000000')
    parser.add_argument('--repeats',type=int,default=3)
    parser.add_argument('--output',default='benchmark_picd.json')
    args = parser.parse_args()
    sizes = [int(value) for value in args.sizes.split(',')]
    if args.repeats < 1 or any(size < 1 for size in sizes): parser.error('Sizes and repeats must be positive')
    report = bench(sizes,os.environ['PICD_BENCHMARK_DSN'],args.repeats)
    Path(args.output).write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps(report,indent=2))
