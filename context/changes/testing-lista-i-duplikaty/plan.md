# Rdzeń listy zakupów pod kształtem formularza — Implementation Plan

## Overview

Faza 1 wdrożenia z `context/foundation/test-plan.md` §3. Ma dowieść dwóch rzeczy
na kodzie, który już działa:

- **ryzyko #2** — produkt dodany przez jednego członka jest widoczny dla drugiego;
- **ryzyko #5** — blokada duplikatu trafia w obie strony: blokuje to, co rodzina
  uważa za ten sam produkt, i przepuszcza to, co uważa za różne.

Faza jest w 95% testowa. Jedyna zmiana w kodzie produkcyjnym to jedna linia w
`NameComparison::normalize()` — sprowadzenie wewnętrznych odstępów, żeby
`Mleko  2%` i `Mleko 2%` przestały być dwoma produktami.

## Current State Analysis

Research (`context/changes/testing-lista-i-duplikaty/research.md`) wykonał obie
ścieżki sondą i nie znalazł błędu na ścieżce szczęśliwej. Aplikacja zachowuje się
zgodnie z PRD. Luki są w zestawie testów.

Stan wyjściowy: **49 testów, 150 asercji, zielone** na commicie `320ece2`.

Co jest nie tak z dzisiejszym zestawem:

| Miejsce | Co robi | Czego nie dowodzi |
|---|---|---|
| `tests/Feature/AddProductTest.php:15-26` | POST → `assertRedirect` → `Product::sole()` | że cokolwiek się wyrenderowało — antywzorzec nazwany wprost w test-plan §2 |
| `tests/Feature/AddProductTest.php:20` | wysyła `category_id` jako liczbę całkowitą | że kształt, który wysyła przeglądarka (`""` albo `"7"`), przechodzi |
| `tests/Feature/AddProductTest.php:55-65` | wysyła `'  mleko '`, oczekuje odrzucenia | przycięcia — globalny `TrimStrings` przycina ładunek przed walidacją, więc test dowodzi wyłącznie nieczułości na wielkość liter |
| `tests/Feature/ProductListTest.php:33-41` | fabryka tworzy produkt, jeden użytkownik czyta | że **zapis przez HTTP** przez członka A dociera do członka B — czyli dokładnie ryzyka #2 |

Trzy fakty o kodzie, które kształtują tę fazę:

- **Odczyt listy nie jest zawężony i nie może być** — `ProductController::index()`
  (`app/Http/Controllers/ProductController.php:23-31`) czyta wszystkie produkty,
  a tabela nie ma kolumny właściciela. Ryzyko #2 nie zrealizuje się przez odczyt;
  jeśli w ogóle, to przez zapis.
- **Blokada duplikatu jest globalna, nie w obrębie kategorii**
  (`app/Http/Requests/StoreProductRequest.php:67-69`). Zamierzone, ale nigdzie
  nie zapisane ani nie przetestowane.
- **Efektywna reguła równości na HTTP to złożenie dwóch przycięć** — globalny
  `TrimStrings` (unicode) i `NameComparison::normalize()` (ASCII). Nikt tego nie
  zadeklarował.

## Desired End State

Po tej fazie zestaw testów odpowiada twierdząco na cztery pytania, na które dziś
nie odpowiada:

1. Czy produkt wysłany formularzem przez członka A pojawia się na wyrenderowanej
   liście członka B?
2. Czy ładunek w kształcie, który realnie wysyła przeglądarka, przechodzi — w obu
   wariantach pola kategorii?
3. Czy komunikat o duplikacie dociera na ekran po polsku?
4. Czy reguła równości nazw blokuje to, co rodzina uważa za ten sam produkt —
   z wielkością liter, przycięciem i wewnętrznymi odstępami włącznie?

Weryfikacja: `composer test` zielone przy większej liczbie testów niż 49, a każdy
nowy test daje się obalić przez tymczasowe cofnięcie reguły, którą pinuje.

### Key Discoveries:

- `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:461-462`
  — `TrimStrings` i `ConvertEmptyStringsToNull` są w **grupie globalnej**, więc
  działają na każdym teście funkcjonalnym idącym przez HTTP.
- `vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php:2311-2322`
  — `prohibits` nie strzela, gdy pole jest puste. Dlatego `category_id=""` z
  formularza przechodzi obok `prohibits:new_category`.
