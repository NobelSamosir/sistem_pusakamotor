<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

/**
 * Identitas penjual/pembeli diisi langsung di form transaksi. Kelas ini memakai data
 * yang sudah ada bila orangnya sama, atau membuat data baru bila belum pernah tercatat.
 *
 * Urutan pencocokan: id yang dipilih dari saran > NIK > nama + No. HP. Tanpa NIK dan
 * tanpa No. HP selalu dibuat data baru, karena nama saja tidak cukup membedakan orang.
 */
class PartyResolver
{
    /** @param array{id?:int|null,name?:string|null,phone?:string|null,address?:string|null,id_number?:string|null,type?:string|null} $p */
    public function supplier(array $p): Supplier
    {
        return $this->resolve(Supplier::class, 'supplier_name', 'supplier_type', $p);
    }

    public function customer(array $p): Customer
    {
        return $this->resolve(Customer::class, 'customer_name', 'customer_type', $p);
    }

    /** Validasi isian identitas di form transaksi, dengan awalan kunci (mis. "seller."). */
    public static function rules(string $prefix, string $table, bool $required = true): array
    {
        return [
            rtrim($prefix, '.') => [$required ? 'required' : 'nullable', 'array'],
            $prefix.'id' => ['nullable', 'integer', "exists:{$table},id"],
            $prefix.'name' => [$required ? 'required' : 'nullable', 'string', 'max:150'],
            $prefix.'phone' => ['nullable', 'string', 'max:30'],
            $prefix.'address' => ['nullable', 'string', 'max:255'],
            $prefix.'id_number' => ['nullable', 'digits:16'],
            $prefix.'type' => ['nullable', 'in:perorangan,dealer'],
        ];
    }

    /** @template T of Model @param class-string<T> $class @return T */
    private function resolve(string $class, string $nameCol, string $typeCol, array $p): Model
    {
        $name = trim((string) ($p['name'] ?? ''));
        $phone = $this->clean($p['phone'] ?? null);
        $nik = $this->clean($p['id_number'] ?? null);
        $address = $this->clean($p['address'] ?? null);

        $party = null;
        if (! empty($p['id'])) {
            $party = $class::find($p['id']);
        }
        if (! $party && $nik) {
            $party = $class::where('id_number', $nik)->first();
        }
        if (! $party && $phone && $name !== '') {
            $party = $class::whereRaw("lower({$nameCol}) = ?", [mb_strtolower($name)])->where('phone', $phone)->first();
        }

        $values = array_filter([
            $nameCol => $name !== '' ? $name : null,
            'phone' => $phone,
            'id_number' => $nik,
            'address' => $address,
            $typeCol => $p['type'] ?? null,
        ], fn ($v) => $v !== null);

        if ($party) {
            $party->update($values);   // lengkapi data lama dengan isian terbaru

            return $party;
        }

        return $class::create($values + [$typeCol => 'perorangan']);
    }

    private function clean(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === '' ? null : $v;
    }
}
