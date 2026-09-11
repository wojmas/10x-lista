<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Support\ShopRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The recommendation rule from §Business Logic, pinned for the first time.
 *
 * Until this file existed the suite guarded every *input* of the rule — the
 * uniqueness of a shop-category pair, the shared category vocabulary — and
 * nothing at all about the rule itself. context/foundation/test-plan.md §6.3
 * assigns these cases here by name.
 *
 * No HTTP: the rule reads models, so it needs the database, but it does not
 * need a request. The panel the user actually reads is pinned separately in
 * HomeRecommendationTest.
 */
class ShopRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_counts_distinct_categories_not_products(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        // Two products share a category. A shop offering both categories covers
        // two things worth driving for, not three.
        Product::factory()->create(['category_id' => $dairy->id]);
        Product::factory()->create(['category_id' => $dairy->id]);
        Product::factory()->create(['category_id' => $bread->id]);

        $shop = Shop::factory()->create();
        $shop->categories()->attach([$dairy->id, $bread->id]);

        $recommendation = $this->recommend();

        $this->assertSame(2, $recommendation->total);
        $this->assertSame(2, $recommendation->covered);
    }

    /**
     * A tie goes to the shop added first — §Business Logic.
     *
     * The names are picked so that id order and alphabetical order disagree:
     * "Żabka" is created first and sorts last. Without that, a tie-break
     * replaced by a sort on name would keep this test green.
     *
     * What this does NOT prove: that the rows arrive in id order at all. That
     * depends on Shop::inPrecedenceOrder() surviving, and removing its ORDER BY
     * leaves this test green because SQLite returns insertion order from a
     * freshly filled table anyway. That half is owed by §3 Phase 4 of
     * context/foundation/test-plan.md, which runs on Postgres. Here we pin the
     * half we own: the tie-break applied in PHP once the rows have arrived.
     */
    public function test_a_tie_goes_to_the_shop_added_first(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        Product::factory()->create(['category_id' => $dairy->id]);

        $first = Shop::factory()->create(['name' => 'Żabka']);
        $second = Shop::factory()->create(['name' => 'Auchan']);
        $first->categories()->attach($dairy->id);
        $second->categories()->attach($dairy->id);

        $recommendation = $this->recommend();

        $this->assertTrue($first->is($recommendation->shop));
        $this->assertTrue($second->is($recommendation->alternative));
    }

    /**
     * A shop with no categories is legal in the database — only the form
     * rejects one, and S-06 will produce one by removing categories. It must
     * never win, and with nothing else around there is no recommendation at all.
     */
    public function test_a_shop_covering_nothing_never_wins(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        Product::factory()->create(['category_id' => $dairy->id]);

        $empty = Shop::factory()->create(['name' => 'Pusty']);
        $stocked = Shop::factory()->create(['name' => 'Biedronka']);
        $stocked->categories()->attach($dairy->id);

        $recommendation = $this->recommend();

        $this->assertTrue($stocked->is($recommendation->shop));
        $this->assertNull($recommendation->alternative);

        // On its own the empty shop leaves the list without a recommendation
        // rather than becoming one by default.
        $stocked->delete();

        $alone = $this->recommend();

        $this->assertNull($alone->shop);
        $this->assertSame(0, $alone->covered);
        $this->assertSame(1, $alone->total);
        $this->assertTrue($empty->exists());
    }

    public function test_the_alternative_appears_only_when_it_covers_something(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);
        $sweets = Category::factory()->create(['name' => 'Słodycze']);

        Product::factory()->create(['category_id' => $dairy->id]);
        Product::factory()->create(['category_id' => $bread->id]);
        Product::factory()->create(['category_id' => $sweets->id]);

        $best = Shop::factory()->create(['name' => 'Biedronka']);
        $best->categories()->attach([$dairy->id, $bread->id, $sweets->id]);

        $partial = Shop::factory()->create(['name' => 'Lidl']);
        $partial->categories()->attach($dairy->id);

        Shop::factory()->create(['name' => 'Pusty']);

        $recommendation = $this->recommend();

        $this->assertTrue($best->is($recommendation->shop));
        $this->assertSame(3, $recommendation->covered);
        $this->assertTrue($partial->is($recommendation->alternative));
        $this->assertSame(1, $recommendation->alternativeCovered);

        // With the partially covering shop gone, only a zero-coverage shop is
        // left to be runner-up — and it is not offered as one.
        $partial->delete();

        $this->assertNull($this->recommend()->alternative);
    }

    public function test_an_empty_shopping_list_has_no_recommendation(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        Shop::factory()->create()->categories()->attach($dairy->id);

        $recommendation = $this->recommend();

        $this->assertNull($recommendation->shop);
        $this->assertSame(0, $recommendation->total);
    }

    /**
     * Feed the rule the same input the home screen does. The shops it scores
     * against are the rule's own business — it reads them itself, which is why
     * no test here can hand it a badly ordered collection.
     */
    private function recommend(): ShopRecommendation
    {
        return ShopRecommendation::for(Product::query()->with('category')->get());
    }
}
