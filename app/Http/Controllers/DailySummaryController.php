<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
class DailySummaryController extends Controller
{
    public function index()
    {
        return Inertia::render('DailySummary');
    }
}
