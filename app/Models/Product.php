<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'category_id', 'branch_id', 'barcode', 'price', 'stock_quantity', 'image'
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
