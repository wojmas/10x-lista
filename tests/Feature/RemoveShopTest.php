<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Removing a shop that is no longer worth driving to.
 *
 * Polish diacritics force escape: false on assertSee, the same trap as
 * ShopListTest and HomeRecommendationTest.
 */
class RemoveShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_removed_shop_is_gone_from_the_database_and_the_list(): void
    {
        $shop = Shop::factory()->create(['name' => 'Żabka']);
        $shop->categories()->attach(Category::factory()->create(['name' => 'Napoje'])->id);

        Shop::factory()->create(['name' => 'Biedronka']);

        $this->actingAs(User::factory()->create());

        $response = $this->delete("/shops/{$shop->id}");

        $response->assertRedirect(route('shops.index', absolute: false));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertDontSee('Żabka', escape: false);

        $this->assertSame(0, Shop::query()->whereKey($shop->id)->count());
    }

    /**
     * The assignments go with the shop through the cascade on
     * category_shop.shop_id. The categories themselves must survive: they are
     * shared with products, and a category that vanished with a shop would take
     * the products describing themselves with it.
     */
    public function test_the_category_assignments_go_with_it_but_the_categories_stay(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        $shop = Shop::factory()->create(['name' => 'Lidl']);
        $shop->categories()->attach([$dairy->id, $bread->id]);

        $this->actingAs(User::factory()->create())
            ->delete("/shops/{$shop->id}")
            ->assertRedirect(route('shops.index', absolute: false));

        $this->assertSame(0, DB::table('category_shop')->where('shop_id', $shop->id)->count());
        $this->assertSame(2, Category::query()->count());
    }

    /**
     * The point of removing a shop: the home screen stops sending the family
     * there. Nothing in destroy() recalculates anything — the recommendation is
     * computed on every visit to the home screen, and this proves that is
     * enough.
     */
    public function test_removing_the_recommended_shop_hands_the_recommendation_to_the_next_one(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        Product::factory()->create(['category_id' => $dairy->id]);
        Product::factory()->create(['category_id' => $bread->id]);

        $best = Shop::factory()->create(['name' => 'Biedronka']);
        $best->categories()->attach([$dairy->id, $bread->id]);

        $second = Shop::factory()->create(['name' => 'Lidl']);
        $second->categories()->attach($dairy->id);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Biedronka');

        $this->actingAs($user)
            ->delete("/shops/{$best->id}")
            ->assertRedirect(route('shops.index', absolute: false));

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Lidl')
            ->assertSee('Pokrywa 1 z 2 kategorii z listy.', escape: false)
            ->assertDontSee('Biedronka');
    }

    /**
     * The shop list belongs to the whole family, so two members can have it open
     * and both submit the same removal — or one member can double-tap. The
     * second request has to end on the list, not on a 404 for an action that did
     * what they wanted.
     */
    public function test_removing_a_shop_that_is_already_gone_returns_to_the_list(): void
    {
        $shop = Shop::factory()->create(['name' => 'Żabka']);

        $this->actingAs(User::factory()->create());

        $this->delete("/shops/{$shop->id}")->assertRedirect(route('shops.index', absolute: false));
        $this->delete("/shops/{$shop->id}")->assertRedirect(route('shops.index', absolute: false));

        $this->assertSame(0, Shop::query()->count());
    }

    /**
     * The removal button has to be a working form on the rendered list, not just
     * a route that answers when a test posts to it directly.
     */
    public function test_the_list_renders_a_working_removal_form(): void
    {
        $shop = Shop::factory()->create(['name' => 'Biedronka']);

        $rendered = $this->actingAs(User::factory()->create())
            ->get('/shops')
            ->assertOk()
            ->assertSee('Usuń', escape: false)
            ->getContent();

        $this->assertStringContainsString(route('shops.destroy', $shop), $rendered);
        $this->assertStringContainsString('confirm(', $rendered);
    }

    public function test_guests_can_not_remove_a_shop(): void
    {
        $shop = Shop::factory()->create(['name' => 'Biedronka']);

        $this->delete("/shops/{$shop->id}")->assertRedirect('/login');

        $this->assertSame(1, Shop::query()->count());
    }
}