- `app/Support/NameComparison.php:19-27` — jedyna definicja równości, cztery
  wołające: `StoreProductRequest:69`, `StoreShopRequest:96`,
  `CategoryResolver:66`, `CategorySeeder:43`. Zmiana `normalize()` dotyka
  wszystkich czterech.
- `tests/Unit/NameComparisonTest.php:6` — testy jednostkowe dziedziczą po
  `PHPUnit\Framework\TestCase`, nie po `Tests\TestCase`. Konwencja projektu to
  „Unit = bez kontenera Laravela", nie „Unit = jedna klasa".
- `tests/Feature/AddShopTest.php` został poprawiony w S-03 dokładnie tak, jak ta
  faza poprawia `AddProductTest` (ustalenia F6 i F9 przeglądu S-03).
- Przegląd S-03 ustalenie F4: `assertSessionHasErrors()` **postarza dane flash**,
  więc wywołanie go przed `followingRedirects()` czyni asercję pustą.
- `Product::latest()` sortuje po `created_at` bez rozstrzygnięcia remisu
  (dowód M w researchu). Asercje na kolejność produktów byłyby chwiejne.

## What We're NOT Doing

- **Nie zmieniamy zachowania blokady duplikatu** poza sprowadzeniem odstępów.
  Blokada zostaje globalna (ta sama nazwa w dwóch kategoriach = jeden produkt) —
  decyzja właściciela, w tej fazie tylko przypięta testem i dopisana do docbloka.
- **Nie normalizujemy unicode'u.** NBSP i spacja zerowej szerokości są dziś
  obsługiwane przez `TrimStrings` po HTTP i nieobsługiwane poza HTTP; NFD nie jest
  obsługiwane nigdzie. Decyzja właściciela: akceptujemy, że w skrajnym przypadku
  pojawią się dwa produkty. Oba idą do `test-plan.md` §7 jako świadome wykluczenia.
- **Nie obudowujemy `ProductController::store()` transakcją.** Awaria między
  utworzeniem kategorii a produktu zostawia osieroconą kategorię — pojawia się
  jako dodatkowa pozycja na liście wyboru, nie jest utratą danych.
- **Nie asercjujemy kolejności produktów na liście.** `latest()` remisuje.
- **Nie dotykamy ryzyk #1, #3, #4, #6** — mają własne fazy w §3.
- **Nie wpinamy niczego w CI.** `test-plan.md` §7 wyklucza to świadomie.
- **Nie ruszamy `AddShopTest`, `ShopListTest`, `CategoryResolverTest`,
  `CategorySeederTest`.** Zmiana `normalize()` ich dotyczy, ale wszystkie ich dane
  kontrolne mają pojedyncze spacje, więc żaden nie zmienia wyniku.

## Implementation Approach

Trzy fazy, od najtańszej warstwy w górę — dokładnie tak, jak każe `test-plan.md`
§1 zasada 1.

**Faza 1** zajmuje się regułą w izolacji: jedna linia w `normalize()` plus testy
jednostkowe. Nie potrzebuje bazy ani HTTP, więc jest najszybszą pętlą sprzężenia
zwrotnego, jaka w tym projekcie istnieje.

**Faza 2** zajmuje się zachowaniem po HTTP: to, czego rodzina realnie doświadcza.
Poprawia słabe przypadki w `AddProductTest` **na miejscu** — tak jak przegląd S-03
poprawił `AddProductTest`-owy odpowiednik w `AddShopTest` — i dokłada szew między
dwoma członkami, którego nie ma nigdzie.

**Faza 3** wypełnia `test-plan.md`: §6.1 i §6.2 przestają być `TBD`, §7 dostaje
dwa wykluczenia, §3 wiersz 1 idzie na `complete`.

Każdy nowy test musi przejść **próbę obalenia**: tymczasowo cofnąć regułę, którą
pinuje, i zobaczyć, że test upada. Ten krok jest w kryteriach sukcesu, nie w
dobrych intencjach — przegląd S-02 (F1) i S-03 (F4) obie znalazły testy, które
przechodziły z niewłaściwego powodu.

## Critical Implementation Details

