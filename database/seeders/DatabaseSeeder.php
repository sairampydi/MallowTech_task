<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Product::query()->updateOrCreate(
            ['code' => 'NOTEBOOK-001'],
            [
                'name' => 'Everyday Notebook',
                'price' => '4.99',
                'tax_rate' => '10.00',
                'stock' => 25,
            ],
        );
        Product::query()->updateOrCreate(
            ['code' => 'PEN-001'],
            [
                'name' => 'Ballpoint Pen',
                'price' => '1.50',
                'tax_rate' => '10.00',
                'stock' => 4,
            ],
        );

        Customer::query()->updateOrCreate(
            ['email' => 'alex@example.com'],
            ['name' => 'Alex Morgan'],
        );
        Customer::query()->updateOrCreate(
            ['email' => 'jordan@example.com'],
            ['name' => 'Jordan Lee'],
        );
    }
}
