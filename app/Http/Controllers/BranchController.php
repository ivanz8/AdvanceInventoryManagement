<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

    /**
     * Update the specified branch.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Branch $branch)
    {
        try {
            Log::info('Updating branch: ' . $branch->id);
            Log::info('Request data: ' . json_encode($request->all()));

            // Validate the request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
            ]);

            // Update the branch
            $branch->update($validated);

            Log::info('Branch updated successfully: ' . $branch->id);

            return back()->with([
                'success' => 'Branch updated successfully',
                'branch' => $branch
            ]);

        } catch (ValidationException $e) {
            Log::error('Validation error in branch update: ' . json_encode($e->errors()));
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating branch: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update branch: ' . $e->getMessage()]);
        }
    }

    /**
     * Store a newly created branch.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating new branch');
            Log::info('Request data: ' . json_encode($request->all()));

            // Validate the request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'contact_number' => 'nullable|string|max:255',
            ]);

            // Create the branch
            $branch = Branch::create($validated);

            Log::info('Branch created successfully: ' . $branch->id);

            return back()->with([
                'success' => 'Branch created successfully',
                'branch' => $branch
            ]);

        } catch (ValidationException $e) {
            Log::error('Validation error in branch creation: ' . json_encode($e->errors()));
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating branch: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create branch: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete the specified branch.
     *
     * @param  \App\Models\Branch  $branch
     * @return \Illuminate\Http\Response
     */
    public function destroy(Branch $branch)
    {
        try {
            Log::info('Deleting branch: ' . $branch->id);

            // Check if branch has associated products
            if ($branch->products()->count() > 0) {
                throw new \Exception('Cannot delete branch with existing products');
            }

            // Delete the branch
            $branch->delete();

            Log::info('Branch deleted successfully: ' . $branch->id);

            return back()->with('success', 'Branch deleted successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting branch: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete branch: ' . $e->getMessage()]);
        }
    }
}
