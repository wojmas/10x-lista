<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the family actually reads on the home screen.
 *
 * The rule itself is pinned in ShopRecommendationTest; these four go through
 * HTTP because the failure they guard against is a correct answer rendered into
 * the wrong panel — the right shop computed and never shown, or an empty list
 * presented as a recommendation.
 *
 * Polish diacritics force escape: false on assertSee, the same trap as
 * ShopListTest.
 */
class HomeRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_panel_names_the_winning_shop_with_its_coverage(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);
        $sweets = Category::factory()->create(['name' => 'Słodycze']);
        $chemicals = Category::factory()->create(['name' => 'Chemia']);

        foreach ([$dairy, $bread, $sweets, $chemicals] as $category) {
            Product::factory()->create(['category_id' => $category->id]);
        }

        $shop = Shop::factory()->create(['name' => 'Biedronka']);
        $shop->categories()->attach([$dairy->id, $bread->id, $sweets->id]);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertSee('Pokrywa 3 z 4 kategorii z listy.', escape: false);
    }

    public function test_an_empty_list_asks_for_products_instead_of_naming_a_shop(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        Shop::factory()->create(['name' => 'Biedronka'])->categories()->attach($dairy->id);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Dodaj produkty, żeby zobaczyć rekomendowany sklep.', escape: false)
            ->assertDontSee('Biedronka');
    }

    public function test_a_list_nothing_covers_says_so_instead_of_recommending(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $chemicals = Category::factory()->create(['name' => 'Chemia']);

        Product::factory()->create(['category_id' => $chemicals->id]);

        Shop::factory()->create(['name' => 'Biedronka'])->categories()->attach($dairy->id);

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Żaden sklep nie pokrywa kategorii z tej listy.', escape: false)
            ->assertSee(route('shops.index'), escape: false)
            ->assertDontSee('Jedź do', escape: false);
    }

    public function test_the_alternative_is_shown_only_when_it_covers_something(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        Product::factory()->create(['category_id' => $dairy->id]);
        Product::factory()->create(['category_id' => $bread->id]);

        $best = Shop::factory()->create(['name' => 'Biedronka']);
        $best->categories()->attach([$dairy->id, $bread->id]);

        $partial = Shop::factory()->create(['name' => 'Lidl']);
        $partial->categories()->attach($dairy->id);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Alternatywa:', escape: false)
            ->assertSee('Lidl');

        // Swap the partially covering shop for one that covers nothing: the
        // panel drops the alternative rather than offering a pointless drive.
        $partial->delete();
        Shop::factory()->create(['name' => 'Żabka']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Biedronka')
            ->assertDontSee('Alternatywa:', escape: false)
            ->assertDontSee('Żabka', escape: false);
    }
}
