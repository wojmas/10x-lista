# Rekomendacja sklepu na stronie głównej (S-04) — Implementation Plan

## Overview

Strona główna przestaje być samą listą zakupów i zaczyna odpowiadać na pytanie
„GDZIE jechać". Reguła z §Business Logic PRD — sklep pokrywający najwięcej
różnych kategorii z aktualnej listy, remis na korzyść sklepu dodanego pierwszego
— dostaje jedną implementację w `App\Support`, jeden panel w `home.blade.php` i
pierwszy komplet testów, jaki ta reguła w ogóle będzie miała.

To gwiazda przewodnia roadmapy (S-04). Wszystkie jej wejścia są już wdrożone i
zapięte testami; brakuje wyłącznie samej reguły i jej ekranu.

## Current State Analysis

Co istnieje i działa:

- **Model danych jest kompletny.** `Shop::categories()` (`app/Models/Shop.php:23`)
  to relacja wiele-do-wielu przez `category_shop`, opisana w migracji jako
  „wejście, po którym S-04 liczy pokrycie". `Product::category()`
  (`app/Models/Product.php:20`) daje drugą stronę porównania.
- **Wejścia reguły mają strażników w zestawie.** Unikalność pary
  sklep–kategoria pilnuje `tests/Feature/ShopCategoryAssignmentTest.php`,
  konsekwencję po HTTP — `AddShopTest::test_a_category_ticked_and_typed_in_at_once_is_covered_only_once`,
  a spójność słownika kategorii `CategoryResolver` + `NameComparison` +
  `CategorySeederTest`.
- **`ProductController::index()` jest przygotowany.** Eager-load `with('category')`
  (`app/Http/Controllers/ProductController.php:24`) został tam wpisany wprost z
  myślą o tej fazie — docblock mówi „S-04 will read the same relation for every
  product on every recommendation recalculation".
- **Kolejność sklepów jest już zapisana w bazie jako kontrakt.** Migracja
  `2026_09_10_100000_create_shops_table.php` świadomie nie ma kolumny `position`:
  klucz główny rośnie monotonicznie, więc kolejność `id` *jest* regułą „sklep
  dodany pierwszy". Sortowanie po `created_at` remisowałoby przy dwóch sklepach
  w tej samej sekundzie.

Czego brakuje:

