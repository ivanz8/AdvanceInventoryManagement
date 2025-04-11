<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('ExpenseCategory');
    }
}
