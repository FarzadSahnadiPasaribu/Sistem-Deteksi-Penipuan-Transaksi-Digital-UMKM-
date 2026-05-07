"""
Script seeding: generate transaksi dummy ke database untuk demo.
Jalankan dari folder backend: python seed.py
"""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

import json
import uuid
import random
from datetime import datetime, timedelta

from app.models.database import Base, engine, SessionLocal
from app.models.transaction import Transaction
from ml.data_generator import generate_umkm_transactions, FEATURE_COLUMNS


def seed_database(n_normal: int = 200, n_fraud: int = 20):
    print("Membuat tabel database...")
    Base.metadata.create_all(bind=engine)

    print(f"Generate {n_normal + n_fraud} transaksi demo...")
    df = generate_umkm_transactions(n_normal=n_normal, n_fraud=n_fraud, seed=99)

    db = SessionLocal()
    inserted = 0

    try:
        for _, row in df.iterrows():
            trx_id = f"SEED-{uuid.uuid4().hex[:8].upper()}"
            is_fraud = bool(row["is_fraud"])
            fraud_score = round(random.uniform(0.6, 0.95) if is_fraud else random.uniform(0.05, 0.35), 4)

            if fraud_score >= 0.75:
                risk = "KRITIS"
            elif fraud_score >= 0.50:
                risk = "TINGGI"
            elif fraud_score >= 0.30:
                risk = "SEDANG"
            else:
                risk = "RENDAH"

            if is_fraud and risk == "KRITIS":
                status = "BLOCKED"
            elif is_fraud:
                status = "REVIEW"
            else:
                status = "APPROVED"

            merchant_names = ["Toko Berkah Jaya", "UD Maju Bersama", "CV Sumber Rejeki",
                               "Warung Pak Slamet", "Toko Sembako Bu Ani", "Kedai Kopi Nusantara",
                               "Toko Kelontong Makmur", "UD Harapan Indah"]
            recipient_names = ["Supplier Indofood", "CV Distribusi Jaya", "PT Bahan Baku Nusantara",
                                "Rekening Pribadi", "Transfer ke Luar Kota", "Vendor Lokal"]

            trx = Transaction(
                transaction_id=trx_id,
                merchant_name=random.choice(merchant_names),
                amount=float(row["amount"]),
                recipient_name=random.choice(recipient_names) if random.random() > 0.2 else None,
                description="Pembayaran bahan baku" if not is_fraud else "Transfer mendadak",
                hour=int(row["hour"]),
                day_of_week=int(row["day_of_week"]),
                transaction_count_1h=int(row["transaction_count_1h"]),
                transaction_count_24h=int(row["transaction_count_24h"]),
                avg_amount_7d=float(row["avg_amount_7d"]),
                amount_deviation=float(row["amount_deviation"]),
                is_new_recipient=bool(row["is_new_recipient"]),
                location_change=bool(row["location_change"]),
                is_weekend=bool(row["is_weekend"]),
                velocity_score=float(row["velocity_score"]),
                is_fraud=is_fraud,
                fraud_score=fraud_score,
                risk_level=risk,
                score_isolation_forest=round(fraud_score * random.uniform(0.8, 1.1), 4),
                score_lof=round(fraud_score * random.uniform(0.7, 1.2), 4),
                score_rule_based=round(fraud_score * random.uniform(0.6, 1.3), 4),
                explanation=json.dumps(
                    ["Transaksi normal sesuai pola historis"] if not is_fraud
                    else ["Nominal jauh di atas rata-rata", "Penerima baru terdeteksi"],
                    ensure_ascii=False,
                ),
                status=status,
                created_at=row.get("timestamp", datetime.now()),
            )
            db.add(trx)
            inserted += 1

        db.commit()
        print(f"✅ {inserted} transaksi berhasil disimpan ke database!")

    except Exception as e:
        db.rollback()
        print(f"❌ Error: {e}")
        raise
    finally:
        db.close()


if __name__ == "__main__":
    seed_database(n_normal=200, n_fraud=20)
