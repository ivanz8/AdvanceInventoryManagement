<?php

namespace App\Actions\Fortify;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewProducts;

class CreateNewProduct implements CreatesNewProducts
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     * 'name', 'category_id', 'barcode', 'price', 'stock_quantity',
     * @param  array<string, string>  $input
     */
    public function create(array $input): Product
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'string', 'max:255'],
            'barcode' => ['required', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:255'],
            'stock_quantity' => ['required', 'string', 'max:255'],
        ])->validate();

        return Product::create([
            'name' => $input['name'],
            'category_id' => $input['category_id'],
            'barcode' => $input['barcode'],
            'price' => $input['price'],
            'stock_quantity' => $input['stock_quantity'],
        ]);
    }
}
