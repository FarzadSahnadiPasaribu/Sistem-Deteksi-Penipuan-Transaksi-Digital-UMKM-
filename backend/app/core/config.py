from pydantic_settings import BaseSettings
from functools import lru_cache
import os


class Settings(BaseSettings):
    APP_NAME: str = "Sistem Deteksi Penipuan Transaksi UMKM"
    APP_VERSION: str = "1.0.0"
    APP_DESCRIPTION: str = (
        "API Deteksi Penipuan Transaksi Digital UMKM "
        "menggunakan Anomaly Detection (Isolation Forest + LOF)"
    )

    DATABASE_URL: str = "sqlite:///./data/umkm_fraud.db"
    MODEL_PATH: str = os.path.join(os.path.dirname(__file__), "../../ml/saved_models")

    API_PREFIX: str = "/api/v1"
    CORS_ORIGINS: list = ["*"]

    # Threshold fraud score untuk alert
    FRAUD_SCORE_ALERT: float = 0.5
    FRAUD_SCORE_CRITICAL: float = 0.75

    # Claude AI (optional)
    ANTHROPIC_API_KEY: str = ""

    class Config:
        env_file = ".env"
        case_sensitive = True


@lru_cache()
def get_settings() -> Settings:
    return Settings()
