<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->middleware('auth')->name('home');

require __DIR__.'/auth.php';
