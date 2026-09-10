---
change_id: testing-kontrakty-rekomendacji
title: Kontrakty rekomendacji przed S-04 — faza 2 wdrożenia testów
status: implementing
created: 2026-09-10
updated: 2026-09-10
archived_at: null
---

## Notes

Rollout Phase 2 of `context/foundation/test-plan.md`: "Kontrakty rekomendacji przed S-04".

Risks covered: #1 (rekomendacja wskazuje inny sklep, niż wynika z reguły — remis rozstrzygnięty niedeterministycznie albo kategoria policzona dwukrotnie), #3 (kategoria rozdwaja się na wariancie zapisu, więc sklep pokrywa połowę swojego asortymentu i cicho przegrywa rekomendację). Test types planned: integration, unit.

Risk response intent:

- **#1**: dowieść, że przy danej liście i zestawie sklepów remis rozstrzyga się na sklep dodany pierwszy, powtarzalnie i przy identycznych znacznikach czasu, a pokrycie liczy różne kategorie raz.
- **#3**: dowieść, że kategoria wpisana w dowolnym wariancie zapisu daje jeden rekord przez KAŻDEGO pisarza (formularz, seeder, kod), więc pokrycie sklepu odzwierciedla cały zamierzony zbiór.

Zakres fazy to wyłącznie kontrakty, na których stanie S-04 — sama reguła rekomendacji jeszcze nie istnieje i jej testy należą do planu S-04.
