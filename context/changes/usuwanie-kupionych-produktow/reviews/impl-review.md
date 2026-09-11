<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Usuwanie kupionych produktów (S-05)

- **Plan**: `context/changes/usuwanie-kupionych-produktow/plan.md`
- **Scope**: Phase 1 of 1 (full plan)
- **Date**: 2026-09-11
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 1 observation
- **Commits reviewed**: `e493636` (phase 1), `114eaea` (epilogue)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

No runtime defects were found. Every finding is a documentation or
robustness issue — the feature behaves as specified.

### What passed, and on what evidence

- **Plan Adherence** — all four "Changes Required" entries exist and match intent:
  route (`routes/web.php:14`), `destroy()` without model binding
  (`ProductController.php:85`), button in the list row (`home.blade.php:75-86`),
  test file (`RemoveProductTest.php`). Four deviations are documented in the
  plan's own `## Deviations Taken During Implementation` section, satisfying the
  `lessons.md` rule.
- **Scope Discipline** — every item in "What We're NOT Doing" holds: no
  `SoftDeletes` on `Product`, no flash component, no confirmation dialog, no
  bulk removal, no orphan-category cleanup, no product editing, no shop-order
  pinning. The red button restyle was owner-directed mid-phase and is recorded
  as deviation 4.
- **Safety & Quality** — route sits inside the `auth` group; `@csrf` present;
  `Product::destroy($id)` is parameterized by Eloquent; Blade escapes by
  default; no new queries per list row (`index()` still eager-loads `category`).
  Production asset build verified: `Dockerfile:3-9` runs `npm run build` after
  `COPY resources ./resources`, so the new Tailwind classes are generated at
  deploy time despite `public/build/` being gitignored.
- **Success Criteria (automated, re-run during this review)** — `composer test`
  69 passed / 230 assertions; `vendor/bin/pint --test` 69 files PASS. Both
  falsification attempts confirmed during implementation hit exactly one test
  each.

## Findings

### F1 — Docblock states the opposite of the behavior it justifies

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: `app/Http/Controllers/ProductController.php:78`
- **Detail**: The docblock reads "Product::destroy() reports how many rows went
  and **throws on none**, so both requests end on the list with the product
  gone." The second clause contradicts the first: if it threw when nothing
  matched, the race case would produce a 500, not a return to the list. The
  actual behaviour is the opposite — `destroy()` returns `0` and throws nothing,
  which is the entire reason binding was rejected. In a project where docblocks
  are the carrier for load-bearing contracts, a comment asserting the inverse of
  the code is worse than no comment: the next reader either distrusts the
  decision or "fixes" the code to match the prose.
- **Fix**: Reword to state that `destroy()` returns the number of rows removed
  and does not throw when the id matches nothing.
- **Decision**: FIXED

### F2 — Route parameter `{product}` invites the binding the design forbids

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: `routes/web.php:14`, `app/Http/Controllers/ProductController.php:85`
- **Detail**: The route declares `{product}` while the method signature is
  `destroy(int $id)`. Laravel resolves route parameters to method arguments by
  name first; `product` does not match `id`, so this works only through the
  fallback that splices unmatched route values in positionally. It does work —
  six tests prove it — but two things follow. First, the binding is established
  on the less obvious of Laravel's two resolution paths. Second, and more
  important, `{product}` is precisely the cue that leads a future reader to
  type-hint `Product $product` "for consistency", which silently activates route
  model binding and restores the 404 the plan rejects. The race test catches
  that, but the name is an attractive nuisance placed directly next to the trap.
- **Fix A ⭐ Recommended**: Rename the route parameter to `{id}`.
  - Strength: Name-matches the method argument, so resolution goes through the
    normal path, and removes the cue that invites the binding regression.
    `route('products.destroy', $product)` in the view keeps working unchanged —
    Laravel resolves the model to its route key regardless of the placeholder name.
  - Tradeoff: Diverges from the plan's stated Contract, which spelled out
    `/products/{product}`; needs a line in the Deviations section.
  - Confidence: HIGH — the URL shape is unchanged, and the full suite re-run
    proves the resolution.
  - Blind spot: None significant; no other code references this route by
    parameter name.
- **Fix B**: Leave as is and rely on `test_removing_a_product_that_is_already_gone_returns_to_the_list`.
  - Strength: Zero diff; the regression is already covered by a test that was
    falsification-verified to fail on exactly this change.
  - Tradeoff: Keeps the misleading name, so the trap is re-encountered by every
    future reader; the test catches it only after someone has written the wrong
    code.
  - Confidence: MEDIUM — the test is real, but it protects against the outcome
    rather than preventing the mistake.
  - Blind spot: Assumes the test survives; a reader convinced the binding is
    correct might change the test instead of the code.
- **Decision**: FIXED via Fix A

### F3 — Plan body still describes five tests; six exist

- **Severity**: ⚠️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: `context/changes/usuwanie-kupionych-produktow/plan.md` §Desired End
  State (line 59), §Testing Strategy (line ~262)
- **Detail**: Deviation 1 renamed the first test to
  `test_removing_products_recalculates_the_recommendation` and deviation 3 added a
  sixth, `test_the_list_renders_a_working_removal_button`. Both are recorded in
  the Deviations section, but §Desired End State still says "przechodzi w
  **pięciu** scenariuszach" and §Testing Strategy still opens with "**Pięć**
  testów" and lists the old test name. `/10x-archive` and any later review read
  the plan as ground truth, so the body now contradicts its own deviations log.
- **Fix**: Update the two counts and the test name in the plan body; leave the
  Deviations section as the record of why they changed.
- **Decision**: FIXED

### F4 — Checked-off success criterion records a command that errors

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: `context/changes/usuwanie-kupionych-produktow/plan.md:370`, `CLAUDE.md`
- **Detail**: Progress row 1.3 is checked and SHA-stamped but reads
  `docker compose exec app php artisan pint --test`, which returns
  `Command "pint" is not defined.` — re-confirmed during this review. The
  criterion was genuinely met via `vendor/bin/pint` and deviation 2 says so, but
  the row itself remains a runnable that fails. The same wrong invocation sits in
  `CLAUDE.md` under Commands, which is loaded into every future session in this
  repository — so the next agent will reach for the broken command again.
- **Fix A ⭐ Recommended**: Correct the command in both `CLAUDE.md` and the plan's
  Progress row.
  - Strength: Fixes the source, not just this change's copy — `CLAUDE.md` is the
    file every future session reads first, so leaving it wrong guarantees the
    error recurs.
  - Tradeoff: Touches a repo-root file outside this change's planned scope;
    belongs in its own small commit.
  - Confidence: HIGH — verified by running both forms during this review.
  - Blind spot: Have not checked whether any CI config or script depends on the
    `artisan pint` spelling; a grep before editing would close this.
- **Fix B**: Correct only the plan's Progress row and leave `CLAUDE.md` for a
  separate change.
  - Strength: Keeps this change's diff strictly within its planned scope.
  - Tradeoff: The misleading instruction stays in the file with the widest reach;
    deviation 2 already flagged it, so leaving it is a knowing choice.
  - Confidence: HIGH — the plan-side edit is trivially correct.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — grep wykazał dodatkowo 16 wystąpień w trzech zarchiwizowanych planach; archiwum celowo nietknięte
