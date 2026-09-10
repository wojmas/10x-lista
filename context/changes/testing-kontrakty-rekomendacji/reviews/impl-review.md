<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kontrakty rekomendacji przed S-04

- **Plan**: `context/changes/testing-kontrakty-rekomendacji/plan.md`
- **Scope**: Phases 1–3 of 3 (full plan)
- **Date**: 2026-09-10
- **Verdict**: APPROVED (triaged 2026-09-10: F1, F4 fixed; F2, F3 accepted)
- **Findings**: 0 critical, 1 warning, 3 observations
- **Commits**: `ad33299`, `0d20280`, `5e23c19`, `bf8b30e`

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Notes on the PASS dimensions:

- **Scope Discipline** — every item in "What We're NOT Doing" held. No tie-break
  test written, no Postgres run configured, no `Shop::inPrecedenceOrder()` scope,
  no `prohibits` added, no regression tests for the already-covered writers, no
  zero-coverage test, no recommendation-rule tests. Two out-of-plan edits in
  Phase 3 (§2 row #3, `Last updated`) are recorded in `## Implementation
  Deviations` per `lessons.md`, which is exactly the handling that lesson asks for.
- **Safety & Quality** — no production logic changed. The two production-file
  edits are docblocks only; `git diff` confirms no executable line moved in
  `app/`. Nothing to scan for injection, N+1, or data-safety.
- **Pattern Consistency** — `ShopCategoryAssignmentTest` matches its non-HTTP
  siblings (`CategoryResolverTest`, `CategorySeederTest`): `Tests\TestCase` +
  `RefreshDatabase`, class-level docblock stating the why. The collision test
  follows `AddShopTest` conventions (string ids, `followRedirects()`, assertion on
  rendered content).
- **Success Criteria** — re-ran every automated check at review time:
  Feature OK (47), full OK (53 tests, 167 assertions), Pint PASS (65 files),
  `migrate:fresh` clean, zero `TBD` in §6.3, zero grep hits for precedence
  claims in `tests/`. Manual items have observable evidence in the transcript
  (two falsification runs each landing on exactly one intended test, one
  documented-state control run) — not rubber-stamped.

## Findings

### F1 — `change.md` still claims the deferred tie-break contract as this change's scope

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: `context/changes/testing-kontrakty-rekomendacji/change.md:14-19`
- **Detail**: The Notes block was written before research and never revised. It
  still states `Risks covered: #1 (... remis rozstrzygnięty niedeterministycznie
  ...), #3`, `Test types planned: integration, unit`, and two risk-response
  intents that the change deliberately did not deliver:
  - **#1** — "dowieść, że ... remis rozstrzyga się na sklep dodany pierwszy" —
    moved to Phase 4 by owner decision; nothing in this change proves it.
  - **#3** — "dowieść, że kategoria ... daje jeden rekord przez KAŻDEGO pisarza
    (formularz, seeder, kod)" — research established this was already covered
    in S-03, and the phase deliberately avoided adding tests to a closed gap.

  `test-plan.md` §3 and `plan.md` were both corrected; `change.md` was not. It is
  the change's identity file and the first thing `/10x-archive` reads, so it is
  now the one artefact overstating what landed — the same class of error the
  change existed to remove from the test suite. No unit test was added either,
  so `Test types planned: integration, unit` is wrong on a third count.
- **Fix**: Rewrite the Notes block to match delivery — risks covered `#3, #1
  (część: podwójne policzenie)`, test types `integration`, and replace the two
  intent bullets with what was proven plus a line naming Phase 4 as owner of the
  remis half.
- **Decision**: FIXED — Notes block rewritten to match delivery (risks `#3, #1 (część)`, test types `integration`, deferral to Phase 4 named, deleted-test side effect recorded).

### F2 — Success criterion 1.2 no longer describes the end state

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `context/changes/testing-kontrakty-rekomendacji/plan.md` — Progress row 1.2
- **Detail**: The criterion reads "Pełny zestaw zielony i liczniejszy niż 53
  testy". It held at Phase 1 (54 tests) but Phase 2 deleted one, so the change
  ends at 53 — not more than 53. The baseline in the criterion was also wrong to
  begin with: `a570c06` runs 52 tests, not 53. The Deviations section records the
  off-by-one; the criterion title itself is left as-is (correctly — Progress
  titles must not be renamed).
- **Fix**: Leave the row alone; it is historically accurate for the phase it
  belongs to. No action needed beyond the deviation note already present.
- **Decision**: ACCEPTED — row left as-is; historically accurate for Phase 1, deviation note already records the off-by-one.

### F3 — `substr_count` assertion guards a narrower case than it appears to

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: `tests/Feature/AddShopTest.php:110`
- **Detail**: `assertSame(1, substr_count($rendered->getContent(), 'Nabiał'))`
  cannot catch a doubled *assignment*: the unique index on
  `(shop_id, category_id)` makes a doubled write throw during the POST, so
  `assertRedirect` fails several lines earlier. What it does uniquely guard is a
  doubled *render* — a Blade loop bug showing one assignment twice — which the
  database assertion below it would miss. That is a real, if narrow, purpose, and
  the inline comment states it accurately.
  The cost is a false-failure surface: the assertion breaks if "Nabiał" ever
  appears elsewhere on the shops page (a filter, a nav hint, an empty-state
  example), for a reason unrelated to what the test pins.
- **Fix**: Keep it. If the shops page ever grows another mention of a category
  name, narrow the count to the chip markup rather than the whole document.
- **Decision**: ACCEPTED — assertion kept; narrow purpose is real and the inline comment states it. Revisit if the shops page grows another category mention.

### F4 — Baseline test count stale in plan body and brief

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: `context/changes/testing-kontrakty-rekomendacji/plan.md:26`, `plan-brief.md` §Starting Point
- **Detail**: Both still open with "53 testy, zielone na commicie `a570c06`";
  the actual baseline is 52. The correction lives only in `## Implementation
  Deviations`, so a reader who starts at the top of either document gets the
  wrong number before reaching the correction.
- **Fix**: Change both to 52; the deviation note then reads as history rather
  than as a contradiction of the body.
- **Decision**: FIXED — baseline corrected to 52 in plan.md §Current State Analysis and plan-brief.md §Starting Point.
