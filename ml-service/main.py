"""
Layanan model harga pasar motor bekas - Sistem Pusaka Motor.

Memuat pipeline XGBoost skenario B (target log harga) hasil ekspor dari
notebook Uji ML.ipynb, lalu menyediakan tiga endpoint:

    GET  /health    status layanan dan model yang dimuat
    GET  /metadata  urutan fitur, daftar kategori (isi dropdown), MAE
    POST /predict   17 fitur motor -> prediksi, batas bawah (L), batas atas (U)

Jalankan:  uvicorn main:app --port 8001
Dokumentasi interaktif otomatis: http://127.0.0.1:8001/docs
"""
import json
import warnings as py_warnings
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import sklearn
import xgboost
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "models" / "xgb_skenario_b.joblib"
META_PATH = BASE_DIR / "models" / "metadata.json"

# Ambang peringatan keandalan, diambil dari temuan Bab 4.1.6 dan 4.1.8
BATAS_HARGA_TINGGI = 50_000_000   # MAE kelompok Rp50-100 juta: Rp13,6 jt (internal), Rp22,2 jt (uji baru)
BATAS_HARGA_RENDAH = 10_000_000   # MAE kelompok < Rp10 juta: 25,3% s.d. 33,7% dari harga rata-rata

if not MODEL_PATH.exists() or not META_PATH.exists():
    raise RuntimeError(
        "File model belum ada. Taruh xgb_skenario_b.joblib dan metadata.json "
        f"di folder: {BASE_DIR / 'models'}"
    )

with open(META_PATH, encoding="utf-8") as f:
    META = json.load(f)

MAE = float(META["mae"])
ORDER = META["feature_order"]
CATS = META["categories"]

# Peringatan bila versi library di laptop berbeda dengan versi di Colab.
# Beda versi bisa membuat model gagal dimuat atau memberi angka yang sedikit lain.
VERSI_SEKARANG = {"scikit-learn": sklearn.__version__, "xgboost": xgboost.__version__}
for lib, versi in VERSI_SEKARANG.items():
    versi_colab = META.get("versions", {}).get(lib)
    if versi_colab and versi_colab != versi:
        print(f"[PERINGATAN] {lib} di laptop {versi}, di Colab {versi_colab}. "
              f"Pasang versi yang sama: pip install {lib}=={versi_colab}")

with py_warnings.catch_warnings():
    py_warnings.simplefilter("ignore")
    MODEL = joblib.load(MODEL_PATH)


class Motor(BaseModel):
    """17 fitur sesuai Tabel 3.3. Nama dan ejaan kategori harus sama dengan data latih."""
    year: int = Field(..., ge=1970, le=2100)
    mileage_numeric: float = Field(..., ge=0)
    n_dokumen: int = Field(..., ge=0)
    ada_bpkb: int = Field(..., ge=0, le=1)
    ada_stnk: int = Field(..., ge=0, le=1)
    ada_faktur: int = Field(..., ge=0, le=1)
    ada_buku_servis: int = Field(..., ge=0, le=1)
    dokumen_tidak_diketahui: int = Field(..., ge=0, le=1)
    brand: str
    transmission: str
    engine_capacity: str
    body_type: str
    ownership_status: str
    seller_type: str
    provinsi: str
    model: str
    kota: str


app = FastAPI(title="Layanan Model Pusaka Motor", version=str(META.get("version", "1.0")))


@app.get("/health")
def health():
    return {
        "status": "ok",
        "model": META.get("model_name"),
        "version": META.get("version"),
        "mae": MAE,
        "library": VERSI_SEKARANG,
    }


@app.get("/metadata")
def metadata():
    return {
        "model_name": META.get("model_name"),
        "version": META.get("version"),
        "mae": MAE,
        "rmse": META.get("rmse"),
        "r2": META.get("r2"),
        "feature_order": ORDER,
        "categories": CATS,
    }


@app.post("/predict")
def predict(motor: Motor):
    data = motor.model_dump()
    try:
        baris = pd.DataFrame([data])[ORDER]
    except KeyError as e:
        raise HTTPException(status_code=422, detail=f"Fitur tidak cocok dengan model: {e}")

    # Skenario B: model memprediksi log harga, dikembalikan ke rupiah dengan exp
    prediksi = float(np.exp(MODEL.predict(baris)[0]))

    peringatan = []
    for kolom in ("brand", "model", "kota"):
        if kolom in CATS and str(data[kolom]) not in CATS[kolom]:
            peringatan.append({
                "kode": f"kategori_baru:{kolom}",
                "pesan": f"{kolom} '{data[kolom]}' tidak ada di data latih, hasil sebaiknya dicek manual",
            })
    if prediksi > BATAS_HARGA_TINGGI:
        peringatan.append({
            "kode": "segmen_harga_tinggi",
            "pesan": "Prediksi di atas Rp50 juta, range kurang andal, bandingkan dengan harga pasar manual",
        })
    if prediksi < BATAS_HARGA_RENDAH:
        peringatan.append({
            "kode": "segmen_harga_rendah",
            "pesan": "Prediksi di bawah Rp10 juta, range relatif lebar untuk motor murah",
        })

    return {
        "predicted_price": round(prediksi),
        "lower_bound": round(max(prediksi - MAE, 0)),
        "upper_bound": round(prediksi + MAE),
        "mae": round(MAE),
        "model_version": META.get("version"),
        "warnings": peringatan,
    }
