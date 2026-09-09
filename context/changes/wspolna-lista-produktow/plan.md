# Wspólna lista produktów — Implementation Plan

## Overview

Realizacja S-02 z `context/foundation/roadmap.md`: członek rodziny dodaje produkt z nazwą i kategorią, a na stronie głównej widzi wspólną listę produktów całej rodziny. To pierwszy kawałek domenowy w projekcie — powstaje tu model kategorii, który S-03 przypisze sklepom, a S-04 wykorzysta w regule rekomendacji.

Pokrywa FR-004 (lista na stronie głównej) i FR-005 (dodawanie z nazwą i kategorią). Usuwanie (FR-006) zostaje w S-05, zgodnie z roadmapą.

## Current State Analysis

Po S-01 aplikacja ma działające uwierzytelnianie i chronioną, polską stronę główną — ale ani jednej tabeli domenowej.

**Co już jest i czego nie trzeba budować:**

- **Chroniona strona główna** — `Route::view('/', 'home')->middleware('auth')->name('home')` (`routes/web.php:5`). Niezalogowany trafia na `/login`; pokryte przez `tests/Feature/ExampleTest.php`.
- **Miejsce na listę** — `resources/views/home.blade.php` zawiera pusty stan „Lista zakupów jest pusta. Nikt nie dodał jeszcze żadnego produktu." wewnątrz `<x-app-layout>` z nagłówkiem. Ten fragment ten plan wypełnia.
- **Komponenty formularza** — `input-label`, `text-input`, `input-error` i `primary-button` w `resources/views/components/`, używane przez `resources/views/auth/login.blade.php`. Formularz produktu składa się z nich bez pisania nowych komponentów, poza kontrolką wyboru kategorii.
- **Wzorzec walidacji** — `app/Http/Requests/Auth/LoginRequest.php` pokazuje przyjętą w projekcie formę: FormRequest z `rules()` i własnymi komunikatami, tłumaczenia w `lang/pl/validation.php`.
- **Polska lokalizacja** — `lang/pl.json` na teksty przez `__()`, `lang/pl/validation.php` z przetłumaczonymi regułami `required`, `string`, `max`, `unique` i sekcją `attributes`. `APP_LOCALE=pl` w `.env.example`.
- **Testy** — PHPUnit na SQLite `:memory:` (`phpunit.xml:24-25`), konwencja `RefreshDatabase` we wszystkich testach feature.
- **Nawigacja** — `resources/views/layouts/navigation.blade.php` ma odnośnik `route('home')` pod kluczem `__('Shopping list')`.

**Czego brakuje:**

- Zero tabel domenowych. Migracje to wyłącznie `users` (z nieużywaną kolumną `role`), `cache`, `jobs` i `2026_08_31_113050_add_role_to_users_table.php`.
- Zero modeli poza `app/Models/User.php`.
- Zero kontrolerów domenowych — `app/Http/Controllers/` zawiera tylko `Controller.php` i katalog `Auth/`.
- `/` jest trasą statyczną (`Route::view`), więc nie ma jak przekazać danych do widoku.

## Desired End State

Po wykonaniu planu:

- Zalogowany członek rodziny wchodzi na `/` i widzi listę wszystkich produktów dodanych przez kogokolwiek z rodziny — nazwa i kategoria przy każdej pozycji, najnowsze na górze. Przy pustej bazie widzi dotychczasowy komunikat o pustej liście.
- Przycisk na stronie głównej prowadzi na `/products/create`. Formularz przyjmuje nazwę i pozwala wybrać kategorię z listy albo wpisać nową.
- Zapisanie produktu wraca na stronę główną, gdzie nowa pozycja jest widoczna od razu — także dla pozostałych zalogowanych osób.
- Próba dodania produktu o nazwie, która już jest na liście, zostaje odrzucona polskim komunikatem. Porównanie ignoruje wielkość liter i spacje po bokach, więc „Mleko", „mleko" i „ mleko " to ta sama nazwa.
- Ta sama reguła porównania chroni kategorie: wpisanie „Nabiał" gdy istnieje już „nabiał" nie tworzy drugiej kategorii, tylko używa istniejącej.
- Niezalogowany nie ma dostępu ani do listy, ani do formularza.
- `composer test` przechodzi w całości.

