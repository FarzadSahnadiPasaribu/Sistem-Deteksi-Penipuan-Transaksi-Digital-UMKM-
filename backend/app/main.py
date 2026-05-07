"""
FraudShield UMKM — Deteksi Penipuan Bon Transaksi Digital
Jalankan: uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
import os

from .core.config import get_settings
from .models.database import Base, engine
from .routes import transactions_router, analytics_router, model_router

settings = get_settings()
Base.metadata.create_all(bind=engine)

app = FastAPI(
    title="FraudShield UMKM",
    version="2.0.0",
    description="Sistem Deteksi Penipuan Bon Transaksi Digital UMKM",
    docs_url="/docs",
    redoc_url="/redoc",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(transactions_router, prefix=settings.API_PREFIX)
app.include_router(analytics_router, prefix=settings.API_PREFIX)
app.include_router(model_router, prefix=settings.API_PREFIX)

frontend_path = os.path.join(os.path.dirname(__file__), "../../frontend")
if os.path.exists(frontend_path):
    app.mount("/static", StaticFiles(directory=frontend_path), name="static")

    @app.get("/", include_in_schema=False)
    def serve_frontend():
        return FileResponse(os.path.join(frontend_path, "index.html"))


@app.get("/health", tags=["System"])
def health_check():
    return {"status": "ok", "app": "FraudShield UMKM", "version": "2.0.0"}


@app.on_event("startup")
async def startup_event():
    from .services.fraud_service import get_detector
    try:
        get_detector()
        print("[FraudShield] Siap.")
    except Exception as e:
        print(f"[FraudShield] Warning startup: {e}")
