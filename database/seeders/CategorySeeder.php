<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Starter set of categories so the product form has something to offer right
 * after a fresh install. Idempotent on purpose: production runs migrations on
 * every container start, and re-running this must never duplicate a category —
 * duplicates would split the vocabulary that the S-04 recommendation rule
 * compares against.
 */
class CategorySeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const CATEGORIES = [
        'Nabiał',
        'Pieczywo',
        'Warzywa i owoce',
        'Mięso i wędliny',
        'Mrożonki',
        'Napoje',
        'Sypkie i konserwy',
        'Słodycze i przekąski',
        'Chemia i higiena',
        'Inne',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