### Key Discoveries:

- **`/` musi przejść z `Route::view` na kontroler.** `Route::view` renderuje widok bez żadnych danych, więc lista nie ma jak do niego trafić. Nazwa trasy `home` musi zostać — odwołują się do niej `resources/views/layouts/navigation.blade.php` (cztery miejsca), `AuthenticatedSessionController::store()` i `RegisteredUserController::store()`, a także testy `AuthenticationTest`, `RegistrationEnabledTest` i `ExampleTest`.
- **Kategorie są kontraktem dla dwóch kolejnych kawałków.** S-03 przypisuje kategorie sklepom, S-04 liczy pokrycie kategorii listy przez sklep (§Business Logic PRD). Zapis kategorii jako osobnej tabeli z kluczem obcym oznacza, że S-04 porównuje identyfikatory, a nie napisy.
- **Wybrany wariant „tabela plus dopisywanie z formularza" przenosi ryzyko rozjazdu na moment tworzenia kategorii.** To jest powód, dla którego reguła porównania bez wielkości liter musi obowiązywać także przy dopisywaniu nowej kategorii, nie tylko przy nazwach produktów.
- **Blokada duplikatu produktu jest walidacyjna, nie bazodanowa.** Zwykły `unique` w bazie porównuje znak w znak i nie złapałby „Mleko" obok „mleko"; indeks funkcyjny na `LOWER(name)` działałby, ale różni się składnią między Postgresem produkcyjnym a SQLite testowym. Reguła w FormRequest jest przenośna i pokrywa jedyny sposób, w jaki produkt trafia do bazy.
- **Lista jest wspólna, bez zawężania do użytkownika.** §Kryteria sukcesu wymagają, żeby dodany produkt był widoczny dla wszystkich zalogowanych; PRD wyklucza multitenancy, więc zapytanie nie filtruje po nikim.
- **Wzorzec z S-01 na komunikaty walidacji** — `LoginRequest` rzuca `trans('auth.failed')`, a `lang/pl/validation.php` tłumaczy reguły plus mapuje nazwy pól w sekcji `attributes`. Nowe pola (`name`, `category_id`) wymagają wpisów w tej samej sekcji.

## What We're NOT Doing

- **Nie usuwamy produktów** (FR-006) — zostaje w S-05, gdzie kryterium akceptacji „usunięty produkt wpływa na przeliczenie rekomendacji" da się zweryfikować po S-04.
- **Nie dodajemy sklepów ani rekomendacji** — to S-03 i S-04.
- **Nie zapisujemy, kto dodał produkt.** §Poza zakresem odrzuca historię zakupów, a żadne FR nie pyta o autora. Kolumna bez czytelnika to wzorzec, który przegląd S-01 wytknął przy `role`.
- **Nie dodajemy ilości ani jednostek.** FR-005 mówi wyłącznie o nazwie i kategorii; dwa razy „mleko" to jedyny sposób powiedzenia „dwie butelki" i jest dopuszczony przez wybraną regułę duplikatu tylko wtedy, gdy nazwy się różnią.
- **Nie budujemy ekranu zarządzania kategoriami** — kategorie powstają z seedera i z formularza produktu. Edycja i usuwanie kategorii nie mają pokrycia w żadnym FR.
- **Nie grupujemy listy po kategoriach ani nie dodajemy sortowania wybieranego przez użytkownika.** FR-004 mówi tylko „zobaczyć listę"; grupowanie zostaje jako możliwa późniejsza zmiana.
- **Nie edytujemy produktów.** Brak FR.
- **Nie dotykamy uwierzytelniania, `Dockerfile`, `render.yaml` ani `docker/`.**

