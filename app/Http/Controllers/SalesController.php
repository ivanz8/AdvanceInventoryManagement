<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Order::with(['items.product', 'branch'])
                ->whereIn('status', ['completed', 'confirmed']);

            // Filter by branch if specified
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by date range if specified
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Get sales data
            $sales = $query->orderBy('created_at', 'desc')->get();
            
            // Debug information
            Log::info('Sales data query:', [
                'branch_id' => $request->branch_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_orders' => $sales->count(),
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'request_params' => $request->all(),
                'first_order' => $sales->first() ? [
                    'id' => $sales->first()->id,
                    'total' => $sales->first()->total,
                    'status' => $sales->first()->status,
                    'created_at' => $sales->first()->created_at
                ] : null
            ]);

            // Calculate summary statistics
            $summary = [
                'total_sales' => $sales->sum('total'),
                'total_orders' => $sales->count(),
                'average_order_value' => $sales->count() > 0 ? $sales->sum('total') / $sales->count() : 0,
                'total_items' => $sales->sum(function ($order) {
                    return $order->items->sum('quantity');
                })
            ];

            return response()->json([
                'success' => true,
                'sales' => $sales,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SalesController@index: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching sales data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function branchStats(Request $request)
    {
        $branches = Branch::with(['orders' => function ($query) use ($request) {
            $query->whereIn('status', ['completed', 'confirmed']);
            
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
        }])->get();

        $branchStats = $branches->map(function ($branch) {
            $orders = $branch->orders;
            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'total_sales' => $orders->sum('total'),
                'order_count' => $orders->count(),
                'average_order_value' => $orders->count() > 0 ? $orders->sum('total') / $orders->count() : 0,
                'total_items' => $orders->sum(function ($order) {
                    return $order->items->sum('quantity');
                })
            ];
        });

        return response()->json([
            'branch_stats' => $branchStats
        ]);
    }
}