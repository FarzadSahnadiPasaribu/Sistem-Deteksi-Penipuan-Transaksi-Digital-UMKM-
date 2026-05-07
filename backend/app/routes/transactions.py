from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.orm import Session
from sqlalchemy import desc, func
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime
import json
import uuid

from ..models import get_db, Transaction
from ..services.fraud_service import analyze_transaction

router = APIRouter(prefix="/transactions", tags=["Transaksi"])


# --- Skema Pydantic ---

class TransactionCreate(BaseModel):
    merchant_name: Optional[str] = Field(None, example="Toko Berkah Jaya")
    amount: float = Field(..., gt=0, example=500000)
    recipient_name: Optional[str] = Field(None, example="CV Maju Bersama")
    description: Optional[str] = Field(None, example="Pembayaran bahan baku")

    # Fitur kontekstual (opsional — jika tidak diisi, dihitung otomatis)
    hour: Optional[int] = Field(None, ge=0, le=23)
    day_of_week: Optional[int] = Field(None, ge=0, le=6)
    transaction_count_1h: int = Field(0, ge=0, example=1)
    transaction_count_24h: int = Field(1, ge=0, example=5)
    avg_amount_7d: float = Field(0, ge=0, example=450000)
    amount_deviation: float = Field(0, ge=0, example=0.11)
    is_new_recipient: bool = Field(False)
    location_change: bool = Field(False)
    is_weekend: Optional[bool] = None
    velocity_score: float = Field(0, ge=0, le=1, example=0.05)


class TransactionResponse(BaseModel):
    id: int
    transaction_id: str
    merchant_name: Optional[str]
    amount: float
    recipient_name: Optional[str]
    description: Optional[str]
    is_fraud: bool
    fraud_score: float
    risk_level: str
    explanation: Optional[List[str]]
    status: str
    created_at: datetime

    class Config:
        from_attributes = True


class TransactionDetail(TransactionResponse):
    hour: int
    day_of_week: int
    transaction_count_1h: int
    transaction_count_24h: int
    avg_amount_7d: float
    amount_deviation: float
    is_new_recipient: bool
    location_change: bool
    is_weekend: bool
    velocity_score: float
    score_isolation_forest: float
    score_lof: float
    score_rule_based: float


# --- Endpoints ---

@router.post("/analyze", response_model=dict, summary="Analisis Transaksi Baru")
def create_and_analyze_transaction(
    payload: TransactionCreate,
    db: Session = Depends(get_db),
):
    """
    Kirim transaksi baru untuk dianalisis apakah terindikasi penipuan.
    Transaksi disimpan ke database beserta hasil deteksi.
    """
    now = datetime.now()

    data = payload.dict()
    data["hour"] = data.get("hour") if data.get("hour") is not None else now.hour
    data["day_of_week"] = data.get("day_of_week") if data.get("day_of_week") is not None else now.weekday()
    data["is_weekend"] = data.get("is_weekend") if data.get("is_weekend") is not None else (now.weekday() >= 5)

    trx_id = f"TRX-{uuid.uuid4().hex[:8].upper()}"
    data["transaction_id"] = trx_id

    # Jalankan deteksi
    detection = analyze_transaction(data)

    # Tentukan status transaksi
    if detection["is_fraud"] and detection["risk_level"] == "KRITIS":
        status = "BLOCKED"
    elif detection["is_fraud"]:
        status = "REVIEW"
    else:
        status = "APPROVED"

    # Simpan ke database
    trx = Transaction(
        transaction_id=trx_id,
        merchant_name=data.get("merchant_name"),
        amount=data["amount"],
        recipient_name=data.get("recipient_name"),
        description=data.get("description"),
        hour=data["hour"],
        day_of_week=data["day_of_week"],
        transaction_count_1h=data["transaction_count_1h"],
        transaction_count_24h=data["transaction_count_24h"],
        avg_amount_7d=data["avg_amount_7d"],
        amount_deviation=data["amount_deviation"],
        is_new_recipient=data["is_new_recipient"],
        location_change=data["location_change"],
        is_weekend=data["is_weekend"],
        velocity_score=data["velocity_score"],
        is_fraud=detection["is_fraud"],
        fraud_score=detection["fraud_score"],
        risk_level=detection["risk_level"],
        score_isolation_forest=detection["score_isolation_forest"],
        score_lof=detection["score_lof"],
        score_rule_based=detection["score_rule_based"],
        explanation=json.dumps(detection["explanation"], ensure_ascii=False),
        status=status,
    )
    db.add(trx)
    db.commit()
    db.refresh(trx)

    return {
        "transaction_id": trx_id,
        "amount": data["amount"],
        "merchant_name": data.get("merchant_name"),
        "recipient_name": data.get("recipient_name"),
        "is_fraud": detection["is_fraud"],
        "fraud_score": detection["fraud_score"],
        "risk_level": detection["risk_level"],
        "status": status,
        "explanation": detection["explanation"],
        "score_detail": {
            "isolation_forest": detection["score_isolation_forest"],
            "local_outlier_factor": detection["score_lof"],
            "rule_based": detection["score_rule_based"],
        },
        "db_id": trx.id,
        "created_at": trx.created_at.isoformat(),
    }


