<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    /**
     * @param  array{name: string, email: string}  $customerData
     * @param  list<array{product_id: int, quantity: int}>  $requestedItems
     */
    public function handle(array $customerData, array $requestedItems): Order
    {
        $quantities = [];
        foreach ($requestedItems as $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
        }
        ksort($quantities);

        return DB::transaction(function () use ($customerData, $quantities): Order {
            foreach ($quantities as $productId => $quantity) {
                $updated = Product::query()
                    ->whereKey($productId)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);

                if ($updated === 0) {
                    $productExists = Product::query()->whereKey($productId)->exists();
                    $message = $productExists
                        ? "Insufficient stock for product {$productId}."
                        : "Product {$productId} is no longer available.";

                    throw ValidationException::withMessages([
                        'items' => [$message],
                    ]);
                }
            }

            $products = Product::query()
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $customer = Customer::query()->updateOrCreate(
                ['email' => $customerData['email']],
                ['name' => $customerData['name']],
            );

            $subtotalCents = 0;
            $taxCents = 0;
            $lineItems = [];

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);
                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => ["Product {$productId} is no longer available."],
                    ]);
                }

                $unitPriceCents = $this->decimalToMinorUnits((string) $product->price);
                $taxRateBasisPoints = $this->decimalToMinorUnits((string) $product->tax_rate);
                $lineSubtotalCents = $unitPriceCents * $quantity;
                $lineTaxCents = intdiv(
                    ($lineSubtotalCents * $taxRateBasisPoints) + 5000,
                    10000,
                );

                $subtotalCents += $lineSubtotalCents;
                $taxCents += $lineTaxCents;
                $lineItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'tax_rate' => $product->tax_rate,
                    'subtotal' => $this->asMoney($lineSubtotalCents),
                    'tax_amount' => $this->asMoney($lineTaxCents),
                    'total' => $this->asMoney($lineSubtotalCents + $lineTaxCents),
                ];
            }

            $order = $customer->orders()->create([
                'subtotal' => $this->asMoney($subtotalCents),
                'tax_amount' => $this->asMoney($taxCents),
                'total' => $this->asMoney($subtotalCents + $taxCents),
            ]);
            $order->items()->createMany($lineItems);

            return $order->load(['customer', 'items.product']);
        }, attempts: 5);
    }

    private function decimalToMinorUnits(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function asMoney(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
