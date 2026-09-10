# Kontrakty rekomendacji przed S-04 — Implementation Plan

## Overview

Faza 2 wdrożenia z `context/foundation/test-plan.md` §3. Zamierzeniem fazy było
zapinowanie dwóch kontraktów, na których stanie S-04: **kolejności rozstrzygania
remisu sklepów** (ryzyko #1) i **tożsamości przypisania kategorii** (ryzyko #3).

Research (`context/changes/testing-kontrakty-rekomendacji/research.md`) wykonał
obie ścieżki sondą na SQLite i na Postgresie i ustalił, że **tylko jeden z tych
kontraktów da się dziś uczciwie zapinować**:

- **Pokrycie sklepu** — unikalność pary sklep–kategoria i zwijanie kolizji
  „checkbox + wpisana nazwa" trzymają na obu silnikach, nic tego nie pilnuje.
  Test upada dziś, na sterowniku, na którym biegnie zestaw. **W zakresie.**
- **Precedencja sklepów** — rozbieżność jest odtwarzalna **wyłącznie na
  Postgresie**. Test napisany na SQLite przechodzi po usunięciu `orderBy('id')`.
  **Poza zakresem — przechodzi do Fazy 4.**

Faza dowozi więc trzy rzeczy: dwa testy pokrycia, kasację testu przechodzącego z
niewłaściwego powodu, i zapisanie odroczenia tak, żeby plan przestał twierdzić,
że Faza 2 pokrywa ryzyko #1.

## Current State Analysis

Stan wyjściowy: **53 testy, zielone** na commicie `a570c06` (SQLite `:memory:`,
`phpunit.xml`).

Research nie znalazł błędu w kodzie produkcyjnym. Kod zachowuje się zgodnie z
PRD na obu silnikach. Luki są w zestawie testów i w sposobie, w jaki plan opisuje
własne pokrycie.

| Miejsce | Co robi | Czego nie dowodzi |
|---|---|---|
| `tests/Feature/ShopListTest.php:45-58` | tworzy `Zeta`(id 1), `Alfa`(id 2), `assertSeeInOrder(['Zeta','Alfa'])`; docblock deklaruje pinowanie precedencji S-04 | **niczego o precedencji** — przechodzi po usunięciu `orderBy('id')` z kontrolera, na SQLite i na Postgresie. `select * from shops` bez `ORDER BY` zwraca na świeżej tabeli kolejność wstawienia, więc asercja nie ma jak upaść |
| `database/migrations/2026_09_10_100100_create_category_shop_table.php:23` | `unique(['shop_id','category_id'])`, docblock nazywa to wprost inwariantem danych dla S-04 | inwariant żyje wyłącznie w migracji — usunięcie tej linii w przyszłej migracji nie łamie nic w zestawie |
| `app/Http/Controllers/ShopController.php:58-64` | `sync()` zwija powtórzone id; formularz sklepu dopuszcza checkbox i wpisaną nazwę wskazujące tę samą kategorię | że kolizja daje **jedno** przypisanie. `AddShopTest:68` pinuje osobno wpisaną nazwę różniącą się wielkością liter, nigdy razem z checkboxem |
| `app/Http/Requests/StoreShopRequest.php:34-36` | brak `prohibits:new_category`, przeciwnie niż `StoreProductRequest:29` | że asymetria jest decyzją — nigdzie nie jest zapisana, a to ona tworzy ścieżkę podwójnego policzenia |

Trzy fakty o kodzie, które kształtują tę fazę:

- **Kontrakt precedencji istnieje w jednym miejscu i nie jest wielokrotnego
  użytku** — `ShopController::index():26-29`, inline. Nic nie zmusza S-04 do tej
  samej klauzuli; `Shop::all()` albo `withCount(...)->orderByDesc(...)` cicho
  go porzuci. Decyzja właściciela: **zostawić inline**, nie wynosić do scope'u —
  faza nie zmienia kodu produkcyjnego poza docblockami.
- **Ryzyko #3 jest w większości historyczne.** Trzeci pisarz kategorii (seeder z
  przeglądu S-02) został skonsolidowany w S-03. Dziś pisarzy produkcyjnych jest
  dwóch — `CategoryResolver::resolve()` i `CategorySeeder` — obaj idą przez
  `NameComparison`, obaj mają test. §2 planu nazywa dopisywanie testów do
  zamkniętej luki drugim antywzorcem tego ryzyka.
