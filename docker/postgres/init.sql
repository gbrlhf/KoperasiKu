SELECT 'CREATE DATABASE koperadata_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'koperadata_test')\gexec
