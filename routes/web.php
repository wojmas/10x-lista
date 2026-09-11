<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // The route name "home" is load-bearing: navigation, both auth controllers
    // and three feature tests resolve it. Changing the URI is fine; renaming is not.
    Route::get('/', [ProductController::class, 'index'])->name('home');

    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
    Route::get('/shops/create', [ShopController::class, 'create'])->name('shops.create');
    Route::post('/shops', [ShopController::class, 'store'])->name('shops.store');
});

require __DIR__.'/auth.php';
