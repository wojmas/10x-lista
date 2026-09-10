---
date: 2026-09-10T14:14:01+0200
researcher: Wojciech Mastej
git_commit: a570c06124f2b42491816fe8333d4cadb4309d8c
branch: main
repository: 10x-lista
topic: "Kontrakty rekomendacji przed S-04 — ugruntowanie ryzyk #1 i #3"
tags: [research, codebase, testing, rekomendacja, kategorie, sklepy, sqlite-postgres]
status: complete
last_updated: 2026-09-10
last_updated_by: Wojciech Mastej
---

# Research: Kontrakty rekomendacji przed S-04

**Date**: 2026-09-10T14:14:01+0200
**Researcher**: Wojciech Mastej
**Git Commit**: `a570c06124f2b42491816fe8333d4cadb4309d8c`
**Branch**: `main`
**Repository**: `10x-lista`

## Research Question

Ugruntowanie Fazy 2 wdrożenia z `context/foundation/test-plan.md`:

- **Ryzyko #1** — rekomendacja wskazuje inny sklep, niż wynika z reguły: remis rozstrzygnięty niedeterministycznie albo kategoria policzona dwukrotnie.
- **Ryzyko #3** — kategoria rozdwaja się na wariancie zapisu, więc sklep pokrywa połowę asortymentu i cicho przegrywa rekomendację.

Do zakwestionowania: „posortowane znaczy deterministyczne", „liczba dopasowań równa się liczbie różnych kategorii", „formularz jest jedynym pisarzem". Zakres to wyłącznie kontrakty — sama reguła rekomendacji nie istnieje i należy do S-04.

## Summary

Research wykonał obie ścieżki sondą (siedem plików testowych, uruchomionych na SQLite **i** na Postgresie w osobnej bazie `probe_test`), po czym sondy usunął. Wnioski:

1. **Ryzyko #1, połowa „remis" — potwierdzone, gorzej niż zakładał plan.** Jedyny dzisiejszy test, który twierdzi, że pinuje precedencję sklepów (`tests/Feature/ShopListTest.php:45`), **przechodzi po usunięciu `orderBy('id')` z kontrolera — na obu silnikach**. To test przechodzący z niewłaściwego powodu, dokładnie ta klasa, którą §6.6 planu każe tropić. Realna rozbieżność istnieje i jest odtwarzalna, ale **wyłącznie na Postgresie**: po zmianie nazwy sklepu (kolumna indeksowana → non-HOT update) nieuporządkowany odczyt zwraca sklep dodany pierwszy **na końcu**. Na SQLite ta sama sekwencja nie zmienia niczego. Konsekwencja dla planu: **kontraktu remisu nie da się dowieść na sterowniku, na którym biegnie dzisiejszy zestaw.**

2. **Ryzyko #1, połowa „kategoria policzona dwukrotnie" — zabezpieczone strukturalnie, ale nietestowane.** Unikalność `(shop_id, category_id)` trzyma na obu silnikach (`UniqueConstraintViolationException`), a `sync()` w formularzu zwija duplikaty, w tym przypadek „zaznaczone «Nabiał» + wpisane «NABIAŁ»" → jedna kategoria, jedno przypisanie. Żaden test tego nie pinuje. Tanie, deterministyczne, niezależne od silnika — najlepszy stosunek koszt × sygnał w tej fazie.

3. **Ryzyko #3 — w większości już pokryte; „każdy pisarz" to dziś dwa pisarze, nie trzy.** W kodzie produkcyjnym kategorie zapisują dokładnie dwa miejsca: `CategoryResolver::resolve()` (obsługuje oba formularze) i `CategorySeeder`. Oba idą przez `NameComparison`, oba mają testy. Trzeci pisarz z przeglądu S-02 został **skonsolidowany** — ryzyko #3 w formie zapisanej w planie jest już w dużej mierze zamknięte. Zostaje jedna realna luka: konsekwencja pokrycia sklepu przy kolizji „checkbox + wpisana nazwa" (pkt 2 wyżej).

4. **Wskazówka z §6.6 o `Product::latest()` — prawdziwa, ale nie dotyczy ryzyka #1.** `order by "created_at" desc` bez klucza wtórnego faktycznie nie rozstrzyga remisu, ale rekomendacja liczy **różne kategorie** z listy, a nie kolejność produktów. Kolejność produktów wpływa wyłącznie na wygląd listy. Wniosek: nie ciągnąć tego do Fazy 2.

