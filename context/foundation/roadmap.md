---
project: "Lista Zakupów"
version: 1
status: draft
created: 2026-08-31
updated: 2026-09-10
prd_version: 1
main_goal: quality
top_blocker: none
---

# Roadmap: Lista Zakupów

> Wyprowadzone z `context/foundation/prd.md` (v1) oraz automatycznie zbadanego stanu bazowego kodu.
> Dokument edytowany w miejscu; archiwizowany, gdy zostanie zastąpiony.
> Pozycje poniżej są uporządkowane według zależności. Tabela „At a glance" jest indeksem.

## Vision recap

Rodzina traci czas i pieniądze na dwa sposoby: ktoś zapomina kupić produkt, o którym nie wiedziała osoba jadąca do sklepu, i ktoś jedzie do sklepu, który akurat nie ma połowy potrzebnych rzeczy. Współdzielone listy (Google Keep, WhatsApp, kartka na lodówce) rozwiązują tylko pierwszy problem — żadna nie odpowiada na pytanie „GDZIE jechać na te zakupy?".

Cechą odróżniającą ten produkt — jedyną, której usunięcie sprowadziłoby go do gorszej wersji Google Keep — jest połączenie wspólnej listy zakupów z rekomendacją sklepu opartą na kategoriach produktów, które ten sklep faktycznie oferuje.

## North star

**S-04: użytkownik widzi na stronie głównej rekomendowany sklep, przeliczany po każdej zmianie listy** — to jedyny element, który odróżnia produkt od dowolnej wspólnej listy zakupów; jeśli rekomendacja okaże się bezużyteczna w praniu, reszta funkcji nie ma znaczenia.

> „Gwiazda przewodnia" oznacza tu najmniejszy, kończący się realnym efektem kawałek pracy, którego działanie udowadnia główną hipotezę produktu — dlatego ustawiam go tak wcześnie, jak pozwalają jego zależności.

## At a glance

| ID   | Change ID                      | Outcome (użytkownik może …)                                            | Prerequisites | PRD refs                     | Status   |
| ---- | ------------------------------ | ---------------------------------------------------------------------- | ------------- | ---------------------------- | -------- |
| F-01 | `trwalosc-danych-produkcyjnych` | (foundation) potwierdzona ścieżka odtworzenia produkcyjnej bazy danych | —             | §Guardrails, §NFR (24/7)     | ready    |
| S-01 | `logowanie-i-prywatny-dostep`   | zalogować się i zobaczyć stronę główną niedostępną dla niezalogowanych | —             | FR-001, US-01, §Access Control | done     |
| S-02 | `wspolna-lista-produktow`       | dodać produkt z nazwą i kategorią i zobaczyć wspólną listę rodziny     | S-01          | FR-004, FR-005, US-01        | done     |
| S-03 | `konfiguracja-sklepow`          | dodać sklep i przypisać mu kategorie produktów                         | S-02          | FR-008, US-01                | done     |
| S-04 | `rekomendacja-sklepu`           | zobaczyć rekomendowany sklep przeliczany po każdej zmianie listy       | S-02, S-03    | FR-007, US-01                | proposed |
| S-05 | `usuwanie-kupionych-produktow`  | usunąć kupiony produkt i zobaczyć przeliczoną bez niego rekomendację   | S-04          | FR-006, US-01                | proposed |
| S-06 | `edycja-i-usuwanie-sklepow`     | poprawić kategorie przypisane sklepowi i usunąć sklep                  | S-03          | brak FR — patrz pytanie 3    | proposed |

## Streams

Pomoc nawigacyjna — grupuje pozycje dzielące ten sam łańcuch zależności. Kanoniczna kolejność nadal wynika z grafu zależności poniżej; ta tabela to proponowana kolejność czytania równoległych torów.

| Stream | Theme            | Chain                                            | Note                                                                                                       |
| ------ | ---------------- | ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| A      | Cykl zakupowy    | `S-01` → `S-02` → `S-03` → `S-04` → `S-05`       | Zawiera gwiazdę przewodnią (`S-04`), ustawioną tak wcześnie, jak pozwalają jej zależności.                  |
| B      | Trwałość danych  | `F-01`                                           | Bez zależności, biegnie równolegle do całego strumienia A. Wymuszony celem „jakość i solidność".            |
| C      | Utrzymanie danych | `S-06`                                          | Odgałęzienie od `S-03`; nie blokuje gwiazdy przewodniej, więc może poczekać za całym strumieniem A.         |

## Baseline

Co jest już w kodzie na dzień `2026-08-31` (zbadane automatycznie, potwierdzone przez właściciela).
Fundamenty poniżej zakładają, że to istnieje, i **nie** budują tego ponownie.

