<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShopRequest;
use App\Models\Category;
use App\Models\Shop;
use App\Support\CategoryResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    public function create(): View
    {
        return view('shops.create', [
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Create the shop and assign its categories as one act.
     *
     * Both halves run in a transaction: a shop that lost its categories to a
     * failure halfway through would sit in the list looking configured while
     * covering nothing, and no screen in the MVP can repair it (that is S-06).
     * sync() also collapses a category picked twice — the checkbox list and the
     * typed-in name can name the same one — independently of the unique
     * constraint on the pivot.
     */
    public function store(StoreShopRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $shop = Shop::create(['name' => trim($request->string('name')->toString())]);

            $categoryIds = $request->collect('category_ids')->map(intval(...));

            if ($request->filled('new_category')) {
                $categoryIds->push(CategoryResolver::resolve($request->string('new_category')->toString())->id);
            }

            $shop->categories()->sync($categoryIds->all());
        });

        return redirect()->route('shops.index');
    }
}
