<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        try {
            $branchId = $request->branch_id;
            
            Log::info('Fetching dashboard data for branch: ' . $branchId);
            
            // Get today's sales - using startOfDay and endOfDay to ensure we get all of today's transactions
            $todayStart = Carbon::now()->startOfDay();
            $todayEnd = Carbon::now()->endOfDay();
            
            $todaySales = Order::where('branch_id', $branchId)
                ->whereIn('status', ['completed', 'confirmed'])
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->sum('total');

            Log::info('Today sales: ' . $todaySales);

            // Get today's transaction count
            $todayCount = Order::where('branch_id', $branchId)
                ->whereIn('status', ['completed', 'confirmed'])
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->count();

            Log::info('Today transaction count: ' . $todayCount);

            // Get daily sales for the last 7 days
            $dailySales = Order::where('branch_id', $branchId)
                ->whereIn('status', ['completed', 'confirmed'])
                ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total) as total')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(function($sale) {
                    return [
                        'date' => Carbon::parse($sale->date)->format('M d'),
                        'total' => floatval($sale->total)
                    ];
                });

            Log::info('Daily sales data: ' . json_encode($dailySales));

            // Get weekly sales for the last 4 weeks
            $startDate = Carbon::now()->startOfWeek()->subWeeks(4);
            $weeklySales = collect();
            
            for ($i = 0; $i < 4; $i++) {
                $weekStart = $startDate->copy()->addWeeks($i);
                $weekEnd = $weekStart->copy()->endOfWeek();
                
                $total = Order::where('branch_id', $branchId)
                    ->whereIn('status', ['completed', 'confirmed'])
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->sum('total');
                
                $weeklySales->push([
                    'date' => 'Week ' . $weekStart->format('M d'),
                    'total' => floatval($total)
                ]);
            }

            Log::info('Weekly sales data: ' . json_encode($weeklySales));

            // Get monthly sales for the last 12 months
            $startDate = Carbon::now()->startOfMonth()->subMonths(11);
            $monthlySales = collect();
            
            for ($i = 0; $i < 12; $i++) {
                $monthStart = $startDate->copy()->addMonths($i);
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                $total = Order::where('branch_id', $branchId)
                    ->whereIn('status', ['completed', 'confirmed'])
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('total');
                
                $monthlySales->push([
                    'date' => $monthStart->format('M Y'),
                    'total' => floatval($total)
                ]);
            }

            Log::info('Monthly sales data: ' . json_encode($monthlySales));

            // Get yearly sales for the last 5 years
            $startDate = Carbon::now()->startOfYear()->subYears(4);
            $yearlySales = collect();
            
            for ($i = 0; $i < 5; $i++) {
                $yearStart = $startDate->copy()->addYears($i);
                $yearEnd = $yearStart->copy()->endOfYear();
                
                $total = Order::where('branch_id', $branchId)
                    ->whereIn('status', ['completed', 'confirmed'])
                    ->whereBetween('created_at', [$yearStart, $yearEnd])
                    ->sum('total');
                
                $yearlySales->push([
                    'date' => $yearStart->format('Y'),
                    'total' => floatval($total)
                ]);
            }

            Log::info('Yearly sales data: ' . json_encode($yearlySales));

            // Get top selling products with better error handling
            $sortBy = $request->input('sort_by', 'quantity_sold');
            $sortOrder = $request->input('sort_order', 'desc');

            $topProducts = Product::where('branch_id', $branchId)
                ->with(['category'])
                ->withCount(['orderItems as quantity_sold' => function($query) {
                    $query->select(DB::raw('COALESCE(SUM(quantity), 0)'))
                        ->whereHas('order', function($q) {
                            $q->whereIn('status', ['completed', 'confirmed']);
                        });
                }])
                ->withSum(['orderItems as revenue' => function($query) {
                    $query->select(DB::raw('COALESCE(SUM(quantity * price), 0)'))
                        ->whereHas('order', function($q) {
                            $q->whereIn('status', ['completed', 'confirmed']);
                        });
                }], 'quantity * price')
                ->when($sortBy === 'quantity_sold', function($query) use ($sortOrder) {
                    return $query->orderBy('quantity_sold', $sortOrder);
                })
                ->when($sortBy === 'revenue', function($query) use ($sortOrder) {
                    return $query->orderBy('revenue', $sortOrder);
                })
                ->when($sortBy === 'margin', function($query) use ($sortOrder) {
                    return $query->orderByRaw("(revenue - (price * quantity_sold)) / revenue * 100 " . $sortOrder);
                })
                ->limit(10)
                ->get()
                ->map(function($product) {
                    $product->margin = $product->revenue > 0 
                        ? round((($product->revenue - ($product->price * $product->quantity_sold)) / $product->revenue) * 100, 2)
                        : 0;
                    return $product;
                });

            Log::info('Top products data: ' . json_encode($topProducts));

            // Get sales targets (you can customize these based on your business logic)
            $targets = [
                'daily' => 10000, // Example daily target
                'weekly' => 70000, // Example weekly target
                'monthly' => 300000, // Example monthly target
                'yearly' => 3600000, // Example yearly target
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'todaySales' => $todaySales,
                    'todayCount' => $todayCount,
                    'lastUpdated' => Carbon::now()->toISOString(),
                    'dailySales' => [
                        'labels' => $dailySales->pluck('date'),
                        'values' => $dailySales->pluck('total')
                    ],
                    'weeklySales' => [
                        'labels' => $weeklySales->pluck('date'),
                        'values' => $weeklySales->pluck('total')
                    ],
                    'monthlySales' => [
                        'labels' => $monthlySales->pluck('date'),
                        'values' => $monthlySales->pluck('total')
                    ],
                    'yearlySales' => [
                        'labels' => $yearlySales->pluck('date'),
                        'values' => $yearlySales->pluck('total')
                    ],
                    'topProducts' => $topProducts,
                    'targets' => $targets
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in dashboard: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function realtime(Request $request)
    {
        try {
            $branchId = $request->branch_id;
            
            Log::info('Fetching realtime data for branch: ' . $branchId);
            
            // Get today's sales
            $todaySales = Order::where('branch_id', $branchId)
                ->whereIn('status', ['completed', 'confirmed'])
                ->whereDate('created_at', Carbon::today())
                ->sum('total');

            Log::info('Realtime today sales: ' . $todaySales);

            // Get today's transaction count
            $todayCount = Order::where('branch_id', $branchId)
                ->whereIn('status', ['completed', 'confirmed'])
                ->whereDate('created_at', Carbon::today())
                ->count();

            Log::info('Realtime today count: ' . $todayCount);

            return response()->json([
                'success' => true,
                'data' => [
                    'todaySales' => $todaySales,
                    'todayCount' => $todayCount,
                    'lastUpdated' => Carbon::now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in realtime: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching real-time data: ' . $e->getMessage()
            ], 500);
        }
    }
} 