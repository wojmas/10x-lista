<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing is the only repair path for a shop whose categories were assigned
 * wrong — until it existed, the fix was a query against the production database.
 *
 * Polish diacritics force escape: false on assertSee, the same trap as
 * ShopListTest and HomeRecommendationTest.
 */
class EditShopTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The ids go in as strings on purpose: that is what an HTML form posts, and
     * passing integers here hid a cast bug that only broke over real HTTP.
     *
     * The redirect is followed rather than merely asserted, because the repair
     * is only done once the member can see the corrected shop on the list.
     */
    public function test_a_member_changes_the_name_and_the_assigned_categories(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);
        $drinks = Category::factory()->create(['name' => 'Napoje']);

        $shop = Shop::factory()->create(['name' => 'Biedronak']);
        $shop->categories()->attach([$dairy->id, $bread->id]);

        $this->actingAs(User::factory()->create());

        $response = $this->put("/shops/{$shop->id}", [
            'name' => 'Biedronka',
            'category_ids' => [(string) $dairy->id, (string) $drinks->id],
        ]);

        $response->assertRedirect(route('shops.index', absolute: false));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertSee('Nabiał', escape: false)
            ->assertSee('Napoje')
            ->assertDontSee('Pieczywo');

        $shop->refresh()->load('categories');

        $this->assertSame('Biedronka', $shop->name);
        $this->assertEqualsCanonicalizing(
            ['Nabiał', 'Napoje'],
            $shop->categories->pluck('name')->all()
        );
    }

    /**
     * The ordinary case this screen exists for: fix the categories, leave the
     * name alone. A duplicate-name rule that does not skip the edited shop
     * rejects exactly this submission, and the member cannot save anything.
     */
    public function test_saving_without_changing_the_name_is_not_rejected_as_a_duplicate(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        $shop = Shop::factory()->create(['name' => 'Biedronka']);
        $shop->categories()->attach($dairy->id);

        $this->actingAs(User::factory()->create())
            ->from(route('shops.edit', $shop, absolute: false))
            ->put("/shops/{$shop->id}", [
                'name' => 'Biedronka',
                'category_ids' => [(string) $dairy->id, (string) $bread->id],
            ])
            ->assertRedirect(route('shops.index', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['Nabiał', 'Pieczywo'],
            $shop->refresh()->load('categories')->categories->pluck('name')->all()
        );
    }

    /**
     * Renaming one shop onto another one's name would split that shop's
     * categories across two rows, so each covers half of what the real shop
     * offers and both lose the recommendation to a third — with nothing on
     * screen to explain it. Case and spacing do not make it a different shop.
     */
    public function test_renaming_onto_another_shops_name_is_rejected(): void
    {
        Shop::factory()->create(['name' => 'Biedronka']);
        $shop = Shop::factory()->create(['name' => 'Lidl']);
        $category = Category::factory()->create();
        $shop->categories()->attach($category->id);

        $this->actingAs(User::factory()->create())
            ->from(route('shops.edit', $shop, absolute: false))
            ->put("/shops/{$shop->id}", [
                'name' => '  biedronka ',
                'category_ids' => [(string) $category->id],
            ])
            ->assertRedirect(route('shops.edit', $shop, absolute: false))
            ->assertSessionHasErrors('name');

        $this->assertSame('Lidl', $shop->refresh()->name);
    }

    /**
     * Clearing every category is how a shop is taken out of the recommendation
     * without deleting it and losing its place in the tie-break order. The shop
     * stays on the list saying so, and the home screen stops naming it.
     */
    public function test_clearing_every_category_keeps_the_shop_but_drops_it_from_the_recommendation(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        Product::factory()->create(['category_id' => $dairy->id]);

        $best = Shop::factory()->create(['name' => 'Biedronka']);
        $best->categories()->attach($dairy->id);

        $runnerUp = Shop::factory()->create(['name' => 'Lidl']);
        $runnerUp->categories()->attach($dairy->id);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Biedronka');

        $response = $this->actingAs($user)->put("/shops/{$best->id}", ['name' => 'Biedronka']);

        $response->assertRedirect(route('shops.index', absolute: false))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $best->refresh()->categories()->count());

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertSee('Brak kategorii — ten sklep nie trafi do rekomendacji.', escape: false);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Lidl')
            ->assertDontSee('Biedronka');
    }

    /**
     * The category a shop turns out to be missing often does not exist yet —
     * that is why the edit form carries the same "type in a new one" field as
     * the add form, and why it must go through CategoryResolver rather than
     * creating a second "Nabiał" next to the first.
     */
    public function test_a_typed_in_category_is_created_and_assigned(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $shop = Shop::factory()->create(['name' => 'Żabka']);
        $shop->categories()->attach($dairy->id);

        $this->actingAs(User::factory()->create())
            ->put("/shops/{$shop->id}", [
                'name' => 'Żabka',
                'category_ids' => [(string) $dairy->id],
                'new_category' => 'Napoje',
            ])
            ->assertRedirect(route('shops.index', absolute: false));

        $this->assertSame(2, Category::query()->count());
        $this->assertEqualsCanonicalizing(
            ['Nabiał', 'Napoje'],
            $shop->refresh()->load('categories')->categories->pluck('name')->all()
        );
    }

    public function test_the_form_arrives_with_the_current_state_ticked(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        $shop = Shop::factory()->create(['name' => 'Biedronka']);
        $shop->categories()->attach($dairy->id);

        $rendered = $this->actingAs(User::factory()->create())
            ->get(route('shops.edit', $shop, absolute: false))
            ->assertOk()
            ->assertSee('Biedronka')
            ->getContent();

        // Whitespace collapsed first: the checkbox attributes are spread over
        // two indented lines in the template, so a literal substring search on
        // the raw markup would break on reformatting rather than on behaviour.
        $markup = preg_replace('/\s+/', ' ', $rendered);

        $this->assertStringContainsString("value=\"{$dairy->id}\" checked", $markup);
        $this->assertStringNotContainsString("value=\"{$bread->id}\" checked", $markup);
    }

    /**
     * The edit routes take a route-model-bound {shop} precisely so that a stale
     * link or a hand-edited URL answers 404 instead of rendering a form over
     * nothing. Removal deliberately does the opposite (RemoveShopTest pins that
     * half), so without this test a refactor swapping one for the other would
     * pass the suite.
     */
    public function test_editing_a_shop_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/shops/999999/edit')
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login_on_both_routes(): void
    {
        $shop = Shop::factory()->create(['name' => 'Biedronka']);

        $this->get(route('shops.edit', $shop, absolute: false))->assertRedirect('/login');
        $this->put("/shops/{$shop->id}", ['name' => 'Lidl'])->assertRedirect('/login');

        $this->assertSame('Biedronka', $shop->refresh()->name);
    }
}
