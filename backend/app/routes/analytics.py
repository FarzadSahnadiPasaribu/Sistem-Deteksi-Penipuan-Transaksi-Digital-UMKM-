from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from sqlalchemy import func, desc
from datetime import datetime, timedelta
from typing import Optional

from ..models import get_db, Transaction

router = APIRouter(prefix="/analytics", tags=["Analitik"])


@router.get("/summary", summary="Ringkasan Statistik")
def get_summary(db: Session = Depends(get_db)):
    """Ringkasan statistik sistem deteksi penipuan."""
    total = db.query(func.count(Transaction.id)).scalar() or 0
    total_fraud = db.query(func.count(Transaction.id)).filter(Transaction.is_fraud == True).scalar() or 0
    total_normal = total - total_fraud
    total_blocked = db.query(func.count(Transaction.id)).filter(Transaction.status == "BLOCKED").scalar() or 0
    total_review = db.query(func.count(Transaction.id)).filter(Transaction.status == "REVIEW").scalar() or 0
    total_approved = db.query(func.count(Transaction.id)).filter(Transaction.status == "APPROVED").scalar() or 0

    total_amount = db.query(func.sum(Transaction.amount)).scalar() or 0
    fraud_amount = (
        db.query(func.sum(Transaction.amount)).filter(Transaction.is_fraud == True).scalar() or 0
    )

    avg_fraud_score = db.query(func.avg(Transaction.fraud_score)).scalar() or 0
    fraud_rate = (total_fraud / total * 100) if total > 0 else 0

    risk_breakdown = {}
    for level in ["AMAN", "WASPADA", "BERISIKO", "BERBAHAYA", "RENDAH", "SEDANG", "TINGGI", "KRITIS"]:
        count = db.query(func.count(Transaction.id)).filter(
            Transaction.risk_level == level
        ).scalar() or 0
        risk_breakdown[level] = count

    return {
        "total_transactions": total,
        "total_fraud": total_fraud,
        "total_normal": total_normal,
        "fraud_rate_pct": round(fraud_rate, 2),
        "total_blocked": total_blocked,
        "total_review": total_review,
        "total_approved": total_approved,
        "total_amount_idr": total_amount,
        "fraud_amount_idr": fraud_amount,
        "avg_fraud_score": round(float(avg_fraud_score), 4),
        "risk_breakdown": risk_breakdown,
    }


@router.get("/trend", summary="Tren Transaksi Harian")
def get_trend(
    days: int = Query(30, ge=1, le=365),
    db: Session = Depends(get_db),
):
    """Tren jumlah transaksi normal vs fraud per hari."""
    since = datetime.now() - timedelta(days=days)
    transactions = (
        db.query(Transaction)
        .filter(Transaction.created_at >= since)
        .all()
    )

    daily: dict = {}
    for t in transactions:
        if t.created_at:
            day = t.created_at.strftime("%Y-%m-%d")
            if day not in daily:
                daily[day] = {"date": day, "normal": 0, "fraud": 0, "total": 0, "total_amount": 0}
            daily[day]["total"] += 1
            daily[day]["total_amount"] += t.amount
            if t.is_fraud:
                daily[day]["fraud"] += 1
            else:
                daily[day]["normal"] += 1

    return {"days": days, "data": sorted(daily.values(), key=lambda x: x["date"])}


@router.get("/top-fraud", summary="Transaksi Fraud Tertinggi")
def get_top_fraud(
    limit: int = Query(10, ge=1, le=50),
    db: Session = Depends(get_db),
):
    """Daftar transaksi dengan fraud score tertinggi."""
    items = (
        db.query(Transaction)
        .filter(Transaction.is_fraud == True)
        .order_by(desc(Transaction.fraud_score))
        .limit(limit)
        .all()
    )

    return {
        "data": [
            {
                "transaction_id": t.transaction_id,
                "merchant_name": t.merchant_name,
                "amount": t.amount,
                "fraud_score": t.fraud_score,
                "risk_level": t.risk_level,
                "status": t.status,
                "created_at": t.created_at.isoformat() if t.created_at else None,
            }
            for t in items
        ]
    }


@router.get("/risk-distribution", summary="Distribusi Level Risiko")
def get_risk_distribution(db: Session = Depends(get_db)):
    """Distribusi transaksi berdasarkan level risiko dan status."""
    result = []
    for level in ["AMAN", "WASPADA", "BERISIKO", "BERBAHAYA", "RENDAH", "SEDANG", "TINGGI", "KRITIS"]:
        count = db.query(func.count(Transaction.id)).filter(
            Transaction.risk_level == level
        ).scalar() or 0
        avg_amount = (
            db.query(func.avg(Transaction.amount)).filter(
                Transaction.risk_level == level
            ).scalar() or 0
        )
        result.append({
            "risk_level": level,
            "count": count,
            "avg_amount": round(float(avg_amount)),
        })
    return {"data": result}


@router.get("/hourly-pattern", summary="Pola Transaksi per Jam")
def get_hourly_pattern(db: Session = Depends(get_db)):
    """Pola jumlah transaksi fraud dan normal per jam dalam sehari."""
    transactions = db.query(Transaction.hour, Transaction.is_fraud).all()

    hourly = {h: {"hour": h, "normal": 0, "fraud": 0} for h in range(24)}
    for hour, is_fraud in transactions:
        if hour is not None:
            if is_fraud:
                hourly[hour]["fraud"] += 1
            else:
                hourly[hour]["normal"] += 1

    return {"data": list(hourly.values())}
