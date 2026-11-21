<?php

/*
| Konfigurasi sistem Pusaka Motor.
| Nilai bisa diubah lewat .env tanpa menyentuh kode.
*/

return [
    // Alamat layanan model FastAPI (ml-service)
    'ml_url' => env('ML_SERVICE_URL', 'http://127.0.0.1:8001'),
    'ml_timeout' => (int) env('ML_SERVICE_TIMEOUT', 5),

    // Fitur konteks showroom: selalu sama untuk semua motor yang diprediksi.
    // Ejaan HARUS sama persis dengan data latih (cek GET /metadata layanan model).
    'seller_type' => env('SHOWROOM_SELLER_TYPE', 'Diler'),
    'provinsi' => env('SHOWROOM_PROVINSI', 'Jawa Barat'),
    'kota' => env('SHOWROOM_KOTA', 'Karawang Kab.'),

    // PL default (persen laba terhadap HPP), 0.15 = 15%
    'default_markup' => (float) env('DEFAULT_MARKUP', 0.15),

    // File pemetaan jarak tempuh hasil ekspor notebook cleaning (opsional)
    'mileage_map_path' => storage_path('app/mileage_map.json'),
];
