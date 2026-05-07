from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.orm import Session
from sqlalchemy import desc, func
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime
import json
import uuid

from ..models import get_db, Transaction
from ..services.fraud_service import analyze_receipt

router = APIRouter(prefix="/transactions", tags=["Transaksi"])


class ReceiptInput(BaseModel):
    # Info bon
    platform: Optional[str] = Field(None, example="Shopee")
    seller_name: Optional[str] = Field(None, example="Toko Elektronik Murah")
    order_id: Optional[str] = Field(None, example="SPX-20240101-001")
    description: Optional[str] = Field(None, example="iPhone 14 Pro Max")

    # Nilai transaksi
    total_amount: float = Field(..., gt=0, example=500000)
    subtotal: Optional[float] = Field(None, example=650000)
    discount_amount: Optional[float] = Field(0, example=150000)
    item_count: int = Field(1, ge=1, example=1)

    # Metode pembayaran
    payment_method: Optional[str] = Field(None, example="Transfer Bank")
    is_cod: bool = Field(False)
    is_transfer_pribadi: bool = Field(False)
    platform_verified: bool = Field(True)

    # Info penjual
    seller_age_days: int = Field(365, ge=0, example=365)

    # Indikator tambahan
    has_urgent_words: bool = Field(False)
    price_ratio: float = Field(1.0, ge=0, le=2, example=1.0)


@router.post("/analyze", summary="Periksa Bon Transaksi")
def analyze_transaction(payload: ReceiptInput, db: Session = Depends(get_db)):
    """Periksa apakah bon/struk transaksi terindikasi penipuan."""
    now = datetime.now()

    subtotal = payload.subtotal or payload.total_amount
    discount_amount = payload.discount_amount or 0
    discount_pct = min(discount_amount / subtotal, 0.99) if subtotal > 0 else 0
    subtotal_ratio = payload.total_amount / subtotal if subtotal > 0 else 1.0

    trx_id = payload.order_id or f"BON-{uuid.uuid4().hex[:8].upper()}"

    data = {
        "transaction_id": trx_id,
        "total_amount": payload.total_amount,
        "discount_pct": round(discount_pct, 4),
        "subtotal_ratio": round(subtotal_ratio, 4),
        "item_count": payload.item_count,
        "hour": now.hour,
        "is_cod": int(payload.is_cod),
        "is_transfer_pribadi": int(payload.is_transfer_pribadi),
        "seller_age_days": payload.seller_age_days,
        "price_ratio": payload.price_ratio,
        "has_urgent_words": int(payload.has_urgent_words),
        "platform_verified": int(payload.platform_verified),
    }

    detection = analyze_receipt(data)

    if detection["risk_level"] == "BERBAHAYA":
        status = "DIBLOKIR"
    elif detection["is_fraud"]:
        status = "DITINJAU"
    else:
        status = "AMAN"

    trx = Transaction(
        transaction_id=trx_id,
        merchant_name=payload.seller_name,
        amount=payload.total_amount,
        recipient_name=payload.platform,
        description=payload.description,
        hour=now.hour,
        day_of_week=now.weekday(),
        transaction_count_1h=0,
        transaction_count_24h=0,
        avg_amount_7d=payload.subtotal or payload.total_amount,
        amount_deviation=round(abs(1 - subtotal_ratio), 4),
        is_new_recipient=payload.is_transfer_pribadi,
        location_change=not payload.platform_verified,
        is_weekend=(now.weekday() >= 5),
        velocity_score=round(discount_pct, 4),
        is_fraud=detection["is_fraud"],
        fraud_score=detection["fraud_score"],
        risk_level=detection["risk_level"],
        score_isolation_forest=detection["score_if"],
        score_lof=detection["score_lof"],
        score_rule_based=detection["score_rule"],
        explanation=json.dumps(detection["explanation"], ensure_ascii=False),
        status=status,
    )
    db.add(trx)
    db.commit()
    db.refresh(trx)

    return {
        "transaction_id": trx_id,
        "platform": payload.platform,
        "seller_name": payload.seller_name,
        "total_amount": payload.total_amount,
        "discount_pct": round(discount_pct * 100, 1),
        "is_fraud": detection["is_fraud"],
        "fraud_score": detection["fraud_score"],
        "risk_level": detection["risk_level"],
        "status": status,
        "explanation": detection["explanation"],
        "db_id": trx.id,
        "checked_at": trx.created_at.isoformat(),
    }


@router.get("/", summary="Riwayat Pemeriksaan Bon")
def list_transactions(
    skip: int = Query(0, ge=0),
    limit: int = Query(20, ge=1, le=100),
    is_fraud: Optional[bool] = Query(None),
    risk_level: Optional[str] = Query(None),
    status: Optional[str] = Query(None),
    db: Session = Depends(get_db),
):
    query = db.query(Transaction)
    if is_fraud is not None:
        query = query.filter(Transaction.is_fraud == is_fraud)
    if risk_level:
        query = query.filter(Transaction.risk_level == risk_level.upper())
    if status:
        query = query.filter(Transaction.status == status.upper())

    total = query.count()
    items = query.order_by(desc(Transaction.created_at)).offset(skip).limit(limit).all()

    return {
        "total": total, "skip": skip, "limit": limit,
        "data": [{
            "id": t.id,
            "transaction_id": t.transaction_id,
            "merchant_name": t.merchant_name,
            "amount": t.amount,
            "is_fraud": t.is_fraud,
            "fraud_score": t.fraud_score,
            "risk_level": t.risk_level,
            "status": t.status,
            "created_at": t.created_at.isoformat() if t.created_at else None,
        } for t in items]
    }


@router.get("/{transaction_id}", summary="Detail Bon")
def get_transaction(transaction_id: str, db: Session = Depends(get_db)):
    trx = db.query(Transaction).filter(Transaction.transaction_id == transaction_id).first()
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
        "description": trx.description,
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


@router.patch("/{transaction_id}/status", summary="Update Status")
def update_status(
    transaction_id: str,
    new_status: str = Query(..., regex="^(AMAN|DITINJAU|DIBLOKIR)$"),
    db: Session = Depends(get_db),
):
    trx = db.query(Transaction).filter(Transaction.transaction_id == transaction_id).first()
    if not trx:
        raise HTTPException(status_code=404, detail="Transaksi tidak ditemukan")
    trx.status = new_status
    db.commit()
    return {"message": "Status diperbarui", "status": trx.status}
