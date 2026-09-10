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

    public function test_guests_are_redirected_to_login(): void
    {
        Shop::factory()->create();

        $this->get('/shops')->assertRedirect('/login');
    }
}
