<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlModel extends Model
{
    protected $fillable = [
        'model_name', 'algorithm', 'version', 'target_transform', 'mae', 'rmse', 'r2',
        'model_file', 'is_active', 'trained_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['mae' => 'decimal:2', 'rmse' => 'decimal:2', 'r2' => 'decimal:4', 'is_active' => 'boolean', 'trained_at' => 'date'];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }
}
