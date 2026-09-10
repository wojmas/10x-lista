<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddShopTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The ids go in as strings on purpose: that is what an HTML form posts, and
     * passing integers here hid a cast bug that only broke over real HTTP.
     *
     * The redirect is followed rather than just asserted, because FR-008 is only
     * met once the member can *see* the shop with its categories. Reading the
     * relation out of the database would leave a regression in the list view's
     * category rendering invisible to every automated test.
     */
    public function test_a_member_adds_a_shop_with_the_categories_they_ticked(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);
        $other = Category::factory()->create(['name' => 'Napoje']);

        $this->actingAs(User::factory()->create());

        $response = $this->post('/shops', [
            'name' => 'Biedronka',
            'category_ids' => [(string) $dairy->id, (string) $bread->id, (string) $other->id],
        ]);

        $response->assertRedirect(route('shops.index', absolute: false));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertSee('Nabiał')
            ->assertSee('Pieczywo')
            ->assertSee('Napoje');

        $shop = Shop::query()->firstOrFail();

        $this->assertSame('Biedronka', $shop->name);
        $this->assertEqualsCanonicalizing(
            ['Nabiał', 'Pieczywo', 'Napoje'],
            $shop->categories->pluck('name')->all()
        );
    }

    public function test_a_typed_in_category_is_created_and_assigned(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/shops', [
                'name' => 'Żabka',
                'new_category' => 'Napoje',
            ])
            ->assertRedirect(route('shops.index', absolute: false));

        $this->assertSame(1, Category::query()->count());
        $this->assertSame(['Napoje'], Shop::query()->firstOrFail()->categories->pluck('name')->all());
    }

    public function test_a_typed_in_category_differing_only_in_case_reuses_the_existing_one(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);

        $this->actingAs(User::factory()->create())
            ->post('/shops', [
                'name' => 'Lidl',
                'new_category' => 'NABIAŁ',
            ])
            ->assertRedirect(route('shops.index', absolute: false));

        $this->assertSame(1, Category::query()->count());
        $this->assertSame([$dairy->id], Shop::query()->firstOrFail()->categories->pluck('id')->all());
    }

    /**
     * Two entries named "Biedronka" would split that shop's categories between
     * them, so each covers half of what the real shop offers and both lose the
     * S-04 recommendation to a third shop — with nothing reported as an error.
     */
    public function test_a_shop_whose_name_differs_only_in_case_or_spacing_is_rejected(): void
    {
        Shop::factory()->create(['name' => 'Biedronka']);
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('shops.create', absolute: false))
            ->post('/shops', [
                'name' => '  biedronka ',
                'category_ids' => [$category->id],
            ])
            ->assertRedirect(route('shops.create', absolute: false))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Shop::query()->count());
    }

    public function test_a_shop_without_any_category_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('shops.create', absolute: false))
            ->post('/shops', ['name' => 'Biedronka'])
            ->assertRedirect(route('shops.create', absolute: false))
            ->assertSessionHasErrors('category_ids');

        $this->assertSame(0, Shop::query()->count());
    }

    /**
     * A stale form can post a category that no longer exists. The rejection has
     * to be visible: the per-element failure is keyed "category_ids.0", so a view
     * reading only "category_ids" would send the member back to a form showing no
     * error at all, with no shop created and nothing explaining why.
     */
    public function test_a_category_that_does_not_exist_is_rejected_with_a_visible_message(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->from(route('shops.create', absolute: false))
            ->post('/shops', ['name' => 'Biedronka', 'category_ids' => ['99999']]);

        $response->assertRedirect(route('shops.create', absolute: false));

        // Deliberately no assertSessionHasErrors() here: it ages the flash data,
        // so the followRedirects() below would then render a form with no errors
        // and this test would pass no matter how the view reads them. Asserting
        // the rendered message covers the session key anyway.
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Wybrana wartość pola kategoria jest nieprawidłowa.', escape: false);

        $this->assertSame(0, Shop::query()->count());
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('shops.create', absolute: false))
            ->post('/shops', ['name' => '', 'category_ids' => [$category->id]])
            ->assertRedirect(route('shops.create', absolute: false))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Shop::query()->count());
    }

    public function test_guests_are_redirected_to_login_on_both_routes(): void
    {
        $this->get('/shops/create')->assertRedirect('/login');
        $this->post('/shops', ['name' => 'Biedronka'])->assertRedirect('/login');

        $this->assertSame(0, Shop::query()->count());
    }
}
