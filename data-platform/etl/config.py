"""Configuration is explicit: no built-in production credentials or financial defaults."""
from dataclasses import dataclass
from pathlib import Path
import os

from dotenv import load_dotenv

load_dotenv(Path(__file__).parents[1] / '.env')

@dataclass(frozen=True)
class Settings:
    source_dsn: str
    warehouse_dsn: str
    pseudonym_secret: str
    minio_endpoint: str
    minio_access_key: str
    minio_secret_key: str
    minio_bucket: str
    spark_master: str
    output_dir: Path
    timezone: str

    @classmethod
    def from_env(cls):
        required = ['PICD_SOURCE_DSN', 'PICD_WAREHOUSE_DSN', 'PICD_PSEUDONYM_SECRET',
                    'PICD_MINIO_ENDPOINT', 'PICD_MINIO_ACCESS_KEY', 'PICD_MINIO_SECRET_KEY']
        missing = [key for key in required if not os.getenv(key) or 'CHANGE_ME' in os.getenv(key, '')]
        if missing:
            raise ValueError('Required PIC D environment variables missing/unconfigured: '+', '.join(missing))
        secret = os.environ['PICD_PSEUDONYM_SECRET']
        if len(secret) < 32 or secret.startswith('REPLACE_WITH'):
            raise ValueError('PICD_PSEUDONYM_SECRET must be a unique, random 32+ character value')
        if os.getenv('PICD_TIMEZONE', 'Asia/Jakarta') != 'Asia/Jakarta':
            raise ValueError('PRD v1.4 requires Asia/Jakarta timezone')
        return cls(os.environ['PICD_SOURCE_DSN'], os.environ['PICD_WAREHOUSE_DSN'], secret,
                   os.environ['PICD_MINIO_ENDPOINT'], os.environ['PICD_MINIO_ACCESS_KEY'],
                   os.environ['PICD_MINIO_SECRET_KEY'], os.getenv('PICD_MINIO_BUCKET','koperadata-analytics'),
                   os.getenv('PICD_SPARK_MASTER','local[*]'),
                   Path(os.getenv('PICD_OUTPUT_DIR', '.runtime')).expanduser().resolve(), 'Asia/Jakarta')
