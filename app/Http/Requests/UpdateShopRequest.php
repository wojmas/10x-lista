<?php

namespace App\Http\Requests;

use App\Models\Shop;
use App\Support\NameComparison;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopRequest extends FormRequest
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
     * Two rules here differ from StoreShopRequest, and both look like omissions
     * until you know why they stand:
     *
     * 1. The duplicate-name check skips the shop being edited. Without that,
     *    saving the form without touching the name — the ordinary case, since
     *    the point of this screen is fixing categories — would be rejected for
     *    colliding with itself.
     *
     * 2. "category_ids" is not required_without:new_category. A shop with no
     *    categories is a legal state that only this form can produce, and it is
     *    the way to take a shop out of the recommendation without deleting it
     *    and losing its place in the tie-break order (§Business Logic settles a
     *    tie in favour of the shop added first, and that is the id). What keeps
     *    it honest is downstream: ShopRecommendation drops a shop covering
     *    nothing rather than recommending a pointless drive, and the shop list
     *    says so on the row. Adding the rule back would make the screen unable
     *    to express what the shop list is already prepared to show.
     *
     * Everything else matches StoreShopRequest deliberately, duplication and
     * all: a shared base class for two implementations would cost more than the
     * dozen lines it saves, and the long docblocks over there record decisions
     * specific to adding a shop that editing does not inherit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->notTakenByAnotherShop()],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'new_category' => ['nullable', 'string', 'max:255'],
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
     * Rejects a name already used by a *different* shop, comparing through
     * NameComparison so "Biedronka", "biedronka" and " biedronka " are one shop.
     *
     * Two entries named "Biedronka" would split that shop's categories between
     * them, so each covers half of what the real shop offers and both lose the
     * S-04 recommendation to a third shop, with nothing on screen to explain it.
     *
     * Names are read into PHP rather than compared in SQL, for the same reason
     * as everywhere else in this codebase: LOWER() is ASCII-only in the SQLite
     * used by tests but locale-aware in production Postgres.
     *
     * The check is no more atomic here than in StoreShopRequest, and the same
     * trade-off is accepted for the same reason: two members renaming shops onto
     * the same name in the same moment both pass it, and the second trips the
     * unique index on shops.name as a 500 where a validation message would be
     * kinder. Read the closing paragraph of StoreShopRequest::notAlreadyConfigured()
     * for why that is left alone at a family's scale.
     */
    private function notTakenByAnotherShop(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $edited = $this->route('shop');

            $taken = Shop::query()
                ->when($edited instanceof Shop, fn ($query) => $query->whereKeyNot($edited->getKey()))
                ->pluck('name')
                ->contains(fn (string $existing): bool => NameComparison::matches($existing, $value));

            if ($taken) {
                $fail('Ten sklep jest już skonfigurowany.');
            }
        };
    }
}
