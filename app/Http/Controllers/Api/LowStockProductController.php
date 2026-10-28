<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LowStockProductController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => ['sometimes', 'integer', 'min:0'],
        ]);
        $threshold = (int) ($validated['threshold'] ?? config('inventory.low_stock_threshold'));

        $products = Product::query()
            ->where('stock', '<', $threshold)
            ->orderBy('stock')
            ->orderBy('name')
            ->get();

        return response()->json([
            'threshold' => $threshold,
            'data' => $products,
        ]);
    }
}
