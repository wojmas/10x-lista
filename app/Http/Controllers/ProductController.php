<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\NameComparison;
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

    public function create(): View
    {
        return view('products.create', [
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create([
            'name' => trim($request->string('name')->toString()),
            'category_id' => $this->resolveCategory($request)->id,
        ]);

        return redirect()->route('home');
    }

    /**
     * Either the member picked an existing category, or typed a new name.
     *
     * A typed name is matched against existing categories through
     * NameComparison first — without that, "Nabiał" typed next to an existing
     * "nabiał" would create a second category and split the vocabulary that
     * S-03 and S-04 compare against.
     */
    private function resolveCategory(StoreProductRequest $request): Category
    {
        if ($request->filled('category_id')) {
            return Category::findOrFail($request->integer('category_id'));
        }

        $typed = trim($request->string('new_category')->toString());

        $existing = Category::query()
            ->get()
            ->first(fn (Category $category): bool => NameComparison::matches($category->name, $typed));

        return $existing ?? Category::create(['name' => $typed]);
    }
}
