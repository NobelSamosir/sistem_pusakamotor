<?php

namespace App\Http\Controllers\Api;

use Illuminate\Validation\Rule;

/** Aturan validasi data motor, dipakai di pembelian dan ubah data unit. */
final class MotorcycleRules
{
    public static function rules(?int $ignoreId = null, string $prefix = ''): array
    {
        return [
            $prefix.'frame_number' => ['required', 'string', 'max:30', Rule::unique('motorcycles', 'frame_number')->ignore($ignoreId)],
            $prefix.'engine_number' => ['required', 'string', 'max:30', Rule::unique('motorcycles', 'engine_number')->ignore($ignoreId)],
            $prefix.'license_plate' => ['nullable', 'string', 'max:15'],
            $prefix.'bpkb_number' => ['nullable', 'string', 'max:30'],
            $prefix.'brand' => ['required', 'string', 'max:50'],
            $prefix.'model' => ['required', 'string', 'max:100'],
            $prefix.'year' => ['required', 'integer', 'between:1970,'.(date('Y') + 1)],
            $prefix.'transmission' => ['required', 'string', 'max:30'],
            $prefix.'engine_capacity' => ['required', 'string', 'max:30'],
            $prefix.'body_type' => ['required', 'string', 'max:50'],
            $prefix.'mileage' => ['required', 'integer', 'min:0'],
            $prefix.'ownership_status' => ['required', 'string', 'max:30'],
            $prefix.'has_bpkb' => ['boolean'],
            $prefix.'has_stnk' => ['boolean'],
            $prefix.'has_faktur' => ['boolean'],
            $prefix.'has_buku_servis' => ['boolean'],
            $prefix.'color' => ['nullable', 'string', 'max:30'],
            $prefix.'condition_notes' => ['nullable', 'string'],
        ];
    }
}