## Implementation Approach

Dwie fazy, każda kończąca się czymś widocznym w przeglądarce, ułożone tak, żeby ryzyko schodziło najpierw:

1. Najpierw model danych i odczyt. Kształt tabel i relacji to jedyna decyzja w tym kawałku, która kosztuje migrację na żywych danych, jeśli okaże się zła — więc powstaje pierwsza i zostaje sprawdzona na widocznej liście, zanim dojdzie zapis.
2. Potem zapis. Formularz, walidacja i ścieżka dopisywania kategorii opierają się na modelu z fazy 1; gdyby model wymagał korekty, lepiej dowiedzieć się o tym przed napisaniem walidacji.

## Critical Implementation Details

**Zmiana `/` z `Route::view` na kontroler jest zmianą kontraktu, z której korzysta pięć innych miejsc.** Nazwa trasy `home` musi przetrwać — inaczej `route('home')` w nawigacji, w obu kontrolerach uwierzytelniania i w trzech testach zacznie rzucać `RouteNotFoundException`. To dokładnie ten tryb awarii, który przegląd S-01 znalazł jako F1 przy `route('dashboard')`, więc `composer test` po tej zmianie jest bramką, a nie formalnością.

**Reguła porównania nazw musi być zaimplementowana raz i użyta w dwóch miejscach** — przy sprawdzaniu duplikatu produktu i przy dopasowywaniu dopisywanej kategorii do istniejącej. Rozjechanie się tych dwóch implementacji da sytuację, w której produkty są deduplikowane, a kategorie nie, co po cichu psuje regułę z S-04.

## Phase 1: Wspólna lista na stronie głównej

### Overview

Wprowadzić tabele `categories` i `products` z relacją, wypełnić kategorie startowym zestawem i pokazać listę produktów na stronie głównej zamiast statycznego pustego stanu.

### Changes Required:

#### 1. Migracja kategorii

**File**: `database/migrations/<timestamp>_create_categories_table.php`

**Intent**: Tabela kategorii produktów — zbiór, którym opisywane są produkty i który S-03 przypisze sklepom.

**Contract**: Kolumny `id`, `name` (string, unique), `timestamps`. Ograniczenie `unique` na `name` jest siatką bezpieczeństwa dla dokładnych duplikatów; porównanie bez wielkości liter żyje w walidacji (patrz Faza 2), bo indeks funkcyjny różniłby się składnią między Postgresem a SQLite w testach.

#### 2. Migracja produktów

**File**: `database/migrations/<timestamp>_create_products_table.php`

**Intent**: Tabela produktów na wspólnej liście zakupów.

**Contract**: Kolumny `id`, `name` (string), `category_id` (klucz obcy do `categories`, wymagany), `timestamps`. Bez kolumny właściciela — lista jest wspólna dla całej rodziny. Zachowanie przy usunięciu kategorii: `restrict`, żeby skasowanie kategorii nie zabrało po cichu produktów; usuwanie kategorii i tak nie ma ekranu w MVP. Migracja produktów musi mieć znacznik czasu późniejszy niż migracja kategorii.

#### 3. Modele i relacja

**File**: `app/Models/Category.php`, `app/Models/Product.php`

**Intent**: Modele Eloquent z relacją w obie strony, żeby widok mógł czytać kategorię produktu bez ręcznego łączenia tabel.

**Contract**: `Product` należy do `Category` (`belongsTo`), `Category` ma wiele produktów (`hasMany`). Oba modele deklarują pola wypełnialne atrybutem `#[Fillable]` — tak jak `app/Models/User.php:13`. `Product`: `name`, `category_id`. `Category`: `name`.

#### 4. Fabryki i seeder kategorii

**File**: `database/factories/CategoryFactory.php`, `database/factories/ProductFactory.php`, `database/seeders/CategorySeeder.php`, `database/seeders/DatabaseSeeder.php`

