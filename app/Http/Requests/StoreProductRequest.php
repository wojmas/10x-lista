<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\NameComparison;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * The route already sits behind the "auth" middleware; every logged-in
     * family member may add products (PRD §Access Control).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->notAlreadyOnTheList()],
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_without:new_category', 'prohibits:new_category'],
            'new_category' => ['nullable', 'string', 'max:255', 'required_without:category_id'],
        ];
    }

    /**
     * The global "attributes" list in lang/pl/validation.php maps "name" to
     * "imię", which is right for the account forms and wrong here.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nazwa produktu',
            'category_id' => 'kategoria',
            'new_category' => 'nowa kategoria',
        ];
    }

    /**
     * Rejects a product whose name is already on the list, comparing through
     * NameComparison so "Mleko", "mleko" and " mleko " are one name.
     *
     * The check is deliberately list-wide rather than scoped to the chosen
     * category: the family keeps one shopping list and buys milk once, so "Mleko"
     * under Napoje is the same errand as "Mleko" under Nabiał. Scoping it per
     * category would put two identical-looking rows on a flat list with nothing
     * on screen to tell them apart.
     *
     * The check loads product names and compares them in PHP rather than in
     * SQL: LOWER() is ASCII-only in the SQLite used by tests but locale-aware
     * in production Postgres, so a database-side comparison would behave
     * differently in the two environments. At the scale this product targets
     * (a few dozen entries) reading the names is free; if the list ever grows
     * into the thousands this is the line to revisit.
     */
    private function notAlreadyOnTheList(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $taken = Product::query()
                ->pluck('name')
                ->contains(fn (string $existing): bool => NameComparison::matches($existing, $value));

            if ($taken) {
                $fail('Ten produkt jest już na liście zakupów.');
            }
        };
    }
}
