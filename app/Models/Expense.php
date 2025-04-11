<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_category_id', 'amount', 'expense_date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
