"""
Uji penerimaan layanan model.
Mengirim contoh Honda Vario 2022 (Gambar 4.23) ke /predict dan
membandingkannya dengan angka yang tersimpan di metadata.json.

Jalankan (layanan harus sudah menyala di port 8001):
    python test_vario.py
"""
import json
import urllib.request
from pathlib import Path

URL = "http://127.0.0.1:8001"
META = json.loads((Path(__file__).parent / "models" / "metadata.json").read_text(encoding="utf-8"))
contoh = META["check_example"]["input"]
harapan = META["check_example"]["expected_price"]


def rupiah(x):
    return "Rp" + f"{x:,.0f}".replace(",", ".")


req = urllib.request.Request(
    URL + "/predict",
    data=json.dumps(contoh).encode("utf-8"),
    headers={"Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(req) as resp:
    hasil = json.loads(resp.read().decode("utf-8"))

print("Prediksi     :", rupiah(hasil["predicted_price"]))
print("Range        :", rupiah(hasil["lower_bound"]), "sampai", rupiah(hasil["upper_bound"]))
print("Harapan      :", rupiah(harapan), "(dari notebook)")
print("Peringatan   :", [w["kode"] for w in hasil["warnings"]] or "tidak ada")

if hasil["predicted_price"] == harapan:
    print("\nLULUS: layanan memberi angka yang sama persis dengan notebook.")
else:
    selisih = hasil["predicted_price"] - harapan
    print(f"\nGAGAL: selisih {rupiah(selisih)}. Hampir pasti versi scikit-learn atau xgboost "
          "berbeda dengan Colab. Cek bagian 'versions' di metadata.json.")
