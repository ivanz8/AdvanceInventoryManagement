<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Branch;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);
        
        $products = Product::with(['category', 'branch'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($products);
    }

    public function create()
    {
        return inertia('Products/Create', [
            'branches' => Branch::all(),
            'categories' => ProductCategory::all()
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:product_categories,id',
                'branch_id' => 'required|exists:branches,id',
                'barcode' => 'required|numeric|unique:products,barcode',
                'price' => 'required|numeric|min:0.01',
                'stock_quantity' => 'required|integer|min:0',
                'image' => 'required|image|max:2048'
            ]);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $validated['image'] = $path;
            }

            $product = Product::create($validated);

            DB::commit();
            
            return redirect()->action([ProductCategoryController::class, 'index'])
                ->with('success', 'Product added successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create product. Please try again.'])
                ->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $product = Product::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:product_categories,id',
                'branch_id' => 'required|exists:branches,id',
                'barcode' => 'required|numeric|unique:products,barcode,' . $id,
                'price' => 'required|numeric|min:0.01',
                'stock_quantity' => 'required|integer|min:0',
                'image' => 'nullable|image|max:2048'
            ]);

            // Handle image update if a new image is provided
            if ($request->hasFile('image')) {
                // Delete the old image
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                
                // Store the new image
                $path = $request->file('image')->store('products', 'public');
                $validated['image'] = $path;
            }

            $product->update($validated);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product. Please try again.',
                'errors' => ['error' => $e->getMessage()]
            ], 422);
        }
    }
}