## Detailed Findings

### A. Precedencja sklepów — kontrakt istnieje w jednym miejscu i nie jest wielokrotnego użytku

Reguła biznesowa (`context/foundation/prd.md` §Business Logic): przy remisie wygrywa sklep dodany jako pierwszy. Migracja świadomie nie dodaje kolumny `position` i wskazuje klucz główny jako nośnik tego kontraktu:

- `database/migrations/2026_09_10_100000_create_shops_table.php:18-22` — *„There is deliberately no position column. […] the primary key is strictly increasing, so id order is that contract. Sorting by created_at would tie again […]"*

Kontrakt jest jednak zapisany **inline w kontrolerze**, nie jako scope ani metoda modelu:

- `app/Http/Controllers/ShopController.php:26-29` — `Shop::query()->with('categories')->orderBy('id')->get()`
- `app/Models/Shop.php:22-25` — relacja `categories()` bez żadnego uporządkowania

Nic nie zmusza S-04 do użycia tej samej klauzuli. Jeśli S-04 napisze własne zapytanie (`Shop::all()`, `withCount(...)->orderByDesc(...)`), kontrakt milcząco zniknie.

### B. Falsyfikacja: test precedencji przechodzi bez uporządkowania

`tests/Feature/ShopListTest.php:45-58` deklaruje w docblocku, że pilnuje precedencji S-04. Próba obalenia (usunięcie `orderBy('id')` z `ShopController::index()`, przywrócone po sondzie):

```
### FALSIFICATION: orderBy('id') removed from ShopController::index()
--- SQLite ---   OK (4 tests, 11 assertions)
--- Postgres --- OK (4 tests, 11 assertions)
```

Test przechodzi na obu silnikach mimo usuniętej reguły. Powód: `select * from shops` bez `ORDER BY` zwraca na świeżej tabeli kolejność wstawienia — w SQLite jako skan po `rowid`, w Postgresie jako seq scan po świeżym stercie. Asercja `assertSeeInOrder(['Zeta', 'Alfa'])` nie ma jak upaść.

### C. Kiedy nieuporządkowany odczyt naprawdę się rozjeżdża

Sonda przeszła po wariantach odczytu, przy `orderBy('id')` nadal usuniętym. Trzy sklepy w kolejności `Zeta`(id 1), `Alfa`(id 2), `Mika`(id 3), następnie zmiana nazwy pierwszego przez `DB::table('shops')->where('id', …)->update(['name' => 'Zeta II'])`:

| | przed zmianą nazwy | po zmianie nazwy |
|---|---|---|
| SQLite | `Zeta, Alfa, Mika` | `Zeta II, Alfa, Mika` |
| Postgres | `Zeta, Alfa, Mika` | **`Alfa, Mika, Zeta II`** |

Na Postgresie sklep dodany pierwszy ląduje na końcu, bo `name` jest kolumną indeksowaną (unique), więc UPDATE nie jest HOT i nowa krotka dopisuje się na koniec sterty. Rozstrzygnięcie remisu bez jawnego `ORDER BY` wskazałoby wtedy **sklep dodany drugi**, bez żadnego błędu.

Sonda na samym rankingu potwierdziła to na kształcie zapytania, które S-04 najpewniej napisze:

```
PROBE tie before-update              = PierwszySklep:2, DrugiSklep:2
PROBE tie after-update  (pgsql)      = DrugiSklep:2, PierwszySklep!:2      ← remis odwrócony
PROBE tie after-update  (sqlite)     = PierwszySklep!:2, DrugiSklep:2
PROBE tie after-update + orderBy(id) = PierwszySklep!:2, DrugiSklep:2      ← stabilne na obu
```

Dodatkowy wariant tej samej pułapki, widoczny **tylko** na SQLite: `Shop::query()->pluck('name')` (czyli `select name from shops`) zwraca kolejność **alfabetyczną**, bo planista używa unikalnego indeksu na `name` jako indeksu pokrywającego. `select *` zwraca kolejność wstawienia. Czyli „nieuporządkowane" nie ma jednej kolejności nawet w obrębie jednego silnika — zależy od wybranych kolumn.

**To jest konkretne obalenie założenia „posortowane znaczy deterministyczne"**: deterministyczna jest wyłącznie jawna klauzula `orderBy('id')`, a jej brak daje wynik, który wygląda poprawnie w każdym dzisiejszym teście.

