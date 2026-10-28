<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestockProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class RestockProductController extends Controller
{
    public function __invoke(RestockProductRequest $request, Product $product): JsonResponse
    {
        $product->increment('stock', $request->validated('quantity'));

        return response()->json(['data' => $product->refresh()]);
    }
}