- **Wskazówka §6.6 o `Product::latest()` nie dotyczy ryzyka #1.** Rekomendacja
  liczy różne kategorie z listy, nie kolejność produktów. Backport tej korekty
  jest już w `test-plan.md`.

## Desired End State

Po tej fazie zestaw testów odpowiada twierdząco na dwa pytania, na które dziś nie
odpowiada, i **przestaje udawać**, że odpowiada na trzecie:

1. Czy dwukrotne przypisanie tej samej kategorii do tego samego sklepu jest
   niemożliwe na poziomie danych?
2. Czy jedno zgłoszenie formularza wskazujące tę samą kategorię dwiema drogami
   naraz daje jedno przypisanie, a nie dwa?
3. Czy sklepy wracają w kolejności dodania? — **nie odpowiada, i mówi to
   wprost.** Odpowiedź należy do Fazy 4, na Postgresie.

Weryfikacja: `composer test` zielone; oba nowe testy dają się obalić przez
tymczasowe cofnięcie reguły, którą pinują; `grep` po zestawie nie znajduje
żadnego innego testu twierdzącego, że pilnuje precedencji sklepów;
`test-plan.md` §3 przypisuje ryzyko #1 Fazie 4, a §6.3 nie zawiera `TBD`.

### Key Discoveries:

- `tests/Feature/ShopListTest.php:45` — test przechodzący z niewłaściwego
  powodu. Próba obalenia (usunięcie `orderBy('id')`) dała `OK (4 tests, 11
  assertions)` na **obu** silnikach.
- Rozbieżność precedencji na Postgresie jest odtwarzalna, ale utajona: pojawia
  się po UPDATE kolumny indeksowanej (`name` ma unique index → non-HOT update →
  nowa krotka na końcu sterty). Żaden dzisiejszy ekran nie zmienia nazwy sklepu
  — wprowadzi to dopiero S-06 (`context/foundation/roadmap.md:147`).
- „Nieuporządkowane" nie ma jednej kolejności nawet w obrębie jednego silnika:
  na SQLite `Shop::query()->pluck('name')` zwraca kolejność **alfabetyczną**
  (unikalny indeks na `name` jako indeks pokrywający), a `select *` — kolejność
  wstawienia.
- `app/Http/Controllers/ShopController.php:58-64` — `sync()` zwija duplikaty
  niezależnie od ograniczenia bazy; sonda potwierdziła
  `PROBE checkbox+typed-same: categories=1 pivot=1 shopcats=1` na obu silnikach.
- `tests/Feature/AddShopTest.php:15-22` — konwencja tego pliku: id jako łańcuchy,
  `followRedirects()` zamiast samego `assertRedirect`, asercja na wyrenderowaną
  treść. Nowy test formularza trzyma tę konwencję.
- `tests/Feature/AddShopTest.php:113-121` — komentarz o `assertSessionHasErrors()`
  postarzającym dane flash (ustalenie F4 przeglądu S-03). Nie dotyczy nowych
  testów, ale plik już zna tę pułapkę.
- Sklep o zerowym pokryciu jest w bazie legalny (`database/factories/ShopFactory.php:14-17`),
  odrzuca go dopiero `StoreShopRequest:34`. To **fakt wejściowy dla S-04**, nie
  luka do zamknięcia dziś — trafia do §6.3, nie do testu.

## What We're NOT Doing

- **Nie piszemy testu remisu sklepów.** Nie da się go dziś obalić na sterowniku
  zestawu, a `test-plan.md` §2 nazywa test przechodzący na silniku, na którym
  rozbieżność jest niewidoczna, ostrzejszym antywzorcem tego ryzyka. Przechodzi
  do Fazy 4.
