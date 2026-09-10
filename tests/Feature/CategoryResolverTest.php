<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Shop;
use App\Support\CategoryResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryResolverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pins the mechanism the race recovery depends on: the colliding insert must
     * roll back to a savepoint, not take the caller's transaction down with it.
     *
     * ShopController::store() wraps resolve() in a transaction, and on Postgres a
     * failed statement aborts the whole thing — the recovery lookup then throws
     * instead of returning the category the parallel request just made, turning a
     * recoverable race into a 500. SQLite does not behave that way, so this test
     * cannot fail on the suite's own driver; it is here to state the contract and
     * to guard the day these tests run against Postgres.
     */
    public function test_a_colliding_insert_leaves_an_open_transaction_usable(): void
    {
        Category::factory()->create(['name' => 'Napoje']);

        DB::transaction(function (): void {
            try {
                DB::transaction(fn (): Category => Category::create(['name' => 'Napoje']));
                $this->fail('Oczekiwano naruszenia ograniczenia unikalności.');
            } catch (UniqueConstraintViolationException) {
                // this is the interleaving resolve() recovers from
            }

            $this->assertSame('Napoje', CategoryResolver::resolve('Napoje')->name);

            Shop::create(['name' => 'Biedronka']);
        });

        $this->assertSame(1, Shop::query()->count());
        $this->assertSame(1, Category::query()->count());
    }

    public function test_an_unknown_name_creates_exactly_one_category(): void
    {
        $category = CategoryResolver::resolve('Napoje');

        $this->assertSame('Napoje', $category->name);
        $this->assertSame(1, Category::query()->count());
    }

    public function test_a_name_differing_only_in_case_or_spacing_returns_the_existing_one(): void
    {
        $existing = Category::factory()->create(['name' => 'Nabiał']);

        $this->assertSame($existing->id, CategoryResolver::resolve('NABIAŁ')->id);
        $this->assertSame($existing->id, CategoryResolver::resolve('  nabiał  ')->id);
        $this->assertSame(1, Category::query()->count());
    }

    /**
     * Polish diacritics are significant by decision taken in S-02: "nabial" and
     * "nabiał" are two different names, and this test is what keeps that
     * decision from being quietly reversed.
     */
    public function test_a_name_differing_by_a_polish_diacritic_is_a_separate_category(): void
    {
        Category::factory()->create(['name' => 'Nabiał']);

        $resolved = CategoryResolver::resolve('nabial');

        $this->assertSame('nabial', $resolved->name);
        $this->assertSame(2, Category::query()->count());
    }
}
