"""
Service layer untuk deteksi penipuan.
Menghubungkan API dengan ML model.
"""

import os
import sys
import json
import uuid
from datetime import datetime
from typing import Optional

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "../../ml"))

from fraud_detector import UMKMFraudDetector
from data_generator import generate_umkm_transactions
from train import train_and_evaluate


_detector: Optional[UMKMFraudDetector] = None
MODEL_PATH = os.path.join(os.path.dirname(__file__), "../../ml/saved_models")


def get_detector() -> UMKMFraudDetector:
    """Singleton: muat model sekali, gunakan berulang kali."""
    global _detector

    if _detector is not None:
        return _detector

    meta_path = os.path.join(MODEL_PATH, "metadata.json")
    if os.path.exists(meta_path):
        _detector = UMKMFraudDetector.load(MODEL_PATH)
        print("[FraudService] Model dimuat dari disk.")
    else:
        print("[FraudService] Model belum ada, memulai training otomatis...")
        _detector, _ = train_and_evaluate()
        print("[FraudService] Training selesai.")

    return _detector


def analyze_transaction(transaction_data: dict) -> dict:
    """Analisis satu transaksi, kembalikan hasil deteksi."""
    detector = get_detector()

    features = {
        "amount": float(transaction_data.get("amount", 0)),
        "hour": int(transaction_data.get("hour", datetime.now().hour)),
        "day_of_week": int(transaction_data.get("day_of_week", datetime.now().weekday())),
        "transaction_count_1h": int(transaction_data.get("transaction_count_1h", 0)),
        "transaction_count_24h": int(transaction_data.get("transaction_count_24h", 0)),
        "avg_amount_7d": float(transaction_data.get("avg_amount_7d", 0)),
        "amount_deviation": float(transaction_data.get("amount_deviation", 0)),
        "is_new_recipient": int(transaction_data.get("is_new_recipient", 0)),
        "location_change": int(transaction_data.get("location_change", 0)),
        "is_weekend": int(transaction_data.get("is_weekend", 0)),
        "velocity_score": float(transaction_data.get("velocity_score", 0)),
    }

    result = detector.predict_single(features)
    result["transaction_id"] = transaction_data.get(
        "transaction_id", f"TRX-{uuid.uuid4().hex[:8].upper()}"
    )
    return result


def retrain_model() -> dict:
    """Re-train ulang model (dipanggil dari API admin)."""
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
    """Informasi tentang model yang sedang aktif."""
    meta_path = os.path.join(MODEL_PATH, "metadata.json")
    if not os.path.exists(meta_path):
        return {"status": "not_trained"}

    with open(meta_path) as f:
        meta = json.load(f)

    report_path = os.path.join(MODEL_PATH, "../../data/evaluation_report.json")
    metrics = {}
    if os.path.exists(report_path):
        with open(report_path) as f:
            report = json.load(f)
        metrics = report.get("metrics", {})

    return {
        "status": "ready",
        "model_type": "Ensemble (Isolation Forest + LOF + Rules)",
        "contamination": meta.get("contamination"),
        "ensemble_weights": meta.get("ensemble_weights"),
        "is_trained": meta.get("is_trained"),
        "metrics": metrics,
        "trained_at": report.get("trained_at") if metrics else None,
    }
