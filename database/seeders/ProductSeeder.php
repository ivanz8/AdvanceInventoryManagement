<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Branch;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create some test categories if they don't exist
        $foodCategory = ProductCategory::firstOrCreate(['name' => 'Food']);
        $drinksCategory = ProductCategory::firstOrCreate(['name' => 'Drinks']);
        $snacksCategory = ProductCategory::firstOrCreate(['name' => 'Snacks']);

        // Get the first branch
        $branch = Branch::first();
        
        if (!$branch) {
            $this->command->error('No branches found. Please run the BranchSeeder first.');
            return;
        }

        // Create some test products
        $products = [
            [
                'name' => 'Pizza',
                'category_id' => $foodCategory->id,
                'branch_id' => $branch->id,
                'barcode' => '123456789',
                'price' => 10.99,
                'stock_quantity' => 20,
                'image' => 'https://via.placeholder.com/150',
            ],
            [
                'name' => 'Burger',
                'category_id' => $foodCategory->id,
                'branch_id' => $branch->id,
                'barcode' => '123456790',
                'price' => 8.99,
                'stock_quantity' => 15,
                'image' => 'https://via.placeholder.com/150',
            ],
            [
                'name' => 'Cola',
                'category_id' => $drinksCategory->id,
                'branch_id' => $branch->id,
                'barcode' => '123456791',
                'price' => 2.99,
                'stock_quantity' => 50,
                'image' => 'https://via.placeholder.com/150',
            ],
            [
                'name' => 'Water',
                'category_id' => $drinksCategory->id,
                'branch_id' => $branch->id,
                'barcode' => '123456792',
                'price' => 1.99,
                'stock_quantity' => 100,
                'image' => 'https://via.placeholder.com/150',
            ],
            [
                'name' => 'Chips',
                'category_id' => $snacksCategory->id,
                'branch_id' => $branch->id,
                'barcode' => '123456793',
                'price' => 3.99,
                'stock_quantity' => 30,
                'image' => 'https://via.placeholder.com/150',
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        $this->command->info('Products seeded successfully!');
    }
} 