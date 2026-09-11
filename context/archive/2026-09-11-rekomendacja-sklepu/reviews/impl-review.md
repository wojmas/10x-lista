<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Rekomendacja sklepu na stronie głównej (S-04)

- **Plan**: `context/changes/rekomendacja-sklepu/plan.md`
- **Scope**: Phase 1–2 of 2 (pełny plan)
- **Date**: 2026-09-11
- **Verdict**: NEEDS ATTENTION → po triage: APPROVED (F1 i F2 naprawione, F3 przyjęte)
- **Findings**: 0 critical, 2 warnings, 1 observation
- **Commits reviewed**: `06f664e`, `1fcf0de`, `922615b`, `e495bd7`

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | WARNING |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

### Evidence behind the PASS verdicts

- **Plan Adherence** — wszystkie 12 zmienionych plików figuruje w planie; zero plików w diffie spoza planu, zero planowanych pozycji bez implementacji. Jedyny dodatek (piąty test reguły) jest udokumentowany w `plan.md` §Deviations zgodnie z regułą z `lessons.md`.
- **Safety & Quality** — całe wyjście przez `{{ }}` (escape'owane); `escape: false` występuje wyłącznie w asercjach testowych, nie w produkcji. Zapytania przez query builder, zero konkatenacji SQL. Sonda licznika zapytań: **4 zapytania na `/`, stałe** przy 8 sklepach i 15 produktach — brak N+1. Brak migracji, brak operacji destrukcyjnych, `category_id` jest NOT NULL (`foreignId()->constrained()`), więc `pluck('category_id')` nie może dać null.
- **Pattern Consistency** — `App\Support` zgodnie z ustalonym wzorcem (`NameComparison`, `CategoryResolver`); testy w `tests/Feature/` z `RefreshDatabase`, jak reszta zestawu; scope przez atrybut `#[Scope]` zgodnie ze stylem modeli (`#[Fillable]`). Pint czysty na 68 plikach.

### Kryteria automatyczne — wykonane

| Komenda | Wynik |
|---|---|
| `phpunit --filter ShopRecommendationTest` | OK (5 testów, 17 asercji) |
| `phpunit --filter HomeRecommendationTest` | OK (4 testy, 17 asercji); po F1: OK (5 testów, 21 asercji) |
| `phpunit` | OK (62 testy, 201 asercji); po triage: OK (63 testy, 205 asercji) |
| `pint --test` | PASS (68 plików) |

### Sondy wykonane na potrzeby tego przeglądu

- **Stabilność sortowania przy 8 sklepach o równym pokryciu**, nazwy w kolejności odwrotnej do `id` → zwycięzca `Zeta` (id 1), alternatywa `Yota` (id 2). Reguła remisu trzyma się przy liczbie remisujących większej niż dwa, czyli tam, gdzie niestabilny algorytm faktycznie by się wysypał. Mechanizm potwierdzony w źródle: `Collection::sortBy()` idzie przez `arsort()`, stabilny od PHP 8.0.
- **Zero sklepów w bazie** → `$ranked[0]` na pustej kolekcji nie rzuca (operator `??` woła `offsetExists()` na `Collection`), `shop` jest null, panel renderuje „Żaden sklep nie pokrywa kategorii z tej listy" z odnośnikiem. Zachowanie poprawne — ale nietestowane, patrz F1.

## Findings

### F1 — Stan „zero sklepów" działa, ale nie ma strażnika w zestawie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `tests/Feature/HomeRecommendationTest.php`, `app/Support/ShopRecommendation.php:82`
- **Detail**: §Desired End State planu wymienia ten stan wprost — „Żaden sklep nie pokrywa ani jednej kategorii z listy **(także gdy sklepów nie ma wcale)**". Sonda potwierdza, że działa. Ale żaden z dziewięciu nowych testów nie uruchamia ścieżki z pustą tabelą `shops`: `test_a_list_nothing_covers_says_so_instead_of_recommending` tworzy jeden sklep, a `test_a_shop_covering_nothing_never_wins` tworzy sklep bez kategorii. Ścieżka `$ranked[0]` na **pustej** kolekcji nie jest wykonywana przez żaden test, więc zamiana `?? 0` na `$ranked[0]['covered']` przeszłaby cały zestaw na zielono i wywaliła stronę główną świeżo założonej instancji — czyli dokładnie pierwszy ekran, jaki zobaczy nowy użytkownik.
- **Fix**: Dopisać jeden test do `HomeRecommendationTest`: brak sklepów w bazie, produkt na liście, asercja na komunikat o braku dopasowania i na odnośnik do `shops.index`.
- **Decision**: FIXED — dopisany test `test_a_list_with_no_shops_at_all_points_at_the_shop_configuration`; próba obalenia (usunięcie `?? 0`) wywala dokładnie ten test i żaden inny.

### F2 — `ShopRecommendation::for()` ma dwa warunki wejściowe, których nie egzekwuje

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: `app/Support/ShopRecommendation.php:56`, `app/Http/Controllers/ProductController.php:38`
- **Detail**: Reguła zakłada dwie rzeczy o argumencie `$shops` i nie sprawdza żadnej: (1) kolekcja przychodzi uporządkowana przez `inPrecedenceOrder()` — bez tego reguła remisu jest cicho zepsuta; (2) relacja `categories` jest eager-loadowana — bez tego linia 68 strzela jednym zapytaniem na sklep. Oba warunki żyją wyłącznie w docblocku.

  To nie jest hipotetyczne. Plan sam nazywa S-05 („Usuwanie kupionych produktów") drugim konsumentem tej reguły, a `ProductController::index()` i `ShopController::index()` już dziś powtarzają ten sam łańcuch `Shop::query()->with('categories')->inPrecedenceOrder()->get()` w dwóch miejscach. Autor S-05, który napisze `ShopRecommendation::for($products, Shop::all())`, dostanie złą rekomendację przy remisie **i** N+1 — a zestaw zostanie zielony, bo wszystkie dziewięć testów podaje kolekcję już uporządkowaną.

  Ironia jest warta odnotowania: cała Faza 1 tego planu polegała na skonsolidowaniu klauzuli precedencji do jednego miejsca, żeby nie rozjechały się dwie kopie. Zapytanie, które tę klauzulę wywołuje, jest teraz w dwóch kopiach.
- **Fix A ⭐ Recommended**: Przenieść pobranie sklepów do reguły — `ShopRecommendation::for(Collection $products)` samo woła `Shop::query()->with('categories')->inPrecedenceOrder()->get()`.
  - Strength: Kasuje oba warunki wejściowe razem z możliwością ich złamania; jeden konsument nie może podać złej kolekcji, bo jej nie podaje. Domyka intencję Fazy 1 na poziomie zapytania, nie tylko klauzuli. `ProductController::index()` schudnie do jednego argumentu.
  - Tradeoff: Reguła sięga do bazy, więc przestaje być czystą funkcją dwóch kolekcji; testy reguły tracą możliwość podania dowolnego zestawu sklepów bez zapisu do bazy — ale one i tak używają `RefreshDatabase` i piszą do bazy, więc realna strata jest niewielka.
  - Confidence: HIGH — `CategoryResolver` w tym projekcie już jest klasą z `App\Support`, która sama odpytuje bazę, więc wzorzec istnieje i nie wprowadza nowego kształtu.
  - Blind spot: Nie sprawdzono, czy S-05 będzie potrzebował przeliczenia na kolekcji sklepów wczytanej wcześniej w tym samym żądaniu (np. dla dwóch różnych list) — wtedy parametr byłby potrzebny.
- **Fix B**: Zostawić sygnaturę i dopisać test, który podaje sklepy w złej kolejności i oczekuje poprawnego wyniku (czyli wymusić sortowanie wewnątrz reguły, nie na wejściu).
  - Strength: Reguła zostaje czystą funkcją swoich argumentów; warunek wejściowy znika przez wymuszenie, nie przez ukrycie zapytania.
  - Tradeoff: Podwójne sortowanie (raz w SQL dla ekranu sklepów, raz w PHP w regule), i nadal nie rozwiązuje warunku o eager-loadzie — N+1 zostaje możliwy.
  - Confidence: MEDIUM — rozwiązuje połowę problemu; druga połowa wraca przy pierwszym konsumencie, który zapomni `with('categories')`.
  - Blind spot: Nie zmierzono, czy sortowanie w PHP po `id` nie wchodzi w konflikt z kontraktem, który Faza 4 planu testów ma zapinać po stronie bazy.
- **Decision**: FIXED via Fix A — `ShopRecommendation::for(Collection $products)` sam czyta sklepy przez `Shop::query()->with("categories")->inPrecedenceOrder()->get()`. Oba warunki wejściowe zniknęły razem ze sposobem ich złamania; `ProductController` stracił import `Shop`; liczba zapytań na `/` bez zmian (4, stałe).

### F3 — Pozycja 2.9 odhaczona na podstawie argumentu ze znacznika, nie z obejrzenia

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: `context/changes/rekomendacja-sklepu/plan.md` §Progress 2.9
- **Detail**: Połowa pozycji („odmiana liczebnika dla 1, 2 i 5") ma twardy dowód — wyjście HTTP pokazało „1 z 1 / 2 z 2 / 3 z 5 kategorii". Druga połowa („panel czyta się na szerokości telefonu") jest odhaczona na podstawie inspekcji klas Tailwinda: brak `whitespace-nowrap`, `min-w-*`, stałej szerokości i `truncate`, `break-words` na obu długich napisach, obecny `<meta name="viewport">`. To rozumowanie, nie obserwacja — nie wyklucza problemu, którego nie da się wyczytać z listy klas (np. przycisk „Dodaj produkt" w nagłówku zawijający się nieładnie obok panelu). Odstępstwo jest uczciwie opisane w `plan.md` §Deviations, więc to nie jest podbicie pieczątki — ale checkbox mówi „zweryfikowane", a dowód jest pośredni.
- **Fix**: Właściciel otwiera `/` na telefonie albo w trybie responsywnym przeglądarki i potwierdza; alternatywnie pozycja zostaje jako świadomie przyjęte ryzyko.
- **Decision**: ACCEPTED — właściciel obejrzy panel na telefonie po swojemu; checkbox 2.9 zostaje odhaczony, a §Deviations opisuje, na czym stoi dowód.
