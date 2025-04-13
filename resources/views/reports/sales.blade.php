<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .summary-box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .metric {
            padding: 10px;
            background: #f8f8f8;
        }
        .metric-label {
            font-size: 12px;
            color: #666;
        }
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f8f8;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sales Report</h1>
        <p>{{ $dateRange == 'custom' ? "$startDate to $endDate" : ucfirst($dateRange) }}</p>
    </div>

    <div class="summary-box">
        <h2>Summary</h2>
        <div class="summary-grid">
            <div class="metric">
                <div class="metric-label">Total Sales</div>
                <div class="metric-value">₱{{ number_format($summary['total_sales'], 2) }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Total Transactions</div>
                <div class="metric-value">{{ number_format($summary['total_transactions']) }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Average Transaction</div>
                <div class="metric-value">₱{{ number_format($summary['average_transaction'], 2) }}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Total Items Sold</div>
                <div class="metric-value">{{ number_format($summary['total_items_sold']) }}</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Transaction ID</th>
                <th>Branch</th>
                <th>Items</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
                <tr>
                    <td>{{ $sale->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $sale->id }}</td>
                    <td>{{ $sale->branch->name }}</td>
                    <td>
                        @foreach($sale->items as $item)
                            {{ $item->product->name }} (x{{ $item->quantity }})@if(!$loop->last), @endif
                        @endforeach
                    </td>
                    <td class="text-right">₱{{ number_format($sale->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ now()->format('F d, Y H:i:s') }}</p>
    </div>
</body>
</html> 