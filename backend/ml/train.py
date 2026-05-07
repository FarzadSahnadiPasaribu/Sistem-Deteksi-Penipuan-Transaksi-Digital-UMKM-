"""
Script training model deteksi penipuan UMKM.
Jalankan: python train.py
"""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

import json
import numpy as np
import pandas as pd
from datetime import datetime

from data_generator import generate_umkm_transactions, FEATURE_COLUMNS
from fraud_detector import UMKMFraudDetector


def train_and_evaluate():
    print("=" * 60)
    print("SISTEM DETEKSI PENIPUAN TRANSAKSI DIGITAL UMKM")
    print("Kelompok 6: Farrel, Farzad, Hafif, Arung")
    print("=" * 60)

    # 1. Generate dataset
    print("\n[1/4] Membuat dataset transaksi UMKM...")
    data_path = os.path.join(os.path.dirname(__file__), "../../data/umkm_transactions.csv")

    if os.path.exists(data_path):
        df = pd.read_csv(data_path)
        print(f"Dataset dimuat dari file: {data_path}")
    else:
        df = generate_umkm_transactions(
            n_normal=1000,
            n_fraud=50,
            seed=42,
            save_path=data_path,
        )

    print(f"Dataset: {len(df)} transaksi | Normal: {(df.is_fraud==0).sum()} | Fraud: {(df.is_fraud==1).sum()}")

    # 2. Split train/test (80/20 stratified)
    print("\n[2/4] Membagi data train/test (80%/20%)...")
    from sklearn.model_selection import train_test_split
    train_df, test_df = train_test_split(
        df, test_size=0.2, stratify=df["is_fraud"], random_state=42
    )
    print(f"Train: {len(train_df)} | Test: {len(test_df)}")

    # 3. Training
    print("\n[3/4] Melatih model Ensemble (Isolation Forest + LOF + Rules)...")
    detector = UMKMFraudDetector(
        contamination=0.05,
        if_n_estimators=200,
        lof_n_neighbors=20,
        ensemble_weights=(0.45, 0.35, 0.20),
    )
    detector.fit(train_df)

    # 4. Evaluasi
    print("\n[4/4] Evaluasi pada data test...")
    metrics = detector.evaluate(test_df)

    print("\n--- Hasil Evaluasi ---")
    print(f"  Akurasi        : {metrics['accuracy']:.4f} ({metrics['accuracy']*100:.1f}%)")
    print(f"  Precision      : {metrics['precision']:.4f}")
    print(f"  Recall         : {metrics['recall']:.4f}")
    print(f"  F1-Score       : {metrics['f1_score']:.4f}")
    print(f"  ROC-AUC        : {metrics['roc_auc']:.4f}")
    print(f"\n  Fraud terdeteksi: {metrics['total_fraud_detected']} / {metrics['total_fraud_actual']}")
    print(f"\n{metrics['classification_report']}")

    cm = metrics["confusion_matrix"]
    print("  Confusion Matrix:")
    print(f"    TN={cm[0][0]:4d}  FP={cm[0][1]:4d}")
    print(f"    FN={cm[1][0]:4d}  TP={cm[1][1]:4d}")

    # Simpan model
    model_path = os.path.join(os.path.dirname(__file__), "saved_models")
    detector.save(model_path)

    # Simpan laporan evaluasi
    report_path = os.path.join(os.path.dirname(__file__), "../../data/evaluation_report.json")
    report = {
        "trained_at": datetime.now().isoformat(),
        "model": "Ensemble (Isolation Forest + LOF + Rules)",
        "dataset": {
            "total": len(df),
            "normal": int((df.is_fraud == 0).sum()),
            "fraud": int((df.is_fraud == 1).sum()),
        },
        "metrics": {k: v for k, v in metrics.items() if k != "classification_report"},
    }
    os.makedirs(os.path.dirname(report_path), exist_ok=True)
    with open(report_path, "w") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
    print(f"\nLaporan evaluasi disimpan: {report_path}")

    # Demo prediksi single transaksi
    print("\n--- Demo Prediksi ---")
    demo_transactions = [
        {
            "amount": 150_000,
            "hour": 14,
            "day_of_week": 2,
            "transaction_count_1h": 1,
            "transaction_count_24h": 5,
            "avg_amount_7d": 200_000,
            "amount_deviation": 0.25,
            "is_new_recipient": 0,
            "location_change": 0,
            "is_weekend": 0,
            "velocity_score": 0.1,
        },
        {
            "amount": 85_000_000,
            "hour": 2,
            "day_of_week": 6,
            "transaction_count_1h": 12,
            "transaction_count_24h": 45,
            "avg_amount_7d": 300_000,
            "amount_deviation": 283.0,
            "is_new_recipient": 1,
            "location_change": 1,
            "is_weekend": 1,
            "velocity_score": 0.95,
        },
    ]

    for i, trx in enumerate(demo_transactions, 1):
        result = detector.predict_single(trx)
        label = "FRAUD" if result["is_fraud"] else "NORMAL"
        print(f"\n  Transaksi {i} [{label}]:")
        print(f"    Fraud Score : {result['fraud_score']:.4f}")
        print(f"    Risk Level  : {result['risk_level']}")
        print(f"    Alasan      :")
        for reason in result["explanation"]:
            print(f"      - {reason}")

    print("\n" + "=" * 60)
    print("Training selesai!")
    return detector, metrics


if __name__ == "__main__":
    train_and_evaluate()
