<?php

use App\Http\Controllers\Api\CustomerOrderHistoryController;
use App\Http\Controllers\Api\LowStockProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RestockProductController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/customers/orders', CustomerOrderHistoryController::class);
Route::post('/products', [ProductController::class, 'store']);
Route::post('/products/{product}/restock', RestockProductController::class);
Route::get('/products/low-stock', LowStockProductController::class);
