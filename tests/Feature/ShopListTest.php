<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_a_shop_with_its_categories(): void
    {
        $shop = Shop::factory()->create(['name' => 'Biedronka']);
        $shop->categories()->attach([
            Category::factory()->create(['name' => 'Nabiał'])->id,
            Category::factory()->create(['name' => 'Pieczywo'])->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/shops')
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertSee('Nabiał')
            ->assertSee('Pieczywo');
    }

    public function test_an_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/shops')
            ->assertOk()
            ->assertSee('Nie ma jeszcze żadnego sklepu', escape: false);
    }

    /**
     * §Business Logic settles an S-04 tie in favour of the shop added first, and
     * id is the only column that never ties. The screen must show that same
     * precedence, otherwise the recommendation could name a shop other than the
     * "first" one visible here, with nothing to explain the difference.
     */
    public function test_shops_are_listed_in_ascending_id_order(): void
    {
        // The names run against the alphabet on purpose, so the assertion fails
        // if the query ever sorts by name or by recency instead of by id.
        $first = Shop::factory()->create(['name' => 'Zeta']);
        $second = Shop::factory()->create(['name' => 'Alfa']);

        $this->assertLessThan($second->id, $first->id);

        $this->actingAs(User::factory()->create())
            ->get('/shops')
            ->assertOk()
            ->assertSeeInOrder(['Zeta', 'Alfa']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        Shop::factory()->create();

        $this->get('/shops')->assertRedirect('/login');
    }
}
