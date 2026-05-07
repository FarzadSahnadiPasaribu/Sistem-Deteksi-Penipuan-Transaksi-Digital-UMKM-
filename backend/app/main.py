"""
Sistem Deteksi Penipuan Transaksi Digital UMKM
Kelompok 6: Farrel, Farzad, Hafif, Arung

Jalankan: uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
Docs: http://localhost:8000/docs
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

# Buat tabel database
Base.metadata.create_all(bind=engine)

app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    description=settings.APP_DESCRIPTION,
    docs_url="/docs",
    redoc_url="/redoc",
)

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# API Routes
app.include_router(transactions_router, prefix=settings.API_PREFIX)
app.include_router(analytics_router, prefix=settings.API_PREFIX)
app.include_router(model_router, prefix=settings.API_PREFIX)

# Sajikan frontend statis
frontend_path = os.path.join(os.path.dirname(__file__), "../../frontend")
if os.path.exists(frontend_path):
    app.mount("/static", StaticFiles(directory=frontend_path), name="static")

    @app.get("/", include_in_schema=False)
    def serve_frontend():
        return FileResponse(os.path.join(frontend_path, "index.html"))


@app.get("/health", tags=["System"])
def health_check():
    return {
        "status": "ok",
        "app": settings.APP_NAME,
        "version": settings.APP_VERSION,
    }


@app.on_event("startup")
async def startup_event():
    """Pre-load model saat server pertama kali berjalan."""
    from .services.fraud_service import get_detector
    try:
        get_detector()
        print(f"[Startup] {settings.APP_NAME} siap melayani.")
    except Exception as e:
        print(f"[Startup] Warning: {e}")