Uwaga o osiągalności: dziś żaden ekran nie zmienia nazwy sklepu — S-06 (`edycja-i-usuwanie-sklepow`, `context/foundation/roadmap.md:40`) dopiero to wprowadzi. Rozbieżność jest więc **utajona, nie osiągalna z UI**. Sam `touch()` (aktualizacja tylko `updated_at`, kolumna nieindeksowana → HOT update) kolejności nie zmienił na żadnym silniku.

### D. Podwójne policzenie kategorii — trzyma, nietestowane

Sonda, oba silniki:

```
PROBE pivot-double-attach: Illuminate\Database\UniqueConstraintViolationException
PROBE sync-dup-ids rows=1
PROBE sync-again  rows=1
PROBE checkbox+typed-same: categories=1 pivot=1 shopcats=1
```

- `database/migrations/2026_09_10_100100_create_category_shop_table.php:23` — `$table->unique(['shop_id', 'category_id'])`, z docblockiem nazywającym to wprost inwariantem danych dla S-04: *„without it S-04 would count the same category twice and recommend the wrong shop, silently"*.
- `app/Http/Controllers/ShopController.php:58-64` — `sync()` zwija powtórzone id niezależnie od ograniczenia.

Ścieżka realna i nietestowana: formularz sklepu **dopuszcza** jednoczesne zaznaczenie checkboxa i wpisanie nazwy (`app/Http/Requests/StoreShopRequest.php:34-36` — brak `prohibits`), inaczej niż formularz produktu, który to zabrania (`app/Http/Requests/StoreProductRequest.php:29` — `prohibits:new_category`). Gdy oba wskazują tę samą kategorię w innym zapisie („Nabiał" + „NABIAŁ"), `CategoryResolver` zwraca to samo id, a `sync()` zwija je do jednego przypisania. Działa — i nic tego nie pilnuje.

Drugie założenie do obalenia, „liczba dopasowań równa się liczbie różnych kategorii", dotyczy strony produktów: trzy produkty w dwóch kategoriach dają `products=3 distinct-categories=2`. To jednak wejście reguły, której jeszcze nie ma — należy do S-04, nie do tej fazy.

### E. Pisarze kategorii — trzeci pisarz już nie istnieje

Pełny przegląd zapisów do `categories` i do pivota w `app/`, `database/`, `routes/`:

| Pisarz | Lokalizacja | Idzie przez `NameComparison`? | Test |
|---|---|---|---|
| `CategoryResolver::resolve()` | `app/Support/CategoryResolver.php:44` | tak, przez `findNamed()` (`:62-67`) | `tests/Feature/CategoryResolverTest.php` |
| `CategorySeeder` | `database/seeders/CategorySeeder.php:50` | tak (`:42-44`) | `tests/Feature/CategorySeederTest.php` |
| formularz produktu | `app/Http/Controllers/ProductController.php:51` | pośrednio, przez resolver | `tests/Feature/AddProductTest.php:88` |
| formularz sklepu | `app/Http/Controllers/ShopController.php:61` | pośrednio, przez resolver | `tests/Feature/AddShopTest.php:68` |
| pivot `category_shop` | `app/Http/Controllers/ShopController.php:64` (jedyny pisarz) | n/d | **brak** |
| `CategoryFactory` | `database/factories/CategoryFactory.php:20` | nie — ale tylko testy | n/d |

Wniosek: **„formularz jest jedynym pisarzem" było prawdziwym zarzutem w S-02 i zostało już naprawione przez konsolidację w `CategoryResolver`.** Dwaj dzisiejsi pisarze produkcyjni oba wołają jedną regułę i oba mają test. Ryzyko #3 w brzmieniu z §2 planu jest w istotnej części **historyczne** — plan powinien to odnotować, zamiast zamawiać testy na nieistniejącą lukę.

### F. Sklep bez kategorii — dozwolony w bazie, odrzucany przez formularz

`database/factories/ShopFactory.php:14-17` mówi wprost, że sklep bez kategorii jest w bazie legalny; odrzuca go dopiero `StoreShopRequest` (`:34`, `required_without:new_category`), co pinuje `AddShopTest::test_a_shop_without_any_category_is_rejected`. S-04 musi więc obsłużyć sklep o zerowym pokryciu, mimo że formularz takiego nie utworzy — S-06 (usuwanie kategorii) może go wyprodukować. To fakt wejściowy dla S-04, nie luka do zamknięcia dziś.

