<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:product_categories,name',
                'description' => 'nullable|string|max:1000'
            ]);

            $category = ProductCategory::create($validated);

            return redirect()->back()->with('success', 'Category created successfully');
        } catch (\Exception $e) {
            Log::error('Category creation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create category. Please try again.');
        }
    }

    public function update(Request $request, ProductCategory $category)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:product_categories,name,' . $category->id,
                'description' => 'nullable|string|max:1000'
            ]);

            $category->update($validated);

            return redirect()->back()->with('success', 'Category updated successfully');
        } catch (\Exception $e) {
            Log::error('Category update failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update category. Please try again.');
        }
    }

    public function destroy(ProductCategory $category)
    {
        try {
            // Check if category has associated products
            if ($category->products()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with associated products. Please remove or reassign the products first.'
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Category deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category: ' . $e->getMessage()
            ], 500);
        }
    }
} 