- **Frontend:** partial — Tailwind CSS 4 i Vite 8 podpięte (`package.json`, `vite.config.js`), ale jedyny widok to `resources/views/welcome.blade.php`. Brak układu strony, komponentów i katalogu tłumaczeń `lang/`.
- **Backend / API:** partial — szkielet Laravel 13.8, `routes/web.php` zawiera jedną trasę-domknięcie `/`. Brak kontrolerów poza bazowym `app/Http/Controllers/Controller.php`.
- **Data:** partial — PostgreSQL 16 (`docker-compose.yml` lokalnie, Neon na produkcji). Tylko domyślne migracje Laravela (`users`, `cache`, `jobs`) i jedyny model `app/Models/User.php`. Brak schematu domenowego (produkty, sklepy, kategorie).
- **Auth:** partial — tabela `users` i `config/auth.php` obecne, ale brak tras logowania, kontrolerów, widoków i użycia middleware `auth`. Breeze/Fortify niezainstalowane.
- **Deploy / infra:** present — produkcyjny `Dockerfile` (nginx + php-fpm + supervisor), `render.yaml` (Blueprint), health check `/up` (`bootstrap/app.php:11`), aplikacja wdrożona pod `https://lista-zakupow-acl5.onrender.com`. Brak `.github/workflows` — świadomie, natywny auto-deploy Render (`context/deployment/deploy-plan.md`).
- **Observability:** absent — brak zewnętrznego śledzenia błędów (Sentry/OTel/Bugsnag). Dostępne są wyłącznie domyślne logi Laravela kierowane przez supervisor na stdout i widoczne w panelu Render.

## Foundations

### F-01: Trwałość i odtwarzalność danych produkcyjnych

