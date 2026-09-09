<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Wspólna lista produktów

- **Plan**: `context/changes/wspolna-lista-produktow/plan.md`
- **Scope**: Phases 1–2 of 2 (full plan)
- **Date**: 2026-09-09
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 1 warning, 3 observations
- **Commits reviewed**: `75ab273`, `7f1ff8e`, `454bffe`

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated criteria were re-run at review time and all pass: `composer test` 29 passed (79 assertions), `migrate:fresh --seed` applies 9 migrations plus the category seeder, `route:list` carries `home`, `products.create` and `products.store`, `pint --test` PASS on 53 files.

Plan Adherence passes on a close reading: every "Changes Required" entry has a matching file, and the four places where implementation departed from the plan — field labels in `StoreProductRequest::attributes()` instead of the global `lang/pl/validation.php`, the add button in the page header, `NameComparison::matches()` alongside `normalize()`, and the PHP-side duplicate comparison — are all recorded in the plan's `## Addendum`, which is what `context/foundation/lessons.md` requires.

## Findings

### F1 — CategorySeeder bypasses the comparison rule and splits categories by letter case

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: `database/seeders/CategorySeeder.php:38`
- **Detail**: The plan's Critical Implementation Details state that the name-comparison rule "must be implemented once and used in two places", and both of those places do use `NameComparison`. The seeder is a third writer of category rows, and it matches exactly: `Category::firstOrCreate(['name' => $name])`. Reproduced in a throwaway test — create `nabiał` (as a member would by typing it into the product form), then run `CategorySeeder`, and the table ends up holding both `nabiał` and `Nabiał`. That is precisely the split the whole category design exists to prevent: S-04 counts how many of the list's categories a shop covers, so two records for one concept let a product silently fall outside a shop's coverage and push the recommendation to the wrong shop, with no error anywhere. The path is not hypothetical — the plan's own Migration Notes name running the seeder against production as an option, and production has no starter categories until someone does exactly that, potentially after members have already typed their own.
- **Fix**: Have the seeder resolve each name through `NameComparison` before inserting — read existing category names once, skip any that already match, insert the rest. This keeps the seeder idempotent in the stronger sense the rest of the code already assumes, and reuses the single comparison definition instead of adding a fourth spelling of "same name".
- **Decision**: FIXED — the seeder now reads existing names once and skips any that match through `NameComparison`. New `tests/Feature/CategorySeederTest.php` pins creation, idempotency and the case-variant case; verified as a real regression guard by temporarily restoring `firstOrCreate`, which makes the third test fail.

### F2 — Two members typing the same new category at once can produce a 500

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: `app/Http/Controllers/ProductController.php:64-71`
- **Detail**: `resolveCategory()` checks for an existing category and then creates one, with nothing between the two steps. Two requests submitting the same brand-new category name concurrently both find nothing, both call `Category::create()`, and the second violates the `unique` index on `categories.name` — an uncaught `QueryException`, so the member sees a 500 while the product they typed is lost. The window is milliseconds and the family is three to five people, so this is unlikely rather than impossible; it is worth naming because the PRD's premise is exactly concurrent use ("pozostali członkowie rodziny wpisują produkty na bieżąco"), and the failure discards the user's input rather than degrading gracefully.
- **Fix**: Catch the unique-violation `QueryException` around the create and re-run the lookup, returning the category the other request just made.
- **Decision**: FIXED — the lookup moved into `findCategoryNamed()`, and `resolveCategory()` now catches `UniqueConstraintViolationException` around the insert and re-runs that lookup. The race window is too narrow to reproduce in a test, so this one is covered by reasoning rather than by a regression guard.

### F3 — Nothing pins the "exactly one category field" rule

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `tests/Feature/AddProductTest.php`
- **Detail**: `StoreProductRequest` uses `prohibits:new_category` on `category_id` so that filling both the select and the new-category field is rejected. Verified by probe: submitting both returns a 302 with no product created, so the rule works today. No test covers it, though — `AddProductTest` exercises each field alone and the both-empty case, but never both-filled. A future edit to `rules()` could drop `prohibits` and every test would stay green while the form silently started ignoring one of the two inputs.
- **Fix**: Add a case to `AddProductTest` posting both `category_id` and `new_category`, asserting a validation error and no product created.
- **Decision**: FIXED — `test_filling_both_category_fields_is_rejected` asserts an error on `category_id` and that neither a product nor a second category is created.

### F4 — Two acceptance items were never verified

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `context/changes/wspolna-lista-produktow/plan.md` (Progress 1.7, 2.9)
- **Detail**: 16 of 18 Progress rows are checked with a commit SHA. The two left open — "lista czyta się poprawnie na szerokości ~375 px" and "formularz czyta się i obsługuje poprawnie na szerokości ~375 px" — are visual checks that were not performed, and were deliberately left unchecked rather than rubber-stamped. Responsiveness is a stated non-functional requirement in the PRD ("aplikacja musi działać na telefonie"), and the markup was written for it (`flex flex-wrap` with `gap`, `break-words`, `max-w-2xl` on the form), but writing responsive classes is not the same as having looked at the result. Both views also changed after the last time anything was seen in a browser: the home page header gained the add button in Phase 2.
- **Fix**: Open `/` and `/products/create` at ~375 px, confirm no horizontal scrolling and that the header's title and button wrap rather than collide, then check both rows.
- **Decision**: FIXED — the owner opened both views at ~375 px and confirmed them. Progress rows 1.7 and 2.9 are now checked, carrying the SHAs of the phase commits whose work they verify (`75ab273` for the list, `7f1ff8e` for the form). All 18 Progress rows are complete.
