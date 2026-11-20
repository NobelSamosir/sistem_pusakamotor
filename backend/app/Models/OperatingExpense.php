<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatingExpense extends Model
{
    protected $fillable = ['user_id', 'expense_date', 'category', 'description', 'amount'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }
}