**Intent**: Fabryki dla testów oraz startowy zestaw kategorii, żeby formularz z Fazy 2 miał z czego wybierać zaraz po `migrate:fresh --seed`.

**Contract**: `CategoryFactory` generuje unikalne nazwy; `ProductFactory` domyślnie tworzy własną kategorię przez relację. `CategorySeeder` wstawia kilka-kilkanaście polskich kategorii spożywczych (np. nabiał, pieczywo, warzywa, mięso, chemia, mrożonki) w sposób idempotentny — ponowne uruchomienie nie duplikuje wpisów. `DatabaseSeeder` woła `CategorySeeder`, zachowując istniejące tworzenie konta testowego.

#### 5. Kontroler listy i trasa

**File**: `app/Http/Controllers/ProductController.php`, `routes/web.php`

**Intent**: Zamienić statyczną trasę `/` na kontroler, który pobiera produkty wraz z ich kategoriami i przekazuje je do widoku.

**Contract**: `ProductController::index()` zwraca widok `home` z kolekcją produktów posortowaną malejąco po dacie utworzenia, z załadowaną relacją kategorii (`with('category')`), żeby renderowanie listy nie generowało zapytania na pozycję. W `routes/web.php` `Route::view('/', 'home')` ustępuje miejsca `Route::get('/', [ProductController::class, 'index'])`. **Nazwa trasy `home` i middleware `auth` muszą zostać bez zmian** — `route('home')` jest używane w nawigacji, w obu kontrolerach uwierzytelniania i w trzech testach.

#### 6. Widok listy

**File**: `resources/views/home.blade.php`

**Intent**: Wyrenderować listę produktów w istniejącej karcie, zachowując dotychczasowy pusty stan, gdy nic nie ma.

**Contract**: Wewnątrz istniejącego `<x-app-layout>` i karty: gdy kolekcja jest pusta, zostaje dotychczasowy komunikat; w przeciwnym razie lista pozycji, każda z nazwą produktu i nazwą jego kategorii. Układ czytelny na szerokości telefonu — nazwa i kategoria nie mogą wymuszać poziomego przewijania. Teksty po polsku wprost w Blade, zgodnie z konwencją z S-01 dla widoków własnych.

#### 7. Testy listy

**File**: `tests/Feature/ProductListTest.php`

**Intent**: Pokryć FR-004 i barierę wspólnej widoczności z §Kryteria sukcesu.

**Contract**: Test feature z `RefreshDatabase`. Przypadki: zalogowany widzi produkt utworzony fabryką wraz z nazwą jego kategorii; produkt dodany „przez inną osobę" (dowolny rekord, bez powiązania z zalogowanym) też jest widoczny; przy pustej bazie strona pokazuje komunikat o pustej liście; gość nadal jest przekierowany na `/login`.

### Success Criteria:

#### Automated Verification:

- Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- Cały zestaw testów przechodzi: `composer test`
- Trasa `home` nadal istnieje i wskazuje na kontroler: `php artisan route:list` pokazuje `GET /` o nazwie `home`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- Po `migrate:fresh --seed` i zalogowaniu strona główna pokazuje komunikat o pustej liście
- Po dodaniu produktu przez `tinker` pozycja pojawia się na liście razem z nazwą kategorii
- Lista czyta się poprawnie na szerokości ~375 px, bez poziomego przewijania
- Odnośnik „Lista zakupów" w nagłówku i wylogowanie nadal działają

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie, zanim przejdziesz do następnej fazy.

---

## Phase 2: Dodawanie produktu z kategorią

### Overview

Dać osobny ekran dodawania: nazwa produktu, wybór kategorii z listy lub wpisanie nowej, z blokadą duplikatów po obu stronach.

### Changes Required:

#### 1. Reguła porównania nazw

**File**: `app/Support/NameComparison.php` (albo równoważne miejsce wybrane przez implementującego)

