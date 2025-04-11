<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SalesController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [ProductCategoryController::class, 'index'])->name('dashboard');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/pos', function () {
        $branches = \App\Models\Branch::with(['products' => function($query) {
            $query->with('category');
        }])->get();
        
        // Debug information
        Log::info('Branches loaded for POS:', [
            'branch_count' => $branches->count(),
            'branches_with_products' => $branches->filter(function($branch) {
                return $branch->products->count() > 0;
            })->count()
        ]);
        
        return Inertia::render('POS', [
            'branches' => $branches
        ]);
    })->name('pos');
    
    // Add Sales page route
    Route::get('/sales', function () {
        $branches = \App\Models\Branch::all();
        return Inertia::render('Sales', [
            'branches' => $branches
        ]);
    })->name('sales');
    
    // Add POS test route for debugging
    Route::get('/pos-test', function () {
        $branches = \App\Models\Branch::with(['products' => function($query) {
            $query->with('category');
        }])->get();
        
        return Inertia::render('POSTest', [
            'branches' => $branches
        ]);
    })->name('pos.test');
    
    Route::get('/profile', function () {
        return Inertia::render('Profile/Show');
    })->name('profile.show');
    
    Route::get('/products', function () {
        return Inertia::render('Products/Index');
    })->name('products.index');
    
    Route::get('/products/create', function () {
        return Inertia::render('Products/Create');
    })->name('products.create');
    
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    
    // Add order processing route
    Route::post('/api/orders', [OrderController::class, 'store'])->name('orders.store');
    
    // Add sales routes
    Route::get('/api/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/api/sales/branch-stats', [SalesController::class, 'branchStats'])->name('sales.branch-stats');
});




