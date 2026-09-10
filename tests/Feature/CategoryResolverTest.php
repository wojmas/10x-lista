<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\CategoryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResolverTest extends TestCase
{
    use RefreshDatabase;

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