@router.get("/", summary="Daftar Semua Transaksi")
def list_transactions(
    skip: int = Query(0, ge=0),
    limit: int = Query(20, ge=1, le=100),
    is_fraud: Optional[bool] = Query(None),
    risk_level: Optional[str] = Query(None),
    status: Optional[str] = Query(None),
    db: Session = Depends(get_db),
):
    """Ambil daftar transaksi dengan filter opsional."""
    query = db.query(Transaction)
    if is_fraud is not None:
        query = query.filter(Transaction.is_fraud == is_fraud)
    if risk_level:
        query = query.filter(Transaction.risk_level == risk_level.upper())
    if status:
        query = query.filter(Transaction.status == status.upper())

    total = query.count()
    items = query.order_by(desc(Transaction.created_at)).offset(skip).limit(limit).all()

    results = []
    for t in items:
        results.append({
            "id": t.id,
            "transaction_id": t.transaction_id,
            "merchant_name": t.merchant_name,
            "amount": t.amount,
            "recipient_name": t.recipient_name,
            "is_fraud": t.is_fraud,
            "fraud_score": t.fraud_score,
            "risk_level": t.risk_level,
            "status": t.status,
            "created_at": t.created_at.isoformat() if t.created_at else None,
        })

    return {"total": total, "skip": skip, "limit": limit, "data": results}


@router.get("/{transaction_id}", summary="Detail Transaksi")
def get_transaction(transaction_id: str, db: Session = Depends(get_db)):
    """Ambil detail lengkap satu transaksi termasuk skor model."""
    trx = db.query(Transaction).filter(
        Transaction.transaction_id == transaction_id
    ).first()
    if not trx:
        raise HTTPException(status_code=404, detail="Transaksi tidak ditemukan")

    explanation = []
    if trx.explanation:
        try:
            explanation = json.loads(trx.explanation)
        except Exception:
            explanation = [trx.explanation]

    return {
        "id": trx.id,
        "transaction_id": trx.transaction_id,
        "merchant_name": trx.merchant_name,
        "amount": trx.amount,
        "recipient_name": trx.recipient_name,
        "description": trx.description,
        "hour": trx.hour,
        "day_of_week": trx.day_of_week,
        "transaction_count_1h": trx.transaction_count_1h,
        "transaction_count_24h": trx.transaction_count_24h,
        "avg_amount_7d": trx.avg_amount_7d,
        "amount_deviation": trx.amount_deviation,
        "is_new_recipient": trx.is_new_recipient,
        "location_change": trx.location_change,
        "is_weekend": trx.is_weekend,
        "velocity_score": trx.velocity_score,
        "is_fraud": trx.is_fraud,
        "fraud_score": trx.fraud_score,
        "risk_level": trx.risk_level,
        "score_detail": {
            "isolation_forest": trx.score_isolation_forest,
            "local_outlier_factor": trx.score_lof,
            "rule_based": trx.score_rule_based,
        },
        "explanation": explanation,
        "status": trx.status,
        "created_at": trx.created_at.isoformat() if trx.created_at else None,
    }


@router.patch("/{transaction_id}/status", summary="Update Status Transaksi")
def update_status(
    transaction_id: str,
    new_status: str = Query(..., regex="^(APPROVED|BLOCKED|REVIEW)$"),
    db: Session = Depends(get_db),
):
    """Update status transaksi secara manual oleh admin."""
    trx = db.query(Transaction).filter(
        Transaction.transaction_id == transaction_id
    ).first()
    if not trx:
        raise HTTPException(status_code=404, detail="Transaksi tidak ditemukan")

    trx.status = new_status.upper()
    db.commit()
    return {"message": "Status berhasil diperbarui", "status": trx.status}
