<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\View\View;

class ShopController extends Controller
{
    /**
     * List the configured shops with the categories each one offers.
     *
     * The ascending id order is a contract, not a cosmetic choice: §Business
     * Logic settles an S-04 tie in favour of the shop added first, and the
     * screen must show the same precedence the recommendation will apply.
     * Categories are eager-loaded so rendering does not fire one query per shop.
     */
    public function index(): View
    {
        return view('shops.index', [
            'shops' => Shop::query()
                ->with('categories')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
