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

    /**
     * The read side of the visibility guardrail: the query is not scoped to
     * whoever is signed in, so a product nobody in this session created still
     * shows up — with its category, which is the whole payload of a list row.
     *
     * This covers the query only. The full path — one member submits the form,
     * another loads the page — is pinned in AddProductTest, which is the test
     * that actually exercises the write.
     */
    public function test_the_list_is_not_scoped_to_the_signed_in_member(): void
    {
        $category = Category::factory()->create(['name' => 'Nabiał']);
        Product::factory()->create(['name' => 'Mleko', 'category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Mleko')
            ->assertSee('Nabiał');
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
