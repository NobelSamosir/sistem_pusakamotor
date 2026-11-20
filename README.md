# Sistem SIA dan DSS Showroom Pusaka Motor

Struktur folder:

```
pusaka-motor/
├── backend/      Laravel (dibuat di Tahap 2)
├── frontend/     React + Vite (dibuat di Tahap 4)
├── ml-service/   Layanan model FastAPI (Tahap 1, sudah tersedia)
│   ├── main.py
│   ├── test_vario.py
│   ├── requirements.txt
│   └── models/   taruh xgb_skenario_b.joblib dan metadata.json di sini
└── notebooks/    sel ekspor untuk Colab
```

## Tahap 1: persiapan dan layanan model

### 1. Pasang alat di laptop (Windows)

| Alat | Keterangan | Cek di PowerShell |
| --- | --- | --- |
| VS Code | Editor kode | |
| Git | Riwayat perubahan kode | `git --version` |
| Python | Versi minor sama dengan Colab (lihat `!python --version` di Colab). Saat instalasi centang "Add python.exe to PATH" | `python --version` |
| Node.js LTS | Untuk React (Tahap 4) | `node -v` |
| PHP + Composer + Laravel installer | Lewat Laravel Herd atau perintah php.new di dokumentasi Laravel | `php -v`, `composer -V`, `laravel --version` |

### 2. Buat project Supabase

1. Daftar di supabase.com, buat project baru, region **Southeast Asia (Singapore)**.
2. Simpan password database di tempat aman.
3. Klik **Connect**, pilih **Session pooler**, catat host, port 5432, dan username (`postgres.xxxx`). Dipakai di Tahap 2.
4. Matikan Data API di pengaturan project, atau nanti aktifkan RLS di semua tabel.

### 3. Ekspor model dari Colab

1. Buka `Uji ML.ipynb`, buat sel baru tepat di bawah sel training `model_terbaik = Pipeline([...])`.
2. Salin isi `notebooks/sel_ekspor_model_colab.py` ke sel itu, jalankan.
3. Pastikan output: data uji 2359, MAE 2597925, prediksi Vario 19777670, jumlah fitur 17.
4. Dua file terunduh: `xgb_skenario_b.joblib` dan `metadata.json`. Pindahkan ke `ml-service/models/`.
5. (Disarankan) Jalankan `notebooks/sel_ekspor_mileage_cleaning.py` di notebook cleaning, simpan `mileage_map.json` di `ml-service/models/` juga.

### 4. Jalankan layanan model

```powershell
cd pusaka-motor\ml-service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
pip install scikit-learn==VERSI_COLAB xgboost==VERSI_COLAB
uvicorn main:app --port 8001
```

Kalau `activate` ditolak PowerShell, jalankan sekali:
`Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`

Versi scikit-learn dan xgboost ambil dari bagian `versions` di `models/metadata.json`.

### 5. Uji penerimaan

Biarkan layanan menyala, buka terminal kedua:

```powershell
cd pusaka-motor\ml-service
.venv\Scripts\activate
python test_vario.py
```

Hasil yang benar: `LULUS`, prediksi Rp19.777.670, range Rp17.179.745 sampai Rp22.375.595.
Buka juga http://127.0.0.1:8001/docs untuk mencoba endpoint lewat browser.
Screenshot hasil ini bisa langsung dipakai di Bab 4.2.3.
