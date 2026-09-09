<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\NameComparison;
use Illuminate\Database\Seeder;

/**
 * Starter set of categories so the product form has something to offer right
 * after a fresh install.
 *
 * Matching goes through NameComparison, not through an exact firstOrCreate:
 * this seeder is a third writer of category rows next to the product form's
 * two, and a member may well have typed "nabiał" before anyone runs the seeder
 * on production. An exact match would then add "Nabiał" alongside it and split
 * the vocabulary that the S-04 recommendation rule counts against.
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
        $existing = Category::query()->pluck('name');

        foreach (self::CATEGORIES as $name) {
            $alreadyThere = $existing->contains(
                fn (string $known): bool => NameComparison::matches($known, $name)
            );

            if ($alreadyThere) {
                continue;
            }

            Category::create(['name' => $name]);
            $existing->push($name);
        }
    }
}
