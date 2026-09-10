---
date: 2026-09-10T13:11:25+0200
researcher: Wojciech Mastej
git_commit: 320ece274dd5b362e11e027bae8b0f4895818d5b
branch: main
repository: 10xdevs
topic: "Faza 1 test-planu — gdzie mieszkają ryzyka #2 (produkt niewidoczny dla innego członka) i #5 (blokada duplikatu)"
tags: [research, codebase, products, validation, name-comparison, test-plan-phase-1]
status: complete
last_updated: 2026-09-10
last_updated_by: Wojciech Mastej
---

# Research: Faza 1 test-planu — ryzyka #2 i #5

**Date**: 2026-09-10T13:11:25+0200
**Researcher**: Wojciech Mastej
**Git Commit**: `320ece274dd5b362e11e027bae8b0f4895818d5b`
**Branch**: `main`
**Repository**: 10xdevs

## Research Question

Faza 1 z `context/foundation/test-plan.md` §3 ma dowieść dwóch rzeczy: że produkt
dodany przez jednego członka jest widoczny dla drugiego (ryzyko #2), i że blokada
duplikatu trafia w obie strony — blokuje to, co rodzina uważa za ten sam produkt,
i przepuszcza to, co uważa za różne (ryzyko #5).

Plan testów świadomie nie wskazuje plików (§1 zasada 3). Ten research ma je
wskazać: gdzie następuje zapis i odczyt, gdzie mieszka definicja równości nazw,
kto ją woła, gdzie są granice transakcji i czego istniejący zestaw nie widzi.

Kontekst z §2 do ugruntowania:

- **#2**: „Gdzie następuje odczyt listy i czy jest zawężony, granice transakcji
  przy zapisie, co realnie odczytuje przekierowanie".
- **#5**: „Jedyna definicja równości nazw i komplet jej wołających; czy
  porównanie odbywa się w aplikacji czy w bazie".

## Summary

**Kod działa.** Obie ścieżki zostały wykonane sondą (patrz *Dowody wykonane*) i
zachowują się tak, jak opisuje PRD. Nie znaleziono błędu na ścieżce szczęśliwej.
Luki są w **zestawie testów**, nie w aplikacji — z trzema wyjątkami, które są
pytaniami o wyrocznię, nie o implementację.

Sześć ustaleń, które zmieniają kształt planu Fazy 1:

1. **Efektywna reguła równości na HTTP to złożenie dwóch rzeczy, nie jedna.**
   `TrimStrings` (globalny middleware Laravela, unicode-owy) przycina ładunek,
   *zanim* walidacja zawoła `NameComparison` (PHP-owy `trim()`, ASCII-owy). Test
   jednostkowy na samym `NameComparison` nie opisuje tego, czego doświadcza
   członek rodziny. To jest właśnie problem wyroczni, przed którym ostrzega §2.
2. **Istniejący test duplikatu nie dowodzi tego, co sugeruje jego nazwa.**
   `AddProductTest:61` wysyła `'  mleko '`, ale middleware przycina to przed
   walidacją — test dowodzi wyłącznie nieczułości na wielkość liter. Przycięcie
   pokrywa `NameComparisonTest:18` na poziomie jednostkowym i nikt tego nie łączy.
3. **Szew ryzyka #2 nie jest pokryty nigdzie.** `AddProductTest` kończy na
   `assertRedirect` i asercji na wierszu w bazie; `ProductListTest` tworzy produkt
   fabryką, nie przez HTTP. Sekwencja „członek A wysyła formularz → członek B
   odświeża stronę główną" nie istnieje w zestawie, mimo że to dokładnie ona jest
   ryzykiem.
4. **Żaden test nie wysyła ładunku w kształcie formularza.** Przeglądarka wysyła
   `category_id=""` przy wpisanej nowej kategorii i `category_id="7"` (tekst) przy
   wybranej z listy. Zestaw wysyła liczbę całkowitą albo pomija pole zupełnie.
   Sonda potwierdza, że oba realne kształty przechodzą — ale nic tego nie pilnuje.
5. **Wewnętrzne odstępy nie są sprowadzane do jednego.** `Mleko  2%` i `Mleko 2%`
   to dziś dwa osobne produkty. Pytanie o wyrocznię dla właściciela, nie błąd.
6. **`Product::latest()` nie ma rozstrzygnięcia remisu.** Przy identycznym
   `created_at` kolejność listy zależy od silnika. Asercja na kolejność produktów
   byłaby testem chwiejnym — Faza 1 nie powinna jej pisać.

## Detailed Findings

### Ryzyko #2 — ścieżka zapisu i odczytu

**Trasy** (`routes/web.php:10-13`) — wszystkie trzy w grupie `auth`:

- `GET /` → `ProductController::index`, nazwa `home`
- `GET /products/create` → `ProductController::create`
- `POST /products` → `ProductController::store`

**Odczyt listy nie jest zawężony i nie może być.** `ProductController::index()`
(`app/Http/Controllers/ProductController.php:23-31`) czyta `Product::query()
->with('category')->latest()->get()` — bez filtra po użytkowniku. Tabela
`products` nie ma kolumny właściciela; migracja
(`database/migrations/2026_09_09_130100_create_products_table.php:19-27`) mówi to
wprost w komentarzu. To jest mechanizm, na którym stoi gwarancja z PRD
§Guardrails, i jednocześnie powód, dla którego ryzyko #2 **nie może** zrealizować
się przez zawężenie zapytania. Jeśli kiedyś się zrealizuje, to przez zapis, nie
przez odczyt.

**Zapis** (`ProductController::store`, linie 47-59) ma trzy kroki:

1. rozstrzygnięcie kategorii — albo `Category::findOrFail($request->integer('category_id'))`,
   albo `CategoryResolver::resolve(...)`;
2. `Product::create([...])`;
3. `redirect()->route('home')`.

**Granice transakcji: nie ma żadnej.** Krok 1 może wstawić wiersz do `categories`
(`CategoryResolver::resolve` → `app/Support/CategoryResolver.php:44`, we własnej
zagnieżdżonej transakcji), krok 2 wstawia wiersz do `products` — osobno. Awaria
między nimi zostawia osieroconą kategorię. To nie jest utrata danych w sensie
§Guardrails (produkt nigdy nie został potwierdzony użytkownikowi, a osierocona
kategoria pojawia się po prostu jako dodatkowa pozycja na liście wyboru), więc
nie warto tego obudowywać transakcją ani testem. Warto natomiast wiedzieć, że
`ProductController` — w odróżnieniu od `ShopController::store`
(`app/Http/Controllers/ShopController.php:52-65`) — transakcji nie otwiera.

**Co realnie odczytuje przekierowanie**: `route('home')` → `index()` → widok
`resources/views/home.blade.php:18-31`. Widok renderuje `$product->name` i
`$product->category->name`, albo stan pusty „Lista zakupów jest pusta". Nazwa
trasy `home` jest nośna — `routes/web.php:8-9` ostrzega, że rozwiązują ją
nawigacja, oba kontrolery uwierzytelniania i trzy testy funkcjonalne.

**Luki w istniejącym zestawie:**

| Plik | Co robi | Czego nie dowodzi |
|---|---|---|
| `tests/Feature/AddProductTest.php:15-26` | POST → `assertRedirect` → `Product::sole()` | Że cokolwiek się wyrenderowało. Dokładnie antywzorzec z §2 („asercja na wiersz w bazie zamiast na wyrenderowaną listę") |
| `tests/Feature/AddProductTest.php:20` | wysyła `'category_id' => $category->id` (int) | Że kształt z przeglądarki przechodzi — drugi antywzorzec z §2 |
| `tests/Feature/ProductListTest.php:33-41` | fabryka tworzy produkt, jeden użytkownik czyta | Że **zapis przez HTTP** przez jednego członka dociera do **innego** członka. Nazwa testu obiecuje więcej, niż test wykonuje |

### Ryzyko #5 — definicja równości i komplet wołających

**Jedyna definicja**: `app/Support/NameComparison.php:19-27`.

```php
public static function normalize(string $name): string
{
    return mb_strtolower(trim($name));
}
```

**Komplet wołających — cztery, wyczerpująco** (`grep -rn NameComparison app database`):

| Wołający | Linia | Co porównuje | Za middlewarem `TrimStrings`? |
|---|---|---|---|
| `StoreProductRequest::notAlreadyOnTheList()` | `app/Http/Requests/StoreProductRequest.php:69` | nazwa produktu vs wszystkie nazwy produktów | tak |
| `StoreShopRequest::notAlreadyConfigured()` | `app/Http/Requests/StoreShopRequest.php:96` | nazwa sklepu vs wszystkie nazwy sklepów | tak |
| `CategoryResolver::findNamed()` | `app/Support/CategoryResolver.php:66` | wpisana kategoria vs wszystkie kategorie | tak (obie ścieżki wołające są HTTP) |
| `CategorySeeder::run()` | `database/seeders/CategorySeeder.php:43` | stała z kodu vs istniejące kategorie | **nie** |

Czwarty wiersz to ten sam trzeci pisarz, którym zajmuje się ustalenie F1 z
przeglądu S-02 — dziś już przez `NameComparison`. Jego wejścia to stałe z
`CategorySeeder::CATEGORIES` (linie 24-35), więc rozbieżność z middlewarem jest
dziś nieosiągalna. Przestanie taka być, jeśli seeder kiedykolwiek zacznie czytać
z zewnątrz.

**Porównanie odbywa się w aplikacji, nie w bazie** — świadomie. Zarówno
`StoreProductRequest.php:53-58` jak i `CategoryResolver.php:56-61` niosą to samo
uzasadnienie: `LOWER()` jest ASCII-owe w SQLite z testów i zależne od locale w
produkcyjnym Postgresie, więc porównanie po stronie bazy zachowywałoby się różnie
w dwóch środowiskach. Próg rewizji: „gdyby lista urosła w tysiące". To ustalenie
jest wejściem dla Fazy 4 — reguła równości jest jednym z niewielu miejsc,
w których zestaw *celowo* nie zależy od silnika.

**Duplikat produktu jest globalny, nie w obrębie kategorii**
(`StoreProductRequest.php:67-69` — `Product::query()->pluck('name')` bez
zawężenia). Wykonane: `Mleko` w kategorii Nabiał blokuje `Mleko` w kategorii
Napoje. Decyzja nie jest nigdzie zapisana ani przetestowana.

**Efektywna reguła na HTTP to złożenie dwóch przycięć.** Globalny middleware
`TrimStrings` (`vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:461`,
grupa globalna) woła `Str::trim`, które usuwa również unicode'owe odstępy —
NBSP, BOM, spację zerowej szerokości. Dopiero potem walidacja woła
`NameComparison`, którego `trim()` jest ASCII-owy. Skutki:

- na poziomie jednostkowym `NameComparison::matches('mleko', "mleko\u{00A0}")`
  daje **false**;
- na poziomie HTTP ten sam ładunek zostaje **odrzucony jako duplikat**, bo
  middleware zdążył usunąć NBSP.

To jest dokładnie problem wyroczni z §2: pary oczekiwań wyprowadzone z
implementacji `normalize()` opisują coś innego niż to, czego doświadcza rodzina.
Test jednostkowy pinuje regułę; dopiero test integracyjny pinuje zachowanie.

**Skutek dla istniejącego testu**: `AddProductTest:61` wysyła `'  mleko '` i
oczekuje odrzucenia. Middleware przycina to do `'mleko'`, więc test przechodzi
przez samo `mb_strtolower`. Usunięcie `trim()` z `NameComparison` nie zepsuje
tego testu — zepsuje tylko `NameComparisonTest:18`. Nazwa testu obiecuje
pokrycie przycięcia na ścieżce HTTP, którego test nie daje.

### Dowody wykonane

Wszystkie poniższe uzyskano sondą (jednorazowy plik testowy, uruchomiony przez
`docker compose exec app php vendor/bin/phpunit`, usunięty po odczytaniu). Nie
są to wnioski z lektury frameworka.

| # | Co sprawdzono | Wynik |
|---|---|---|
| A | `POST /products` z `category_id=''` + `new_category='Nabiał'` (kształt przeglądarki, nowa kategoria) | 302, 1 produkt, 0 błędów |
| B | `POST /products` z `category_id='7'` (tekst) + `new_category=''` | 302, 1 produkt, 0 błędów |
| C | to samo z podążeniem za przekierowaniem | 200, `Mleko` widoczne na liście |
| D | duplikat z `from('/products/create')` + podążenie | 200, nadal 1 produkt, komunikat „już na liście" wyrenderowany |
| E | `name = '   Mleko   '` | zapisane jako `Mleko` — middleware przyciął przed walidacją i przed zapisem |
| F | `Mleko` w Nabiał, potem `Mleko` w Napoje | odrzucone, 1 produkt — blokada jest globalna |
| G | `Mleko  2%` (dwie spacje) i `Mleko 2%` | oba przyjęte, 3 produkty |
| H | `NameComparison::matches` NFC `bąk` vs NFD `ba̧k` | false |
| I | `NameComparison::matches('mleko', "mleko\u{00A0}")` | false |
| K | członek A wysyła POST, członek B robi `GET /` | 200, `Mleko` widoczne |
| L | `POST` z `"Mleko\u{00A0}"` przy istniejącym `Mleko` | odrzucone jako duplikat; `Str::trim` usuwa NBSP |
| M | dwa produkty z identycznym `created_at` | SQL: `order by "created_at" desc`, bez rozstrzygnięcia remisu |
| N | `category_id='0'` i `category_id='999'` | oba odrzucone przez `exists`, 0 produktów |

Stan wyjściowy zestawu przed Fazą 1: **49 testów, 150 asercji, zielone.**

### Gdzie mieszkają warstwy (wejście do §6 cookbooka)

| Warstwa | Katalog | Klasa bazowa | Baza |
|---|---|---|---|
| jednostkowa | `tests/Unit/` | `PHPUnit\Framework\TestCase` (`tests/Unit/NameComparisonTest.php:6`) | brak — czysty PHP, bez kontenera Laravela |
| integracyjna | `tests/Feature/` | `Tests\TestCase` + `RefreshDatabase` | SQLite `:memory:` z `phpunit.xml:26-27` |

`tests/Feature/` mieści też testy, które nie idą przez HTTP —
`CategoryResolverTest` i `CategorySeederTest` wołają kod wprost, ale potrzebują
bazy. Konwencja projektu jest więc „Unit = bez aplikacji", nie „Unit = jedna
klasa". Warto to zapisać w §6.1, bo naiwne czytanie nazw katalogów sugeruje co
innego.

Fabryki: `ProductFactory` (`database/factories/ProductFactory.php:19-25`) tworzy
kategorię przez `Category::factory()`, więc `Product::factory()->create()` bez
argumentów mnoży kategorie — istotne, gdy test liczy kategorie. Obie fabryki
używają `fake()->unique()->word()`, czyli nazwy nie są kontrolowane, dopóki się
ich nie poda jawnie.

## Code References

- `routes/web.php:7-18` — cała grupa `auth`; trzy trasy produktowe
- `app/Http/Controllers/ProductController.php:23-31` — odczyt listy, brak zawężenia (ryzyko #2)
- `app/Http/Controllers/ProductController.php:47-59` — zapis, brak transakcji obejmującej oba wstawienia
- `app/Http/Requests/StoreProductRequest.php:27-31` — reguły walidacji, w tym `prohibits:new_category`
- `app/Http/Requests/StoreProductRequest.php:60-75` — reguła duplikatu, globalna, w PHP (ryzyko #5)
- `app/Support/NameComparison.php:19-27` — jedyna definicja równości nazw
- `app/Support/CategoryResolver.php:24-50` — rozstrzyganie kategorii + odzyskanie po wyścigu (SAVEPOINT)
- `database/seeders/CategorySeeder.php:37-53` — czwarty wołający, poza middlewarem
- `database/migrations/2026_09_09_130100_create_products_table.php:19-27` — brak kolumny właściciela, `restrictOnDelete`
- `resources/views/home.blade.php:18-31` — to, co realnie renderuje przekierowanie
- `resources/views/products/create.blade.php:23-31` — `<option value="">` czyli źródło `category_id=''`
- `tests/Feature/AddProductTest.php:55-65` — test duplikatu, który nie dowodzi przycięcia
- `tests/Feature/ProductListTest.php:33-41` — widoczność dowodzona fabryką, nie zapisem HTTP
- `tests/Unit/NameComparisonTest.php:10-33` — wzorzec testu jednostkowego (§6.1)
- `vendor/laravel/framework/src/Illuminate/Foundation/Configuration/Middleware.php:461-462` — `TrimStrings` i `ConvertEmptyStringsToNull` w grupie globalnej
- `vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php:2311-2322` — `prohibits` nie działa, gdy pole jest puste; stąd `category_id=''` przechodzi

## Architecture Insights

- **Middleware jest częścią reguły biznesowej, choć nikt tego nie zadeklarował.**
  `TrimStrings` + `ConvertEmptyStringsToNull` sprawiają, że formularz w kształcie
  przeglądarki działa (`category_id=''` → `null` → `prohibits` nie strzela) i że
  duplikat z NBSP jest łapany. Żaden komentarz w `app/` o tym nie mówi. To jest
  najcieńsze miejsce w tej fazie: zmiana `bootstrap/app.php` mogłaby po cichu
  zmienić regułę duplikatu, a dziś nic by tego nie zauważyło.
- **Trzy „jedyne definicje" trzymają się dyscypliny.** `NameComparison`,
  `CategoryResolver` i uporządkowanie sklepów po `id` są jednomiejscowe i
  udokumentowane, każde z nazwanym powodem i progiem rewizji. Faza 1 powinna to
  pinować, nie przebudowywać.
- **`Product::latest()` vs `Shop::orderBy('id')`.** Sklepy uporządkowane są po
  `id`, bo S-03 uznał `created_at` za remisujące (`plan-brief.md` §Key Decisions).
  Produkty tego ustalenia nie dostały — `latest()` sortuje po `created_at` bez
  rozstrzygnięcia remisu. Dla listy zakupów to nie jest błąd (kolejność nie jest
  kontraktem), ale jest pułapką na test: asercja na kolejność byłaby chwiejna
  między silnikami. Faza 1 nie pisze takiej asercji; §6.6 powinno to zapamiętać.
- **Wszystkie cztery komunikaty walidacji są po polsku i renderują się w widoku.**
  Sonda D potwierdza, że komunikat duplikatu dociera na ekran. Asercja na
  komunikat (nie na klucz błędu) jest tu tania i mocniejsza.

## Historical Context (from prior changes)

- `context/archive/2026-09-09-wspolna-lista-produktow/reviews/impl-review.md` — F1:
  `CategorySeeder` był trzecim pisarzem kategorii i pomijał `NameComparison`,
  co rozdwajało słownik. Naprawione, pokryte `CategorySeederTest`. To jest źródło
  ryzyka #3 i powód, dla którego lista wołających w tym researchu jest wyczerpująca.
- Ten sam przegląd, F3: `prohibits:new_category` działał, ale nic go nie pinowało —
  dopisano `test_filling_both_category_fields_is_rejected`. Wzorzec do powtórzenia:
  reguła zweryfikowana sondą i natychmiast przypięta testem.
- `context/archive/2026-09-09-konfiguracja-sklepow/reviews/impl-review.md` — F1
  (krytyczne): odzyskanie po wyścigu w `CategoryResolver` było martwe wewnątrz
  transakcji na Postgresie. **Awaria odtworzona wykonaniem, nie przewidziana** —
  to jedyne takie ustalenie w projekcie i całe źródło ryzyka #6.
- Ten sam przegląd, F6: `AddShopTest` przypadek 1 asercjował trwałość, nie listę;
  poprawiony na podążenie za przekierowaniem. **`AddProductTest` ma dziś dokładnie
  tę samą wadę i nie został poprawiony** — Faza 1 domyka tę asymetrię.
- Ten sam przegląd, F4, notatka narzędziowa warta przeniesienia do §6.2:
  `assertSessionHasErrors()` postarza dane flash, więc wywołanie go **przed**
  `followRedirects()` czyni asercję pustą. Kolejność ma znaczenie.
- `context/foundation/lessons.md` — odstępstwa od planu podjęte w trakcie
  implementacji dopisuje się do `plan.md` przed commitem fazy.

## Related Research

Brak wcześniejszych `research.md` w tym repozytorium — to pierwszy. Najbliższe
odpowiedniki to sekcje *Key Discoveries* w archiwalnych planach:
`context/archive/2026-09-09-wspolna-lista-produktow/plan.md` i
`context/archive/2026-09-09-konfiguracja-sklepow/plan.md`.

## Open Questions

Trzy pytania o wyrocznię — o to, co rodzina uważa za ten sam produkt. §2 mówi
wprost, że odpowiedzi nie wolno wyprowadzić z implementacji `normalize()`.
Plan Fazy 1 potrzebuje ich rozstrzygnięcia, zanim napisze pary oczekiwań.

1. **`Mleko  2%` (dwie spacje) i `Mleko 2%` — jeden produkt czy dwa?** Dziś dwa
   (dowód G). Jeśli jeden, `normalize()` musi sprowadzać wewnętrzne odstępy do
   pojedynczej spacji — jednolinijkowa zmiana, ale zmienia regułę dla wszystkich
   czterech wołających, w tym dla kategorii i sklepów.
2. **`Mleko` w kategorii Nabiał i `Mleko` w kategorii Napoje — jeden produkt czy
   dwa?** Dziś jeden, drugi odrzucony (dowód F). Wygląda na zamierzone (lista
   zakupów jest jedna), ale nie jest nigdzie zapisane ani przetestowane.
3. **Czy warianty NFD w ogóle nas obchodzą?** `bąk` zapisane jako `a` + ogonek
   nie równa się `bąk` prekomponowanemu (dowód H). Klawiatura telefonu produkuje
   NFC, więc ścieżka jest osiągalna praktycznie tylko przez wklejenie. Kandydat
   do §7 („czego świadomie nie testujemy") raczej niż do testu.

Poza wyrocznią jedno pytanie inżynierskie, nie blokujące Fazy 1:

4. Czy zależność reguły duplikatu od `TrimStrings` ma zostać udokumentowana w
   `NameComparison` (komentarz) i przypięta testem integracyjnym na NBSP, czy
   uznajemy ją za szczegół frameworka? Sonda L pokazuje, że dziś działa na naszą
   korzyść.
