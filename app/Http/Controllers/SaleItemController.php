<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SaleItemController extends Controller
{
    public function index()
    {
        return Inertia::render('SaleItem');
    }
}