**Intent**: Jedna implementacja normalizacji nazwy, używana i przy sprawdzaniu duplikatu produktu, i przy dopasowywaniu dopisywanej kategorii — żeby obie ścieżki nie rozjechały się w czasie.

**Contract**: Funkcja przyjmująca nazwę i zwracająca jej postać porównawczą: obcięte spacje z obu stron, sprowadzenie do małych liter z obsługą polskich znaków (`mb_strtolower`). **Polskie znaki diakrytyczne zostają** — „nabial" i „nabiał" to zgodnie z decyzją właściciela dwie różne nazwy. To jedyna reguła porównania w projekcie; oba miejsca użycia muszą wołać ją, a nie powtarzać logikę.

#### 2. Walidacja produktu

**File**: `app/Http/Requests/StoreProductRequest.php`

**Intent**: Zwalidować nazwę i kategorię oraz odrzucić produkt, którego nazwa jest już na liście.

**Contract**: `name` wymagane, tekst, rozsądny limit długości. Kategoria: albo `category_id` wskazujące istniejący rekord, albo nazwa nowej kategorii — dokładnie jedno z dwóch, nigdy oba puste. Duplikat nazwy produktu sprawdzany regułą z punktu 1 przeciwko wszystkim istniejącym produktom; odrzucenie daje polski komunikat wskazujący, że taki produkt już jest na liście. Wzorzec pliku i sposób podawania własnych komunikatów jak w `app/Http/Requests/Auth/LoginRequest.php`.

#### 3. Kontroler dodawania

**File**: `app/Http/Controllers/ProductController.php`, `routes/web.php`

**Intent**: Wyświetlić formularz i zapisać produkt, tworząc po drodze kategorię, jeśli użytkownik wpisał nową.

**Contract**: `create()` zwraca widok formularza z listą kategorii posortowaną alfabetycznie. `store(StoreProductRequest $request)` rozstrzyga kategorię: przy wskazanym `category_id` używa istniejącego rekordu; przy wpisanej nazwie szuka kategorii pasującej regułą z punktu 1 i **używa znalezionej zamiast tworzyć drugą**, a dopiero przy braku trafienia tworzy nową. Po zapisie przekierowanie na `route('home')`. Trasy: `GET /products/create` o nazwie `products.create` i `POST /products` o nazwie `products.store`, obie za middleware `auth`, obie w tym samym pliku co trasa `home`.

#### 4. Widok formularza

**File**: `resources/views/products/create.blade.php`

**Intent**: Formularz dodawania złożony z istniejących komponentów, czytelny na telefonie.

**Contract**: Wewnątrz `<x-app-layout>`, z użyciem `x-input-label`, `x-text-input`, `x-input-error` i `x-primary-button` — tak jak `resources/views/auth/login.blade.php`. Pole nazwy, kontrolka wyboru kategorii z listy oraz pole na nazwę nowej kategorii, z czytelnym rozróżnieniem, że wypełnia się jedno albo drugie. Token `@csrf`. Wprowadzone wartości wracają przez `old()` po odrzuceniu przez walidację, żeby użytkownik nie wpisywał wszystkiego od nowa. Teksty po polsku wprost w Blade.

#### 5. Wejście z listy do formularza

**File**: `resources/views/home.blade.php`

**Intent**: Dać widoczne przejście ze strony głównej do dodawania — bez niego nowy ekran jest nieosiągalny z interfejsu.

**Contract**: Odnośnik lub przycisk prowadzący do `route('products.create')`, widoczny zarówno przy pustej liście, jak i przy wypełnionej.

#### 6. Komunikaty walidacji po polsku

**File**: `lang/pl/validation.php`

**Intent**: Uzupełnić sekcję `attributes` o nowe pola, żeby komunikaty mówiły „pole nazwa", a nie „pole name".

**Contract**: Do istniejącej sekcji `attributes` dochodzą wpisy dla pól formularza produktu. Reguły, których ten formularz używa, a które nie są jeszcze przetłumaczone, dostają polskie zdania — resztę pliku zostawiamy po angielsku, zgodnie z decyzją z S-01.

