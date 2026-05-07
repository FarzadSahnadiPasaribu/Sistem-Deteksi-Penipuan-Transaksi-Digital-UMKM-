"""
Script training model deteksi penipuan bon UMKM.
Jalankan: python train.py
"""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

import json
import pandas as pd
from datetime import datetime
from sklearn.model_selection import train_test_split

from data_generator import generate_receipt_transactions, FEATURE_COLUMNS
from fraud_detector import FraudDetector


def train_and_evaluate():
    print("=" * 55)
    print("  DETEKSI PENIPUAN BON TRANSAKSI DIGITAL UMKM")
    print("=" * 55)

    data_path = os.path.join(os.path.dirname(__file__), "../../data/umkm_transactions.csv")

    print("\n[1/4] Memuat dataset...")
    if os.path.exists(data_path):
        df = pd.read_csv(data_path)
        print(f"Dataset dimuat dari file: {data_path}")
    else:
        df = generate_receipt_transactions(n_normal=1000, n_fraud=50, seed=42, save_path=data_path)

    print(f"Total: {len(df)} | Normal: {(df.is_fraud==0).sum()} | Fraud: {(df.is_fraud==1).sum()}")

    print("\n[2/4] Membagi data train/test (80/20)...")
    train_df, test_df = train_test_split(df, test_size=0.2, stratify=df["is_fraud"], random_state=42)
    print(f"Train: {len(train_df)} | Test: {len(test_df)}")

    print("\n[3/4] Melatih model...")
    detector = FraudDetector(contamination=0.05, if_n_estimators=200, lof_n_neighbors=20)
    detector.fit(train_df)

    print("\n[4/4] Evaluasi...")
    metrics = detector.evaluate(test_df)
    print(f"\n  Akurasi   : {metrics['accuracy']*100:.1f}%")
    print(f"  Precision : {metrics['precision']:.4f}")
    print(f"  Recall    : {metrics['recall']:.4f}")
    print(f"  F1-Score  : {metrics['f1_score']:.4f}")
    print(f"  ROC-AUC   : {metrics['roc_auc']:.4f}")
    print(f"\n  Fraud terdeteksi: {metrics['total_fraud_detected']} / {metrics['total_fraud_actual']}")
    cm = metrics["confusion_matrix"]
    print(f"  TN={cm[0][0]}  FP={cm[0][1]}  FN={cm[1][0]}  TP={cm[1][1]}")

    model_path = os.path.join(os.path.dirname(__file__), "saved_models")
    detector.save(model_path)

    report_path = os.path.join(os.path.dirname(__file__), "../../data/evaluation_report.json")
    os.makedirs(os.path.dirname(report_path), exist_ok=True)
    with open(report_path, "w") as f:
        json.dump({
            "trained_at": datetime.now().isoformat(),
            "model": "Ensemble (Isolation Forest + LOF + Rules)",
            "dataset": {"total": len(df), "normal": int((df.is_fraud==0).sum()), "fraud": int((df.is_fraud==1).sum())},
            "metrics": {k: v for k, v in metrics.items() if k != "classification_report"},
        }, f, indent=2, ensure_ascii=False)

    print("\n" + "=" * 55)
    print("  Training selesai!")
    print("=" * 55)
    return detector, metrics


if __name__ == "__main__":
    train_and_evaluate()
