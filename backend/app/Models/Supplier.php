<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['supplier_name', 'supplier_type', 'phone', 'id_number', 'address'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
