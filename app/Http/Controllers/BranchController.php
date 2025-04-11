<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    public function index()
    {
        return Inertia::render('Branch');
    }

    public function getBranches()
    {
        $branches = Branch::all();
        return response()->json($branches);
    }

    public function getBranchProducts(Branch $branch)
    {
        try {
            Log::info('Fetching products for branch: ' . $branch->id);
            
            // First, get all products that belong to this branch
            $products = Product::with('category')
                ->where('branch_id', $branch->id)
                ->select('id', 'name', 'category_id', 'price', 'stock_quantity as stock', 'image')
                ->get();
            
            Log::info('Initial products count: ' . $products->count());
            
            // If no products found, get all products and assign them to this branch
            if ($products->isEmpty()) {
                Log::info('No products found for branch, checking for unassigned products');
                $allProducts = Product::whereNull('branch_id')->get();
                Log::info('Found ' . $allProducts->count() . ' unassigned products');
                
                foreach ($allProducts as $product) {
                    $product->branch_id = $branch->id;
                    $product->save();
                }
                
                $products = Product::with('category')
                    ->where('branch_id', $branch->id)
                    ->select('id', 'name', 'category_id', 'price', 'stock_quantity as stock', 'image')
                    ->get();
                    
                Log::info('After assignment products count: ' . $products->count());
            }
            
            Log::info('Final products count: ' . $products->count());
            Log::info('Products: ' . $products->toJson());
            
            return response()->json($products)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
                
        } catch (\Exception $e) {
            Log::error('Error in getBranchProducts: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
