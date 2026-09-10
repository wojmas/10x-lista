<?php

namespace App\Http\Requests;

use App\Models\Shop;
use App\Support\NameComparison;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
{
    /**
     * The route already sits behind the "auth" middleware; every logged-in
     * family member may configure shops (PRD §Access Control).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A shop with no categories covers nothing, so it can never win the S-04
     * recommendation — while still looking configured on screen. FR-008 treats
     * adding a shop and assigning categories as one act, so the rules do too:
     * the checkbox array may be empty only when a new category was typed in.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->notAlreadyConfigured()],
            'category_ids' => ['array', 'required_without:new_category'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'new_category' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_ids.required_without' => 'Zaznacz co najmniej jedną kategorię albo wpisz nową.',
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
            'name' => 'nazwa sklepu',
            'category_ids' => 'kategorie',
            'new_category' => 'nowa kategoria',
        ];
    }

    /**
     * Rejects a shop whose name is already configured, comparing through
     * NameComparison so "Biedronka", "biedronka" and " biedronka " are one shop.
     *
     * A duplicate would not just look untidy: two entries named "Biedronka"
     * split the categories between them, so each covers half of what the real
     * shop offers and both lose the S-04 recommendation to a third shop.
     *
     * Names are read into PHP rather than compared in SQL, for the same reason
     * as with products: LOWER() is ASCII-only in the SQLite used by tests but
     * locale-aware in production Postgres.
     */
    private function notAlreadyConfigured(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $taken = Shop::query()
                ->pluck('name')
                ->contains(fn (string $existing): bool => NameComparison::matches($existing, $value));

            if ($taken) {
                $fail('Ten sklep jest już skonfigurowany.');
            }
        };
    }
}
