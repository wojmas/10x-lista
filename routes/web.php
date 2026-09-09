<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// The route name "home" is load-bearing: navigation, both auth controllers and
// three feature tests resolve it. Changing the URI is fine; renaming is not.
Route::get('/', [ProductController::class, 'index'])->middleware('auth')->name('home');

require __DIR__.'/auth.php';
