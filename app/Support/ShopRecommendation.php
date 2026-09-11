<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * Which shop to drive to for the current shopping list.
 *
 * §Business Logic settles it: the shop covering the most distinct categories
 * from the list wins, and a tie goes to the shop added first. This is the only
 * feature separating the product from any shared shopping list, so the rule
 * lives in one place — the home screen recomputes it on every visit, which is
 * what makes ticking a product off, or editing a shop, feed straight back into
 * the answer with no cache to invalidate.
 *
 * The count runs in PHP rather than as a SQL COUNT on purpose. A rule expressed
 * in SQL is exposed to the very engine divergence that context/foundation/test-plan.md
 * §6.3 documents as unprovable on the suite's SQLite driver; counting here keeps
 * every branch of the rule testable. The cost is reading every shop into memory,
 * which is free at this product's scale (a family of 3-5, a handful of shops)
 * and is the line to revisit if the shop list ever runs into the thousands.
 */
final readonly class ShopRecommendation
{
    /**
     * @param  Shop|null  $shop  The recommendation, or null when nothing covers anything
     * @param  int  $covered  Distinct categories from the list that $shop offers
     * @param  int  $total  Distinct categories on the list
     * @param  Shop|null  $alternative  Runner-up, present only when it covers something
     * @param  int  $alternativeCovered  Distinct categories from the list that $alternative offers
     */
    private function __construct(
        public ?Shop $shop,
        public int $covered,
        public int $total,
        public ?Shop $alternative,
        public int $alternativeCovered,
    ) {}

    /**
     * Score the shops against the list.
     *
     * The shops are read here rather than passed in. That is deliberate: the
     * rule depends on them arriving in precedence order with categories
     * eager-loaded, and a caller who got either wrong would produce a wrong
     * tie-break or an N+1 with nothing reported and every test still green.
     * Owning the query removes the way to get it wrong — the same reason
     * Shop::inPrecedenceOrder() exists at all.
     *
     * The ordering is load-bearing, not cosmetic: PHP sorts are stable, so
     * sorting by coverage descending leaves shops of equal coverage in their
     * incoming id order — and that is how "a tie goes to the shop added first"
     * is implemented. Reversing these two operations breaks the rule silently.
     *
     * @param  Collection<int, Product>  $products
     */
    public static function for(Collection $products): self
    {
        $shops = Shop::query()->with('categories')->inPrecedenceOrder()->get();

        // Coverage counts distinct categories, not products: three products in
        // two categories are two categories' worth of reason to drive there.
        $wanted = $products->pluck('category_id')->unique();

        $ranked = $shops
            ->map(fn (Shop $shop): array => [
                'shop' => $shop,
                // Compared by category id, not by name. NameComparison already
                // settled identity when the row was written; comparing names
                // again here would make this a second writer of that rule.
                'covered' => $shop->categories->pluck('id')->intersect($wanted)->count(),
            ])
            ->sortByDesc('covered')
            ->values();

        // The collection is sorted descending, so the first two entries are the
        // two best-covering shops, already tie-broken by id.
        //
        // A shop covering nothing is legal in the database (only the add form
        // rejects it; the edit screen produces one whenever a member clears its
        // categories, which is how a shop is taken out of the running without
        // deleting it), but it is not a recommendation — sending someone to a shop we know stocks
        // none of their list is the mistake this product exists to prevent. The
        // same reasoning drops a zero-coverage runner-up: "alternative: Żabka
        // (0 of 4)" is noise that undermines the panel it sits in.
        $winner = ($ranked[0]['covered'] ?? 0) > 0 ? $ranked[0] : null;
        $runnerUp = ($ranked[1]['covered'] ?? 0) > 0 ? $ranked[1] : null;

        return new self(
            shop: $winner['shop'] ?? null,
            covered: $winner['covered'] ?? 0,
            total: $wanted->count(),
            alternative: $runnerUp['shop'] ?? null,
            alternativeCovered: $runnerUp['covered'] ?? 0,
        );
    }
}