- **Outcome:** (foundation) potwierdzona i opisana ścieżka odtworzenia produkcyjnej bazy danych — bariera „dane nie mogą się gubić" jest weryfikowalna, a nie tylko zadeklarowana.
- **Change ID:** `trwalosc-danych-produkcyjnych`
- **PRD refs:** §Success Criteria → Guardrails („dane nie mogą się gubić"), §Non-Functional Requirements (dostępność 24/7)
- **Unlocks:** ścieżkę weryfikacji bariery „dane nie mogą się gubić", wymaganą przez S-02, S-03 i S-05 (każdy z nich trwale zapisuje dane rodziny na produkcji); domyka lukę L8 z `context/deployment/deploy-plan.md` (brak automatycznych backupów na darmowym tierze Neon).
- **Prerequisites:** —
- **Parallel with:** S-01, S-02, S-03, S-04, S-05
- **Blockers:** —
- **Unknowns:**
  - Jaki zakres odtworzenia danych daje darmowy tier Neon (retencja historii) i czy wystarcza bez własnego zrzutu bazy? — Owner: twórca. Block: no.
- **Risk:** `deploy-plan.md` zostawia backupy jako follow-up po MVP; przy celu „jakość i solidność" oznaczałoby to, że jedyna twarda bariera PRD nie ma dowodu. Zakres jest celowo minimalny — potwierdzenie i opisanie ścieżki odtworzenia, nie budowa własnego systemu backupów.
- **Status:** ready

## Slices

### S-01: Logowanie i prywatny dostęp

- **Outcome:** użytkownik loguje się loginem i hasłem, a niezalogowany zostaje przekierowany na stronę logowania — strona główna przestaje być publiczna.
- **Change ID:** `logowanie-i-prywatny-dostep`
- **PRD refs:** FR-001, US-01 (przesłanka „zalogowany członek rodziny"), §Access Control, §Non-Functional Requirements (interfejs w języku polskim, responsywność)
- **Prerequisites:** —
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:** — (konta zakładane wstępnie poza aplikacją, zgodnie z §Open Questions PRD)
- **Risk:** to pierwszy kawałek wprowadzający układ strony, polskie tłumaczenia i responsywność — odłożenie tych decyzji oznacza przerabianie każdego kolejnego widoku. Ryzyko przeciwne: rozrost do panelu zarządzania kontami (FR-002/FR-003), które są odłożone.
- **Status:** done

### S-02: Wspólna lista produktów

- **Outcome:** użytkownik dodaje produkt z nazwą i kategorią oraz widzi na stronie głównej wspólną listę produktów całej rodziny.
- **Change ID:** `wspolna-lista-produktow`
- **PRD refs:** FR-004, FR-005, US-01
- **Prerequisites:** S-01
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:**
  - Czy kategoria produktu to wybór ze stałej listy, czy dowolny tekst wpisywany przez użytkownika? — Owner: twórca. Block: no.
- **Risk:** tutaj powstaje model kategorii, od którego zależą S-03 i S-04 — dowolny tekst rozbije dopasowanie w regule rekomendacji (literówka = niepokryta kategoria). Bariera „dodany produkt widoczny dla wszystkich" wymaga odczytu wspólnego dla rodziny, a nie listy per użytkownik.
- **Status:** done

### S-03: Konfiguracja sklepów z kategoriami

- **Outcome:** użytkownik dodaje sklep i przypisuje mu kategorie produktów w osobnym widoku.
- **Change ID:** `konfiguracja-sklepow`
- **PRD refs:** FR-008, US-01
- **Prerequisites:** S-02
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:** —
- **Risk:** kolejność dodawania sklepów jest znacząca — §Business Logic rozstrzyga remis na korzyść sklepu dodanego jako pierwszy, więc ta kolejność musi być trwale zapisana, a nie wynikać z przypadkowego sortowania w zapytaniu. Sekwencjonowane po S-02, bo kategorie przypisywane sklepom muszą być tym samym zbiorem, którym opisywane są produkty.
- **Status:** done

### S-04: Rekomendacja sklepu na stronie głównej

- **Outcome:** użytkownik widzi na stronie głównej rekomendowany sklep, przeliczany po każdej zmianie listy zakupów.
- **Change ID:** `rekomendacja-sklepu`
- **PRD refs:** FR-007, US-01 (kryteria akceptacji), §Business Logic
- **Prerequisites:** S-02, S-03
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:**
  - Co pokazać, gdy żaden sklep nie pokrywa ani jednej kategorii z listy (albo gdy lista jest pusta)? — Owner: twórca. Block: no.
- **Risk:** to gwiazda przewodnia i jedyna cecha odróżniająca produkt od zwykłej wspólnej listy. Sama reguła jest prosta, ale rozstrzyganie remisu zależy od stabilnej kolejności sklepów z S-03 — bez niej rekomendacja bywa niedeterministyczna przy tym samym zestawie danych.
- **Status:** proposed

### S-05: Usuwanie kupionych produktów

- **Outcome:** użytkownik usuwa kupiony produkt, produkt znika z listy, a rekomendacja przelicza się bez niego.
- **Change ID:** `usuwanie-kupionych-produktow`
- **PRD refs:** FR-006, US-01 (kryteria akceptacji)
- **Prerequisites:** S-04
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:** —
- **Risk:** domyka pełen cykl zakupowy z §Success Criteria i jest jedynym miejscem, gdzie kryterium akceptacji „usunięty produkt wpływa na przeliczenie rekomendacji" da się zweryfikować — dlatego sekwencjonowane po S-04, a nie razem z dodawaniem produktów. Usunięcie jest trwałe (§Non-Goals: brak historii zakupów), więc pomyłkowe kliknięcie oznacza bezpowrotną utratę pozycji.
- **Status:** proposed

### S-06: Edycja i usuwanie sklepów

- **Outcome:** użytkownik poprawia zestaw kategorii przypisanych sklepowi oraz usuwa sklep, który przestał być potrzebny.
- **Change ID:** `edycja-i-usuwanie-sklepow`
- **PRD refs:** brak — żadne FR nie opisuje edycji ani usuwania sklepu. Sąsiaduje z FR-008, ale go nie realizuje. Rozstrzygnięcie odłożone do planowania tej pozycji (Otwarte pytanie 3).
- **Prerequisites:** S-03
- **Parallel with:** F-01, S-04, S-05
- **Blockers:** —
- **Unknowns:**
  - Żadne FR nie opisuje edycji ani usuwania sklepu — czy PRD dostaje nowe wymaganie (np. FR-009), czy pozycja zostaje jako świadome rozszerzenie zakresu przez właściciela? — Owner: twórca. Block: no.
  - Co ma się stać z rekomendacją i z danymi, gdy usuwany sklep jest właśnie tym rekomendowanym? — Owner: twórca. Block: no.
- **Risk:** dodany przez właściciela podczas planowania S-03, gdy okazało się, że pierwsze przypisanie kategorii prawie na pewno będzie niepełne, a bez edycji jedyną drogą naprawy jest zmiana w bazie. Świadomie ustawione **po** gwieździe przewodniej: rekomendacja z S-04 działa na danych tylko dopisywanych, więc utrzymanie może poczekać. Główne ryzyko to zakres — „edycja sklepu" łatwo urasta do zarządzania kategoriami, którego §Poza zakresem nie przewiduje.
- **Status:** proposed

## Backlog Handoff

| Roadmap ID | Change ID                       | Suggested issue title                                          | Ready for `/10x-plan` | Notes                                    |
| ---------- | ------------------------------- | -------------------------------------------------------------- | --------------------- | ---------------------------------------- |
| F-01       | `trwalosc-danych-produkcyjnych` | Potwierdzić i opisać ścieżkę odtworzenia produkcyjnej bazy      | yes                   | Może biec równolegle do całego strumienia A |
| S-01       | `logowanie-i-prywatny-dostep`   | Logowanie loginem i hasłem, odcięcie niezalogowanych            | done                  | Zarchiwizowane 2026-09-09                |
| S-02       | `wspolna-lista-produktow`       | Dodawanie produktów i wspólna lista na stronie głównej          | done                  | Zarchiwizowane 2026-09-09                |
| S-03       | `konfiguracja-sklepow`          | Dodawanie sklepów i przypisywanie im kategorii                  | done                  | Zarchiwizowane 2026-09-10                |
| S-04       | `rekomendacja-sklepu`           | Rekomendacja sklepu przeliczana po każdej zmianie listy         | no                    | Gwiazda przewodnia; czeka na S-03        |
| S-05       | `usuwanie-kupionych-produktow`  | Usuwanie kupionych produktów i przeliczenie rekomendacji        | no                    | Czeka na S-04                            |
| S-06       | `edycja-i-usuwanie-sklepow`     | Edycja kategorii sklepu i usuwanie sklepu                       | no                    | Czeka na S-03; brak pokrycia w FR — patrz Otwarte pytanie 3 |

## Open Roadmap Questions

1. ~~**Czy kategoria produktu to wybór ze stałej, wcześniej zdefiniowanej listy, czy dowolny tekst wpisywany przez użytkownika?**~~ — **Rozstrzygnięte 2026-09-09 przy planowaniu S-02**: osobna tabela kategorii z możliwością dopisania nowej z formularza, a porównanie nazw idzie przez `App\Support\NameComparison` (bez wielkości liter i spacji po bokach, polskie znaki znaczące). Szczegóły w `context/archive/2026-09-09-wspolna-lista-produktow/plan.md`.
2. **Kiedy wdrożyć panel administracyjny do zarządzania kontami?** — Owner: twórca. Block: roadmap-wide (nic obecnie nie blokuje). FR-002/FR-003 są odłożone jako nice-to-have; na MVP konta zakładane wstępnie poza aplikacją. Przeniesione z §Open Questions PRD.
3. **Czy edycja i usuwanie sklepów dostają własne wymaganie w PRD, czy zostają rozszerzeniem zakresu poza PRD?** — Owner: twórca. Block: S-06 (nie blokuje planowania, ale S-06 jest jedyną pozycją roadmapy bez czystego odwołania do FR). §Wymagania funkcjonalne mają wyłącznie FR-008 „dodać sklep i przypisać mu kategorie" — nic o poprawianiu ani kasowaniu. Rozstrzygnięcie: albo PRD dostaje FR-009/FR-010 i podbicie `version`, albo S-06 zostaje udokumentowanym rozszerzeniem zakresu przez właściciela.

## Parked

- **Obsługa wielu rodzin (multitenancy)** — Why parked: §Non-Goals PRD. Skala to 3–5 osób w jednej rodzinie.
- **Automatyczne kategoryzowanie produktów** — Why parked: §Non-Goals PRD. Na MVP kategorie nadawane ręcznie; AI/automat to potencjalna przyszłość.
- **Historia zakupów** — Why parked: §Non-Goals PRD. Usunięty produkt znika bezpowrotnie, bez archiwum.
- **Złożony system decyzyjny (oceny sklepów, jakość per produkt, listy wykluczeń)** — Why parked: §Non-Goals PRD. Reguła „więcej pokrytych kategorii = lepszy sklep" wystarcza na MVP.
- **Panel administracyjny do tworzenia i usuwania kont (FR-002, FR-003)** — Why parked: oba oznaczone jako nice-to-have; konta zakładane wstępnie poza aplikacją. Powiązane z Open Roadmap Question 2.
- **Zewnętrzne śledzenie błędów (np. Sentry)** — Why parked: logi produkcyjne są już dostępne w panelu Render przez supervisor/stdout, co przy 3–5 użytkownikach pokrywa potrzebę diagnostyki. Dodać, gdy błąd na produkcji przejdzie niezauważony.

## Done

(Pusta przy pierwszej generacji. `/10x-archive` dopisuje tu pozycję i przestawia jej `Status` na `done`, gdy archiwizowana zmiana ma pasujący `Change ID`.)

- **S-01: użytkownik loguje się loginem i hasłem, a niezalogowany zostaje przekierowany na stronę logowania — strona główna przestaje być publiczna.** — Archived 2026-09-09 → `context/archive/2026-08-31-logowanie-i-prywatny-dostep/`. Lesson: —.
- **S-02: użytkownik dodaje produkt z nazwą i kategorią oraz widzi na stronie głównej wspólną listę produktów całej rodziny.** — Archived 2026-09-09 → `context/archive/2026-09-09-wspolna-lista-produktow/`. Lesson: —.
- **S-03: użytkownik dodaje sklep i przypisuje mu kategorie produktów w osobnym widoku.** — Archived 2026-09-10 → `context/archive/2026-09-09-konfiguracja-sklepow/`. Lesson: —.
