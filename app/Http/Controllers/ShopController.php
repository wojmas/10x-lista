<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
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
     * The order is not cosmetic — it is the precedence the recommendation
     * applies, and this screen must show the same one. It lives in
     * Shop::inPrecedenceOrder(), which carries the contract and the standing
     * warning that no test guards it yet; read that docblock before changing
     * how shops are ordered anywhere.
     *
     * Categories are eager-loaded so rendering does not fire one query per shop.
     */
    public function index(): View
    {
        return view('shops.index', [
            'shops' => Shop::query()
                ->with('categories')
                ->inPrecedenceOrder()
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

            $shop->categories()->sync($this->assignedCategoryIds($request));
        });

        return redirect()->route('shops.index');
    }

    /**
     * The edit form is the same form as adding, filled in.
     *
     * The full category list comes along for the checkboxes; the shop's own
     * categories are what decides which of them start ticked, so the relation
     * has to be loaded rather than counted on to lazy-load inside the view.
     */
    public function edit(Shop $shop): View
    {
        return view('shops.edit', [
            'shop' => $shop->load('categories'),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Save the new name and the new set of categories as one act.
     *
     * The transaction is here for the same reason as in store(), with a sharper
     * edge: a failure between the two writes would leave the shop wearing the
     * name from this submission and the categories from before it — a state no
     * screen reports and nobody would think to check.
     *
     * An empty category_ids means sync([]) detaches everything, and that is
     * intended: UpdateShopRequest explains why a shop covering nothing is a
     * legal state and what stops it from becoming a bad recommendation.
     */
    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        DB::transaction(function () use ($request, $shop): void {
            $shop->update(['name' => trim($request->string('name')->toString())]);

            $shop->categories()->sync($this->assignedCategoryIds($request));
        });

        return redirect()->route('shops.index');
    }

    /**
     * Take a shop out of the configuration, for good.
     *
     * The id arrives as a plain int rather than a route-model-bound Shop, the
     * same call as in ProductController::destroy(): the shop list is shared, so
     * two members can have it open and both submit the same removal. Binding
     * would answer the second one with a 404 — an error page for an action that
     * did what the member wanted. Shop::destroy() raises nothing when the id
     * matches no row, so both requests end on the list with the shop gone.
     *
     * The category assignments go with it through the cascade on
     * category_shop.shop_id; nothing here detaches them. The categories
     * themselves stay, including ones now used by no shop — cleaning those up is
     * out of scope, and restrictOnDelete on category_id is what keeps a stray
     * cleanup from taking a category still attached to products.
     *
     * Nothing here recalculates the recommendation, for the same reason as with
     * products: ProductController::index() computes it on every visit to the
     * home screen, so a second copy of the rule here could only drift from it.
     */
    public function destroy(int $id): RedirectResponse
    {
        Shop::destroy($id);

        return redirect()->route('shops.index');
    }

    /**
     * The categories one submission of the shop form names — ticked, typed in,
     * or both. Shared by store() and update() so the two forms cannot drift on
     * what "typed in a new category" means.
     *
     * @return array<int, int>
     */
    private function assignedCategoryIds(StoreShopRequest|UpdateShopRequest $request): array
    {
        // Cast explicitly rather than with intval(...): Collection::map hands
        // the callback (value, key), and intval() reads that second argument
        // as the numeric base, which quietly turns "3" at index 2 into 0.
        $categoryIds = $request->collect('category_ids')->map(fn (mixed $id): int => (int) $id);

        if ($request->filled('new_category')) {
            $categoryIds->push(CategoryResolver::resolve($request->string('new_category')->toString())->id);
        }

        return $categoryIds->all();
    }
}
