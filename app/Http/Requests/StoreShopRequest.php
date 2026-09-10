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
     * The absence of a "prohibits:new_category" rule is a decision, not an
     * oversight — StoreProductRequest carries exactly that rule, because a
     * product has one category and naming it twice can only be a mistake. A
     * shop has many, so ticking several and typing in one more is ordinary use
     * and must keep working.
     *
     * The price of that freedom: both routes can name the same category in
     * different spellings, so one submission can point at it twice. Nothing
     * here rejects that. What keeps the coverage honest is downstream —
     * CategoryResolver returns the same id for both spellings, sync() in
     * ShopController::store() collapses the repeat, and the unique index on
     * (shop_id, category_id) is the backstop. AddShopTest pins the collapse;
     * ShopCategoryAssignmentTest pins the backstop. Add "prohibits" here and
     * both of those stop describing anything a member can actually do.
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
            // Without the wildcard entry a per-element failure renders the raw
            // key ("category_ids.0") to a Polish-speaking member.
            'category_ids.*' => 'kategoria',
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
     *
     * The check is not atomic, so two members submitting "Biedronka" at the same
     * moment both pass it and the second trips the unique index on shops.name as
     * an uncaught exception — a 500 where a validation message would be kinder.
     * Accepted at this scale: the data stays correct because the index is the
     * real guarantee, and two people configuring the same shop in the same second
     * is not a thing a five-person family does.
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
