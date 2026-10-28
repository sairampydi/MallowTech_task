<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class StorefrontController extends Controller
{
    public function __invoke(): View
    {
        return view('storefront', [
            'products' => Product::query()->orderBy('name')->get(),
            'lowStockThreshold' => (int) config('inventory.low_stock_threshold'),
        ]);
    }
}
