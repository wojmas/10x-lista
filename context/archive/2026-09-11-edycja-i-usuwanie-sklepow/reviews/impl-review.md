<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Edycja i usuwanie sklepów (S-06)

- **Plan**: `context/changes/edycja-i-usuwanie-sklepow/plan.md`
- **Scope**: Phases 1–3 of 3 (full plan)
- **Date**: 2026-09-11
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations (wszystkie naprawione w triage 2026-09-11)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated criteria re-run during this review: `composer test` (82 passed, 303 assertions), `--filter=EditShopTest` (7), `--filter=RemoveShopTest` (6), `--filter='AddShopTest|ShopListTest|ShopRecommendationTest|HomeRecommendationTest'` (22), `pint --test` (72 files, clean). Manual items 1.5–2.6 were verified by a throwaway Playwright script driving the running app at 390×844 rather than by the owner; that substitution is recorded in the plan's "Odstępstwa od planu" section.

Diff scope (`46dc18d~1..HEAD`) touches exactly the files the plan named — no unplanned files, nothing planned left unimplemented.

## Findings

### F1 — Trzy docblocki nadal mówią, że S-06 nie istnieje

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Models/Shop.php:46, app/Http/Controllers/ShopController.php:49, app/Support/ShopRecommendation.php:82
- **Detail**: `Shop::inPrecedenceOrder()` niesie zdanie „Nothing renames a shop until S-06" jako część uzasadnienia, dlaczego rozbieżność kolejności w Postgresie jest utajona — a od tej zmiany rename istnieje, więc przesłanka wygasła. Realnym zabezpieczeniem jest teraz `ORDER BY id` w obu konsumentach i to powinno tam stać zamiast nieaktualnej obietnicy. Analogicznie `ShopController::store()` twierdzi, że „no screen in the MVP can repair it (that is S-06)", a `ShopRecommendation` mówi o S-06 w czasie przyszłym. Następny czytelnik zaufa nieprawdziwym zdaniom.
- **Fix**: Zaktualizować trzy docblocki: w `Shop` zastąpić „nic nie zmienia nazwy do S-06" stwierdzeniem, że rename już istnieje i że gwarancją jest scope `inPrecedenceOrder()` używany przez obu konsumentów; w `ShopController::store()` wskazać ekran edycji jako drogę naprawy; w `ShopRecommendation` zmienić czas przyszły na teraźniejszy.
  - Strength: Usuwa jedyne miejsca w kodzie, które po tej zmianie kłamią; koszt to trzy komentarze.
  - Tradeoff: Żaden poza czasem edycji.
  - Confidence: HIGH — treść wszystkich trzech zdań sprawdzona w diffie.
  - Blind spot: Brak.
- **Decision**: Fixed — trzy docblocki zaktualizowane (Shop, ShopController::store, ShopRecommendation)

### F2 — Powtarzalne „Edytuj" i „Usuń" bez nazwy sklepu w nazwie dostępnej

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (dostępność)
- **Location**: resources/views/shops/index.blade.php:30-33, resources/views/shops/index.blade.php:54-57
- **Detail**: Lista sklepów renderuje po jednym linku „Edytuj" i przycisku „Usuń" na wiersz. Czytnik ekranu odczytujący listę linków/przycisków usłyszy „Edytuj, Edytuj, Edytuj" bez informacji, którego sklepu dotyczą (WCAG 2.4.4). Przy produktach ten problem nie występował, bo akcja była jedna na wiersz i tekst niósł intencję. PRD wymaga działania na telefonie, gdzie nawigacja czytnikiem po liście akcji jest typowa.
- **Fix**: Dodać `aria-label="Edytuj sklep {{ $shop->name }}"` na linku i `aria-label="Usuń sklep {{ $shop->name }}"` na przycisku; widoczny tekst zostaje bez zmian.
  - Strength: Dwie linijki, zero zmian wizualnych, testy oparte na `getByRole({name})` nadal przechodzą, bo nazwa dostępna zawiera widoczny tekst.
  - Tradeoff: Nazwa sklepu pojawia się w markupie trzeci raz w wierszu.
  - Confidence: MED — reguła WCAG jednoznaczna, ale repo nie ma dziś żadnego `aria-label`, więc to pierwszy taki wzorzec w projekcie.
  - Blind spot: Nie sprawdzono, czy `getByRole('button', { name: 'Usuń' })` w `RemoveShopTest` (asercja na treści HTML) nie wymagałby korekty — test sprawdza tylko obecność napisu, więc powinien przejść.