## Code References

- `app/Http/Controllers/ShopController.php:26-29` — jedyne miejsce z kontraktem precedencji (`orderBy('id')`), inline w kontrolerze
- `app/Http/Controllers/ShopController.php:58-64` — `sync()` zwijające duplikaty; jedyny pisarz pivota
- `app/Http/Requests/StoreShopRequest.php:34-36` — brak `prohibits`, więc checkbox i wpisana nazwa mogą przyjść razem
- `app/Http/Requests/StoreProductRequest.php:29` — `prohibits:new_category`, przeciwna decyzja na formularzu produktu
- `app/Support/CategoryResolver.php:24-50` — jedyna ścieżka find-or-create, z odzyskiwaniem po wyścigu przez SAVEPOINT
- `app/Support/NameComparison.php:41-49` — jedyna definicja równości nazw
- `database/migrations/2026_09_10_100000_create_shops_table.php:18-22` — uzasadnienie „id jest kontraktem precedencji"
- `database/migrations/2026_09_10_100100_create_category_shop_table.php:23` — `unique(['shop_id','category_id'])` jako inwariant dla S-04
- `database/seeders/CategorySeeder.php:37-53` — drugi (i ostatni) pisarz kategorii
- `tests/Feature/ShopListTest.php:45-58` — test precedencji przechodzący z niewłaściwego powodu
- `tests/Feature/CategoryResolverTest.php:28-47` — kontrakt SAVEPOINT, świadomie niewykonalny na SQLite
- `context/foundation/roadmap.md:129` — S-04 sam nazywa stabilną kolejność sklepów swoją zależnością

## Architecture Insights

- **Kontrakty S-04 są dziś udokumentowane w komentarzach, nie w asercjach.** Trzy pliki (obie migracje, kontroler sklepów) opisują w docblockach, czego S-04 będzie potrzebował. Docblock nie upada, gdy ktoś usunie `orderBy('id')`.
- **Powtarzający się wzorzec „porównuj w PHP, nie w SQL"** (`StoreProductRequest:59-64`, `StoreShopRequest:76-78`, `CategoryResolver:54-60`) to świadoma obrona przed rozjazdem SQLite/Postgres na `LOWER()`. Ten sam rozjazd wraca tutaj od drugiej strony — tym razem dotyczy kolejności wierszy i **nie da się go obejść w PHP**, bo kolejność powstaje w bazie.
- **`CategoryResolverTest:28` to precedens dla testu, który nie może upaść na sterowniku zestawu.** Projekt już raz zaakceptował taki test, opisując wprost dlaczego. To gotowy wzorzec dla kontraktu remisu — pod warunkiem, że plan nazwie ten kompromis, zamiast udawać dowód.

## Historical Context (from prior changes)

- `context/archive/2026-09-09-konfiguracja-sklepow/plan.md` — S-03 uporządkował sklepy po `id` właśnie pod tie-break S-04; decyzja o braku kolumny `position` pochodzi stamtąd.
- `context/archive/2026-09-09-wspolna-lista-produktow/reviews/impl-review.md` — źródło ryzyka #3 (seeder jako trzeci pisarz dokładający wariant różniący się wielkością liter). Naprawa przez konsolidację w `CategoryResolver` jest już w kodzie.
- `context/archive/2026-09-09-konfiguracja-sklepow/reviews/impl-review.md` ustalenie F1 — wcześniejszy przypadek rozjazdu SQLite/Postgres (przerwanie transakcji), ten sam mechanizm co ryzyko #6.
- `context/foundation/test-plan.md` §6.6 (Faza 1) — notatka o `Product::latest()` i o próbie obalenia jako obowiązkowym kroku; oba zastosowane w tym researchu.

## Weryfikacja wskazówek z planu

