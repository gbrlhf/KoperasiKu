import os
from typing import List, Optional
import numpy as np
import psycopg2
from fastapi import FastAPI
from pydantic import BaseModel, Field
from sklearn.linear_model import LinearRegression

app = FastAPI(title="KoperasiKu ML Service", version="1.0.0")


class DemandRequest(BaseModel):
    product_id: int
    history: Optional[List[float]] = None
    horizon_days: int = Field(default=7, ge=1, le=90)


class CreditRiskRequest(BaseModel):
    credit_limit: float = Field(gt=0)
    current_debt: float = Field(ge=0)
    overdue_count: int = Field(ge=0)
    on_time_ratio: float = Field(default=1.0, ge=0.0, le=1.0)


def check_db() -> bool:
    try:
        conn = psycopg2.connect(
            host=os.getenv("DB_HOST", "db"),
            port=os.getenv("DB_PORT", "5432"),
            dbname=os.getenv("DB_NAME", "koperadata"),
            user=os.getenv("DB_USER", "postgres"),
            password=os.getenv("DB_PASSWORD", "secret"),
            connect_timeout=2,
        )
        conn.close()
        return True
    except Exception:
        return False


@app.get("/health")
def health():
    return {
        "status": "ok",
        "service": "koperasiku-ml",
        "db_connected": check_db(),
    }


@app.post("/predict/demand")
def predict_demand(payload: DemandRequest):
    series = payload.history if payload.history and len(payload.history) >= 2 else [10.0, 12.0, 11.0, 14.0, 15.0, 16.0, 18.0]
    X = np.arange(len(series)).reshape(-1, 1)
    y = np.array(series, dtype=float)

    model = LinearRegression()
    model.fit(X, y)

    future_X = np.arange(len(series), len(series) + payload.horizon_days).reshape(-1, 1)
    preds = np.maximum(0, model.predict(future_X))
    total_forecast = float(np.round(np.sum(preds), 2))
    safety_stock = float(np.round(np.std(y) * 1.5, 2))

    return {
        "product_id": payload.product_id,
        "horizon_days": payload.horizon_days,
        "daily_forecast": [round(float(v), 2) for v in preds],
        "total_predicted_demand": total_forecast,
        "recommended_reorder_qty": round(total_forecast + safety_stock, 2),
        "model": "LinearRegression",
    }


@app.post("/predict/credit-risk")
def predict_credit_risk(payload: CreditRiskRequest):
    utilization = min(payload.current_debt / payload.credit_limit, 1.5)
    score = (
        0.45 * utilization
        + 0.35 * min(payload.overdue_count / 3.0, 1.0)
        + 0.20 * (1.0 - payload.on_time_ratio)
    )
    risk_prob = round(min(max(score, 0.01), 0.99), 4)
    tier = "HIGH" if risk_prob >= 0.65 else ("MEDIUM" if risk_prob >= 0.35 else "LOW")

    return {
        "risk_probability": risk_prob,
        "risk_tier": tier,
        "utilization_ratio": round(utilization, 4),
        "recommended_max_limit": round(payload.credit_limit * (0.5 if tier == "HIGH" else (0.8 if tier == "MEDIUM" else 1.1)), 2),
    }
