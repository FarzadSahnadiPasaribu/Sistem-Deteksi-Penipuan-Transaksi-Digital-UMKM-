"""
Generator data transaksi bon e-commerce sintetis.
Fitur berbasis data yang ada di bon/struk digital UMKM.
"""

import numpy as np
import pandas as pd
from datetime import datetime, timedelta
import random
import os


FEATURE_COLUMNS = [
    "total_amount",         # Total pembayaran di bon
    "discount_pct",         # Persentase diskon (0-1)
    "subtotal_ratio",       # total / subtotal (harusnya mendekati 1)
    "item_count",           # Jumlah item dalam bon
    "hour",                 # Jam transaksi
    "is_cod",               # Metode COD = lebih berisiko
    "is_transfer_pribadi",  # Transfer ke rekening pribadi
    "seller_age_days",      # Umur akun penjual (hari)
    "price_ratio",          # Rasio harga vs rata-rata pasar
    "has_urgent_words",     # Ada kata mendesak (segera/darurat/dll)
    "platform_verified",    # Platform resmi (1) vs tidak dikenal (0)
]


def generate_receipt_transactions(
    n_normal: int = 1000,
    n_fraud: int = 50,
    seed: int = 42,
    save_path: str = None,
) -> pd.DataFrame:
    np.random.seed(seed)
    random.seed(seed)

    records = []

    # --- Transaksi Normal ---
    for i in range(n_normal):
        subtotal = max(10_000, np.random.lognormal(mean=13.5, sigma=0.8))
        subtotal = round(subtotal / 500) * 500
        discount_pct = np.random.uniform(0, 0.30)
        total = subtotal * (1 - discount_pct)
        total = round(total / 500) * 500

        records.append({
            "transaction_id": f"BON-{i+1:05d}",
            "total_amount": total,
            "discount_pct": round(discount_pct, 4),
            "subtotal_ratio": round(total / subtotal if subtotal > 0 else 1, 4),
            "item_count": random.randint(1, 8),
            "hour": int(np.random.choice(range(7, 22), p=np.array([0.04,0.08,0.12,0.12,0.11,0.10,0.09,0.09,0.08,0.07,0.05,0.03,0.01,0.01,0.01]) / 1.0)),
            "is_cod": int(random.random() < 0.20),
            "is_transfer_pribadi": 0,
            "seller_age_days": random.randint(180, 3000),
            "price_ratio": round(np.random.uniform(0.85, 1.15), 4),
            "has_urgent_words": 0,
            "platform_verified": 1,
            "is_fraud": 0,
        })

    # --- Transaksi Fraud ---
    fraud_patterns = [
        # Pola 1: Diskon ekstrem + penjual baru + transfer pribadi
        lambda: {
            "total_amount": round(np.random.uniform(500_000, 5_000_000) / 500) * 500,
            "discount_pct": round(np.random.uniform(0.75, 0.98), 4),
            "subtotal_ratio": round(np.random.uniform(0.02, 0.25), 4),
            "item_count": random.randint(1, 3),
            "hour": random.choice([0,1,2,3,22,23]),
            "is_cod": 0,
            "is_transfer_pribadi": 1,
            "seller_age_days": random.randint(1, 30),
            "price_ratio": round(np.random.uniform(0.05, 0.20), 4),
            "has_urgent_words": 1,
            "platform_verified": 0,
        },
        # Pola 2: Total tidak sesuai subtotal + platform tidak dikenal
        lambda: {
            "total_amount": round(np.random.uniform(200_000, 2_000_000) / 500) * 500,
            "discount_pct": round(np.random.uniform(0.60, 0.90), 4),
            "subtotal_ratio": round(np.random.uniform(0.05, 0.30), 4),
            "item_count": random.randint(5, 20),
            "hour": random.randint(0, 23),
            "is_cod": int(random.random() < 0.5),
            "is_transfer_pribadi": int(random.random() < 0.7),
            "seller_age_days": random.randint(1, 60),
            "price_ratio": round(np.random.uniform(0.10, 0.35), 4),
            "has_urgent_words": int(random.random() < 0.8),
            "platform_verified": 0,
        },
        # Pola 3: Nominal besar + transfer pribadi + kata mendesak
        lambda: {
            "total_amount": round(np.random.uniform(10_000_000, 50_000_000) / 500) * 500,
            "discount_pct": round(np.random.uniform(0.40, 0.70), 4),
            "subtotal_ratio": round(np.random.uniform(0.30, 0.60), 4),
            "item_count": random.randint(1, 5),
            "hour": random.choice([0,1,2,3,23]),
            "is_cod": 0,
            "is_transfer_pribadi": 1,
            "seller_age_days": random.randint(5, 90),
            "price_ratio": round(np.random.uniform(0.15, 0.45), 4),
            "has_urgent_words": 1,
            "platform_verified": int(random.random() < 0.2),
        },
    ]

    for i in range(n_fraud):
        data = random.choice(fraud_patterns)()
        data["transaction_id"] = f"BON-FRAUD-{i+1:04d}"
        data["is_fraud"] = 1
        records.append(data)

    df = pd.DataFrame(records)
    df = df.sample(frac=1, random_state=seed).reset_index(drop=True)

    base_time = datetime(2024, 1, 1, 8, 0, 0)
    df["timestamp"] = [
        base_time + timedelta(hours=random.randint(0, 24 * 90))
        for _ in range(len(df))
    ]

    if save_path:
        os.makedirs(os.path.dirname(save_path), exist_ok=True)
        df.to_csv(save_path, index=False)
        print(f"Dataset disimpan: {save_path}")
        print(f"Total: {len(df)} | Normal: {(df.is_fraud==0).sum()} | Fraud: {(df.is_fraud==1).sum()}")

    return df


if __name__ == "__main__":
    df = generate_receipt_transactions(save_path="../../data/umkm_transactions.csv")
    print(df.head(10).to_string())
