<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\SendOrderConfirmation;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $validated = $request->validated();
        $order = $createOrder->handle($validated['customer'], $validated['items']);

        SendOrderConfirmation::dispatch($order->id);

        return response()->json(['data' => $order], 201);
    }
}
