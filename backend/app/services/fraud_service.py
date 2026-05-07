"""
Service layer: menghubungkan API dengan model deteksi penipuan.
"""

import os
import sys
import json
import uuid
from datetime import datetime
from typing import Optional

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "../../ml"))

from fraud_detector import FraudDetector
from train import train_and_evaluate

_detector: Optional[FraudDetector] = None
MODEL_PATH = os.path.join(os.path.dirname(__file__), "../../ml/saved_models")


def get_detector() -> FraudDetector:
    global _detector
    if _detector is not None:
        return _detector
    meta_path = os.path.join(MODEL_PATH, "metadata.json")
    if os.path.exists(meta_path):
        _detector = FraudDetector.load(MODEL_PATH)
        print("[Service] Model dimuat dari disk.")
    else:
        print("[Service] Model belum ada, mulai training...")
        _detector, _ = train_and_evaluate()
    return _detector


def analyze_receipt(data: dict) -> dict:
    detector = get_detector()
    features = {
        "total_amount": float(data.get("total_amount", 0)),
        "discount_pct": float(data.get("discount_pct", 0)),
        "subtotal_ratio": float(data.get("subtotal_ratio", 1)),
        "item_count": int(data.get("item_count", 1)),
        "hour": int(data.get("hour", datetime.now().hour)),
        "is_cod": int(data.get("is_cod", 0)),
        "is_transfer_pribadi": int(data.get("is_transfer_pribadi", 0)),
        "seller_age_days": int(data.get("seller_age_days", 365)),
        "price_ratio": float(data.get("price_ratio", 1.0)),
        "has_urgent_words": int(data.get("has_urgent_words", 0)),
        "platform_verified": int(data.get("platform_verified", 1)),
    }
    result = detector.predict_single(features)
    result["transaction_id"] = data.get("transaction_id", f"BON-{uuid.uuid4().hex[:8].upper()}")
    return result


def retrain_model() -> dict:
    global _detector
    _detector = None
    detector, metrics = train_and_evaluate()
    _detector = detector
    return {
        "status": "success",
        "message": "Model berhasil dilatih ulang",
        "metrics": {k: v for k, v in metrics.items() if k != "classification_report"},
    }


def get_model_info() -> dict:
    meta_path = os.path.join(MODEL_PATH, "metadata.json")
    if not os.path.exists(meta_path):
        return {"status": "not_trained"}

    with open(meta_path) as f:
        meta = json.load(f)

    metrics = {}
    trained_at = None
    report_path = os.path.join(MODEL_PATH, "../../data/evaluation_report.json")
    if os.path.exists(report_path):
        with open(report_path) as f:
            report = json.load(f)
        metrics = report.get("metrics", {})
        trained_at = report.get("trained_at")

    return {
        "status": "ready",
        "model_type": "Ensemble (Isolation Forest + LOF + Aturan Bisnis)",
        "contamination": meta.get("contamination"),
        "ensemble_weights": meta.get("ensemble_weights"),
        "is_trained": meta.get("is_trained"),
        "metrics": metrics,
        "trained_at": trained_at,
    }