**Kolejność w teście duplikatu.** `assertSessionHasErrors()` postarza dane flash.
Wywołane przed `followingRedirects()` czyni asercję pustą (ustalenie F4 przeglądu
S-03). Test, który ma sprawdzić i błąd sesji, i wyrenderowany komunikat, musi to
zrobić dwoma osobnymi żądaniami albo asercjować wyłącznie na wyrenderowanej
treści.

**Wyrażenie sprowadzające odstępy nie dostaje modyfikatora `/u`.** Bez `/u`
`preg_replace` traktuje wzorzec bajtowo — to bezpieczne na UTF-8, bo bajty
odstępów ASCII nie występują wewnątrz sekwencji wielobajtowych. Z `/u` funkcja
zwraca `null` na niepoprawnym UTF-8, co zamieniłoby brzydkie wejście w błąd typu.
ASCII-owe sprowadzanie jest dokładnie tym, o co poprosił właściciel.

## Phase 1: Reguła równości nazw

### Overview

Sprowadzenie wewnętrznych odstępów w jedynej definicji równości nazw, plus testy
jednostkowe pinujące pełną regułę — również jej granice.

### Changes Required:

#### 1. Reguła równości

**File**: `app/Support/NameComparison.php`

**Intent**: `normalize()` ma sprowadzać ciągi wewnętrznych odstępów ASCII do
pojedynczej spacji, żeby `Mleko  2%` i `Mleko 2%` były jedną nazwą. Podwójna
spacja jest literówką, nie rozróżnieniem. Docblock klasy ma wprost nazwać, czego
reguła **nie** obejmuje, żeby następna osoba nie odkrywała tego sondą.

**Contract**: `normalize(string): string` — sygnatura bez zmian, wynik dla nazw z
pojedynczymi spacjami bez zmian (wszystkie dzisiejsze dane kontrolne). Zmiana
dotyczy wszystkich czterech wołających: `StoreProductRequest:69`,
`StoreShopRequest:96`, `CategoryResolver:66`, `CategorySeeder:43`.

```php
// bez /u — patrz Critical Implementation Details
return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
```

Docblock dostaje ustęp o granicach: odstępy unicode'owe (NBSP, spacja zerowej
szerokości) łapie globalny `TrimStrings` po HTTP i nikt poza HTTP; warianty NFD
nie są łapane nigdzie. Oba są świadomym wykluczeniem — wskazać `test-plan.md` §7.

#### 2. Testy jednostkowe reguły

**File**: `tests/Unit/NameComparisonTest.php`

**Intent**: Dołożyć przypadek na sprowadzanie wewnętrznych odstępów. Istniejące
cztery przypadki zostają — pinują wielkość liter, przycięcie, znaczące znaki
diakrytyczne i nazwy naprawdę różne.

**Contract**: Klasa dalej dziedziczy po `PHPUnit\Framework\TestCase` (bez bazy,
bez kontenera). Nowy przypadek: `Mleko  2%` równa się `Mleko 2%`; tabulator i
nowa linia zachowują się tak samo jak spacja. Nazwa metody ma mówić, co rodzina
uważa za tę samą rzecz — nie jak działa `preg_replace`.

### Success Criteria:

#### Automated Verification:

- Zestaw jednostkowy zielony: `docker compose exec app php vendor/bin/phpunit --testsuite Unit`
- Pełny zestaw dalej zielony, bez regresji na kategoriach i sklepach: `docker compose exec app php vendor/bin/phpunit`
- Formatowanie: `docker compose exec app vendor/bin/pint --test`

#### Manual Verification:

- Próba obalenia: tymczasowe cofnięcie `preg_replace` z `normalize()` wywala nowy
  przypadek i **tylko** jego
- Docblock `NameComparison` czyta się jako odpowiedź na pytanie „co rodzina uważa
  za ten sam produkt", a nie jako opis implementacji

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymać się i
poczekać na potwierdzenie weryfikacji ręcznej przed Fazą 2.

---

## Phase 2: Ścieżka formularza po HTTP

### Overview

To, czego rodzina realnie doświadcza: formularz w kształcie przeglądarki, produkt
widoczny dla drugiego członka, komunikat o duplikacie na ekranie.

### Changes Required:

#### 1. Poprawka słabych przypadków

**File**: `tests/Feature/AddProductTest.php`

