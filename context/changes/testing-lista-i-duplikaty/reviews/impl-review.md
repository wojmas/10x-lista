<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Rdzeń listy zakupów pod kształtem formularza

- **Plan**: `context/changes/testing-lista-i-duplikaty/plan.md`
- **Scope**: Phases 1–3 of 3 (full plan)
- **Date**: 2026-09-10
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 2 observations
- **Commits reviewed**: `d76ba83`, `b085fd6`, `9eb3950`, `889522e`

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

All 16 automated criteria were re-run at review time and pass: unit suite 6 tests / 9 assertions, feature suite 47 / 155, full suite 53 tests / 164 assertions, `pint --test` PASS on 64 files, `migrate:fresh --seed` applies cleanly, §6.1 and §6.2 carry no `TBD`, §3 row 1 reads `complete`.

Scope Discipline passes cleanly. The diff outside the change folder touches exactly six files, and all six are named in the plan's "Changes Required" — no unplanned file appears. Every guardrail in "What We're NOT Doing" holds: `AddShopTest`, `ShopListTest`, `CategoryResolverTest` and `CategorySeederTest` are untouched despite the `normalize()` change reaching them, no list-order assertion was written, `ProductController::store()` gained no transaction, and nothing was wired into CI.

The four falsification probes are the strongest evidence in this review and each one landed on its intended target: reverting `preg_replace` fails only the new unit case; narrowing the query in `index()` fails four tests including the new cross-member seam; changing the duplicate message text fails only the message test; scoping the duplicate query to `category_id` fails only the cross-category test. This is what separates the phase from one that merely turned the suite green.

## Findings

### F1 — Implementation deviations were never appended to the plan

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: `context/changes/testing-lista-i-duplikaty/plan.md` (no `## Addendum` section)
- **Detail**: `context/foundation/lessons.md` carries one accepted rule, and it is exactly this one: "When implementation departs from the plan — a workaround, an extra file touched, a deletion the plan only implied — append it to the plan before the phase commit, with a one-line reason." Three departures were taken and announced only in conversation. First, the `NameComparison` docblock was not just extended with the boundary paragraph the plan asked for — the pre-existing claim "Used in exactly two places" was corrected to "Four callers depend on it". That is a factual fix the research had established, but the plan never asked for it. Second, manual check 2.8 reads "Dodanie produktu i duplikatu **w przeglądarce**"; it was executed by driving the running app over HTTP with `curl` through nginx and asserting on the returned HTML. The stated criteria — Polish message present, one row on the list — were genuinely verified, but the method differs from the one written down, and the visual rendering was never looked at. This is the same class of gap that finding F4 of the S-02 review caught. Third, `test-plan.md` §6.6 was specified as "dwie–trzy linie" and shipped as five bullets. None of these changes an outcome; all three are precisely what the lesson exists to stop from evaporating into a chat transcript.
- **Fix**: Append an `## Addendum` section to `plan.md` recording the three departures, one line each with its reason.
- **Decision**: SKIPPED — owner declined; the three deviations stay in the transcript only.

### F2 — `preg_replace` can return null and `trim()` would receive it

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: `app/Support/NameComparison.php:39`
- **Detail**: `normalize()` reads `mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)))`. `preg_replace` is declared `string|array|null` and returns `null` on a PCRE error; `trim(null)` is deprecated on PHP 8.1+ and becomes a `TypeError` in PHP 9. In practice this is unreachable here: the pattern is `\s+` with no alternation, no backreference and no nested quantifier, so it cannot hit the backtrack or recursion limit, and the `/u` modifier — the one realistic source of `null`, on malformed UTF-8 — was deliberately omitted for exactly this reason and the docblock says so. The project runs no static analysis, so nothing will flag it mechanically either. Recorded so a future reader does not rediscover it and "harden" a path that cannot be taken.
- **Fix**: No action recommended. If the project ever adds PHPStan or Psalm, the rule will surface there and a `?? $name` fallback is the one-token answer.
- **Decision**: FIXED — `?? $name` added to normalize(), with a docblock line stating the fallback covers the remaining theoretical null.

### F3 — `ProductListTest` now holds two tests with nearly the same mechanism

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: `tests/Feature/ProductListTest.php:15-41`
- **Detail**: `test_the_list_shows_a_product_with_its_category` and the renamed `test_the_list_is_not_scoped_to_the_signed_in_member` both create a product with a factory, sign in as a fresh user, and assert the name renders. The plan's Phase 2 item 5 narrowed the second one's name and docblock so it stops overlapping with the new cross-member seam in `AddProductTest`, which it does — but it did not address that the two remaining tests differ only in whether the category name is also asserted. The overlap predates this phase and neither test is wrong; the second one's docblock now states what distinguishes it, which is most of the value. Worth naming so the next person reading the file does not assume one of them is dead weight and delete the wrong one.
- **Fix**: Leave as is. If the file is ever edited again, fold the category assertion into the scoping test and drop the first.
- **Decision**: FIXED — the two tests are merged; the scoping test now also asserts the category, and the redundant one is gone. Suite is 52 tests, 162 assertions, green.
