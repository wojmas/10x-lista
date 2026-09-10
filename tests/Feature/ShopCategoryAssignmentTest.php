<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Shop;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop-to-category assignment is what S-04 counts coverage over, so the
 * pair (shop, category) has to be unique as a matter of data, not of form
 * rules: a shop holding the same category twice covers it twice, wins the
 * recommendation it should have lost, and reports nothing as an error.
 *
 * The invariant lives in the migration for the category_shop table. Until this
 * test existed it lived only there — dropping the constraint in a later
 * migration broke nothing in the suite.
 *
 * No HTTP here on purpose: the guarantee belongs to the database, and every
 * writer of the pivot has to inherit it, not just the one form that exists
 * today (§6.1 — a test that needs the database still lives in tests/Feature).
 */
class ShopCategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_category_cannot_be_assigned_to_a_shop_twice(): void
    {
        $shop = Shop::factory()->create();
        $dairy = Category::factory()->create(['name' => 'Nabiał']);

        $shop->categories()->attach($dairy->id);

        // Nothing is asserted after the exception on purpose: on Postgres a
        // failed statement aborts the surrounding transaction, so a follow-up
        // count() would throw instead of reporting — the same divergence
        // CategoryResolver documents, and the reason Phase 4 exists.
        $this->expectException(UniqueConstraintViolationException::class);

        $shop->categories()->attach($dairy->id);
    }
}
