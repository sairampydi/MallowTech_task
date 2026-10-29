<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_order_calculates_totals_and_deducts_stock(): void
    {
        Queue::fake();
        $product = Product::factory()->create([
            'price' => '10.00',
            'tax_rate' => '10.00',
            'stock' => 5,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'Alex Morgan',
                'email' => 'alex@example.com',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '20.00')
            ->assertJsonPath('data.tax_amount', '2.00')
            ->assertJsonPath('data.total', '22.00')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.customer.email', 'alex@example.com');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 3,
        ]);
        $this->assertDatabaseHas('customers', [
            'email' => 'alex@example.com',
            'name' => 'Alex Morgan',
        ]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        Queue::assertPushed(SendOrderConfirmation::class);
    }

    public function test_insufficient_stock_rejects_order_without_changing_stock(): void
    {
        $availableProduct = Product::factory()->create(['stock' => 5]);
        $unavailableProduct = Product::factory()->create(['stock' => 1]);

        $response = $this->postJson('/api/orders', [
            'customer' => [
                'name' => 'Alex Morgan',
                'email' => 'alex@example.com',
            ],
            'items' => [
                ['product_id' => $availableProduct->id, 'quantity' => 2],
                ['product_id' => $unavailableProduct->id, 'quantity' => 2],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertDatabaseHas('products', [
            'id' => $availableProduct->id,
            'stock' => 5,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $unavailableProduct->id,
            'stock' => 1,
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_it_validates_order_input(): void
    {
        $this->postJson('/api/orders', [
            'customer' => [
                'name' => '',
                'email' => 'not-an-email',
            ],
            'items' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer.name',
                'customer.email',
                'items',
            ]);
    }

    public function test_it_returns_customer_order_history_by_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'alex@example.com']);
        $order = $customer->orders()->create([
            'subtotal' => '20.00',
            'tax_amount' => '2.00',
            'total' => '22.00',
        ]);

        $this->getJson('/api/customers/orders?email=alex%40example.com')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.customer.email', 'alex@example.com');
    }

    public function test_it_returns_products_below_the_requested_stock_threshold(): void
    {
        $lowStockProduct = Product::factory()->create(['stock' => 2]);
        Product::factory()->create(['stock' => 5]);

        $this->getJson('/api/products/low-stock?threshold=3')
            ->assertOk()
            ->assertJsonPath('threshold', 3)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lowStockProduct->id);
    }

    public function test_it_creates_a_product(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Counter Biscuit',
            'code' => 'BISCUIT-100',
            'price' => '12.50',
            'tax_rate' => '5.00',
            'stock' => 20,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Counter Biscuit')
            ->assertJsonPath('data.code', 'BISCUIT-100')
            ->assertJsonPath('data.stock', 20);

        $this->assertDatabaseHas('products', [
            'code' => 'BISCUIT-100',
            'price' => '12.50',
            'tax_rate' => '5.00',
            'stock' => 20,
        ]);
    }

    public function test_it_rejects_a_product_with_a_duplicate_code(): void
    {
        Product::factory()->create(['code' => 'EXISTING-SKU']);

        $this->postJson('/api/products', [
            'name' => 'Duplicate product',
            'code' => 'EXISTING-SKU',
            'price' => '10.00',
            'tax_rate' => '5.00',
            'stock' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_it_increases_stock_for_an_existing_product(): void
    {
        $product = Product::factory()->create(['stock' => 4]);

        $this->postJson("/api/products/{$product->id}/restock", [
            'quantity' => 6,
        ])->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.stock', 10);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);
    }

    public function test_restock_requires_a_positive_integer_quantity(): void
    {
        $product = Product::factory()->create(['stock' => 4]);

        $this->postJson("/api/products/{$product->id}/restock", [
            'quantity' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 4,
        ]);
    }

    public function test_the_storefront_renders_the_catalog_and_order_form(): void
    {
        Product::factory()->create([
            'name' => 'Counter Notebook',
            'code' => 'NOTE-42',
            'stock' => 2,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Store Billing')
            ->assertSee('New order')
            ->assertSee('Counter Notebook')
            ->assertSee('Generate bill')
            ->assertSee('id="new-order"', false)
            ->assertSee('id="open-add-product"', false)
            ->assertSee('id="open-restock"', false)
            ->assertSee('for="new-product-code"', false)
            ->assertSee('for="new-product-tax-rate"', false)
            ->assertSee('for="restock-product"', false)
            ->assertSee('for="restock-quantity"', false);
    }
}
