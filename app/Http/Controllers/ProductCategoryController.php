<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProductCategory;
use App\Models\Branch;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::all();
        $branches = Branch::all();
        
        return Inertia::render('Dashboard', [
            'categories' => $categories,
            'branches' => $branches,
            'success' => session('success'),
        ]);
    }
}
