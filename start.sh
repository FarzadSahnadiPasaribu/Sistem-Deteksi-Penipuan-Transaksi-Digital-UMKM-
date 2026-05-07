#!/bin/bash
# =========================================================
# Sistem Deteksi Penipuan Transaksi Digital UMKM
# Script startup otomatis
# Kelompok 6: Farrel, Farzad, Hafif, Arung
# =========================================================

set -e

echo "=================================================="
echo " SISTEM DETEKSI PENIPUAN TRANSAKSI DIGITAL UMKM"
echo " Kelompok 6: Farrel, Farzad, Hafif, Arung"
echo "=================================================="

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKEND_DIR="$PROJECT_DIR/backend"
DATA_DIR="$PROJECT_DIR/data"
VENV_DIR="$PROJECT_DIR/.venv"

# 1. Buat direktori data
mkdir -p "$DATA_DIR"

# 2. Setup virtual environment
if [ ! -d "$VENV_DIR" ]; then
  echo "[1/5] Membuat virtual environment..."
  python3 -m venv "$VENV_DIR"
fi
source "$VENV_DIR/bin/activate"

# 3. Install dependencies
echo "[2/5] Menginstall dependencies..."
pip install -q -r "$BACKEND_DIR/requirements.txt"

# 4. Copy .env
if [ ! -f "$PROJECT_DIR/.env" ]; then
  cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
  echo "[3/5] File .env dibuat dari .env.example"
fi

# 5. Training model (jika belum ada)
MODEL_META="$BACKEND_DIR/ml/saved_models/metadata.json"
if [ ! -f "$MODEL_META" ]; then
  echo "[4/5] Melatih model deteksi (pertama kali)..."
  cd "$BACKEND_DIR/ml" && python train.py && cd "$PROJECT_DIR"
else
  echo "[4/5] Model sudah ada, skip training."
fi

# 6. Seed database (jika belum ada)
DB_FILE="$DATA_DIR/umkm_fraud.db"
if [ ! -f "$DB_FILE" ]; then
  echo "[5/5] Seeding database dengan data demo..."
  cd "$BACKEND_DIR" && python seed.py && cd "$PROJECT_DIR"
else
  echo "[5/5] Database sudah ada."
fi

echo ""
echo "✅ Setup selesai!"
echo ""
echo "Menjalankan server..."
echo "  Dashboard : http://localhost:8000"
echo "  API Docs  : http://localhost:8000/docs"
echo ""
cd "$BACKEND_DIR"
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
