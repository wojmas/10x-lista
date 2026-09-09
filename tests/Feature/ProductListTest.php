<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_a_product_with_its_category(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);
        Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Mleko')
            ->assertSee('Nabiał');
    }

    /**
     * The PRD guardrail says an added product must be visible to every logged-in
     * family member, so the list is not scoped to whoever is signed in. Nothing
     * ties a product to a user, so seeing it from a freshly created account is
     * the strongest available proof of that.
     */
    public function test_products_are_visible_to_every_family_member(): void
    {
        Product::factory()->create(['name' => 'Chleb']);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Chleb');
    }

    public function test_an_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Lista zakupów jest pusta', escape: false);
    }

    public function test_guests_are_still_redirected_to_login(): void
    {
        Product::factory()->create();

        $this->get('/')->assertRedirect('/login');
    }
}
