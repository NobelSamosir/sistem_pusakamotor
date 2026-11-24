<?php

namespace App\Services\Dss;

use App\Services\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Aturan keputusan Tabel 3.10 dalam bentuk fungsi murni (tanpa database),
 * sehingga mudah diuji dan dijelaskan di Bab 4.
 */
final class DssRules
{
    public const LAYAK = 'Layak dibeli';
    public const MARGIN_TIPIS = 'Margin tipis';
    public const TIDAK_DISARANKAN = 'Tidak disarankan';
    public const BERISIKO_RUGI = 'Berisiko rugi';
    public const DI_BAWAH_PASAR = 'Di bawah pasar';
    public const SESUAI_PASAR = 'Sesuai pasar';
    public const DI_ATAS_PASAR = 'Di atas pasar';

    /**
     * DSS pembelian, sesuai cara showroom membeli (hasil wawancara pemilik).
     *
     * Biaya servis/pajak hanya bisa diperkirakan saat menawar, jadi dipisahkan dari potongan:
     *   - estimasi biaya (B)  : perkiraan servis/pajak yang nanti dibayar showroom
     *   - potongan (D)        : bagian harga kesepakatan yang dipotong dari uang penjual (boleh 0)
     *
     *   Dibayar ke penjual    = kesepakatan - D
     *   Modal perkiraan (HPP) = dibayar ke penjual + B
     *   Dibayar maksimal      = L / (1 + PL) - B        (laba PL tercapai walau laku di L)
     *   Kesepakatan maksimal  = dibayar maksimal + D
     *
     *  - dibayar <= dibayar maksimal      -> Layak dibeli
     *  - dibayar <= L - B (tidak rugi)    -> Margin tipis (tawar turun ke kesepakatan maksimal)
     *  - lainnya                          -> Tidak disarankan
     * Bila D = B, modal = harga kesepakatan; bila D = 0, modal = kesepakatan + B.
     */
    public static function purchase(string $lower, string $markup, string $serviceCost, string $offer, string $deduction = '0'): array
    {
        $L = Money::of($lower);
        $pl = BigDecimal::one()->plus(BigDecimal::of($markup));
        $b = Money::of($serviceCost);
        $d = Money::of($deduction);
        $agreed = Money::of($offer);
        $paid = $agreed->minus($d);

        $targetModal = $L->dividedBy($pl, 2, RoundingMode::DOWN);   // modal maksimal agar laba PL
        $maxPaid = $targetModal->minus($b);
        $maxAgreed = $maxPaid->plus($d);
        $lossPaid = $L->minus($b);                                  // di atas ini rugi bila laku di L

        if (! $maxPaid->isPositive()) {
            [$status, $recommended] = [self::TIDAK_DISARANKAN, null];
        } elseif ($paid->isLessThanOrEqualTo($maxPaid)) {
            [$status, $recommended] = [self::LAYAK, $agreed];
        } elseif ($paid->isLessThanOrEqualTo($lossPaid)) {
            [$status, $recommended] = [self::MARGIN_TIPIS, $maxAgreed];
        } else {
            [$status, $recommended] = [self::TIDAK_DISARANKAN, null];
        }

        $recPaid = $recommended?->minus($d);
        $modal = $recPaid?->plus($b);
        $profit = $modal ? $L->minus($modal) : null;

        return [
            'max_purchase_price' => Money::str($maxAgreed),
            'max_paid_to_seller' => Money::str($maxPaid),
            'loss_limit' => Money::str($lossPaid->plus($d)),
            'decision_status' => $status,
            'recommended_price' => $recommended ? Money::str($recommended) : null,
            'paid_to_seller' => $recPaid ? Money::str($recPaid) : null,
            // Ketidakpastian servis: batas biaya servis untuk harga yang dibayar ke penjual
            'service_cap_target' => Money::str($targetModal->minus($paid)),
            'service_cap_breakeven' => Money::str($L->minus($paid)),
            'expected_profit' => $profit ? Money::str($profit) : null,
            'expected_margin' => $profit ? (string) $profit->dividedBy($modal, 4, RoundingMode::HALF_UP) : null,
        ];
    }

    /**
     * DSS penjualan. HT = HPP x (1 + PL), urutan cek:
     *  1. U < HPP      -> Berisiko rugi (tanpa rekomendasi otomatis)
     *  2. HT < L       -> Di bawah pasar, rekomendasi L
     *  3. L <= HT <= U -> Sesuai pasar, rekomendasi HT
     *  4. HT > U       -> Di atas pasar, rekomendasi U
     */
    public static function sale(string $lower, string $upper, string $hpp, string $markup): array
    {
        $L = Money::of($lower);
        $U = Money::of($upper);
        $H = Money::of($hpp);
        $ht = $H->multipliedBy(BigDecimal::one()->plus(BigDecimal::of($markup)))->toScale(2, RoundingMode::HALF_UP);

        if ($U->isLessThan($H)) {
            [$status, $rec] = [self::BERISIKO_RUGI, null];
        } elseif ($ht->isLessThan($L)) {
            [$status, $rec] = [self::DI_BAWAH_PASAR, $L];
        } elseif ($ht->isLessThanOrEqualTo($U)) {
            [$status, $rec] = [self::SESUAI_PASAR, $ht];
        } else {
            [$status, $rec] = [self::DI_ATAS_PASAR, $U];
        }

        $profit = $rec ? $rec->minus($H) : null;

        return [
            'target_price' => Money::str($ht),
            'decision_status' => $status,
            'recommended_price' => $rec ? Money::str($rec) : null,
            'expected_profit' => $profit ? Money::str($profit) : null,
            'expected_margin' => $profit ? (string) $profit->dividedBy($rec, 4, RoundingMode::HALF_UP) : null,
        ];
    }
}
