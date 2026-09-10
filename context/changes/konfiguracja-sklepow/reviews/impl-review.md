<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Konfiguracja sklepów

- **Plan**: context/changes/konfiguracja-sklepow/plan.md
- **Scope**: Phases 1–3 of 3 (full plan)
- **Date**: 2026-09-10
- **Verdict**: REJECTED at review time — all 10 findings fixed during triage; re-run verdict APPROVED
- **Findings**: 1 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated success criteria re-run at review time: `composer test` 47 passed (137 assertions), `pint --test` 64 files PASS, `migrate:fresh --seed` clean, all three `shops.*` routes registered, `AddProductTest.php` unchanged across the whole change range, no `resolveCategory`/`findCategoryNamed` left in `ProductController`. All manual criteria were verified earlier in the implementation session against the running app (HTTP flows plus a 375px headless render).

## Findings

### F1 — Race recovery in CategoryResolver is dead inside a transaction on PostgreSQL

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality (Reliability)
- **Location**: app/Support/CategoryResolver.php:35-41, called from app/Http/Controllers/ShopController.php:52,61
- **Detail**: `resolve()` catches `UniqueConstraintViolationException` and re-runs `findNamed()` to adopt the category a parallel request just created. On PostgreSQL a failed `INSERT` aborts the enclosing transaction, so the recovery `SELECT` itself throws. `ShopController::store()` wraps the call in `DB::transaction()`, so the recovery path cannot run in production.

  Confirmed by execution against the running Postgres container, not by inspection:

  ```
  driver: pgsql
    zlapano unique violation
  TRANSAKCJA PADLA: Illuminate\Database\QueryException
    SQLSTATE[25P02]: In failed sql transaction: 7 ERROR: current transaction is aborted,
    commands ignored until end of transaction block
  ```

  Failure scenario: two family members submit the shop form at the same moment, both typing the new category "Napoje". Request B clears the `findNamed()` lookup before A commits, then trips the unique index on `categories.name`. Instead of adopting A's category, B's whole transaction rolls back: no shop created, no category assigned, HTTP 500. The comment at CategoryResolver.php:31-34 ("rather than serving a 500 and losing the entry") describes the opposite of the production behaviour.

  The test suite cannot see this: SQLite does not poison a transaction on a failed statement, so `CategoryResolverTest` and `AddShopTest` pass. Note `ProductController::store()` opens no transaction, so the same call is safe there — the transaction wrapper introduced in Phase 3 is what creates the bug. This is the same class of environment divergence the plan already reasoned about for `LOWER()`, applied to a case it did not anticipate.
- **Fix A ⭐ Recommended**: Wrap only the insert in a nested transaction inside `resolve()`, so Laravel emits a `SAVEPOINT` and rolls back to it, leaving the outer transaction usable.
  - Strength: Fixes it at the root, where both callers route through, so a future caller that opens a transaction is covered without knowing about this. Smaller diff than changing every call site.
  - Tradeoff: Adds a `DB` facade dependency to a Support class that currently touches only Eloquent.
  - Confidence: HIGH — savepoint semantics are exactly what Laravel's nested `DB::transaction()` emits; the sub-agent verified the fixed version commits on Postgres.
  - Blind spot: Needs a regression test that runs on Postgres; an SQLite-only suite will keep reporting green either way.
- **Fix B**: Resolve the typed-in category *before* opening the transaction in `ShopController::store()`.
  - Strength: No change to shared code; the resolver keeps its current shape.
  - Tradeoff: Splits the "shop plus its categories are one act" guarantee the plan asked for — a category could be created and then the shop creation fail, leaving an orphan category. Also leaves the trap armed for the next caller that wraps `resolve()` in a transaction.
  - Confidence: MEDIUM — correct for today's single call site, fragile as a rule.
  - Blind spot: Orphan categories are invisible in the MVP (no category management screen), so the cost would not surface until S-06.
- **Decision**: FIXED via Fix A — nested transaction (SAVEPOINT) around the insert in `resolve()`; verified on the Postgres container that the recovery now returns the existing category and the outer transaction stays usable. Regression test added in `CategoryResolverTest`, with a docblock stating that SQLite cannot fail it.

### F2 — An invalid category id produces a silent dead end

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (Reliability)
- **Location**: resources/views/shops/create.blade.php:37
- **Detail**: Validation keys a per-element failure as `category_ids.0`, but the view reads `$errors->get('category_ids')`, which returns an empty array — `MessageBag::get()` only matches wildcards when the requested key contains one. `components/input-error.blade.php` guards with `@if ($messages)`, so nothing renders at all. Confirmed:

  ```
  klucze bledow: ["category_ids.0"]
  get("category_ids"): []
  ```

  Failure scenario: a stale form posts a category id that no longer exists. The member is bounced back to the form with no error anywhere on the page and no shop created — nothing explains why. `products/create.blade.php:32` escapes this only because `category_id` is scalar, so its key matches exactly.
- **Fix**: Render both keys, e.g. `:messages="array_merge($errors->get('category_ids'), Arr::flatten($errors->get('category_ids.*')))"`.
- **Decision**: FIXED — the view now reads both `category_ids` and `category_ids.*`. Verified against the running app: the message renders.

