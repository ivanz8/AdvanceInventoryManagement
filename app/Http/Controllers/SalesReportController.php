<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Branch;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Order;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        $dateRanges = [
            'today' => [Carbon::today(), Carbon::today()->endOfDay()],
            'yesterday' => [Carbon::yesterday(), Carbon::yesterday()->endOfDay()],
            'last7days' => [Carbon::now()->subDays(7), Carbon::now()],
            'thisMonth' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'lastMonth' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'thisYear' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
        ];

        $query = Order::query()
            ->with(['items.product', 'branch'])
            ->where('status', 'completed')
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                return $q->where('branch_id', $request->branch_id);
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                return $q->whereHas('items.product', function ($query) use ($request) {
                    $query->where('category_id', $request->category_id);
                });
            })
            ->when($request->filled('date_range'), function ($q) use ($request, $dateRanges) {
                if (isset($dateRanges[$request->date_range])) {
                    return $q->whereBetween('created_at', $dateRanges[$request->date_range]);
                }
            })
            ->when($request->filled('custom_range'), function ($q) use ($request) {
                return $q->whereBetween('created_at', [
                    Carbon::parse($request->start_date),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            });

        $sales = $query->get();

        // Calculate summary metrics
        $summary = [
            'total_sales' => $sales->sum('total'),
            'total_transactions' => $sales->count(),
            'average_transaction' => $sales->avg('total') ?? 0,
            'total_items_sold' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];

        // Calculate period comparison
        $previousPeriodSales = Order::query()
            ->where('status', 'completed')
            ->when($request->filled('date_range'), function ($q) use ($request, $dateRanges) {
                if (isset($dateRanges[$request->date_range])) {
                    $currentStart = $dateRanges[$request->date_range][0];
                    $currentEnd = $dateRanges[$request->date_range][1];
                    $periodDiff = $currentStart->diffInDays($currentEnd);
                    
                    return $q->whereBetween('created_at', [
                        $currentStart->copy()->subDays($periodDiff + 1),
                        $currentEnd->copy()->subDays($periodDiff + 1),
                    ]);
                }
            })
            ->sum('total');

        $comparison = [
            'previous_period' => $previousPeriodSales,
            'percentage_change' => $previousPeriodSales > 0 
                ? (($sales->sum('total') - $previousPeriodSales) / $previousPeriodSales) * 100 
                : 0,
        ];

        return [
            'success' => true,
            'sales' => $sales,
            'summary' => $summary,
            'comparison' => $comparison,
            'filters' => [
                'branches' => Branch::select('id', 'name')->get(),
                'categories' => ProductCategory::select('id', 'name')->get(),
                'dateRanges' => array_keys($dateRanges),
            ],
        ];
    }

    public function exportCsv(Request $request)
    {
        $sales = $this->getSalesData($request);
        
        $filename = 'sales_report_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $handle = fopen('php://temp', 'w+');
        
        // Add headers
        fputcsv($handle, ['Date', 'Transaction ID', 'Branch', 'Items', 'Total Amount']);
        
        // Add data
        foreach ($sales as $sale) {
            $items = $sale->items->map(function ($item) {
                return $item->product->name . ' (x' . $item->quantity . ')';
            })->join(', ');
            
            fputcsv($handle, [
                $sale->created_at->format('Y-m-d H:i:s'),
                $sale->id,
                $sale->branch->name,
                $items,
                $sale->total,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $sales = $this->getSalesData($request);
        $summary = $this->calculateSummary($sales);
        
        $pdf = PDF::loadView('reports.sales', [
            'sales' => $sales,
            'summary' => $summary,
            'dateRange' => $request->date_range ?? 'custom',
            'startDate' => $request->start_date ?? now()->startOfMonth()->format('Y-m-d'),
            'endDate' => $request->end_date ?? now()->format('Y-m-d'),
        ]);

        return $pdf->download('sales_report_' . now()->format('Y-m-d_His') . '.pdf');
    }

    private function getSalesData(Request $request)
    {
        return Order::with(['items.product', 'branch'])
            ->where('status', 'completed')
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                return $q->where('branch_id', $request->branch_id);
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                return $q->whereHas('items.product', function ($query) use ($request) {
                    $query->where('category_id', $request->category_id);
                });
            })
            ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                return $q->whereBetween('created_at', [
                    Carbon::parse($request->start_date),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            })
            ->get();
    }

    private function calculateSummary($sales)
    {
        return [
            'total_sales' => $sales->sum('total'),
            'total_transactions' => $sales->count(),
            'average_transaction' => $sales->avg('total') ?? 0,
            'total_items_sold' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];
    }
} 