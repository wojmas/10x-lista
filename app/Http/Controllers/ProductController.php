<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryResolver;
use App\Support\ShopRecommendation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Show the family's shared shopping list.
     *
     * The query deliberately has no owner filter: the PRD guardrail requires an
     * added product to be visible to every logged-in family member, and the
     * product rules out multi-tenancy. The category relation is eager-loaded so
     * rendering the list does not fire one query per row — the recommendation
     * reads the same relation for every product on every recalculation.
     */
    public function index(): View
    {
        $products = Product::query()
            ->with('category')
            ->latest()
            ->get();

        return view('home', [
            'products' => $products,
            // Recalculated on every visit rather than stored: adding a product
            // redirects back here, which is what makes US-01's "updates after
            // every addition" true without any cache to invalidate. The rule
            // reads the shops itself — see its docblock for why that is not the
            // controller's job.
            'recommendation' => ShopRecommendation::for($products),
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Either the member picked an existing category, or typed a new name.
     *
     * The branch stays here because it depends on the shape of this request;
     * finding-or-creating the category itself lives in CategoryResolver, shared
     * with the shop form.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $category = $request->filled('category_id')
            ? Category::findOrFail($request->integer('category_id'))
            : CategoryResolver::resolve($request->string('new_category')->toString());

        Product::create([
            'name' => trim($request->string('name')->toString()),
            'category_id' => $category->id,
        ]);

        return redirect()->route('home');
    }
}
