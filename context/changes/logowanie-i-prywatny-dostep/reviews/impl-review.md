<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Logowanie i prywatny dostęp

- **Plan**: `context/changes/logowanie-i-prywatny-dostep/plan.md`
- **Scope**: Phases 1–4 of 4 (full plan)
- **Date**: 2026-08-31
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations
- **Commits reviewed**: `bd849ef`, `5a18c60`, `7c31a13`, `7935e67`, `26b95a5`, `933f740`

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria were re-run at review time: `composer test` 12 passed (32 assertions), `pint --test` PASS on 39 files, `npm run build` OK, `tailwind.config.js`/`postcss.config.js` absent, `package.json` on `tailwindcss ^4` with `@tailwindcss/vite`, `app:user:create` present in the command registry, `lang/pl/auth.php` carries both `failed` and `throttle`. `migrate:fresh` was not re-run because it would wipe the local development database; it passed during Phase 2.

## Findings

### F1 — Enabling registration produces a 500, not a working form

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: `app/Http/Controllers/Auth/RegisteredUserController.php:44`
- **Detail**: The owner's decision in Phase 2 was to keep Breeze's registration code intact behind a flag, so that re-enabling it is "a change of one value". It is not. `store()` ends with `redirect(route('dashboard', absolute: false))`, but Phase 2 deleted the `dashboard` route. Verified: `route('dashboard')` throws `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [dashboard] not defined.` With `REGISTRATION_ENABLED=true` a submitted form still creates the user and logs them in (`User::create` and `Auth::login` run first), then blows up on the redirect — so the visitor sees a 500 while an account silently exists. `RegistrationTest` only covers the disabled path, so nothing catches this. The manual check 2.10 only confirmed that `/register` renders, not that submitting it works.
- **Fix**: Change the redirect target to `route('home', absolute: false)` — the same edit already applied to `AuthenticatedSessionController::store()` in Phase 2 — and extend `RegistrationTest` with a case that flips `app.registration_enabled` on and asserts a successful registration redirects to `home`, so the disabled-by-default path and the enabled path are both pinned.
  - Strength: Restores the property the owner actually chose, and the new test prevents the same rot when someone eventually removes the flag.
  - Tradeoff: One line of production code plus a test that has to re-register routes with the flag on.
  - Confidence: HIGH — the missing route was confirmed by running `route('dashboard')`.
  - Blind spot: None significant.
- **Decision**: FIXED — redirect retargeted to `route('home')`; new `tests/Feature/Auth/RegistrationEnabledTest.php` flips the flag before boot and covers both the route registration and the successful-registration redirect. Verified as a genuine regression guard: reverting the controller line makes the new test fail with `RouteNotFoundException: Route [dashboard] not defined.`

### F2 — A typo in the account password locks you out with no recovery path

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: `app/Console/Commands/CreateUserCommand.php:33`
- **Detail**: The password is read once through `$this->secret('Hasło')` with no confirmation prompt. Because the input is hidden, a typo is invisible. Password reset and email verification were deliberately removed in Phase 2 and no mailer is configured, so there is no in-app recovery: a mistyped password on a production account can only be repaired by editing the Neon database directly or by creating a second account under a different address. The duplicate-email guard, correct on its own terms, makes re-running the command on the same address fail rather than fix the mistake. Breeze's own registration form uses `'confirmed'` for exactly this reason.
- **Fix A ⭐ Recommended**: Add a second hidden prompt and compare the two before validating, rejecting a mismatch with a clear message.
  - Strength: Removes the failure mode entirely, at the moment it can still be corrected for free; mirrors the `confirmed` rule Breeze applies on the web form.
  - Tradeoff: One extra prompt per account — for three to five accounts created once, negligible friction.
  - Confidence: HIGH — a self-contained change inside one `handle()` method, covered by the existing command test.
  - Blind spot: The piped-stdin invocation used during Phase 3 verification would need a second line on stdin.
- **Fix B**: Leave the prompt as is and document the recovery procedure in README — how to overwrite a password through `tinker` against the production `DB_URL`.
  - Strength: No code change; also useful for the unrelated case of someone simply forgetting their password.
  - Tradeoff: Accepts the failure instead of preventing it, and the documented recovery involves pointing a local shell at the production database — the operation the plan's Migration Notes warn about.
  - Confidence: MEDIUM — depends on the owner being comfortable running tinker against Neon.
  - Blind spot: Not verified whether Neon's free tier tolerates the extra ad-hoc connection during a cold start.
- **Decision**: SKIPPED — owner accepts the risk for now. Worth revisiting if a mistyped password ever locks an account out in practice.

### F3 — Three Blade components left behind with no callers

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: `resources/views/components/danger-button.blade.php`, `modal.blade.php`, `secondary-button.blade.php`
- **Detail**: Phase 2 removed the profile screen, which was the only consumer of these three components. A reference scan across `resources/views` finds zero remaining usages, while every other Breeze component still has at least one. They are inert — Blade only compiles what a view actually renders — but they are dead weight that S-02 through S-05 will read past.
- **Fix**: Delete the three files. Tailwind will also stop scanning them, shrinking the built stylesheet slightly.
- **Decision**: FIXED — all three files deleted; ten components remain, each with at least one caller.

### F4 — `role` is mass-assignable although nothing reads it

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: `app/Models/User.php:13`
- **Detail**: Phase 2 added `role` to `#[Fillable]`. Both current `User::create()` call sites pass explicit arrays — `RegisteredUserController` lists its three keys literally, and `CreateUserCommand` passes `$validator->validated()`, whose rule set has no `role` key — so nothing today can smuggle a role in from user input. The risk is forward-looking: the column exists specifically to become a privilege flag, and leaving it fillable means the first future endpoint that mass-assigns request data promotes whoever sends `role=admin`.
- **Fix**: Drop `role` from `#[Fillable]` and set it explicitly wherever a non-default role is ever needed. Nothing currently assigns it, so this cannot break existing code.
- **Decision**: SKIPPED — owner keeps it fillable for the future admin panel. Re-check when FR-002/FR-003 land and something starts reading the column.

### F5 — The plan does not record five deviations taken during implementation

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: `context/changes/logowanie-i-prywatny-dostep/plan.md`
- **Detail**: Five choices were made during implementation, disclosed in conversation at the time but never written into the plan: `config/app.php` now defaults `app.name` to `Lista zakupów` (a workaround for the plan's own ban on touching `render.yaml`); `.gitignore` gained `/.composer` and `/.config`; the navigation link was retargeted to `route('home')` with a new `Shopping list` translation key rather than removed; `resources/views/welcome.blade.php` was deleted although the plan listed it only in prose; `lang/en/` was published and then removed. The plan is the ground truth that `/10x-archive` and any later review read — leaving it silent makes those five look like undocumented drift to whoever reads it next.
- **Fix**: Append a short addendum section to the plan listing the five deviations and their one-line reasons.
- **Decision**: FIXED + ACCEPTED-AS-RULE: "Deviations taken during implementation belong in the plan, not only in the chat" — recorded in `context/foundation/lessons.md` (file created by this review), and `plan.md` gained an `## Addendum — deviations taken during implementation` section covering the five deviations plus the F1 and F3 fixes applied during triage.
