<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * The single place that turns a typed-in category name into a Category record.
 *
 * Both forms that can mint a category — adding a product and configuring a shop
 * — go through here. A second copy of this rule is exactly the configuration in
 * which the S-02 review found a bug: a third writer of the categories table
 * forgot NameComparison and split the vocabulary that the S-04 recommendation
 * compares against.
 */
class CategoryResolver
{
    /**
     * Return the category with this name, creating it only if none matches.
     */
    public static function resolve(string $name): Category
    {
        $name = trim($name);

        if ($existing = self::findNamed($name)) {
            return $existing;
        }

        // Lookup and insert are two steps, so two members typing the same brand
        // new category at the same moment can both get past the lookup. The
        // second insert then trips the unique index; rather than serving a 500
        // and losing the entry, take the category the other request just made.
        try {
            return Category::create(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            return self::findNamed($name) ?? throw new RuntimeException(
                "Nie udało się utworzyć ani odnaleźć kategorii [{$name}]."
            );
        }
    }

    /**
     * Find an existing category whose name means the same thing, if any.
     *
     * Public because the shop form needs to ask this question on its own, to
     * tell an added-but-already-known category from a genuinely new one.
     */
    public static function findNamed(string $name): ?Category
    {
        return Category::query()
            ->get()
            ->first(fn (Category $category): bool => NameComparison::matches($category->name, $name));
    }
}
