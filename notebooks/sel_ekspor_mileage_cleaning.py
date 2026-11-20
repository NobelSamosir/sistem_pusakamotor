# =====================================================================
# SEL EKSPOR PEMETAAN JARAK TEMPUH (opsional tapi sangat disarankan)
# Jalankan di colab_cleaning_pusakamotor.ipynb, SETELAH kolom
# mileage_numeric dibuat. Ganti `df` dengan nama DataFrame di notebook itu.
# Hasilnya dipakai Laravel untuk mengubah km asli motor showroom
# menjadi mileage_numeric (titik tengah rentang OLX) yang sama persis.
# =====================================================================
peta = (
    df[['mileage', 'mileage_numeric']]
    .dropna()
    .drop_duplicates()
    .sort_values('mileage_numeric')
)
print(peta.to_string(index=False))

peta.to_json('mileage_map.json', orient='records', force_ascii=False, indent=2)

from google.colab import files
files.download('mileage_map.json')
