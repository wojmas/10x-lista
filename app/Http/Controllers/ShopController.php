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
     *
     * WARNING to whoever writes S-04: no test guards this clause. Removing
     * orderBy('id') leaves the whole suite green, and a test written here could
     * not change that. Without an ORDER BY, both engines return insertion order
     * from a freshly filled table, so the assertion never gets the chance to
     * fail. The divergence is real but Postgres-only and latent: it appears
     * after an UPDATE to an indexed column (renaming a shop makes the row
     * non-HOT, so the new tuple lands at the end of the heap and the shop added
     * first comes back last). Nothing renames a shop until S-06.
     *
     * A test claiming to pin this used to live in ShopListTest and was deleted
     * rather than kept green on a false promise. The proof belongs to §3 Phase 4
     * of context/foundation/test-plan.md, which runs the suite on Postgres.
     * Until it lands, this docblock is the contract — do not reach for
     * Shop::all() or your own ordering in S-04.
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

            // Cast explicitly rather than with intval(...): Collection::map hands
            // the callback (value, key), and intval() reads that second argument
            // as the numeric base, which quietly turns "3" at index 2 into 0.
            $categoryIds = $request->collect('category_ids')->map(fn (mixed $id): int => (int) $id);

            if ($request->filled('new_category')) {
                $categoryIds->push(CategoryResolver::resolve($request->string('new_category')->toString())->id);
            }

            $shop->categories()->sync($categoryIds->all());
        });

        return redirect()->route('shops.index');
    }
}
