-- Run as database owner after B/C and analytics migrations. Group roles carry no credentials.
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='picd_source_reader') THEN
        CREATE ROLE picd_source_reader NOLOGIN;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='picd_warehouse_writer') THEN
        CREATE ROLE picd_warehouse_writer NOLOGIN;
    END IF;
END $$;
GRANT USAGE ON SCHEMA public TO picd_source_reader;
DO $$ DECLARE source_table text; BEGIN
    FOREACH source_table IN ARRAY ARRAY['orders','order_items','payments','receivables',
        'receivable_payment_allocations','inventory_balances','products','members',
        'operating_expenses','import_batches','categories','suppliers'] LOOP
        IF to_regclass('public.' || source_table) IS NOT NULL THEN
            EXECUTE format('GRANT SELECT ON TABLE public.%I TO picd_source_reader',source_table);
        END IF;
    END LOOP;
END $$;
GRANT USAGE ON SCHEMA analytics TO picd_warehouse_writer;
GRANT SELECT,INSERT,UPDATE,DELETE ON ALL TABLES IN SCHEMA analytics TO picd_warehouse_writer;
-- DBA creates separate LOGIN roles and grants membership in the appropriate group.
-- Reapply after new source or warehouse tables are introduced.
