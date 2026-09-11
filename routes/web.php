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
    // Parametr celowo nazywa się {id}, nie {product}: druga nazwa podpowiada
    // type-hint Product w destroy(), a ten włącza route model binding i 404,
    // które ta funkcja odrzuca. Powód stoi w docblocku destroy().
    // whereNumber, bo destroy() deklaruje int $id: bez ograniczenia
    // "/products/abc" dociera do kontrolera i kończy się TypeError (500)
    // zamiast 404.
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])
        ->whereNumber('id')
        ->name('products.destroy');

    Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
    Route::get('/shops/create', [ShopController::class, 'create'])->name('shops.create');
    Route::post('/shops', [ShopController::class, 'store'])->name('shops.store');
    // Tu parametr nazywa się {shop} celowo — odwrotnie niż przy produktach.
    // Edycja nieistniejącego sklepu to realny błąd (stary link, literówka w URL),
    // więc route model binding i 404 są właściwą odpowiedzią. Powód, dla którego
    // kasowanie robi odwrotnie, stoi przy trasie shops.destroy.
    Route::get('/shops/{shop}/edit', [ShopController::class, 'edit'])->name('shops.edit');
    Route::put('/shops/{shop}', [ShopController::class, 'update'])->name('shops.update');
    // A tu {id}, nie {shop} — jak przy products.destroy i z tego samego powodu:
    // druga nazwa podpowiada type-hint Shop, ten włącza route model binding, a
    // ten odpowiada 404 na drugie żądanie kasujące ten sam sklep. Powód stoi w
    // docblocku destroy().
    Route::delete('/shops/{id}', [ShopController::class, 'destroy'])
        ->whereNumber('id')
        ->name('shops.destroy');
});

require __DIR__.'/auth.php';