- **Nie konfigurujemy przebiegu na Postgresie.** To całość Fazy 4, nie jej
  wycinek. Decyzja właściciela: odroczyć, bo do S-06 rozbieżność jest
  nieosiągalna z UI.
- **Nie wynosimy precedencji do scope'u modelu.** `Shop::inPrecedenceOrder()`
  dałby jedno miejsce do zapinowania, ale to zmiana kodu produkcyjnego bez testu,
  który by ją uzasadniał — a test należy do Fazy 4. Wtedy warto do tego wrócić.
- **Nie dodajemy `prohibits:new_category` do formularza sklepu.** Asymetria
  wobec formularza produktu jest zamierzona (sklep ma wiele kategorii, produkt
  jedną) i to właśnie tę ścieżkę mamy dowieść. Zapisujemy ją w docblocku.
- **Nie dopisujemy testów regresji dla `CategoryResolver` ani `CategorySeeder`.**
  Obaj mają test; konsolidacja jest zamknięta.
- **Nie testujemy sklepu o zerowym pokryciu.** Fakt wejściowy dla S-04, nie luka.
- **Nie piszemy testów samej reguły rekomendacji.** Reguła nie istnieje —
  należy do planu S-04.

## Implementation Approach

Trzy fazy, w kolejności rosnącego zasięgu: najpierw dwa testy, które upadają
dziś (Faza 1), potem kasacja testu, który nie upada nigdy (Faza 2), na końcu
zapisanie granicy dowodu w planie testów (Faza 3).

Kolejność ma znaczenie: Faza 2 zabiera z zestawu asercję i **nie oddaje nic w
zamian** — jest uzasadniona wyłącznie tym, że Faza 3 przenosi ten dowód do Fazy 4
w planie. Gdyby Faza 3 nie doszła do skutku, kasacja z Fazy 2 zostawiłaby lukę
bez właściciela.

## Critical Implementation Details

**Sekwencja kasacji.** Faza 2 usuwa `test_shops_are_listed_in_ascending_id_order`
i przez to zestaw przestaje pilnować kolejności sklepów **czymkolwiek** — także
sortowania po nazwie albo po świeżości, które stary test jednak łapał. To
świadomy koszt decyzji „nie trzymamy zielonych testów, które kłamią". Docblock w
`ShopController::index()` i wiersz Fazy 4 w §3 są jedynym, co po tej fazie
utrzymuje ten kontrakt przy życiu — obie zmiany są obowiązkowe, nie kosmetyczne.

## Phase 1: Kontrakty pokrycia sklepu

### Overview

Dwa testy zamykające jedyną pozostałą lukę ryzyka #3 i pinujące inwariant, który
migracja nazywa warunkiem poprawności S-04. Oba upadają dziś na SQLite, więc
dają realny sygnał bez nowej infrastruktury.

### Changes Required:

#### 1. Inwariant unikalności przypisania

**File**: `tests/Feature/ShopCategoryAssignmentTest.php` (nowy)

**Intent**: Zapinować inwariant `unique(['shop_id','category_id'])` w zestawie, a
nie tylko w docblocku migracji. Bez tego usunięcie ograniczenia w przyszłej
migracji nie łamie niczego, a S-04 zaczyna liczyć tę samą kategorię dwukrotnie i
poleca zły sklep, po cichu.

**Contract**: `Tests\TestCase` + `RefreshDatabase`, bez HTTP — konwencja z §6.1
planu testów („Unit = bez kontenera Laravela"; test potrzebujący bazy idzie do
`tests/Feature/`, jak `CategoryResolverTest`). Dwukrotne `attach()` tej samej
pary sklep–kategoria musi rzucić
`Illuminate\Database\UniqueConstraintViolationException`. Nazwa metody opisuje,
co to znaczy dla rodziny, nie jak działa baza — np.
`test_the_same_category_cannot_be_assigned_to_a_shop_twice`.