**Intent**: Trzy istniejące przypadki dowodzą mniej, niż obiecują ich nazwy.
Poprawiamy je na miejscu, nie dokładamy testu-widma obok — tak jak przegląd S-03
poprawił `AddShopTest` (F6, F9).

**Contract**:

- `test_a_member_adds_a_product_with_an_existing_category` — podąża za
  przekierowaniem i asercjuje **nazwę produktu i nazwę kategorii na wyrenderowanej
  liście**, nie wiersz w bazie. Wysyła `category_id` jako **tekst** i puste
  `new_category` — czyli to, co wysyła przeglądarka po wybraniu z listy
  (`resources/views/products/create.blade.php:23-31`).
- `test_typing_a_new_category_creates_exactly_one` — wysyła `category_id => ''`
  obok `new_category`, czyli kształt formularza przy wpisanej nowej kategorii.
  Asercja na liczbę kategorii zostaje.
- `test_a_product_already_on_the_list_is_rejected_regardless_of_case` — nazwa i
  treść rozdzielone tak, żeby nie obiecywały przycięcia, którego test nie
  dowodzi. Przycięcie na tym poziomie robi middleware; reguła jest przypięta
  jednostkowo w Fazie 1. Docblock ma to powiedzieć wprost.

#### 2. Szew ryzyka #2

**File**: `tests/Feature/AddProductTest.php`

**Intent**: Jedyny test, który realnie wykonuje ryzyko #2: członek A wysyła
formularz, członek B osobnym żądaniem czyta stronę główną i widzi produkt.
Dzisiejszy `ProductListTest:33-41` tworzy produkt fabryką, więc nie dotyka zapisu.

**Contract**: Dwóch różnych `User`, dwa osobne żądania (`actingAs($a)->post(...)`,
potem `actingAs($b)->get('/')`). Asercja na wyrenderowaną nazwę produktu i
kategorii. Docblock ma nazwać gwarancję z PRD §Guardrails, której test broni.

#### 3. Komunikat duplikatu na ekranie

**File**: `tests/Feature/AddProductTest.php`

**Intent**: Odrzucenie duplikatu ma docierać do członka rodziny po polsku, na
formularzu, a nie tylko do sesji. Dziś nic tego nie pilnuje.

**Contract**: Żądanie z `from('/products/create')` i podążeniem za
przekierowaniem; asercja na treść „Ten produkt jest już na liście zakupów." i na
to, że liczba produktów się nie zmieniła. **Nie** wywoływać
`assertSessionHasErrors()` w tym samym żądaniu — patrz Critical Implementation
Details.

#### 4. Zakres blokady duplikatu

**File**: `tests/Feature/AddProductTest.php`, `app/Http/Requests/StoreProductRequest.php`

**Intent**: Przypiąć decyzję właściciela: blokada jest globalna, nie w obrębie
kategorii. `Mleko` w Nabiale blokuje `Mleko` w Napojach, bo lista zakupów jest
jedna i mleko kupuje się raz. Decyzja nie jest dziś nigdzie zapisana, więc
następna osoba może ją uznać za błąd i „naprawić".

**Contract**: Test tworzy dwie kategorie i produkt w pierwszej, wysyła tę samą
nazwę z drugą kategorią, oczekuje odrzucenia i jednego produktu. Docblock
`notAlreadyOnTheList()` dostaje jedno zdanie z uzasadnieniem.

#### 5. Odchudzenie testu listy

**File**: `tests/Feature/ProductListTest.php`

**Intent**: `test_products_are_visible_to_every_family_member` obiecuje to samo,
co nowy szew z punktu 2, a dowodzi mniej. Zawęzić jego docblock i nazwę do tego,
co realnie sprawdza — że odczyt listy nie jest zawężony do użytkownika — żeby dwa
testy nie udawały tego samego.

**Contract**: Test zostaje (broni braku filtra po właścicielu), zmienia się nazwa
i docblock. Docblock wskazuje nowy test w `AddProductTest` jako miejsce, gdzie
dowodzona jest pełna ścieżka.

### Success Criteria:

#### Automated Verification:

- Zestaw funkcjonalny zielony: `docker compose exec app php vendor/bin/phpunit --testsuite Feature`
- Pełny zestaw zielony i **liczniejszy niż 49 testów**: `docker compose exec app php vendor/bin/phpunit`
- Formatowanie: `docker compose exec app vendor/bin/pint --test`
- Migracje na czystej bazie: `docker compose exec app php artisan migrate:fresh --seed`

