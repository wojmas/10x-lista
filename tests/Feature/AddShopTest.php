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

    public function test_a_member_adds_a_shop_with_the_categories_they_ticked(): void
    {
        $dairy = Category::factory()->create(['name' => 'Nabiał']);
        $bread = Category::factory()->create(['name' => 'Pieczywo']);

        $this->actingAs(User::factory()->create())
            ->post('/shops', [
                'name' => 'Biedronka',
                'category_ids' => [$dairy->id, $bread->id],
            ])
            ->assertRedirect('/shops');

        $shop = Shop::query()->firstOrFail();

        $this->assertSame('Biedronka', $shop->name);
        $this->assertEqualsCanonicalizing(
            ['Nabiał', 'Pieczywo'],
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
            ->assertRedirect('/shops');

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
            ->assertRedirect('/shops');

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
            ->from('/shops/create')
            ->post('/shops', [
                'name' => '  biedronka ',
                'category_ids' => [$category->id],
            ])
            ->assertRedirect('/shops/create')
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Shop::query()->count());
    }

    public function test_a_shop_without_any_category_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/shops/create')
            ->post('/shops', ['name' => 'Biedronka'])
            ->assertRedirect('/shops/create')
            ->assertSessionHasErrors('category_ids');

        $this->assertSame(0, Shop::query()->count());
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from('/shops/create')
            ->post('/shops', ['name' => '', 'category_ids' => [$category->id]])
            ->assertRedirect('/shops/create')
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
