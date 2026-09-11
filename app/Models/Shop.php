<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * The categories this shop offers — the input the recommendation counts
     * coverage over.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Shops in the order §Business Logic settles a recommendation tie: the shop
     * added first wins.
     *
     * The id carries that rule. The shops table deliberately has no position
     * column and created_at would tie again whenever two shops land in the same
     * fraction of a second, so the strictly increasing primary key *is* the
     * contract. Both consumers read it from here — the shop list screen and
     * ShopRecommendation — because the screen must show the same precedence the
     * recommendation applies.
     *
     * WARNING: no test guards this clause, and one written today could not.
     * Without an ORDER BY both engines return insertion order from a freshly
     * filled table, so the assertion never gets the chance to fail. The
     * divergence is real but Postgres-only and latent: after an UPDATE to an
     * indexed column the new tuple lands at the end of the heap, so the shop
     * added first comes back last. Nothing renames a shop until S-06.
     *
     * The proof belongs to §3 Phase 4 of context/foundation/test-plan.md, which
     * runs the suite on Postgres. Until it lands, this docblock is the contract.
     * ShopRecommendationTest pins the other half — the tie-break applied in PHP
     * once the rows arrive — but it cannot pin the order they arrive in.
     */
    #[Scope]
    protected function inPrecedenceOrder(Builder $query): void
    {
        $query->orderBy('id');
    }
}
