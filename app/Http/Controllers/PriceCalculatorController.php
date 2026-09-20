<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PriceCalculatorController extends Controller
{
    public function index(): View
    {
        return view('price_calculator.index');
    }
}
