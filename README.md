# 🛡️ Sistem Deteksi Penipuan Transaksi Digital UMKM

**Kelompok 6** — Farrel • Farzad • Hafif • Arung

> Sistem deteksi penipuan transaksi digital untuk UMKM menggunakan **Anomaly Detection** dengan pendekatan ensemble machine learning.

---

## 📋 Deskripsi

Sistem ini mendeteksi transaksi yang mencurigakan (penipuan/fraud) pada ekosistem transaksi digital UMKM menggunakan kombinasi tiga algoritma:

| Algoritma | Bobot | Keterangan |
|---|---|---|
| **Isolation Forest** | 45% | Mendeteksi outlier global — transaksi dengan pola yang sangat berbeda |
| **Local Outlier Factor** | 35% | Mendeteksi outlier lokal — anomali dalam konteks tetangga terdekat |
| **Rule-Based Logic** | 20% | Aturan bisnis UMKM (jam operasional, frekuensi, rasio nominal) |

---

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────┐
│                   Frontend (HTML/JS)                 │
│   Dashboard • Analisis Form • Riwayat • Model Info  │
└──────────────────────┬──────────────────────────────┘
                       │ HTTP/REST
┌──────────────────────▼──────────────────────────────┐
│              Backend (FastAPI / Python)              │
│   /api/v1/transactions  /analytics  /model          │
└──────────────────────┬──────────────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        ▼              ▼              ▼
   SQLite DB    ML Fraud Detector  Static Files
                (IF + LOF + Rules)
```

---

## 🚀 Cara Menjalankan

### Cepat (menggunakan script)

```bash
chmod +x start.sh
./start.sh
```

Script akan otomatis:
1. Membuat virtual environment Python
2. Menginstall semua dependency
3. Melatih model ML (jika belum ada)
4. Mengisi database dengan data demo
5. Menjalankan server

Akses di:
- **Dashboard**: http://localhost:8000
- **API Docs**: http://localhost:8000/docs

---

### Manual

```bash
# 1. Install dependency
pip install -r backend/requirements.txt

# 2. Training model
cd backend/ml
python train.py

# 3. Seed database (opsional — untuk data demo)
cd backend
python seed.py

# 4. Jalankan server
cd backend
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

---

## 📊 Fitur Sistem

### Dashboard
- Statistik ringkasan (total transaksi, fraud rate, nilai transaksi)
- Grafik tren transaksi 30 hari
- Distribusi level risiko (doughnut chart)
- Pola transaksi per jam
- Daftar fraud score tertinggi

### Analisis Transaksi
- Form input detail transaksi
- Hasil deteksi real-time dengan fraud score
- Penjelasan alasan deteksi (explainable AI)
- Demo transaksi normal & fraud

### Riwayat Transaksi
- Tabel semua transaksi dengan filter
- Detail transaksi lengkap dengan skor per model
- Update status manual (Approve/Block)

### Model AI
- Informasi metrik performa model
- Bobot ensemble
- Tombol retrain model

---

## 📡 API Endpoints

| Method | Endpoint | Keterangan |
|---|---|---|
| `POST` | `/api/v1/transactions/analyze` | Analisis transaksi baru |
| `GET` | `/api/v1/transactions/` | Daftar transaksi |
| `GET` | `/api/v1/transactions/{id}` | Detail transaksi |
| `PATCH` | `/api/v1/transactions/{id}/status` | Update status |
| `GET` | `/api/v1/analytics/summary` | Ringkasan statistik |
| `GET` | `/api/v1/analytics/trend` | Tren harian |
| `GET` | `/api/v1/analytics/top-fraud` | Top fraud |
| `GET` | `/api/v1/analytics/risk-distribution` | Distribusi risiko |
| `GET` | `/api/v1/analytics/hourly-pattern` | Pola per jam |
| `GET` | `/api/v1/model/info` | Info model |
| `POST` | `/api/v1/model/retrain/sync` | Latih ulang model |

---

## 🔍 Fitur untuk Deteksi

Model menggunakan 11 fitur transaksi:

| Fitur | Keterangan |
|---|---|
| `amount` | Nominal transaksi (Rp) |
| `hour` | Jam transaksi (0–23) |
| `day_of_week` | Hari dalam seminggu |
| `transaction_count_1h` | Jumlah transaksi 1 jam terakhir |
| `transaction_count_24h` | Jumlah transaksi 24 jam terakhir |
| `avg_amount_7d` | Rata-rata nominal 7 hari |
| `amount_deviation` | Deviasi dari rata-rata historis |
| `is_new_recipient` | Penerima baru? |
| `location_change` | Perubahan lokasi mendadak? |
| `is_weekend` | Transaksi di akhir pekan? |
| `velocity_score` | Skor kecepatan transaksi (0–1) |

---

## 📁 Struktur Project

```
Sistem-Deteksi-Penipuan-Transaksi-Digital-UMKM-/
├── backend/
│   ├── app/
│   │   ├── core/           # Konfigurasi
│   │   ├── models/         # SQLAlchemy models & database
│   │   ├── routes/         # FastAPI route handlers
│   │   └── services/       # Business logic
│   ├── ml/
│   │   ├── data_generator.py    # Generator data sintetis
│   │   ├── fraud_detector.py    # Core ML model
│   │   └── train.py             # Script training
│   ├── requirements.txt
│   └── seed.py             # Database seeder
├── frontend/
│   ├── css/style.css       # Styling
│   ├── js/
│   │   ├── api.js          # API client
│   │   ├── utils.js        # Helpers
│   │   └── app.js          # App controller
│   └── index.html          # Dashboard utama
├── data/                   # Database & dataset
├── start.sh                # Script startup
└── .env.example
```

---

## 👥 Kelompok 6

| Nama | Peran |
|---|---|
| **Farrel** | Machine Learning & Feature Engineering |
| **Farzad** | Backend API & Database |
| **Hafif** | Frontend Dashboard |
| **Arung** | Analisis Data & Dokumentasi |

---

*Mata Kuliah: Keamanan Transaksi Digital — 2024*