### F3 — That same message is English and shows a raw field key

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Requests/StoreShopRequest.php:56-63, lang/pl/validation.php:25,58,79
- **Detail**: `attributes()` maps `category_ids` but not `category_ids.*`, and `exists`, `array` and `integer` are still the stock English lines. The result a Polish-speaking family member would see is `The selected category_ids.0 is invalid.` — verified against the running app. Every other rule the form can trigger is correctly Polish, including the `name` override that displaces the global "imię". Fixing F2 without this one just surfaces an English message.
- **Fix**: Add `'category_ids.*' => 'kategoria'` to `attributes()` and translate `exists`, `array`, `integer` in `lang/pl/validation.php`.
- **Decision**: FIXED — `category_ids.*` added to `attributes()`; `exists`, `array` and `integer` translated in `lang/pl/validation.php`.

### F4 — AddShopTest has no negative-path case for an invalid category id

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/AddShopTest.php
- **Detail**: No case posts a non-existent id, which is exactly why F2 and F3 shipped unnoticed. `AddProductTest` covers its equivalent branches, including the mutually-exclusive-fields case. This is the second time in one change that a gap between what the test posts and what a real form posts hid a defect — the first was the `intval` bug, already recorded in `## Implementation Deviations`.
- **Fix**: Add a case posting a non-existent id, asserting both the session error key and that the message renders on the returned form.
- **Decision**: FIXED — `AddShopTest` gains a case posting a non-existent id and asserting the rendered Polish message. Note recorded in the test: `assertSessionHasErrors()` ages the flash data, so calling it before `followRedirects()` would make the assertion vacuous.

### F5 — Public findNamed() has no caller; the plan's justification did not materialise

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Support/CategoryResolver.php:50
- **Detail**: The plan made this method public for a stated reason: Phase 3 needed to ask separately whether a typed-in category already exists. Phase 3 never asks — `ShopController.php:61` calls `resolve()` and lets it handle the match. A grep across `app/`, `tests/`, `database/` and `routes/` finds `findNamed` only inside `CategoryResolver` itself. The behaviour is correct and the cost is trivial, but it is public API kept alive by a rationale that never landed, and it is not listed in `## Implementation Deviations`.
- **Fix**: Make it private, or record in the deviations section why it stays public for S-04/S-06.
- **Decision**: FIXED — `findNamed()` is now private. S-04/S-06 can widen it if they actually need it.

### F6 — AddShopTest case 1 asserts persistence, not the list

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/AddShopTest.php:19-39
- **Detail**: The plan's wording is "dodaje sklep z zaznaczonymi kategoriami **i widzi go na liście z tymi kategoriami**". The test asserts the redirect and then reads the relation out of the database; it never follows the redirect or issues `GET /shops`, so nothing asserts the shop and its category names actually render. The end-to-end claim is covered only transitively by `ShopListTest`, which builds its shop through the factory rather than the form. A regression in the index view's category rendering would leave every automated test green — this is the seam manual step 3.5 was checking by hand.
- **Fix**: Follow the redirect and assert the shop name and its categories appear in the response.
- **Decision**: FIXED — case 1 follows the redirect and asserts the shop name and all three category names render on the list.

### F7 — Duplicate shop name submitted twice at once yields a 500, not a validation error

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (Reliability)
- **Location**: app/Http/Requests/StoreShopRequest.php:84-90
- **Detail**: The duplicate check runs in PHP, so two simultaneous submissions of "Biedronka" both pass validation and the second trips the `shops.name` unique index as an uncaught exception. Data stays correct — the index does its job and the transaction rolls back cleanly — so at 3-5 users this is cosmetic. Distinct from F1: here the constraint is the last line of defence working as intended, not a broken recovery path.
- **Fix**: Note the accepted race in the docblock rather than adding code.
- **Decision**: FIXED (documentation) — the accepted race and why it is acceptable at this scale are now in the `notAlreadyConfigured()` docblock.

### F8 — findNamed() reads the whole table without the caveat its siblings carry

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/CategoryResolver.php:52-54
- **Detail**: Reading `categories` into PHP is deliberate and justified at this scale — it avoids the `LOWER()` divergence between SQLite and Postgres. But `StoreProductRequest.php:50-58` documents that trade-off *and* names the revisit threshold, and `StoreShopRequest.php:73-76` cross-references it. The resolver, which is now the first file a reader hits, says nothing.
- **Fix**: Add the same one-line caveat and revisit threshold to the `findNamed()` docblock.
- **Decision**: FIXED (documentation) — `findNamed()` now carries the whole-table-read rationale and the revisit threshold, matching `StoreProductRequest`.

### F9 — Redirect assertions use literal paths where siblings use route()

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/AddShopTest.php:30,48,63,85
- **Detail**: Literal `'/shops'` where `AddProductTest:21,32,49` uses `route('home', absolute: false)`. Self-consistent within the file, but it breaks the named-route indirection that `routes/web.php:8-9` explicitly calls load-bearing.
- **Fix**: Switch to `route('shops.index', absolute: false)`.
- **Decision**: FIXED — assertions use `route(..., absolute: false)`.

### F10 — Roadmap left mid-flight

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:37,117,165
- **Detail**: `change.md` reads `implemented` and every Progress box is `[x]`, but the roadmap calls S-03 `in-progress` in two places and still `planning` in the next-actions table, whose Notes column points at `/10x-implement konfiguracja-sklepow phase 1`. Lines 37 and 117 are `/10x-archive`'s job to flip to `done`; line 165 is not covered by either skill's sync and would mislead whoever opens S-04.
- **Fix**: Update line 165's status and Notes when archiving; let `/10x-archive` handle 37 and 117.
- **Decision**: FIXED — roadmap line 165 now reads `in-progress` with Notes pointing at `/10x-archive`. Lines 37 and 117 stay for `/10x-archive` to flip to `done`.
