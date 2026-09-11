<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Taking a bought product off the shared list.
 *
 * Polish diacritics force escape: false on assertSee, the same trap as
 * HomeRecommendationTest and ShopListTest.
 */
class RemoveProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The acceptance criterion this whole slice exists to prove: a removed
     * product feeds back into the recommendation. It is also why S-05 is
     * sequenced after S-04 rather than shipped alongside adding products.
     *
     * Two removals, not one, and that is arithmetic rather than preference. A
     * single removal drops any one shop's coverage by at most one, so the gap
     * between two shops moves by at most one — which cannot carry a strict win
     * into a strict loss. The only single-removal version that changes the named
     * shop goes through a tie, and a tie is decided by shop precedence, which no
     * test pins until test-plan §3 Faza 4. Such a test would pass for the wrong
     * reason. Both ends here are strict: 3 vs 2 before, 1 vs 2 after.
     */
    public function test_removing_products_recalculates_the_recommendation(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);
        $chemicals = Category::factory()->create(['name' => 'Chemia']);
        $cosmetics = Category::factory()->create(['name' => 'Kosmetyki']);

        $milk = Product::factory()->create(['name' => 'Mleko', 'category_id' => $dairy->id]);
        $loaf = Product::factory()->create(['name' => 'Chleb', 'category_id' => $bread->id]);
        Product::factory()->create(['name' => 'Proszek', 'category_id' => $chemicals->id]);
        Product::factory()->create(['name' => 'Szampon', 'category_id' => $cosmetics->id]);

        Shop::factory()->create(['name' => 'Biedronka'])
            ->categories()->attach([$dairy->id, $bread->id, $chemicals->id]);

        Shop::factory()->create(['name' => 'Rossmann'])
            ->categories()->attach([$chemicals->id, $cosmetics->id]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertSee('Pokrywa 3 z 4 kategorii z listy.', escape: false);

        $this->actingAs($user)->delete("/products/{$milk->id}");

        $this->actingAs($user)
            ->followingRedirects()
            ->delete("/products/{$loaf->id}")
            ->assertOk()
            ->assertDontSee('Mleko')
            ->assertDontSee('Chleb')
            ->assertSee('Rossmann')
            ->assertSee('Pokrywa 2 z 2 kategorii z listy.', escape: false);
    }

    /**
     * The one test that looks at the button rather than calling the route.
     *
     * Every other test here issues DELETE directly, so all five pass with the
     * view rendering nothing at all — or, more likely, with the method spoofing
     * dropped in a later edit, which turns the click into POST /products/{id},
     * a route that does not exist, and a 405 for the member.
     *
     * Asserting on markup normally trips test-plan §6.3 rule 4. It is allowed
     * here because the hidden field is not an implementation detail of the view:
     * it is the contract between the form and the verb the route is registered
     * under, and HTML forms have no other way to express it.
     */
    public function test_the_list_renders_a_working_removal_button(): void
    {
        $product = Product::factory()->create(['name' => 'Mleko']);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Kupione — usuń', escape: false)
            ->assertSee(route('products.destroy', $product), escape: false)
            ->assertSee('name="_method" value="DELETE"', escape: false);
    }

    /**
     * The other half of the "data must not get lost" guardrail, which until now
     * was pinned for adding only. One member removes, another reads the page in
     * a separate request — a removal visible only to whoever clicked would send
     * the person actually driving to the shop after a product already bought.
     */
    public function test_a_product_removed_by_one_member_is_gone_for_another(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);
        $product = Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->delete("/products/{$product->id}")
            ->assertRedirect(route('home', absolute: false));

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Mleko')
            ->assertSee('Lista zakupów jest pusta', escape: false);
    }

    /**
     * The route is new, and the only thing keeping the family's list private is
     * that it sits inside the "auth" group. Nothing sweeps the route table for
     * that yet — test-plan §3 Faza 3 is not started — so every new route brings
     * its own proof, as AddProductTest does for the two it added.
     */
    public function test_guests_can_not_remove_a_product(): void
    {
        $product = Product::factory()->create();

        $this->delete("/products/{$product->id}")->assertRedirect('/login');

        $this->assertSame(1, Product::query()->count());
    }

    /**
     * Milk comes back on the list every week. The duplicate block in
     * StoreProductRequest is list-wide, so it only lets the name through again
     * because the row is really gone — pinned here because adding SoftDeletes to
     * Product would silently turn every repeat purchase into a rejected form
     * with nothing else in the suite going red.
     */
    public function test_a_removed_name_can_be_added_again(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);
        $product = Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $user = User::factory()->create();

        $this->actingAs($user)->delete("/products/{$product->id}");

        $this->actingAs($user)
            ->followingRedirects()
            ->post('/products', [
                'name' => 'Mleko',
                'category_id' => (string) $category->id,
                'new_category' => '',
            ])
            ->assertOk()
            ->assertSee('Mleko');

        $this->assertSame(1, Product::query()->count());
    }

    /**
     * Two members with the same page open, or one member double-tapping on a
     * slow connection: the second request names a product that is already gone.
     * It gets the list, not a 404 — the member wanted the product off the list
     * and the product is off the list, so an error page would be a lie.
     *
     * This is the only test standing between that decision and a route-model-
     * bound rewrite of ProductController::destroy(), which would look tidier and
     * quietly restore the 404.
     */
    public function test_removing_a_product_that_is_already_gone_returns_to_the_list(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->delete("/products/{$product->id}");

        $this->actingAs($user)
            ->delete("/products/{$product->id}")
            ->assertRedirect(route('home', absolute: false));
    }
}
