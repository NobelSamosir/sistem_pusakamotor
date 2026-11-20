# =====================================================================
# SEL EKSPOR MODEL UNTUK SISTEM PUSAKA MOTOR
# Salin seluruh isi file ini ke SATU sel baru di Uji ML.ipynb,
# letakkan TEPAT DI BAWAH sel training:
#     model_terbaik = Pipeline([...])
#     model_terbaik.fit(X_train, np.log(y_train))
# Variabel yang dipakai: model_terbaik, num, cat, X_train, X_test, y_test
# =====================================================================
import json, sys
import joblib, sklearn, xgboost
import numpy as np
import pandas as pd
from sklearn.metrics import mean_absolute_error

# 1) Cek MAE pada data uji internal (harus sama dengan Bab 4: Rp2.597.925)
pred_cek = np.exp(model_terbaik.predict(X_test))
MAE_EKSPOR = float(mean_absolute_error(y_test, pred_cek))
print("Jumlah data uji :", len(X_test))          # harus 2359
print("MAE             :", round(MAE_EKSPOR))     # harus 2597925

# 2) Cek contoh Honda Vario 2022 seperti Gambar 4.23
vario = {
    'year': 2022, 'mileage_numeric': 12500,
    'n_dokumen': 3, 'ada_bpkb': 1, 'ada_stnk': 1, 'ada_faktur': 1, 'ada_buku_servis': 0,
    'dokumen_tidak_diketahui': 0,
    'brand': 'Honda', 'model': 'Vario', 'transmission': 'Otomatis',
    'engine_capacity': '110-125 cc', 'body_type': 'Scooter',
    'ownership_status': 'Ke-1', 'seller_type': 'Diler',
    'provinsi': 'Jawa Barat', 'kota': 'Bandung Kota',
}
pred_vario = float(np.exp(model_terbaik.predict(pd.DataFrame([vario])[num + cat])[0]))
print("Prediksi Vario  :", round(pred_vario))     # harus 19777670
print("Range Vario     :", round(pred_vario - MAE_EKSPOR), "sampai", round(pred_vario + MAE_EKSPOR))

# 3) Simpan model
joblib.dump(model_terbaik, "xgb_skenario_b.joblib")

# 4) Simpan metadata (urutan fitur, kategori untuk dropdown, MAE, versi library)
meta = {
    "model_name": "XGBoost skenario B",
    "algorithm": "XGBRegressor",
    "target": "log_price",
    "version": "1.0",
    "mae": MAE_EKSPOR,
    "rmse": 5860376,
    "r2": 0.8260,
    "feature_order": list(num) + list(cat),
    "numeric_features": list(num),
    "categorical_features": list(cat),
    "categories": {c: sorted(X_train[c].astype(str).unique().tolist()) for c in cat},
    "check_example": {"input": vario, "expected_price": round(pred_vario)},
    "versions": {
        "python": sys.version.split()[0],
        "scikit-learn": sklearn.__version__,
        "xgboost": xgboost.__version__,
        "pandas": pd.__version__,
        "numpy": np.__version__,
        "joblib": joblib.__version__,
    },
}
with open("metadata.json", "w", encoding="utf-8") as f:
    json.dump(meta, f, indent=2, ensure_ascii=False)

print("\nJumlah fitur    :", len(meta["feature_order"]))   # harus 17
print("Versi library (catat, nanti dipasang sama persis di laptop):")
print(json.dumps(meta["versions"], indent=2))

# 5) Unduh kedua file ke laptop
from google.colab import files
files.download("xgb_skenario_b.joblib")
files.download("metadata.json")
