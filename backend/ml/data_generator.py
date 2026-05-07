"""
Generator data transaksi UMKM sintetis untuk training dan testing model.
Menghasilkan transaksi normal dan anomali (penipuan).
"""

import numpy as np
import pandas as pd
from datetime import datetime, timedelta
import random
import os


def generate_umkm_transactions(
    n_normal: int = 1000,
    n_fraud: int = 50,
    seed: int = 42,
    save_path: str = None,
) -> pd.DataFrame:
    """
    Menghasilkan dataset transaksi UMKM dengan label fraud/normal.

    Fitur yang dihasilkan:
    - amount: Nominal transaksi (Rp)
    - hour: Jam transaksi (0-23)
    - day_of_week: Hari dalam seminggu (0=Senin)
    - transaction_count_1h: Jumlah transaksi dalam 1 jam terakhir
    - transaction_count_24h: Jumlah transaksi dalam 24 jam terakhir
    - avg_amount_7d: Rata-rata nominal 7 hari terakhir
    - amount_deviation: Deviasi nominal dari rata-rata
    - is_new_recipient: Apakah penerima baru
    - location_change: Perubahan lokasi mendadak
    - is_weekend: Apakah akhir pekan
    - velocity_score: Skor kecepatan transaksi
    """
    np.random.seed(seed)
    random.seed(seed)

    records = []

    # --- Transaksi Normal ---
    for i in range(n_normal):
        hour = int(np.random.choice(
            range(8, 22),
            p=np.array([0.05, 0.08, 0.12, 0.12, 0.10, 0.10, 0.08, 0.08,
                        0.08, 0.07, 0.06, 0.04, 0.01, 0.01]) / 1.0
        ))
        day = random.randint(0, 6)
        amount = max(10_000, np.random.lognormal(mean=13.5, sigma=0.8))
        amount = round(amount / 500) * 500

        avg_7d = amount * np.random.uniform(0.7, 1.3)
        deviation = abs(amount - avg_7d) / (avg_7d + 1)

        records.append({
            "transaction_id": f"TRX-{i+1:05d}",
            "amount": amount,
            "hour": hour,
            "day_of_week": day,
            "transaction_count_1h": random.randint(0, 3),
            "transaction_count_24h": random.randint(1, 15),
            "avg_amount_7d": round(avg_7d),
            "amount_deviation": round(deviation, 4),
            "is_new_recipient": int(random.random() < 0.15),
            "location_change": int(random.random() < 0.05),
            "is_weekend": int(day >= 5),
            "velocity_score": round(np.random.uniform(0, 0.3), 4),
            "is_fraud": 0,
        })

    # --- Transaksi Fraud (Anomali) ---
    fraud_patterns = [
        # Pola 1: Nominal sangat besar di luar jam kerja
        lambda: {
            "amount": round(np.random.uniform(50_000_000, 200_000_000) / 500) * 500,
            "hour": random.choice([0, 1, 2, 3, 23]),
            "day_of_week": random.randint(0, 6),
            "transaction_count_1h": random.randint(0, 1),
            "transaction_count_24h": random.randint(1, 3),
            "avg_amount_7d": round(np.random.uniform(100_000, 500_000)),
            "amount_deviation": round(np.random.uniform(50, 200), 4),
            "is_new_recipient": 1,
            "location_change": 1,
            "is_weekend": int(random.randint(0, 6) >= 5),
            "velocity_score": round(np.random.uniform(0.8, 1.0), 4),
        },
        # Pola 2: Transaksi berulang sangat cepat
        lambda: {
            "amount": round(np.random.uniform(500_000, 5_000_000) / 500) * 500,
            "hour": random.randint(8, 22),
            "day_of_week": random.randint(0, 6),
            "transaction_count_1h": random.randint(15, 30),
            "transaction_count_24h": random.randint(50, 100),
            "avg_amount_7d": round(np.random.uniform(200_000, 800_000)),
            "amount_deviation": round(np.random.uniform(2, 10), 4),
            "is_new_recipient": int(random.random() < 0.7),
            "location_change": int(random.random() < 0.5),
            "is_weekend": int(random.randint(0, 6) >= 5),
            "velocity_score": round(np.random.uniform(0.7, 1.0), 4),
        },
        # Pola 3: Penerima baru + lokasi berubah + nominal tidak wajar
        lambda: {
            "amount": round(np.random.uniform(10_000_000, 80_000_000) / 500) * 500,
            "hour": random.randint(0, 23),
            "day_of_week": random.randint(0, 6),
            "transaction_count_1h": random.randint(1, 5),
            "transaction_count_24h": random.randint(2, 10),
            "avg_amount_7d": round(np.random.uniform(50_000, 300_000)),
            "amount_deviation": round(np.random.uniform(20, 100), 4),
            "is_new_recipient": 1,
            "location_change": 1,
            "is_weekend": int(random.randint(0, 6) >= 5),
            "velocity_score": round(np.random.uniform(0.5, 0.9), 4),
        },
    ]

    for i in range(n_fraud):
        pattern_fn = random.choice(fraud_patterns)
        data = pattern_fn()
        data["transaction_id"] = f"TRX-FRAUD-{i+1:04d}"
        data["is_fraud"] = 1
        records.append(data)

    df = pd.DataFrame(records)
    df = df.sample(frac=1, random_state=seed).reset_index(drop=True)

    # Tambahkan timestamp
    base_time = datetime(2024, 1, 1, 8, 0, 0)
    df["timestamp"] = [
        base_time + timedelta(hours=random.randint(0, 24 * 90))
        for _ in range(len(df))
    ]
    df["timestamp"] = pd.to_datetime(df["timestamp"])

    if save_path:
        os.makedirs(os.path.dirname(save_path), exist_ok=True)
        df.to_csv(save_path, index=False)
        print(f"Dataset disimpan: {save_path}")
        print(f"Total: {len(df)} transaksi | Normal: {(df.is_fraud==0).sum()} | Fraud: {(df.is_fraud==1).sum()}")

    return df


FEATURE_COLUMNS = [
    "amount",
    "hour",
    "day_of_week",
    "transaction_count_1h",
    "transaction_count_24h",
    "avg_amount_7d",
    "amount_deviation",
    "is_new_recipient",
    "location_change",
    "is_weekend",
    "velocity_score",
]


if __name__ == "__main__":
    df = generate_umkm_transactions(
        n_normal=1000,
        n_fraud=50,
        save_path="../../data/umkm_transactions.csv",
    )
    print(df.head(10).to_string())