| Wskazówka z §2 Risk Response Guidance | Werdykt |
|---|---|
| #1: „posortowane znaczy deterministyczne" do zakwestionowania | **Potwierdzona i wzmocniona.** Brak `ORDER BY` daje wynik zależny od silnika, od tego czy był UPDATE indeksowanej kolumny, i od wybranych kolumn. |
| #1: najtańsza warstwa — jednostkowa/integracyjna na regule, dane kontrolne z realnym remisem | **Skorygowana.** Reguła jeszcze nie istnieje, więc testować można tylko zapytanie sklepów. Dane kontrolne z realnym remisem są konieczne, ale **niewystarczające** — na SQLite test i tak nie upadnie. |
| #1: antywzorzec „dane, w których każdy sklep ma inny wynik" | **Potwierdzony i już obecny**: `ShopListTest:45` ma poprawne dane, a mimo to nie upada. Antywzorcem jest tu brak silnika, nie brak remisu. |
| #1: „liczba dopasowań równa się liczbie różnych kategorii" | **Potwierdzona jako założenie do pilnowania**, ale po stronie sklepu jest już zabezpieczona przez unikalny indeks pivota; po stronie produktów należy do S-04. |
| #3: „formularz jest jedynym pisarzem — trzecim był seeder" | **Nieaktualna.** Trzeci pisarz został skonsolidowany; dziś są dwaj, obaj przez `NameComparison`, obaj otestowani. |
| #3: najtańsza warstwa — integracyjna przez każdego pisarza | **Skorygowana** — to już istnieje. Zostaje wyłącznie konsekwencja pokrycia przy kolizji checkbox + wpisana nazwa. |
| §6.6: `Product::latest()` nie rozstrzyga remisu | **Potwierdzona co do faktu** (`select * from "products" order by "created_at" desc`), **odrzucona co do znaczenia** dla ryzyka #1 — rekomendacja liczy kategorie, nie kolejność produktów. |

## Najtańsza warstwa dla każdej luki

| Luka | Warstwa | Upada dziś? |
|---|---|---|
| Pivot nie przyjmuje dwa razy tej samej pary sklep–kategoria | integracyjna, bez HTTP | tak, na obu silnikach |
| Checkbox + wpisana nazwa wskazujące tę samą kategorię dają jedno przypisanie | integracyjna po HTTP, ładunek w kształcie formularza | tak, na obu silnikach |
| Sklepy wracają w kolejności dodania także po zmianie nazwy | integracyjna | **tylko na Postgresie** |
| Sklep o zerowym pokryciu jest w bazie legalny | integracyjna, bez HTTP | tak, na obu silnikach |

## Open Questions

1. **Czy Faza 2 pociąga Fazę 4?** Kontraktu remisu nie da się dowieść na SQLite. Trzy wyjścia, decyzja właściciela: (a) przesunąć Fazę 4 przed Fazę 2 albo wykonać jej wąski wycinek — przebieg samych testów kontraktowych na Postgresie; (b) napisać test remisu ze świadomą adnotacją „nie może upaść na sterowniku zestawu", wzorem `CategoryResolverTest:28`; (c) uznać, że dopóki nie ma S-06, rozbieżność jest nieosiągalna, i odłożyć.
2. **Czy precedencja sklepów ma zostać wyniesiona z kontrolera do scope'u modelu?** Dziś test nie może zmusić S-04 do użycia tej samej klauzuli. Scope (`Shop::inPrecedenceOrder()`) dałby jedno miejsce do zapinowania — ale to zmiana kodu produkcyjnego, a faza jest opisana jako czysto testowa. Rozstrzygnięcie należy do planu.
3. **Czy przepisać ryzyko #3 w §2 planu?** Jego uzasadnienie („trzecim pisarzem był seeder") opisuje stan sprzed konsolidacji. Kandydat na backport do §2 — patrz sekcja niżej.
4. **Czy formularz sklepu ma dopuszczać checkbox i wpisaną nazwę jednocześnie?** Formularz produktu tego zabrania (`prohibits`), formularz sklepu nie. Rozbieżność jest prawdopodobnie zamierzona (sklep ma wiele kategorii, produkt jedną), ale nigdzie nie zapisana — a to ona tworzy ścieżkę podwójnego policzenia.

## Kandydaci do backportu w `test-plan.md` §2

- **Ryzyko #3 — korekta uzasadnienia**: trzeci pisarz nie istnieje od S-03; ryzyko zostaje jako ochrona przed regresją konsolidacji, nie jako otwarta luka.
- **Ryzyko #1 — korekta wskazówki reakcji**: „dane kontrolne z realnym remisem" są konieczne, ale niewystarczające; bez przebiegu na Postgresie test nie może upaść. Wiąże ryzyko #1 z ryzykiem #6, które plan trzyma osobno w Fazie 4.
- **Ryzyko #1 — usunąć wskazówkę o `Product::latest()`** jako trop dla tego ryzyka (zostaje jako notatka o wyglądzie listy w §6.6).