- **Regułę rekomendacji nie liczy nic i nie pilnuje nic.** Przemiatanie
  `tests/` po `rekomend|pokryci|remis` daje wyłącznie docblocki opisujące, po co
  istnieją *wejścia* reguły. Faza 2 planu testów wypchnęła testy samej reguły
  wprost tutaj (`test-plan.md` §6.3: „To wejście reguły, więc jego test należy do
  planu S-04").
- **Kontrakt precedencji jest długiem z nazwanym wyzwalaczem.** Klauzula
  `orderBy('id')` żyje inline w `ShopController::index()`
  (`app/Http/Controllers/ShopController.php:44`) i broni jej wyłącznie docblock —
  test, który deklarował jej pilnowanie, został skasowany w Fazie 2 jako zielony
  test kłamiący. `test-plan.md` §3 ustawia **start slice'u S-04** jako wyzwalacz
  przesunięcia Fazy 4 do przodu.
- **`home.blade.php` nie wie o sklepach.** Widok renderuje wyłącznie listę
  produktów albo jej empty state (`resources/views/home.blade.php:19`).

Ograniczenia, w których się mieścimy:

- **Zestaw biegnie na SQLite `:memory:`, produkcja na Postgresie.** `test-plan.md`
  §6.3 reguła 3: kontraktu zależnego od kolejności wierszy nie da się dowieść na
  SQLite. Dotyczy to wyłącznie kolejności **z bazy**, nie tie-breaku liczonego w
  PHP.
- **Sklep o zerowym pokryciu jest w bazie legalny** (`database/factories/ShopFactory.php`)
  — odrzuca go dopiero formularz. Wyprodukuje go S-06 przez usunięcie kategorii.
- **Skala to 3–5 osób i kilka sklepów.** Każdy wybór „liczyć w PHP czy w SQL"
  rozstrzyga się na korzyść czytelności i testowalności, nie wydajności.

## Desired End State

Zalogowany członek rodziny wchodzi na `/` i nad listą zakupów widzi panel
rekomendacji:

- **Jest lista i jest dopasowanie** → nazwa sklepu z najwyższym pokryciem oraz
  licznik „pokrywa 3 z 4 kategorii z listy". Jeśli drugi w kolejności sklep
  pokrywa cokolwiek, pod spodem stoi jako alternatywa z własnym licznikiem.
- **Lista jest pusta** → komunikat kierujący do dodania produktów, panel zostaje
  na miejscu.
- **Żaden sklep nie pokrywa ani jednej kategorii z listy** (także gdy sklepów nie
  ma wcale) → komunikat o braku dopasowania z odnośnikiem do konfiguracji
  sklepów.

Panel przelicza się przy każdym wejściu na stronę główną, a ponieważ dodanie
produktu przekierowuje na `home` (`ProductController::store()`), spełnia to
kryterium akceptacji US-01 „aktualizuje się po każdym dodaniu produktu".

Weryfikacja: cztery nowe testy reguły i cztery testy panelu przechodzą, każdy
przechodzi **próbę obalenia**, a cofnięcie reguły remisu w PHP wywala dokładnie
test remisu.

### Key Discoveries:

- **Pokrycie to liczba różnych kategorii, nie liczba produktów** — `test-plan.md`
  §6.3 podaje to jako fakt wejściowy: trzy produkty w dwóch kategoriach dają
  pokrycie 2.
- **Docblock `ShopController::index()` mówi wprost do autora tego slice'u**:
  „nie pisz w S-04 własnego uporządkowania sklepów" (`app/Http/Controllers/ShopController.php:38`).
- **`App\Support` to ustalony wzorzec tego projektu** — `NameComparison` i
  `CategoryResolver` to reguły domenowe wyjęte z kontrolerów dokładnie po to,
  żeby drugi konsument nie dostał drugiej kopii. S-05 będzie tym drugim
  konsumentem rekomendacji.
- **Sortowanie kolekcji w PHP jest stabilne** (PHP 8.0+ ma stabilne sortowanie,
  `Illuminate\Support\Collection::sortByDesc` idzie przez `uasort`) — to nośnik
  reguły remisu, patrz „Critical Implementation Details".
- **Formularz sklepu wymaga co najmniej jednej kategorii** (`StoreShopRequest`),
  więc sklep o zerowym pokryciu nie powstanie z UI — ale powstaje z fabryki i
  powstanie z S-06.

## What We're NOT Doing

- **Nie usuwamy produktów.** To S-05; kryterium „usunięty produkt wpływa na
  przeliczenie rekomendacji" zweryfikuje tamten slice.
- **Nie edytujemy ani nie kasujemy sklepów.** To S-06.
- **Nie uruchamiamy zestawu na Postgresie i nie dowodzimy kontraktu kolejności
  z bazy.** To Faza 4 `test-plan.md`, własny change folder. Ten plan
  konsoliduje klauzulę, żeby Faza 4 miała jedno miejsce do zapięcia — dowód
  nadal jest długiem.
- **Nie budujemy rankingu wszystkich sklepów** — zwycięzca plus warunkowa
  alternatywa, zgodnie z decyzją właściciela. §Non-Goals PRD odrzuca złożony
  system decyzyjny.
- **Nie ważymy produktów, cen ani jakości.** FR-007 rozstrzyga: prosta reguła
  wystarcza na MVP.
- **Nie dodajemy cache'u ani przeliczania w tle.** Dwa zapytania na żądanie przy
  pięciu użytkownikach to nie jest problem do rozwiązania.
- **Nie ruszamy `ShopController::store()` ani formularzy.**

## Implementation Approach

Reguła liczy się **w PHP**, na kolekcjach wczytanych przez kontroler, a nie
zapytaniem z `COUNT` po stronie bazy. Powód nie jest estetyczny: `test-plan.md`
§6.3 i notatka Fazy 2 w §6.6 dokumentują, że logika oparta o zachowanie silnika
bazy jest w tym projekcie niedowodliwa dzisiejszym zestawem — sonda na dwóch
silnikach kosztowała już jedną fazę. Reguła w PHP jest w całości testowalna na
SQLite, bo nie zależy od niczego, co silniki robią różnie.

Klauzula precedencji przenosi się z ciała `ShopController::index()` do scope'u
`Shop::inPrecedenceOrder()`, z którego korzystają obaj konsumenci. To wprost
wykonuje polecenie z §6.3 („nie pisz w S-04 własnego uporządkowania sklepów") i
zbija dwie przyszłe kopie klauzuli do jednej — tej, którą Faza 4 będzie miała co
zapinać. Kontrakt nadal nie ma testu; zmienia się tylko liczba miejsc, w których
może się zepsuć.

Kolejność faz jest wymuszona: Faza 2 renderuje wartości, których kształt ustala
Faza 1.

## Critical Implementation Details

**Sekwencja sortowania jest nośnikiem reguły remisu.** Kolekcja sklepów wchodzi
do reguły już uporządkowana rosnąco po `id` (scope `inPrecedenceOrder()`), a
następnie jest sortowana malejąco po pokryciu. Sortowanie kolekcji w PHP jest
stabilne, więc sklepy o równym pokryciu zachowują wejściową kolejność `id` — i
tak właśnie powstaje „przy remisie wygrywa sklep dodany pierwszy". Odwrócenie tej
sekwencji (sortowanie po pokryciu przed uporządkowaniem po `id`) albo wstawienie
własnego komparatora z tie-breakiem po nazwie łamie regułę bez żadnego błędu.
Jest to jedyne miejsce w tym planie, gdzie *kolejność operacji* jest kontraktem.

**Granica dowodu remisu.** Test remisu napisany w tej fazie obala złamanie
tie-breaku **w PHP** (np. zamianę na sortowanie po nazwie) — to jest nasza
logika i nasz dowód. Nie obala usunięcia `orderBy('id')` ze scope'u: bez jawnego
`ORDER BY` SQLite i tak zwraca ze świeżej tabeli kolejność wstawienia, więc
asercja nie ma jak upaść. Ta połowa należy do Fazy 4 `test-plan.md` i musi być
nazwana w docblocku testu, żeby następna osoba nie uznała kontraktu za
zabezpieczony w całości.

---

## Faza 1: Reguła pokrycia i konsolidacja precedencji

### Overview

Reguła z §Business Logic dostaje jedną implementację, jeden komplet testów i
jedno źródło kolejności sklepów. Bez zmian w widokach.

### Changes Required:

#### 1. Scope precedencji na modelu Shop

**File**: `app/Models/Shop.php`

**Intent**: Wyjąć klauzulę `orderBy('id')` z ciała `ShopController::index()` do
nazwanego scope'u, żeby oba jej miejsca użycia (lista sklepów i rekomendacja)
czytały jedną definicję zamiast dwóch kopii. Przenieść tu ostrzeżenie z
docblocku kontrolera — po tej zmianie to scope jest nośnikiem kontraktu.

**Contract**: `Shop::inPrecedenceOrder()` — scope zapytania porządkujący rosnąco
po `id`. Docblock musi nieść trzy rzeczy: że `id` realizuje regułę „sklep dodany
pierwszy" z §Business Logic, że żaden test dziś tego nie pilnuje, i że dowód
należy do Fazy 4 `context/foundation/test-plan.md`.

#### 2. Reguła rekomendacji

**File**: `app/Support/ShopRecommendation.php` (nowy)

**Intent**: Policzyć, ile różnych kategorii z aktualnej listy zakupów pokrywa
każdy sklep, i zwrócić zwycięzcę, jego pokrycie, łączną liczbę kategorii na
liście oraz warunkową alternatywę. Wszystko, czego widok potrzebuje, w jednym
obiekcie — żeby `home.blade.php` nie liczył niczego sam, a S-05 miał co wywołać
po usunięciu produktu.

**Contract**: Ta sygnatura jest konsumowana przez Fazę 2, więc jest kontraktem
między fazami:

```php
final readonly class ShopRecommendation
{
    /** @param Collection<int, Product> $products @param Collection<int, Shop> $shops */
    public static function for(Collection $products, Collection $shops): self;

    public ?Shop $shop;              // null = brak dopasowania (pokrycie 0 lub brak sklepów)
    public int $covered;             // liczba różnych kategorii z listy pokrytych przez $shop
    public int $total;               // liczba różnych kategorii na liście
    public ?Shop $alternative;       // drugi w kolejności, wyłącznie gdy pokrywa > 0
    public int $alternativeCovered;
}
```

Reguły, które ta klasa musi spełniać:

- `$total` to liczba **różnych** `category_id` wśród produktów, nie liczba
  produktów.
- Pokrycie sklepu to liczba różnych kategorii z listy obecnych wśród jego
  `categories` — porównanie po `id` kategorii, nie po nazwie (`NameComparison`
  rozstrzygnęło tożsamość już przy zapisie; drugie porównanie nazw tutaj byłoby
  trzecim pisarzem tej reguły, dokładnie tym, czemu `CategoryResolver` zapobiega).
- `$shops` wchodzi uporządkowane po `id` i sortowanie po pokryciu musi tę
  kolejność zachować przy remisie — patrz „Critical Implementation Details".
- Pusta lista produktów → `$shop` null, `$total` 0. Widok odróżnia ten stan od
  braku dopasowania po `$total`.
- Sklep bez kategorii ma pokrycie 0 i nigdy nie zostaje `$shop` ani
  `$alternative`.

#### 3. Kontroler listy sklepów

**File**: `app/Http/Controllers/ShopController.php`

**Intent**: Użyć nowego scope'u zamiast inline'owego `orderBy('id')`. Docblock
metody skrócić — ostrzeżenie dla S-04 jest zrealizowane, a treść kontraktu
przeniosła się na scope; zostaje odsyłacz.

**Contract**: `Shop::query()->with('categories')->inPrecedenceOrder()->get()`.
Wynik widoczny na `/shops` bez zmian — pilnuje tego `ShopListTest`.

#### 4. Testy reguły

**File**: `tests/Feature/ShopRecommendationTest.php` (nowy)

**Intent**: Zapiąć cztery zachowania reguły, których dziś nie pilnuje nic.
Plik idzie do `tests/Feature/`, nie `tests/Unit/`, mimo braku HTTP — to
konwencja z `test-plan.md` §6.1/§6.3: kontrakt na danych potrzebuje bazy.

**Contract**: Cztery testy, każdy z osobną wyrocznią:

- **Pokrycie liczy różne kategorie** — trzy produkty w dwóch kategoriach dają
  `total` 2, a sklep pokrywający obie ma `covered` 2 (nie 3).
- **Remis wygrywa sklep dodany pierwszy** — dwa sklepy o identycznym pokryciu.
  Nazwy muszą być dobrane tak, żeby kolejność `id` różniła się od alfabetycznej
  (np. „Żabka" z `id` 1, „Auchan" z `id` 2, wygrywa „Żabka"), inaczej test
  przeszedłby także po zamianie tie-breaku na sortowanie po nazwie. Docblock
  testu nazywa granicę dowodu z „Critical Implementation Details".
- **Sklep o zerowym pokryciu** — sklep bez kategorii nie wygrywa i nie zostaje
  alternatywą; przy samych takich sklepach `shop` jest null.
- **Alternatywa tylko przy pokryciu > 0** — trzy sklepy o pokryciu 3, 1 i 0:
  alternatywą jest ten o pokryciu 1; przy sklepach o pokryciu 3 i 0
  `alternative` jest null.

#### 5. Korekta faktu wejściowego w planie testów

**File**: `context/foundation/test-plan.md`

**Intent**: §6.3 twierdzi, że „precedencja sklepów żyje inline w
`ShopController::index()`" — po tej fazie to nieprawda. Zaktualizować zdanie,
żeby wskazywało scope, i dopisać w §3 przy Fazie 4 jedną linię: slice S-04
ruszył, klauzula została skonsolidowana, dowód nadal jest długiem tej fazy.

**Contract**: Edycja §6.3 (punkt „Precedencja sklepów…") i dopisek przy wierszu
Fazy 4 w §3. Bez zmian w §2 (mapa ryzyk) i bez zmian statusów faz — ten slice
nie jest fazą wdrożenia testów.

### Success Criteria:

#### Automated Verification:

- Pokrycie liczy różne kategorie, nie produkty: `docker compose exec app php vendor/bin/phpunit --filter ShopRecommendationTest`
- Remis rozstrzyga sklep o niższym `id`: ten sam przebieg
- Sklep o zerowym pokryciu nie wygrywa ani nie zostaje alternatywą: ten sam przebieg
- Alternatywa pojawia się wyłącznie przy pokryciu większym od zera: ten sam przebieg
- Cały zestaw zielony: `docker compose exec app php vendor/bin/phpunit`
- Formatowanie czyste: `docker compose exec app php vendor/bin/pint --test`

#### Manual Verification:

- Próba obalenia: zamiana tie-breaku na sortowanie po nazwie wywala dokładnie test remisu i nic więcej
- Próba obalenia: policzenie produktów zamiast różnych kategorii wywala dokładnie test pokrycia
- `test-plan.md` §6.3 nie mówi już o inline'ie w kontrolerze, a §3 odnotowuje start S-04
- `/shops` listuje sklepy w tej samej kolejności co przed refaktorem

**Implementation Note**: Po tej fazie zatrzymaj się i poczekaj na potwierdzenie
manualnej weryfikacji, zanim ruszysz Fazę 2. Próby obalenia to konwencja tego
projektu (`test-plan.md` §6.6, Faza 1: „cztery próby, cztery trafienia") — obie
poprzednie fazy znalazły dzięki nim testy przechodzące z niewłaściwego powodu.

---

## Faza 2: Panel rekomendacji na stronie głównej

### Overview

To, co Faza 1 policzyła, staje się widoczne. Trzy stany panelu, licznik pokrycia,
warunkowa alternatywa.

### Changes Required:

#### 1. Kontroler strony głównej

**File**: `app/Http/Controllers/ProductController.php`

**Intent**: Dołożyć do `index()` wczytanie sklepów z kategoriami i przekazać do
widoku gotowy obiekt rekomendacji. Kontroler tylko składa wejścia i oddaje wynik
— żadnego liczenia.

**Contract**: Widok `home` dostaje dodatkową zmienną `recommendation` typu
`App\Support\ShopRecommendation`. Sklepy pobierane przez
`Shop::query()->with('categories')->inPrecedenceOrder()->get()` — ta sama
klauzula co na `/shops`, przez scope z Fazy 1. Eager-load `categories` jest
obowiązkowy: bez niego reguła strzela jednym zapytaniem na sklep.

#### 2. Panel na stronie głównej

**File**: `resources/views/home.blade.php`

**Intent**: Dodać nad listą produktów panel rekomendacji z trzema wariantami
treści. Wzorzec wizualny bierzemy z istniejącej karty listy (`bg-white
overflow-hidden shadow-sm sm:rounded-lg`), żeby strona pozostała jednym układem
i działała na telefonie bez nowych decyzji o responsywności.

**Contract**: Trzy warianty, rozróżniane po polach obiektu rekomendacji:

- `total === 0` → „Dodaj produkty, żeby zobaczyć rekomendowany sklep."
- `shop === null` przy `total > 0` → komunikat o braku dopasowania z odnośnikiem
  do `route('shops.index')`. Treść musi być poprawna także wtedy, gdy sklepów nie
  ma w ogóle — jeden stan, jeden string, bo naprawa w obu przypadkach jest ta
  sama: wejść w konfigurację sklepów.
- w pozostałych → nazwa sklepu i licznik „pokrywa `covered` z `total` kategorii
  z listy"; pod spodem, wyłącznie gdy `alternative !== null`, nazwa alternatywy z
  jej własnym licznikiem.

Teksty po polsku, jak reszta interfejsu (§NFR PRD). Odmiana rzeczownika po
liczbie („1 kategorię" / „2 kategorie" / „5 kategorii") jest w polszczyźnie
trójwariantowa — użyć sformułowania odpornego na odmianę albo obsłużyć warianty
przez `trans_choice`; katalog `lang/` już w projekcie istnieje.

#### 3. Testy panelu

**File**: `tests/Feature/HomeRecommendationTest.php` (nowy)

**Intent**: Zapiąć to, co użytkownik faktycznie czyta — cztery przebiegi przez
HTTP na `/`. Reguła jest już zapięta w Fazie 1; tutaj chodzi o to, że właściwy
wariant panelu trafia na ekran.

**Contract**: Cztery testy jako `actingAs(User::factory()->create())->get('/')`:

- Zwycięzca i licznik są widoczne, przy komplecie: produkty w czterech
  kategoriach, sklep pokrywający trzy.
- Pusta lista produktów pokazuje komunikat zachęty, a nie nazwę sklepu.
- Lista bez dopasowania pokazuje komunikat o braku dopasowania.
- Alternatywa jest widoczna, gdy drugi sklep pokrywa cokolwiek, i niewidoczna,
  gdy pokrywa zero.

Polskie znaki w asercjach wymagają `assertSee(..., escape: false)` — ta sama
pułapka, którą rozwiązuje `ShopListTest:39`.

### Success Criteria:

#### Automated Verification:

- Panel pokazuje zwycięzcę i licznik pokrycia: `docker compose exec app php vendor/bin/phpunit --filter HomeRecommendationTest`
- Pusta lista pokazuje komunikat zamiast sklepu: ten sam przebieg
- Brak dopasowania pokazuje komunikat o braku dopasowania: ten sam przebieg
- Alternatywa widoczna wyłącznie przy pokryciu większym od zera: ten sam przebieg
- Cały zestaw zielony: `docker compose exec app php vendor/bin/phpunit`
- Formatowanie czyste: `docker compose exec app php vendor/bin/pint --test`

#### Manual Verification:

- Próba obalenia każdego z czterech nowych testów panelu — cofnięcie wariantu w Blade wywala dokładnie ten test, który go pinuje
- Dodanie produktu na `/products/create` przeładowuje stronę główną ze zaktualizowaną rekomendacją (kryterium akceptacji US-01)
- Panel czyta się na szerokości telefonu, licznik nie łamie układu przy długiej nazwie sklepu
- Strona główna nie strzela zapytaniem na sklep — sprawdzone przez `DB::listen` albo licznik zapytań w debugu

**Implementation Note**: Po tej fazie zatrzymaj się i poczekaj na potwierdzenie
manualnej weryfikacji. Odmiana liczebnika po polsku to rzecz, której żaden test
nie złapie za autora — przeczytaj licznik na ekranie dla 1, 2 i 5 kategorii.

---

## Testing Strategy

### Unit Tests:

Brak nowych. `tests/Unit/` trzyma w tym projekcie wyłącznie reguły bez bazy
(`NameComparisonTest`); reguła rekomendacji operuje na modelach Eloquent, więc
zgodnie z `test-plan.md` §6.1/§6.3 jej testy idą do `tests/Feature/` nawet bez
HTTP.

### Integration Tests:

- `tests/Feature/ShopRecommendationTest.php` — cztery zachowania reguły bez
  warstwy HTTP (Faza 1).
- `tests/Feature/HomeRecommendationTest.php` — cztery warianty panelu przez pełny
  stos HTTP (Faza 2).

Każdy nowy test przechodzi próbę obalenia: cofnij regułę, którą test pinuje, i
sprawdź, że upada dokładnie ten test. Konwencja z `test-plan.md` §6.3 reguła 2.

### Manual Testing Steps:

1. Zalogować się, wejść na `/` z pustą listą — panel zachęca do dodania produktów.
2. Dodać produkt w kategorii, której nie ma żaden sklep — panel mówi o braku
   dopasowania i linkuje do konfiguracji sklepów.
3. Dodać produkty w kategoriach pokrywanych przez sklepy — panel pokazuje
   zwycięzcę z licznikiem, a przy drugim sklepie z niezerowym pokryciem także
   alternatywę.
4. Sprawdzić licznik dla 1, 2 i 5 kategorii — odmiana rzeczownika poprawna we
   wszystkich trzech wariantach.
5. Otworzyć stronę na szerokości telefonu.
6. Porównać kolejność sklepów na `/shops` z tą sprzed refaktoru scope'u.

## Performance Considerations

Strona główna dokłada dwa zapytania: sklepy i ich kategorie przez eager-load.
Reguła przechodzi listę produktów raz i listę sklepów raz, porównując zbiory
`id` w pamięci. Przy skali z PRD (3–5 osób, kilka sklepów, kilkadziesiąt
produktów) to koszt pomijalny. Linia do obserwowania, gdyby projekt urósł, jest
ta sama, którą już nazywa `CategoryResolver`: wczytywanie całej tabeli do PHP.
Jeśli sklepów byłyby tysiące, reguła przenosi się do zapytania z `COUNT` — i
wtedy potrzebuje Fazy 4 planu testów, żeby dało się ją uczciwie zapiąć.

## Migration Notes

Brak migracji i brak zmian w schemacie. Żadne istniejące dane nie wymagają
konwersji — reguła czyta to, co S-02 i S-03 już zapisały.

## References

- Roadmapa: `context/foundation/roadmap.md` → S-04 (gwiazda przewodnia)
- Plan testów: `context/foundation/test-plan.md` §3 (Faza 4), §6.3 (fakty wejściowe S-04), §6.6 (notatki Faz 1–2)
- Poprzedni slice: `context/archive/2026-09-09-konfiguracja-sklepow/plan.md`
- Faza testów, która przygotowała wejścia: `context/archive/2026-09-10-testing-kontrakty-rekomendacji/plan.md`
- Kontrakt precedencji: `app/Http/Controllers/ShopController.php:19-44`
- Wzorzec reguły w `App\Support`: `app/Support/CategoryResolver.php`, `app/Support/NameComparison.php`

## Deviations taken during implementation

Zgodnie z regułą z `context/foundation/lessons.md` — decyzje podjęte przy
wdrożeniu, których plan nie przewidział, zapisane tutaj, nie tylko w rozmowie.

**Faza 1** (`06f664e`):

- **Piąty test w `ShopRecommendationTest`.** Plan wymieniał cztery; doszedł
  `test_an_empty_shopping_list_has_no_recommendation`. Powód: pusta lista jest
  stanem reguły, nie tylko widoku — bez niego bramka `covered > 0` dla
  zwycięzcy miała dowód wyłącznie przez sklep bez kategorii.
- **Scope przez atrybut `#[Scope]`, nie przez prefiks `scopeInPrecedenceOrder`.**
  Laravel 13 wspiera oba; wybrano atrybut dla zgodności ze stylem modeli w tym
  projekcie (`#[Fillable]`).
- **Próby obalenia wykonane mechanicznie przez agenta**, nie potwierdzone ręcznie
  przez właściciela — wymuszone trybem autonomicznym sesji. Wyniki (cztery próby,
  cztery trafienia) są odtwarzalne: cofnij regułę, uruchom `--filter ShopRecommendationTest`.

**Faza 2**:

- **Bez `trans_choice`.** Plan dopuszczał dwie drogi dla odmiany liczebnika i
  wybrano tańszą: „z N kategorii" stoi w dopełniaczu, którego forma jest ta sama
  dla liczby pojedynczej i mnogiej, więc jeden string obsługuje 1, 2 i 5. Katalog
  `lang/` nie dostał nowego pliku.
- **Weryfikacja 2.8 i 2.10 wykonana sondami**, nie ręcznie: jednorazowy test
  liczący zapytania (4 na `/`, niezależnie od liczby sklepów i produktów) oraz
  jednorazowy test przechodzący ścieżkę dodania produktu (licznik szedł
  „pusta lista" → „1 z 1" → „1 z 2"). Oba pliki usunięte po odczycie — nie
  weszły do zestawu.
- **Weryfikacja 2.9 na szerokości telefonu nie została wykonana w przeglądarce.**
  Panel używa tej samej karty i tych samych klas co lista produktów, a nazwa
  sklepu ma `break-words` — ale to argument z podobieństwa, nie obejrzenie.
  Pozostaje do sprawdzenia przez właściciela.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Faza 1: Reguła pokrycia i konsolidacja precedencji

#### Automated

- [x] 1.1 Pokrycie liczy różne kategorie, nie produkty — 06f664e
- [x] 1.2 Remis rozstrzyga sklep o niższym `id` — 06f664e
- [x] 1.3 Sklep o zerowym pokryciu nie wygrywa ani nie zostaje alternatywą — 06f664e
- [x] 1.4 Alternatywa pojawia się wyłącznie przy pokryciu większym od zera — 06f664e
- [x] 1.5 Cały zestaw zielony — 06f664e
- [x] 1.6 Formatowanie czyste — 06f664e

#### Manual

- [x] 1.7 Próba obalenia: sortowanie po nazwie wywala dokładnie test remisu — 06f664e
- [x] 1.8 Próba obalenia: liczenie produktów wywala dokładnie test pokrycia — 06f664e
- [x] 1.9 `test-plan.md` §6.3 i §3 odzwierciedlają konsolidację klauzuli — 06f664e
- [x] 1.10 `/shops` listuje sklepy w tej samej kolejności co przed refaktorem — 06f664e

### Faza 2: Panel rekomendacji na stronie głównej

#### Automated

- [x] 2.1 Panel pokazuje zwycięzcę i licznik pokrycia — 1fcf0de
- [x] 2.2 Pusta lista pokazuje komunikat zamiast sklepu — 1fcf0de
- [x] 2.3 Brak dopasowania pokazuje komunikat o braku dopasowania — 1fcf0de
- [x] 2.4 Alternatywa widoczna wyłącznie przy pokryciu większym od zera — 1fcf0de
- [x] 2.5 Cały zestaw zielony — 1fcf0de
- [x] 2.6 Formatowanie czyste — 1fcf0de

#### Manual

- [x] 2.7 Próba obalenia każdego z czterech testów panelu — 1fcf0de
- [x] 2.8 Dodanie produktu przeładowuje stronę główną ze zaktualizowaną rekomendacją — 1fcf0de
- [ ] 2.9 Panel czyta się na szerokości telefonu, odmiana liczebnika poprawna dla 1, 2 i 5
- [x] 2.10 Strona główna nie strzela zapytaniem na sklep — 1fcf0de
