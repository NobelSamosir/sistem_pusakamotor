<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['customer_name', 'customer_type', 'phone', 'id_number', 'address'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