#### 2. Kolizja checkboxa i wpisanej nazwy

**File**: `tests/Feature/AddShopTest.php`

**Intent**: Dowieść, że gdy jedno zgłoszenie formularza wskaże tę samą kategorię
dwiema drogami — zaznaczonym checkboxem i wpisaną nazwą w innym zapisie —
sklep pokrywa ją **raz**. To jedyna nietestowana ścieżka ryzyka #3 według
researchu.

**Contract**: `POST /shops` z ładunkiem w kształcie formularza (id jako łańcuch,
zgodnie z konwencją pliku): `category_ids => [(string) $dairy->id]` **oraz**
`new_category => 'NABIAŁ'`, przy istniejącej kategorii `Nabiał`. Po podążeniu za
przekierowaniem: jedna kategoria w bazie, dokładnie jedno przypisanie do sklepu,
`Nabiał` widoczne na wyrenderowanej liście dokładnie raz. Test uzupełnia
`test_a_typed_in_category_differing_only_in_case_reuses_the_existing_one`, które
pinuje tę samą regułę **bez** checkboxa.

#### 3. Zapisanie asymetrii formularzy

**File**: `app/Http/Requests/StoreShopRequest.php`

**Intent**: Zapisać w docblocku `rules()`, że brak `prohibits:new_category` jest
decyzją, a nie przeoczeniem — sklep ma wiele kategorii, więc zaznaczenie kilku i
dopisanie jednej nowej to normalne użycie, inaczej niż przy produkcie
(`StoreProductRequest:29`). Bez tego ktoś „naprawi" asymetrię i skasuje ścieżkę,
którą właśnie zapinowaliśmy.

**Contract**: sam docblock — żadnej zmiany reguł walidacji. Ma nazwać
konsekwencję (dwie drogi mogą wskazać tę samą kategorię) i wskazać, co ją
domyka (`sync()` w kontrolerze plus unikalny indeks pivota) oraz który test to
pinuje.

### Success Criteria:

#### Automated Verification:

- Zestaw funkcjonalny zielony: `docker compose exec app php vendor/bin/phpunit --testsuite Feature`
- Pełny zestaw zielony i liczniejszy niż 53 testy: `composer test`
- Formatowanie: `docker compose exec app php vendor/bin/pint --test`
- Migracje na czystej bazie: `docker compose exec app php artisan migrate:fresh`

#### Manual Verification:

- Próba obalenia testu unikalności: tymczasowe usunięcie `unique(['shop_id','category_id'])` z migracji wywala dokładnie ten test i żaden inny
- Próba obalenia testu kolizji: tymczasowa zamiana `sync()` na `syncWithoutDetaching()` z powtórzonym id albo na `attach()` wywala dokładnie ten test
- Docblock w `StoreShopRequest` czyta się jako decyzja o rodzinie („sklep ma wiele kategorii"), nie jako opis implementacji

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na
potwierdzenie weryfikacji ręcznej przed Fazą 2.

---

## Phase 2: Usunięcie testu przechodzącego z niewłaściwego powodu

### Overview

`ShopListTest::test_shops_are_listed_in_ascending_id_order` twierdzi w docblocku,
że pilnuje precedencji S-04, a przechodzi po usunięciu reguły, którą rzekomo
pinuje. Zielony test, który kłamie, jest gorszy niż brak testu: autor S-04
przeczyta docblock i uzna kontrakt za zabezpieczony.

Kod produkcyjny **zostaje bez zmian** — `orderBy('id')` w kontrolerze jest
poprawny i musi tam pozostać.

### Changes Required:

#### 1. Kasacja testu

**File**: `tests/Feature/ShopListTest.php`

**Intent**: Usunąć metodę `test_shops_are_listed_in_ascending_id_order` wraz z
jej docblockiem. Zostają trzy pozostałe testy pliku (lista ze sklepem i
kategoriami, stan pusty, przekierowanie gościa).

**Contract**: po kasacji plik nie może zawierać żadnego odwołania do precedencji
ani do S-04; `assertLessThan` na id znika razem z metodą. Importy sprawdzić —
`Category`, `Shop`, `User` są używane przez pozostałe testy.

#### 2. Kontrakt precedencji jako dług nazwany wprost

**File**: `app/Http/Controllers/ShopController.php`

**Intent**: Przepisać docblock `index()` tak, żeby mówił nie tylko *czym*
`orderBy('id')` jest, ale też że **żaden test tego dziś nie pilnuje i dlaczego** —
rozbieżność jest widoczna wyłącznie na Postgresie, a zestaw biegnie na SQLite.
To jedyne miejsce w kodzie, gdzie autor S-04 na ten kontrakt trafi.

**Contract**: sam docblock. Ma zawierać trzy rzeczy: klauzula jest kontraktem
precedencji z §Business Logic; nie jest zapinowana i nie da się jej zapinować na
sterowniku dzisiejszego zestawu; dowód należy do §3 Fazy 4 `test-plan.md`.
Odesłanie do fazy planu, nie do numeru linii — linie się przesuwają.

### Success Criteria:

#### Automated Verification:

- Pełny zestaw zielony, o jeden test mniej niż po Fazie 1: `composer test`
- Formatowanie: `docker compose exec app php vendor/bin/pint --test`

#### Manual Verification:

- `grep -rn "precedenc\|ascending id\|added first" tests/` nie znajduje żadnego innego testu twierdzącego, że pilnuje kolejności sklepów
- Usunięcie `orderBy('id')` z kontrolera nadal nie wywala zestawu — i to jest teraz stan **udokumentowany**, a nie ukryty; przywrócić klauzulę po sprawdzeniu
- Docblock `index()` czyta się jako ostrzeżenie dla autora S-04, nie jako opis kodu

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na
potwierdzenie weryfikacji ręcznej przed Fazą 3.

---

## Phase 3: Domknięcie planu testów

### Overview

Faza 2 planu wdrożenia deklaruje w §3, że pokrywa ryzyka #1 i #3. Po tej zmianie
pokrywa #3 i **połowę** #1 (podwójne policzenie), a połowa „remis" przechodzi do
Fazy 4. Bez tej korekty plan po zamknięciu fazy kłamałby o własnym pokryciu —
dokładnie ta klasa błędu, którą Faza 2 właśnie usunęła z zestawu testów.

### Changes Required:

#### 1. Przepisanie wiersza Fazy 2 i Fazy 4

**File**: `context/foundation/test-plan.md` (§3 Phased Rollout)

**Intent**: Tabela ma odzwierciedlać to, co faza realnie dowiodła, a odroczona
połowa ryzyka #1 ma dostać właściciela zamiast zniknąć.

**Contract**: w wierszu 2 — `Goal` przepisany na tożsamość przypisania kategorii
(bez obietnicy remisu), `Risks covered` na `#3, #1 (część)`, `Status` na
`complete`. W wierszu 4 — `Risks covered` na `#6, #1 (remis)`, `Goal`
rozszerzony o rozstrzygnięcie remisu sklepów. W „Uzasadnieniu kolejności": akapit
Fazy 2 dostaje zdanie o odroczeniu i jego powodzie, akapit Fazy 4 — zdanie, że
faza przestała być wyłącznie konfiguracją przebiegu i niesie teraz kontrakt,
którego nie da się dowieść gdzie indziej.

#### 2. Wypełnienie §6.3

**File**: `context/foundation/test-plan.md` (§6.3)

**Intent**: Zdjąć `TBD` i dać autorowi S-04 wzorzec na test kontraktu razem z
granicą tego, co da się dowieść dzisiejszym zestawem. To sekcja, do której S-04
sięgnie przed napisaniem pierwszej asercji.

**Contract**: struktura jak w §6.1/§6.2 (Gdzie / Klasa bazowa / Test referencyjny
/ Przebieg + reguły kupione konkretną pomyłką). Reguły do zapisania: kontrakt
przyszłego slice'u pinuje się na warstwie, na której go czyta jego konsument;
próba obalenia jest obowiązkowa, bo dwa testy w tym projekcie przechodziły z
niewłaściwego powodu; kontraktu zależnego od kolejności wierszy **nie da się**
dowieść na SQLite i test, który to udaje, jest gorszy niż jego brak; sklep o
zerowym pokryciu jest w bazie legalny i S-04 musi go obsłużyć, mimo że formularz
takiego nie utworzy.

#### 3. Notatka fazy w §6.6

**File**: `context/foundation/test-plan.md` (§6.6)

**Intent**: Dopisać, czego faza nauczyła — konwencją pozostałych wpisów: dwie-trzy
linie, każda o czymś, co kosztowało pracę.

**Contract**: kandydaci do zapisania — sonda na dwóch silnikach jako warunek
uczciwej oceny ryzyka zależnego od bazy; „nieuporządkowane" nie ma jednej
kolejności nawet w obrębie jednego silnika (zależy od wybranych kolumn);
kasacja zielonego testu jako właściwy ruch, gdy testu nie da się obalić;
odroczenie z właścicielem bije test z adnotacją usprawiedliwiającą.

### Success Criteria:

#### Automated Verification:

- Brak `TBD` przy §6.3: `grep -n "6.3" -A3 context/foundation/test-plan.md`
- Wiersz 2 tabeli §3 ma `Status: complete`, wiersz 4 wymienia `#1`
- Pełny zestaw dalej zielony: `composer test`

#### Manual Verification:

- §6.3 czyta się jako instrukcja dla kogoś z zewnątrz, kto zaczyna S-04 i nie zna historii tej fazy
- Uzasadnienie kolejności w §3 wyjaśnia odroczenie, a nie tylko je odnotowuje
- Wpis §6.6 trzyma konwencję wpisu Fazy 1 (co kosztowało pracę, nie co zrobiliśmy)

---

## Testing Strategy

### Unit Tests:

Brak nowych. Reguła równości nazw (`NameComparison`) jest pokryta z Fazy 1, a
kontrakty tej fazy potrzebują bazy — zgodnie z §6.1 idą do `tests/Feature/`.

### Integration Tests:

- **Bez HTTP**: dwukrotne przypisanie tej samej pary sklep–kategoria rzuca
  `UniqueConstraintViolationException`.
- **Po HTTP**: zgłoszenie formularza wskazujące tę samą kategorię checkboxem i
  wpisaną nazwą daje jedno przypisanie i jedną kategorię w bazie.

### Manual Testing Steps:

1. Próba obalenia testu unikalności — usunąć `unique(['shop_id','category_id'])`
   z migracji, `migrate:fresh`, uruchomić zestaw: upada dokładnie jeden test.
   Przywrócić.
2. Próba obalenia testu kolizji — zamienić `sync()` na `attach()` w
   `ShopController::store()`, uruchomić zestaw: upada dokładnie jeden test.
   Przywrócić.
3. Kontrola po Fazie 2 — usunąć `orderBy('id')` z `ShopController::index()`,
   uruchomić zestaw: **zielony**, i to jest oczekiwane. Potwierdza, że kasacja
   testu nie zabrała żadnego realnego dowodu. Przywrócić.
4. W przeglądarce: dodać sklep z zaznaczonym „Nabiał" i wpisanym „NABIAŁ" —
   sklep pokazuje „Nabiał" raz, bez błędu i bez duplikatu.

## Migration Notes

Brak zmian schematu. Migracje dotykamy wyłącznie tymczasowo, w próbie obalenia z
kroku 1 weryfikacji ręcznej — z obowiązkowym przywróceniem i `migrate:fresh`
przed dalszą pracą.

## References

- Research: `context/changes/testing-kontrakty-rekomendacji/research.md`
- Plan testów: `context/foundation/test-plan.md` §2 (ryzyka #1, #3, #6), §3 (Fazy 2 i 4), §6.1–§6.3
- Wzorzec fazy testowej: `context/archive/2026-09-10-testing-lista-i-duplikaty/plan.md`
- Precedens testu świadomie niewykonalnego na sterowniku zestawu: `tests/Feature/CategoryResolverTest.php:28`
- Źródło kontraktu precedencji: `database/migrations/2026_09_10_100000_create_shops_table.php:18-22`
- Zależność S-04 od stabilnej kolejności sklepów: `context/foundation/roadmap.md:129`

## Implementation Deviations

Odstępstwa od planu podjęte w trakcie implementacji, zapisane tu zgodnie z
`context/foundation/lessons.md`.

- **Faza 1 — stan wyjściowy to 52 testy, nie 53.** Plan (§Current State Analysis
  i kryterium 1.2) podał 53; faktyczny przebieg na `a570c06` daje
  `OK (52 tests, 162 assertions)`. Kryterium „liczniejszy niż 53" zostaje
  spełnione (54 po fazie), ale liczba wyjściowa w planie była o jeden za wysoka.
- **Faza 1 — test unikalności nie asercjuje stanu po wyjątku.** Plan opisywał
  wyłącznie rzucenie `UniqueConstraintViolationException`. Rozważona i odrzucona
  została asercja „po nieudanym `attach()` w pivocie dalej jest jeden wiersz":
  na Postgresie nieudana instrukcja przerywa transakcję, więc taki odczyt
  rzuciłby zamiast raportować — ta sama rozbieżność, którą dokumentuje
  `CategoryResolver`, i powód istnienia Fazy 4. Powód zapisany w komentarzu testu.
- **Faza 1 — „widoczne raz" zapinowane liczeniem wystąpień.** Plan mówił
  „widoczne na wyrenderowanej liście dokładnie raz"; `assertSee` tego nie liczy,
  więc test używa `substr_count()` na treści odpowiedzi. Widok renderuje jeden
  element na przypisanie (`resources/views/shops/index.blade.php`), więc liczba
  wystąpień jest tu wiarygodnym odczytem pokrycia.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Kontrakty pokrycia sklepu

#### Automated

- [x] 1.1 Zestaw funkcjonalny zielony — ad33299
- [x] 1.2 Pełny zestaw zielony i liczniejszy niż 53 testy — ad33299
- [x] 1.3 Formatowanie (Pint) — ad33299
- [x] 1.4 Migracje na czystej bazie — ad33299

#### Manual

- [x] 1.5 Próba obalenia testu unikalności (usunięcie `unique` z migracji) — ad33299
- [x] 1.6 Próba obalenia testu kolizji (`sync()` → `attach()`) — ad33299
- [x] 1.7 Docblock `StoreShopRequest` czyta się jako decyzja o rodzinie — ad33299

### Phase 2: Usunięcie testu przechodzącego z niewłaściwego powodu

#### Automated

- [x] 2.1 Pełny zestaw zielony, o jeden test mniej niż po Fazie 1
- [x] 2.2 Formatowanie (Pint)

#### Manual

- [x] 2.3 `grep` po `tests/` nie znajduje innego testu twierdzącego o precedencji
- [x] 2.4 Kontrola: usunięcie `orderBy('id')` nadal nie wywala zestawu (stan udokumentowany)
- [x] 2.5 Docblock `index()` czyta się jako ostrzeżenie dla autora S-04

### Phase 3: Domknięcie planu testów

#### Automated

- [ ] 3.1 Brak `TBD` przy §6.3
- [ ] 3.2 Wiersz 2 tabeli §3 ma `complete`, wiersz 4 wymienia `#1`
- [ ] 3.3 Pełny zestaw dalej zielony

#### Manual

- [ ] 3.4 §6.3 czyta się jako instrukcja dla kogoś z zewnątrz zaczynającego S-04
- [ ] 3.5 Uzasadnienie kolejności w §3 wyjaśnia odroczenie, nie tylko je odnotowuje
- [ ] 3.6 Wpis §6.6 trzyma konwencję wpisu Fazy 1