#### 7. Testy dodawania

**File**: `tests/Feature/AddProductTest.php`

**Intent**: Pokryć FR-005 i obie reguły porównania nazw.

**Contract**: Test feature z `RefreshDatabase`. Przypadki: zalogowany dodaje produkt z istniejącą kategorią i widzi go na liście; dodanie z nową nazwą kategorii tworzy dokładnie jedną kategorię; wpisanie nazwy kategorii różniącej się tylko wielkością liter od istniejącej **nie tworzy** drugiej kategorii, tylko podpina istniejącą; produkt o nazwie różniącej się od istniejącej tylko wielkością liter lub spacjami po bokach zostaje odrzucony i nie powstaje drugi rekord; pusta nazwa jest odrzucana; gość dostaje przekierowanie na `/login` zarówno na `GET /products/create`, jak i na `POST /products`.

### Success Criteria:

#### Automated Verification:

- Cały zestaw testów przechodzi: `composer test`
- Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- Trasy `products.create` i `products.store` są zarejestrowane: `php artisan route:list`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- Dodanie produktu z wybraną kategorią wraca na stronę główną, gdzie pozycja jest widoczna
- Dodanie produktu z nową nazwą kategorii tworzy ją i od razu jest ona dostępna w wyborze przy kolejnym produkcie
- Wpisanie nazwy kategorii różniącej się tylko wielkością liter od istniejącej nie tworzy duplikatu na liście wyboru
- Próba dodania produktu o nazwie już obecnej na liście pokazuje polski komunikat, a wpisane wartości zostają w formularzu
- Formularz czyta się i obsługuje poprawnie na szerokości ~375 px
- Zalogowanie się drugim kontem pokazuje produkty dodane przez pierwsze

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie.

---

## Testing Strategy

### Unit Tests:

- Reguła porównania nazw z Fazy 2 punkt 1 — jedyny fragment logiki w tym kawałku, który da się sensownie sprawdzić w oderwaniu od bazy i żądania. Przypadki: różna wielkość liter, spacje po bokach, polskie znaki zachowane jako znaczące.

### Integration Tests:

Testy feature na SQLite `:memory:`, wszystkie z `RefreshDatabase` zgodnie z konwencją z `CLAUDE.md`:

- `ProductListTest` — widoczność listy dla zalogowanego, wspólna widoczność niezależnie od tego, kto dodał, pusty stan, odcięcie gościa.
- `AddProductTest` — dodanie z istniejącą kategorią, dodanie z nową kategorią, dopasowanie kategorii różniącej się wielkością liter, odrzucenie duplikatu produktu, odrzucenie pustej nazwy, odcięcie gościa na obu trasach.

### Manual Testing Steps:

1. `docker compose up -d`, potem `docker compose exec app php artisan migrate:fresh --seed`.
2. Zalogować się kontem z seedera — strona główna pokazuje komunikat o pustej liście i przycisk dodawania.
3. Dodać produkt wybierając kategorię z listy — po zapisie widać go na stronie głównej razem z kategorią.
4. Dodać produkt wpisując nową kategorię — sprawdzić, że pojawia się ona w wyborze przy kolejnym produkcie.
5. Wpisać tę samą kategorię inną wielkością liter — sprawdzić, że lista wyboru nie urosła o duplikat.
6. Spróbować dodać produkt o nazwie już obecnej na liście, w innej wielkości liter — sprawdzić polski komunikat i to, że wpisane wartości zostały w formularzu.
7. Założyć drugie konto (`php artisan app:user:create`), zalogować się nim i sprawdzić, że widzi produkty dodane przez pierwsze konto.
8. Zwęzić okno do ~375 px i przejść listę oraz formularz.

## Performance Considerations

