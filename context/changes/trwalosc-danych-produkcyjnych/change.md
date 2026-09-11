---
change_id: trwalosc-danych-produkcyjnych
title: Udokumentowana i raz przeklikana ścieżka odtworzenia produkcyjnej bazy
status: implementing
created: 2026-09-11
updated: 2026-09-11
archived_at: null
---

## Notes

udokumentować i raz przeklikać ścieżkę odtworzenia bazy z Neon instant restore; okno 6 h na free przyjęte świadomie, cykliczny pg_dump odłożony

Wariant A ze ścieżek rozważanych 2026-09-11 (A = tylko dokumentacja, B = dokumentacja + cykliczny `pg_dump`). Bez `/10x-plan` — zakres to jedna sekcja dokumentu plus ręczny test właściciela, plan byłby droższy od roboty.

**Zrobione (agent):**
- `context/deployment/deploy-plan.md` → nowa sekcja „Odtworzenie bazy" (parametry free tieru, dwie procedury, przyjęte ryzyko, checklista testu, obserwacja produkcyjna); L8 zamknięte „z ograniczeniem"; follow-up przeformułowany na `pg_dump`.
- `context/foundation/infrastructure.md` → wiersz ryzyka o brakujących backupach oznaczony jako częściowo zmitygowany.
- `context/foundation/roadmap.md` → unknown o retencji Neona rozstrzygnięty, F-01 na `in-progress`.

**Zostało (właściciel, w panelu Neona):** checklista „Dowód, że to działa" w sekcji „Odtworzenie bazy" — gałąź z przeszłości, sprawdzenie danych, skasowanie gałęzi, korekta nazw w panelu, jeśli UI się rozjechało. Dopiero to zamyka F-01.