#### Manual Verification:

- Próba obalenia dla szwu ryzyka #2: dopisanie `->where('id', 0)` do zapytania w
  `ProductController::index()` wywala nowy test
- Próba obalenia dla komunikatu: zmiana tekstu w `notAlreadyOnTheList()` wywala
  test na komunikacie
- Próba obalenia dla zakresu blokady: zawężenie zapytania duplikatu do
  `category_id` wywala test z punktu 4
- Dodanie produktu w przeglądarce na `/products/create` i duplikatu tuż po —
  komunikat wyświetla się po polsku, lista pokazuje jeden wpis

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymać się i
poczekać na potwierdzenie weryfikacji ręcznej przed Fazą 3.

---

## Phase 3: Domknięcie planu testów

### Overview

`test-plan.md` przestaje być obietnicą i staje się książką kucharską dla tych
dwóch warstw.

### Changes Required:

#### 1. Wzorce kucharskie

**File**: `context/foundation/test-plan.md`

**Intent**: §6.1 i §6.2 przestają być `TBD`. Mają odpowiadać na pytanie „jak dodam
tu test dla X", nie streszczać tej fazy.

**Contract**:

- **§6.1 (test jednostkowy)** — lokalizacja `tests/Unit/`, klasa bazowa
  `PHPUnit\Framework\TestCase` (nie `Tests\TestCase` — konwencja projektu to
  „Unit = bez kontenera Laravela"), test referencyjny `NameComparisonTest`,
  polecenie `docker compose exec app php vendor/bin/phpunit --testsuite Unit`.
  Nazwy metod opisują, co rodzina uważa za tę samą rzecz.
- **§6.2 (test integracyjny formularza)** — lokalizacja `tests/Feature/`,
  `Tests\TestCase` + `RefreshDatabase`, test referencyjny `AddProductTest`.
  Cztery reguły: ładunek w kształcie przeglądarki (tekst, puste pola jako `""`),
  podążenie za przekierowaniem i asercja na wyrenderowaną treść, dwóch członków
  dla gwarancji widoczności, `assertSessionHasErrors()` nigdy przed
  `followingRedirects()`.

#### 2. Wykluczenia

**File**: `context/foundation/test-plan.md`

**Intent**: §7 dostaje dwa nowe wpisy — decyzje właściciela podjęte podczas
planowania tej fazy, z warunkiem ponownego rozważenia.

**Contract**: Dwa wpisy w konwencji istniejących pięciu (opis — uzasadnienie —
„rozważyć ponownie, gdyby…" — źródło):

- odstępy unicode'owe (NBSP, spacja zerowej szerokości) — łapane przez
  `TrimStrings` po HTTP, nie łapane poza HTTP; rozważyć ponownie, gdyby powstał
  pisarz nazw spoza HTTP czytający z zewnątrz;
- warianty NFD — klawiatura telefonu produkuje NFC, ścieżka osiągalna praktycznie
  tylko przez wklejenie; rozważyć ponownie, gdyby rodzina zaczęła wklejać nazwy.

#### 3. Notatki fazy i status

**File**: `context/foundation/test-plan.md`

**Intent**: §6.6 dostaje dwie–trzy linie o tym, czego faza nauczyła. §3 wiersz 1
idzie na `complete`. `Last updated` na dziś.

**Contract**: Notatki mają zawierać co najmniej: pułapkę `assertSessionHasErrors()`
przed `followingRedirects()`, fakt że `TrimStrings` jest częścią efektywnej reguły
równości, i brak rozstrzygnięcia remisu w `Product::latest()` (asercje na
kolejność produktów są chwiejne).

### Success Criteria:

#### Automated Verification:

- W `test-plan.md` nie ma już `TBD — patrz §3 Faza 1`: `grep -c "Faza 1" context/foundation/test-plan.md` nie pokazuje wierszy `TBD`
- Wiersz 1 tabeli §3 ma status `complete`
- Pełny zestaw dalej zielony: `docker compose exec app php vendor/bin/phpunit`

#### Manual Verification:

- §6.1 i §6.2 dają się wykonać przez kogoś, kto nie brał udziału w tej fazie —
  czyta się jako instrukcja, nie jako streszczenie
- §7 nowe wpisy trzymają konwencję pozostałych pięciu

---

## Testing Strategy

### Unit Tests:

- Reguła równości nazw: wielkość liter, przycięcie, wewnętrzne odstępy, znaczące
  znaki diakrytyczne, nazwy naprawdę różne
- Bez bazy i bez kontenera Laravela — najszybsza pętla w projekcie

### Integration Tests:

- Formularz w obu kształtach, które wysyła przeglądarka
- Członek A wysyła → członek B czyta (ryzyko #2)
- Duplikat: odrzucony, komunikat po polsku na ekranie, blokada globalna (ryzyko #5)

### Manual Testing Steps:

1. Otworzyć `/products/create`, dodać `Mleko 2%` w kategorii Nabiał — pojawia się
   na liście
2. Dodać `Mleko  2%` (dwie spacje) — odrzucone z polskim komunikatem
3. Dodać `Mleko` w kategorii Napoje przy istniejącym `Mleko` w Nabiale —
   odrzucone
4. Dla każdego nowego testu: cofnąć regułę, którą pinuje, i potwierdzić, że test
   upada

## Performance Considerations

Brak. Reguła duplikatu czyta całą tabelę nazw do PHP — świadomie, z progiem
rewizji zapisanym w `StoreProductRequest.php:53-58` („gdyby lista urosła w
tysiące"). Ta faza nic w tym nie zmienia.

## Migration Notes

Brak migracji. Zmiana `normalize()` nie dotyka danych zapisanych — porównanie
odbywa się w locie. Istniejące wiersze z podwójnymi spacjami (jeśli jakieś są na
produkcji) zostają, ale kolejny duplikat zostanie od teraz odrzucony.

## References

- Research: `context/changes/testing-lista-i-duplikaty/research.md`
- Plan testów: `context/foundation/test-plan.md` §2 (ryzyka #2 i #5), §3 Faza 1, §6
- Wzorzec poprawki testów na miejscu: `context/archive/2026-09-09-konfiguracja-sklepow/reviews/impl-review.md` F6, F9
- Pułapka `assertSessionHasErrors()`: tamże, F4
- Test referencyjny do naśladowania: `tests/Feature/AddShopTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Reguła równości nazw

#### Automated

- [x] 1.1 Zestaw jednostkowy zielony — d76ba83
- [x] 1.2 Pełny zestaw dalej zielony, bez regresji na kategoriach i sklepach — d76ba83
- [x] 1.3 Formatowanie (Pint) — d76ba83

#### Manual

- [x] 1.4 Próba obalenia: cofnięcie `preg_replace` wywala tylko nowy przypadek — d76ba83
- [x] 1.5 Docblock `NameComparison` czyta się jako odpowiedź o rodzinie, nie o implementacji — d76ba83

### Phase 2: Ścieżka formularza po HTTP

#### Automated

- [x] 2.1 Zestaw funkcjonalny zielony — b085fd6
- [x] 2.2 Pełny zestaw zielony i liczniejszy niż 49 testów — b085fd6
- [x] 2.3 Formatowanie (Pint) — b085fd6
- [x] 2.4 Migracje na czystej bazie — b085fd6

#### Manual

- [x] 2.5 Próba obalenia szwu ryzyka #2 (zawężenie zapytania w `index()`) — b085fd6
- [x] 2.6 Próba obalenia komunikatu duplikatu (zmiana tekstu) — b085fd6
- [x] 2.7 Próba obalenia zakresu blokady (zawężenie do `category_id`) — b085fd6
- [x] 2.8 Dodanie produktu i duplikatu w przeglądarce — komunikat po polsku — b085fd6

### Phase 3: Domknięcie planu testów

#### Automated

- [x] 3.1 Brak `TBD` przy §6.1 i §6.2 — 9eb3950
- [x] 3.2 Wiersz 1 tabeli §3 ma status `complete` — 9eb3950
- [x] 3.3 Pełny zestaw dalej zielony — 9eb3950

#### Manual

- [x] 3.4 §6.1 i §6.2 czytają się jako instrukcja dla kogoś z zewnątrz — 9eb3950
- [x] 3.5 Nowe wpisy §7 trzymają konwencję pozostałych pięciu — 9eb3950