- **Decision**: Fixed — aria-label na linku „Edytuj" i przycisku „Usuń"

### F3 — Trasa DELETE bez ograniczenia numerycznego kończy się 500 zamiast 404

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (niezawodność)
- **Location**: routes/web.php:29 (shops.destroy), routes/web.php:17 (products.destroy — stan zastany)
- **Detail**: Parametr `{id}` nie ma ograniczenia, a metoda kontrolera deklaruje `int $id`. Żądanie `DELETE /shops/abc` trafia do kontrolera z łańcuchem nieliczbowym i kończy się `TypeError` (500), zamiast 404. Ścieżka wymaga ręcznie zmienionego URL przez zalogowanego członka, więc to nie jest incydent bezpieczeństwa — ale odziedziczony po `products.destroy` wzorzec powiela ten sam ostry kant.
- **Fix**: Dopisać `->whereNumber('id')` do obu tras kasowania; nieliczbowy id przestaje w ogóle pasować i wraca 404.
- **Decision**: Fixed — whereNumber('id') na shops.destroy i products.destroy

### F4 — UpdateShopRequest nie powtarza udokumentowanego wyścigu o unikalną nazwę

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Requests/UpdateShopRequest.php:74-99
- **Detail**: `StoreShopRequest` kończy docblock akapitem o tym, że sprawdzenie unikalności nie jest atomowe, że przy równoczesnym zapisie ratuje unikalny indeks i że 500 zamiast komunikatu walidacji jest świadomie zaakceptowane przy tej skali. `UpdateShopRequest` ma dokładnie ten sam wyścig (dwie osoby zmieniające nazwy na tę samą) i nie mówi o nim nic — czytelnik może uznać brak akapitu za przeoczenie albo za inną gwarancję.
- **Fix**: Dopisać do docblocka `notTakenByAnotherShop()` jedno zdanie odsyłające do akapitu w `StoreShopRequest` i potwierdzające ten sam kompromis.
- **Decision**: Fixed — docblock odsyła do akapitu w StoreShopRequest

### F5 — Komentarz Blade wewnątrz znacznika `<form>`

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/views/shops/index.blade.php:44-45
- **Detail**: Komentarz wyjaśniający `@js` siedzi między atrybutami otwierającego znacznika `<form>`. Blade usuwa go przy kompilacji, więc HTML wychodzi poprawny (potwierdzone testami i przebiegiem w przeglądarce), ale konstrukcja jest krucha: zamiana na komentarz HTML albo przeniesienie fragmentu do innego kontekstu rozbije znacznik. Nigdzie indziej w tym repo komentarz nie stoi w środku znacznika.
- **Fix**: Przenieść komentarz nad znacznik `<form>`, do bloku komentarza, który już tam stoi.
- **Decision**: Fixed — komentarz przeniesiony nad znacznik <form>

### F6 — Kontrakt „404 przy nieistniejącym sklepie" nie jest niczym przypięty

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria (pokrycie testami)
- **Location**: routes/web.php:22-26, tests/Feature/EditShopTest.php
- **Detail**: Komentarz przy trasach `shops.edit`/`shops.update` uzasadnia route model binding tym, że edycja nieistniejącego sklepu ma dawać 404 — i to właśnie odróżnia te trasy od `shops.destroy`. Żaden test tego nie sprawdza, więc zamiana `{shop}` na `{id}` przy przyszłym refaktorze przeszłaby bez czerwonego testu. Odwrotny kontrakt (brak 404 przy kasowaniu) jest przypięty w `RemoveShopTest`.
- **Fix**: Dodać do `EditShopTest` jeden przypadek: `GET /shops/999999/edit` zwraca 404 dla zalogowanego członka.
- **Decision**: Fixed — EditShopTest pinuje 404 na nieistniejącym sklepie
