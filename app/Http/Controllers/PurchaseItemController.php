<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    public function index()
    {
        return Inertia::render('PurchaseItem');
    }
}
