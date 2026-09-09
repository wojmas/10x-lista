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

    public function test_a_member_adds_a_product_with_an_existing_category(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);

        $this->actingAs(User::factory()->create())
            ->post('/products', ['name' => 'Mleko', 'category_id' => $category->id])
            ->assertRedirect(route('home', absolute: false));

        $product = Product::query()->sole();
        $this->assertSame('Mleko', $product->name);
        $this->assertTrue($product->category->is($category));
    }

    public function test_typing_a_new_category_creates_exactly_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/products', ['name' => 'Mleko', 'new_category' => 'Nabiał'])
            ->assertRedirect(route('home', absolute: false));

        $this->assertSame(1, Category::query()->count());
        $this->assertSame('Nabiał', Category::query()->sole()->name);
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

    public function test_a_product_already_on_the_list_is_rejected_regardless_of_case(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->post('/products', ['name' => '  mleko ', 'category_id' => $category->id])
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
