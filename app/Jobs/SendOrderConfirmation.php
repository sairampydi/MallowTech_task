<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $order = Order::query()->with('customer')->findOrFail($this->orderId);

        Log::info('Order confirmation email simulated.', [
            'order_id' => $order->id,
            'customer_email' => $order->customer->email,
            'customer_name' => $order->customer->name,
        ]);
    }
}
