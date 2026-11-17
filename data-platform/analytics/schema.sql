-- PIC D owned, PostgreSQL 15+. Does not modify PIC B/C OLTP tables.
CREATE SCHEMA IF NOT EXISTS analytics;
CREATE TABLE IF NOT EXISTS analytics.dim_date (
 date_key integer PRIMARY KEY, calendar_date date NOT NULL UNIQUE,
 iso_week integer NOT NULL, month integer NOT NULL, year integer NOT NULL
);
CREATE TABLE IF NOT EXISTS analytics.dim_product (
 product_key text PRIMARY KEY, sku text NOT NULL, name text,
 category text, lead_time_days integer, updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS analytics.dim_member (
 member_key text PRIMARY KEY, department text, updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS analytics.etl_runs (
 run_id uuid PRIMARY KEY, status text NOT NULL CHECK(status IN ('RUNNING','SUCCEEDED','FAILED')),
 started_at timestamptz NOT NULL DEFAULT now(), finished_at timestamptz,
 watermark timestamptz, source_rows integer NOT NULL DEFAULT 0,
 output_rows integer NOT NULL DEFAULT 0, rejected_rows integer NOT NULL DEFAULT 0,
 data_quality jsonb NOT NULL DEFAULT '{}'::jsonb, error_message text
);
CREATE INDEX IF NOT EXISTS idx_picd_etl_success ON analytics.etl_runs(finished_at DESC) WHERE status='SUCCEEDED';
CREATE TABLE IF NOT EXISTS analytics.fact_sales (
 order_item_key text PRIMARY KEY, order_id text NOT NULL,
 date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 local_date date NOT NULL, completed_at timestamptz NOT NULL,
 product_key text NOT NULL REFERENCES analytics.dim_product(product_key),
 member_key text NOT NULL REFERENCES analytics.dim_member(member_key),
 quantity integer NOT NULL CHECK(quantity > 0), revenue numeric(18,2) NOT NULL,
 cogs numeric(18,2), cogs_complete boolean NOT NULL,
 channel text, payment_choice text, is_historical boolean NOT NULL DEFAULT false
);
CREATE INDEX IF NOT EXISTS idx_picd_sales_date ON analytics.fact_sales(local_date);
CREATE INDEX IF NOT EXISTS idx_picd_sales_product ON analytics.fact_sales(product_key,local_date);
CREATE TABLE IF NOT EXISTS analytics.fact_cash_receipts (
 payment_id text PRIMARY KEY, date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 local_date date NOT NULL, verified_at timestamptz NOT NULL,
 verified_amount numeric(18,2) NOT NULL CHECK(verified_amount > 0),
 method text, source_type text
);
CREATE INDEX IF NOT EXISTS idx_picd_cash_date ON analytics.fact_cash_receipts(local_date);
-- Idempotent upgrade of the starter warehouse. Null identifies old, unclassified rows
-- until the first successful ETL refresh populates all three components.
ALTER TABLE analytics.fact_cash_receipts ADD COLUMN IF NOT EXISTS advance_amount numeric(18,2);
ALTER TABLE analytics.fact_cash_receipts ADD COLUMN IF NOT EXISTS sale_amount numeric(18,2);
ALTER TABLE analytics.fact_cash_receipts ADD COLUMN IF NOT EXISTS receivable_amount numeric(18,2);
CREATE TABLE IF NOT EXISTS analytics.fact_operating_expenses (
 expense_id text PRIMARY KEY, date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 local_date date NOT NULL, amount numeric(18,2) NOT NULL CHECK(amount >= 0), category text
);
CREATE INDEX IF NOT EXISTS idx_picd_exp_date ON analytics.fact_operating_expenses(local_date);
CREATE TABLE IF NOT EXISTS analytics.fact_credit_issuance (
 order_id text PRIMARY KEY, date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 local_date date NOT NULL, original_amount numeric(18,2) NOT NULL CHECK(original_amount >= 0)
);
CREATE INDEX IF NOT EXISTS idx_picd_credit_issuance_date ON analytics.fact_credit_issuance(local_date);
CREATE TABLE IF NOT EXISTS analytics.fact_stock_daily (
 snapshot_key text PRIMARY KEY, snapshot_date date NOT NULL,
 date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 product_key text NOT NULL REFERENCES analytics.dim_product(product_key),
 on_hand integer NOT NULL CHECK(on_hand >= 0),
 reserved integer NOT NULL CHECK(reserved >= 0),
 available integer NOT NULL CHECK(available >= 0),
 UNIQUE(snapshot_date, product_key)
);
CREATE INDEX IF NOT EXISTS idx_picd_stock_day ON analytics.fact_stock_daily(snapshot_date);
CREATE TABLE IF NOT EXISTS analytics.fact_receivables_daily (
 snapshot_key text PRIMARY KEY, snapshot_date date NOT NULL,
 date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 member_key text NOT NULL REFERENCES analytics.dim_member(member_key),
 outstanding_amount numeric(18,2) NOT NULL CHECK(outstanding_amount >= 0),
 overdue_amount numeric(18,2) NOT NULL CHECK(overdue_amount >= 0),
 open_invoices integer NOT NULL CHECK(open_invoices >= 0),
 UNIQUE(snapshot_date, member_key)
);
CREATE INDEX IF NOT EXISTS idx_picd_receivable_day ON analytics.fact_receivables_daily(snapshot_date);
-- Invoice grain companion used to compute aging buckets without double-counting member snapshots.
CREATE TABLE IF NOT EXISTS analytics.fact_receivable_aging_daily (
 snapshot_key text PRIMARY KEY, snapshot_date date NOT NULL,
 date_key integer NOT NULL REFERENCES analytics.dim_date(date_key),
 receivable_key text NOT NULL, member_key text NOT NULL REFERENCES analytics.dim_member(member_key),
 due_date date NOT NULL, outstanding_amount numeric(18,2) NOT NULL CHECK(outstanding_amount >= 0),
 days_overdue integer NOT NULL,
 UNIQUE(snapshot_date,receivable_key)
);
CREATE INDEX IF NOT EXISTS idx_picd_aging_day ON analytics.fact_receivable_aging_daily(snapshot_date);
-- Storage permissions: request DBA to grant SELECT only on public and DML only on analytics.

ALTER TABLE analytics.fact_sales ADD COLUMN IF NOT EXISTS before_stock_cutoff boolean;
