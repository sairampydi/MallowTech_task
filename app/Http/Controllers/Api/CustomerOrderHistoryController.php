<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerOrderHistoryRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerOrderHistoryController extends Controller
{
    public function __invoke(CustomerOrderHistoryRequest $request): JsonResponse
    {
        $customer = Customer::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($customer === null) {
            return response()->json([
                'message' => 'No customer found for the supplied email address.',
            ], 404);
        }

        $orders = $customer->orders()
            ->with(['customer', 'items.product'])
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }
}
