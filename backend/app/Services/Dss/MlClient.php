<?php

namespace App\Services\Dss;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Penghubung Laravel dengan layanan model FastAPI (ml-service).
 * Frontend tidak pernah memanggil layanan model langsung.
 */
class MlClient
{
    /**
     * @return array{predicted_price: float, lower_bound: float, upper_bound: float, mae: float, warnings: array}
     */
    public function predict(array $features): array
    {
        try {
            return Http::baseUrl(config('pusaka.ml_url'))
                ->timeout(config('pusaka.ml_timeout'))
                ->acceptJson()
                ->post('/predict', $features)
                ->throw()
                ->json();
        } catch (ConnectionException) {
            throw new BusinessRuleException('Layanan prediksi sedang tidak aktif. Jalankan ml-service (uvicorn) terlebih dahulu.');
        } catch (RequestException $e) {
            throw new BusinessRuleException('Layanan prediksi menolak data: '.$e->response->body());
        }
    }

    /** Daftar kategori fitur (isi dropdown form), disimpan sementara 10 menit. */
    public function metadata(): array
    {
        return Cache::remember('ml.metadata', 600, function () {
            try {
                return Http::baseUrl(config('pusaka.ml_url'))
                    ->timeout(config('pusaka.ml_timeout'))
                    ->acceptJson()->get('/metadata')->throw()->json();
            } catch (ConnectionException) {
                throw new BusinessRuleException('Layanan prediksi sedang tidak aktif. Jalankan ml-service (uvicorn) terlebih dahulu.');
            }
        });
    }

    public function health(): ?array
    {
        try {
            return Http::baseUrl(config('pusaka.ml_url'))->timeout(3)->acceptJson()->get('/health')->json();
        } catch (\Throwable) {
            return null;
        }
    }
}
