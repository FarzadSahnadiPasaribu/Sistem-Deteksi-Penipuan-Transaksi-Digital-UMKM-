from sqlalchemy import Column, Integer, Float, String, Boolean, DateTime, Text
from sqlalchemy.sql import func
from .database import Base


class Transaction(Base):
    __tablename__ = "transactions"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    transaction_id = Column(String(50), unique=True, index=True)

    # Informasi transaksi
    merchant_name = Column(String(100), nullable=True)
    amount = Column(Float, nullable=False)
    recipient_name = Column(String(100), nullable=True)
    description = Column(String(255), nullable=True)

    # Fitur untuk model
    hour = Column(Integer)
    day_of_week = Column(Integer)
    transaction_count_1h = Column(Integer, default=0)
    transaction_count_24h = Column(Integer, default=0)
    avg_amount_7d = Column(Float, default=0)
    amount_deviation = Column(Float, default=0)
    is_new_recipient = Column(Boolean, default=False)
    location_change = Column(Boolean, default=False)
    is_weekend = Column(Boolean, default=False)
    velocity_score = Column(Float, default=0)

    # Hasil deteksi
    is_fraud = Column(Boolean, default=False)
    fraud_score = Column(Float, default=0)
    risk_level = Column(String(20), default="RENDAH")
    score_isolation_forest = Column(Float, default=0)
    score_lof = Column(Float, default=0)
    score_rule_based = Column(Float, default=0)
    explanation = Column(Text, nullable=True)

    # Status & waktu
    status = Column(String(20), default="PENDING")  # PENDING, APPROVED, BLOCKED
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
