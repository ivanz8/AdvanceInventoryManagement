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
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SalesReportController;
use Illuminate\Http\Request;
use App\Http\Controllers\SalesDashboardController;

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
        return Inertia::render('Profile/Show', [
            'confirmsTwoFactorAuthentication' => false,
            'sessions' => []
        ]);
    })->name('profile.show');
    
    Route::get('/products', function () {
        return Inertia::render('Products/Index');
    })->name('products.index');
    
    // Add API route for products with pagination
    Route::get('/api/products', [ProductController::class, 'index'])->name('products.index');
    
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
    Route::get('/sales/top-products', [SalesController::class, 'getTopProducts'])->name('sales.top-products');

    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store')->middleware(['auth', 'verified']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy')->middleware(['auth', 'verified']);

    // Category Management Routes
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    
    // Image proxy route to avoid CORS issues
    Route::get('/api/proxy-image', function (Request $request) {
        $url = $request->query('url');
        
        if (!$url) {
            return response()->json(['error' => 'URL parameter is required'], 400);
        }
        
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'verify' => false, // Disable SSL verification for testing
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);
            
            $response = $client->get($url);
            $contentType = $response->getHeader('Content-Type')[0] ?? 'image/jpeg';
            
            return response($response->getBody(), 200, [
                'Content-Type' => $contentType,
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'public, max-age=86400'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Image proxy error: ' . $e->getMessage(), [
                'url' => $url,
                'exception' => $e
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch image: ' . $e->getMessage(),
                'url' => $url
            ], 500);
        }
    });

    // Test route for image proxy
    Route::get('/test-image-proxy', function () {
        return view('test-image-proxy');
    });

    // Add this with your other product routes
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');

    // Sales Report Routes
    Route::get('/sales/report', [SalesReportController::class, 'index'])->name('sales.report');
    Route::get('/sales/export/csv', [SalesReportController::class, 'exportCsv'])->name('sales.export.csv');
    Route::get('/sales/export/pdf', [SalesReportController::class, 'exportPdf'])->name('sales.export.pdf');

    // Sales Dashboard Routes
    Route::get('/sales/dashboard', [SalesDashboardController::class, 'dashboard'])->name('sales.dashboard');
    Route::get('/sales/realtime', [SalesDashboardController::class, 'realtime'])->name('sales.realtime');
});