Skala to 3–5 osób i lista rzędu kilkudziesięciu pozycji (`target_scale` w PRD: users small, data volume small), więc nie ma tu budżetu wydajnościowego do pilnowania. Jedyna rzecz warta uwagi to załadowanie relacji kategorii razem z produktami — bez tego renderowanie listy wykona jedno zapytanie na pozycję. Przy tej skali nikt tego nie odczuje, ale wzorzec zostaje skopiowany przez S-04, które będzie czytać kategorie wszystkich produktów przy każdym przeliczeniu rekomendacji.

## Migration Notes

Obie migracje tworzą nowe tabele — nic nie migruje istniejących danych, bo żadnych danych domenowych nie ma. Na produkcji uruchomią się automatycznie przy wdrożeniu (`docker/entrypoint.sh:12` wykonuje `migrate --force`).

**Kategorie z seedera nie trafią na produkcję automatycznie** — `entrypoint.sh` celowo nie woła `db:seed`, tak samo jak w przypadku kont. Po wdrożeniu trzeba raz uruchomić seeder kategorii przeciwko produkcyjnej bazie, albo dopisać pierwsze kategorie z formularza. Ta druga droga działa bez dotykania produkcyjnej bazy z zewnątrz i jest domyślną rekomendacją.

Wycofanie: obie migracje mają `down()`; kolejność usuwania musi być odwrotna do tworzenia z powodu klucza obcego. Reszta to kod, więc cofnięcie to rewert commita i ponowne wdrożenie.

## References

- Roadmapa, pozycja S-02 i otwarte pytanie nr 1: `context/foundation/roadmap.md`
- Wymagania: `context/foundation/prd.md` — FR-004, FR-005, US-01, §Business Logic, §Kryteria sukcesu, §Poza zakresem
- Poprzedni kawałek (wzorce widoków, walidacji i testów): `context/changes/logowanie-i-prywatny-dostep/plan.md` oraz `reviews/impl-review.md`
- Reguły projektu: `context/foundation/lessons.md`
- Wzorzec FormRequest: `app/Http/Requests/Auth/LoginRequest.php`
- Wzorzec widoku z komponentami: `resources/views/auth/login.blade.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Wspólna lista na stronie głównej

#### Automated

- [x] 1.1 Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- [x] 1.2 Cały zestaw testów przechodzi: `composer test`
- [x] 1.3 Trasa `home` nadal istnieje i wskazuje na kontroler: `php artisan route:list` pokazuje `GET /` o nazwie `home`
- [x] 1.4 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [x] 1.5 Po `migrate:fresh --seed` i zalogowaniu strona główna pokazuje komunikat o pustej liście
- [x] 1.6 Po dodaniu produktu przez `tinker` pozycja pojawia się na liście razem z nazwą kategorii
- [ ] 1.7 Lista czyta się poprawnie na szerokości ~375 px, bez poziomego przewijania
- [x] 1.8 Odnośnik „Lista zakupów" w nagłówku i wylogowanie nadal działają

### Phase 2: Dodawanie produktu z kategorią

#### Automated

- [ ] 2.1 Cały zestaw testów przechodzi: `composer test`
- [ ] 2.2 Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- [ ] 2.3 Trasy `products.create` i `products.store` są zarejestrowane: `php artisan route:list`
- [ ] 2.4 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [ ] 2.5 Dodanie produktu z wybraną kategorią wraca na stronę główną, gdzie pozycja jest widoczna
- [ ] 2.6 Dodanie produktu z nową nazwą kategorii tworzy ją i od razu jest dostępna w wyborze przy kolejnym produkcie
- [ ] 2.7 Wpisanie nazwy kategorii różniącej się tylko wielkością liter nie tworzy duplikatu na liście wyboru
- [ ] 2.8 Próba dodania produktu o nazwie już obecnej na liście pokazuje polski komunikat, a wpisane wartości zostają w formularzu
- [ ] 2.9 Formularz czyta się i obsługuje poprawnie na szerokości ~375 px
- [ ] 2.10 Zalogowanie się drugim kontem pokazuje produkty dodane przez pierwsze
