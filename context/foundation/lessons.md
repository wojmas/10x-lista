# Lessons Learned

> Append-only register of recurring rules and patterns. Re-read at start by /10x-frame, /10x-research, /10x-plan, /10x-plan-review, /10x-implement, /10x-impl-review.

## Deviations taken during implementation belong in the plan, not only in the chat

- **Context**: `context/changes/<change-id>/plan.md` — implementing a multi-phase plan where reality forces choices the plan did not anticipate.
- **Problem**: Five decisions taken while implementing `logowanie-i-prywatny-dostep` were announced in conversation and then lost: the `app.name` default moved into `config/app.php` as a workaround for the plan's own ban on touching `render.yaml`; `.gitignore` gained `/.composer` and `/.config`; the navigation link was retargeted rather than removed; `welcome.blade.php` was deleted although the plan named it only in prose; `lang/en/` was published and then removed. The plan is what `/10x-archive` and every later review read as ground truth, so a decision that lives only in a chat transcript reads as undocumented drift to the next person — or the next agent.
- **Rule**: When implementation departs from the plan — a workaround, an extra file touched, a deletion the plan only implied — append it to the plan before the phase commit, with a one-line reason. The phase commit already stages `plan.md`, so this costs nothing extra.
- **Applies to**: Every `/10x-implement` phase; checked by `/10x-impl-review` under Plan Adherence.
