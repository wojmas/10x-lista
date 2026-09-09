<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Show the family's shared shopping list.
     *
     * The query deliberately has no owner filter: the PRD guardrail requires an
     * added product to be visible to every logged-in family member, and the
     * product rules out multi-tenancy. The category relation is eager-loaded so
     * rendering the list does not fire one query per row — S-04 will read the
     * same relation for every product on every recommendation recalculation.
     */
    public function index(): View
    {
        return view('home', [
            'products' => Product::query()
                ->with('category')
                ->latest()
                ->get(),
        ]);
    }
}
