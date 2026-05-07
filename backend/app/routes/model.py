from fastapi import APIRouter, BackgroundTasks
from ..services.fraud_service import get_model_info, retrain_model

router = APIRouter(prefix="/model", tags=["Model AI"])


@router.get("/info", summary="Informasi Model")
def model_info():
    """Tampilkan informasi dan metrik performa model yang aktif."""
    return get_model_info()


@router.post("/retrain", summary="Latih Ulang Model")
def trigger_retrain(background_tasks: BackgroundTasks):
    """
    Latih ulang model dengan data terbaru.
    Proses berjalan di background — pantau status via /model/info.
    """
    background_tasks.add_task(retrain_model)
    return {
        "message": "Proses training dimulai di background.",
        "info": "Cek /api/v1/model/info untuk melihat hasil setelah selesai.",
    }


@router.post("/retrain/sync", summary="Latih Ulang Model (Sinkron)")
def trigger_retrain_sync():
    """Latih ulang model secara sinkron (tunggu sampai selesai)."""
    result = retrain_model()
    return result
