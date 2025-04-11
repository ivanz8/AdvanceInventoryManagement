<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Log;

class AboutController extends Controller
{
    public function index()
    {
        $branches = Branch::with(['products' => function($query) {
            $query->with('category');
        }])->get();
        
        // Debug information
        \Log::info('Branches loaded for About page:', [
            'branch_count' => $branches->count(),
            'branches' => $branches->toArray()
        ]);

        return Inertia::render('About', [
            'branches' => $branches,
            'categories' => ProductCategory::all()->pluck('name', 'id')
        ]);
    }
}
