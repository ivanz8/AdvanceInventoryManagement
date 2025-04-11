<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    public function index()
    {
        return inertia('Dashboard', [
            'stocks' => \App\Models\ProductStock::all()
        ]);
    }
}
