<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Model terbaik hasil Bab 4.1: XGBoost skenario B (target log harga).
 * MAE dipakai untuk range harga: L = prediksi - MAE, U = prediksi + MAE.
 */
class MlModelSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ml_models')->update(['is_active' => false]);

        DB::table('ml_models')->updateOrInsert(
            ['model_name' => 'XGBoost skenario B', 'version' => '1.0'],
            [
                'algorithm' => 'XGBRegressor',
                'target_transform' => 'log',
                'mae' => 2597925,
                'rmse' => 5860376,
                'r2' => 0.8260,
                'model_file' => 'xgb_skenario_b.joblib',
                'is_active' => true,
                'notes' => 'Uji internal 2.359 data (MAE Rp2.597.925, R2 0,8260); uji data baru 586 iklan (MAE Rp2.844.418).',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
