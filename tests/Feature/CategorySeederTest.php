<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_starter_categories(): void
    {
        (new CategorySeeder)->run();

        $this->assertSame(10, Category::query()->count());
        $this->assertTrue(Category::query()->where('name', 'Nabiał')->exists());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        (new CategorySeeder)->run();
        (new CategorySeeder)->run();

        $this->assertSame(10, Category::query()->count());
    }

    /**
     * Production has no starter categories until someone runs this seeder, and
     * by then a family member may already have typed one into the product form
     * with different capitalisation. Adding a second record for the same
     * concept would split the vocabulary the S-04 recommendation counts against.
     */
    public function test_it_does_not_add_a_category_that_differs_only_in_case(): void
    {
        Category::create(['name' => 'nabiał']);

        (new CategorySeeder)->run();

        $this->assertSame(1, Category::query()
            ->whereIn('name', ['nabiał', 'Nabiał'])
            ->count());
    }
}
