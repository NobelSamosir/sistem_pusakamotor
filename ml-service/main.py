"""
Layanan model harga pasar motor bekas - Sistem Pusaka Motor.
Endpoint: GET /health, GET /metadata, POST /predict
Jalankan:  uvicorn main:app --port 8001
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
MODELS_DIR = BASE_DIR / "models"
META_PATH = MODELS_DIR / "metadata.json"
PRE_PATH = MODELS_DIR / "preprocessor.joblib"
BOOSTER_PATH = MODELS_DIR / "xgb_booster.json"
MODEL_PATH = MODELS_DIR / "xgb_skenario_b.joblib"

BATAS_HARGA_TINGGI = 50_000_000
BATAS_HARGA_RENDAH = 10_000_000

if not META_PATH.exists() or not ((PRE_PATH.exists() and BOOSTER_PATH.exists()) or MODEL_PATH.exists()):
    raise RuntimeError(
        "File model belum lengkap. Taruh preprocessor.joblib, xgb_booster.json, dan metadata.json "
        f"di folder: {MODELS_DIR}"
    )

with open(META_PATH, encoding="utf-8") as f:
    META = json.load(f)

MAE = float(META["mae"])
ORDER = META["feature_order"]
CATS = META["categories"]

VERSI_SEKARANG = {"scikit-learn": sklearn.__version__, "xgboost": xgboost.__version__}
for lib, versi in VERSI_SEKARANG.items():
    versi_colab = META.get("versions", {}).get(lib)
    if versi_colab and versi_colab != versi:
        print(f"[PERINGATAN] {lib} di laptop {versi}, di Colab {versi_colab}. "
              f"Pasang versi yang sama: pip install {lib}=={versi_colab}")

with py_warnings.catch_warnings():
    py_warnings.simplefilter("ignore")
    if PRE_PATH.exists() and BOOSTER_PATH.exists():
        PRE = joblib.load(PRE_PATH)
        BOOSTER = xgboost.XGBRegressor()
        BOOSTER.load_model(str(BOOSTER_PATH))
        FORMAT_MODEL = "preprocessor.joblib + xgb_booster.json"
    else:
        PIPELINE = joblib.load(MODEL_PATH)
        FORMAT_MODEL = "xgb_skenario_b.joblib"
print(f"[INFO] Model dimuat dari: {FORMAT_MODEL}")


def predict_log(baris: pd.DataFrame) -> float:
    if FORMAT_MODEL.startswith("preprocessor"):
        return float(BOOSTER.predict(PRE.transform(baris))[0])
    return float(PIPELINE.predict(baris)[0])


class Motor(BaseModel):
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
    return {"status": "ok", "model": META.get("model_name"), "version": META.get("version"),
            "mae": MAE, "format": FORMAT_MODEL, "library": VERSI_SEKARANG}


@app.get("/metadata")
def metadata():
    return {"model_name": META.get("model_name"), "version": META.get("version"), "mae": MAE,
            "rmse": META.get("rmse"), "r2": META.get("r2"), "feature_order": ORDER, "categories": CATS}


@app.post("/predict")
def predict(motor: Motor):
    data = motor.model_dump()
    try:
        baris = pd.DataFrame([data])[ORDER]
    except KeyError as e:
        raise HTTPException(status_code=422, detail=f"Fitur tidak cocok dengan model: {e}")

    prediksi = float(np.exp(predict_log(baris)))

    peringatan = []
    for kolom in ("brand", "model", "kota"):
        if kolom in CATS and str(data[kolom]) not in CATS[kolom]:
            peringatan.append({"kode": f"kategori_baru:{kolom}",
                               "pesan": f"{kolom} '{data[kolom]}' tidak ada di data latih, hasil sebaiknya dicek manual"})
    if prediksi > BATAS_HARGA_TINGGI:
        peringatan.append({"kode": "segmen_harga_tinggi",
                           "pesan": "Prediksi di atas Rp50 juta, range kurang andal, bandingkan dengan harga pasar manual"})
    if prediksi < BATAS_HARGA_RENDAH:
        peringatan.append({"kode": "segmen_harga_rendah",
                           "pesan": "Prediksi di bawah Rp10 juta, range relatif lebar untuk motor murah"})

    return {"predicted_price": round(prediksi), "lower_bound": round(max(prediksi - MAE, 0)),
            "upper_bound": round(prediksi + MAE), "mae": round(MAE),
            "model_version": META.get("version"), "warnings": peringatan}