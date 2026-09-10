<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The payload is the one a browser actually sends: the select carries its
     * value as text, and the untouched "new category" field arrives as an empty
     * string rather than being absent. Following the redirect matters just as
     * much — a 302 proves the request was accepted, not that anything reached
     * the list the family reads.
     */
    public function test_a_member_adds_a_product_with_an_existing_category(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);

        $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->post('/products', [
                'name' => 'Mleko',
                'category_id' => (string) $category->id,
                'new_category' => '',
            ])
            ->assertOk()
            ->assertSee('Mleko')
            ->assertSee('Nabiał');

        $this->assertTrue(Product::query()->sole()->category->is($category));
    }

    /**
     * The other shape the same form produces: the select left on its empty
     * placeholder option, the new-category field filled in.
     */
    public function test_typing_a_new_category_creates_exactly_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/products', [
                'name' => 'Mleko',
                'category_id' => '',
                'new_category' => 'Nabiał',
            ])
            ->assertRedirect(route('home', absolute: false));

        $this->assertSame(1, Category::query()->count());
        $this->assertSame('Nabiał', Category::query()->sole()->name);
    }

    /**
     * The guardrail the whole product rests on: the list belongs to the family,
     * not to whoever typed the entry. One member submits the form, another one
     * loads the page in a separate request and has to see it.
     *
     * ProductListTest covers the read side — that the query is not scoped to the
     * signed-in user. This is the only test that runs the write side through HTTP
     * and then reads it back as somebody else.
     */
    public function test_a_product_added_by_one_member_shows_up_for_another(): void
    {
        $adds = User::factory()->create();
        $shops = User::factory()->create();

        $this->actingAs($adds)
            ->post('/products', ['name' => 'Mleko', 'category_id' => '', 'new_category' => 'Nabiał'])
            ->assertRedirect(route('home', absolute: false));

        $this->actingAs($shops)
            ->get('/')
            ->assertOk()
            ->assertSee('Mleko')
            ->assertSee('Nabiał');
    }

    /**
     * The whole point of the chosen category model: a typed name that differs
     * only in case must reuse the existing record, not split the vocabulary
     * that S-03 and S-04 compare against.
     */
    public function test_a_typed_category_differing_only_in_case_reuses_the_existing_one(): void
    {
        $existing = Category::factory()->create(['name' => 'nabiał']);

        $this->actingAs(User::factory()->create())
            ->post('/products', ['name' => 'Mleko', 'new_category' => '  NABIAŁ  '])
            ->assertRedirect(route('home', absolute: false));

        $this->assertSame(1, Category::query()->count());
        $this->assertTrue(Product::query()->sole()->category->is($existing));
    }

    /**
     * Only letter case is exercised here on purpose. Whitespace never reaches
     * validation over HTTP — Laravel's global TrimStrings has already stripped it
     * — so asserting on it at this layer would prove the middleware, not our rule.
     * The trimming and whitespace-collapsing halves of the rule are pinned in
     * tests/Unit/NameComparisonTest.php, where they are actually reachable.
     */
    public function test_a_product_already_on_the_list_is_rejected_regardless_of_case(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->post('/products', ['name' => 'MLEKO', 'category_id' => (string) $category->id])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Product::query()->count());
    }

    /**
     * The rejection has to reach the member, in Polish, on the form they were
     * filling in — a silent bounce back to the list looks like the app ate the
     * entry.
     *
     * Note the assertion is on the rendered message alone: assertSessionHasErrors()
     * ages the flash data, so calling it before followingRedirects() would leave
     * nothing for the view to render and make this test vacuous.
     */
    public function test_the_duplicate_message_reaches_the_form_in_polish(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);
        Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->from('/products/create')
            ->followingRedirects()
            ->post('/products', ['name' => 'mleko', 'category_id' => (string) $category->id])
            ->assertOk()
            ->assertSee('Ten produkt jest już na liście zakupów.', escape: false);

        $this->assertSame(1, Product::query()->count());
    }

    /**
     * The block is deliberately list-wide, not per category: the family keeps one
     * shopping list and buys milk once, so the same name in a second category is
     * the same errand. Pinned here because the decision lives only in a query
     * that has no WHERE clause — easy for a later reader to mistake for an
     * oversight and "fix".
     */
    public function test_the_same_name_in_another_category_is_still_a_duplicate(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $drinks = Category::factory()->create(['name' => 'Napoje']);
        Product::factory()->create(['name' => 'Mleko', 'category_id' => $dairy->id]);

        $this->actingAs(User::factory()->create())
            ->post('/products', ['name' => 'Mleko', 'category_id' => (string) $drinks->id])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Product::query()->count());
    }

    public function test_a_product_needs_a_name_and_a_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/products', ['name' => '', 'new_category' => 'Nabiał'])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->post('/products', ['name' => 'Mleko'])
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Product::query()->count());
    }

    /**
     * The form asks for a category one way or the other, never both — picking
     * from the list and typing a new name at the same time has no defined
     * meaning, and silently honouring one of the two would surprise whoever
     * filled in the other.
     */
    public function test_filling_both_category_fields_is_rejected(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);

        $this->actingAs(User::factory()->create())
            ->post('/products', [
                'name' => 'Mleko',
                'category_id' => $category->id,
                'new_category' => 'Soki',
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(1, Category::query()->count());
    }

    public function test_the_form_offers_the_seeded_categories(): void
    {
        Category::factory()->create(['name' => 'Pieczywo']);

        $this->actingAs(User::factory()->create())
            ->get('/products/create')
            ->assertOk()
            ->assertSee('Pieczywo');
    }

    public function test_guests_can_not_reach_either_route(): void
    {
        $this->get('/products/create')->assertRedirect('/login');
        $this->post('/products', ['name' => 'Mleko', 'new_category' => 'Nabiał'])->assertRedirect('/login');

        $this->assertSame(0, Product::query()->count());
    }
}
