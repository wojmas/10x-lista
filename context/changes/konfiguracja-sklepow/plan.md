# Konfiguracja sklepów — Implementation Plan

## Overview

Realizacja S-03 z `context/foundation/roadmap.md`: członek rodziny dodaje sklep i przypisuje mu kategorie produktów w osobnym widoku (FR-008). To druga strona modelu kategorii wprowadzonego w S-02 — S-04 policzy, ile kategorii z listy zakupów pokrywa dany sklep, i na tej podstawie wskaże, dokąd jechać.

Ten kawałek utrwala też dwa kontrakty, na których stanie reguła rekomendacji: **kolejność rozstrzygania remisów** i **tożsamość przypisania kategorii do sklepu**.

## Current State Analysis

Po S-02 aplikacja ma pełną, działającą listę zakupów. Sklepy nie istnieją w żadnej formie.

**Co już jest i czego nie trzeba budować:**

- **Model kategorii** — tabela `categories` z unikalnym `name`, model `App\Models\Category` z relacją `products()`, `CategorySeeder` z dziesięcioma polskimi kategoriami spożywczymi, uruchamiany z `DatabaseSeeder`.
- **`App\Support\NameComparison`** — jedyna definicja „ta sama nazwa" (małe litery, obcięte spacje, polskie znaki znaczące), z metodami `normalize()` i `matches()`. Ma dziś trzech użytkowników: blokadę duplikatu produktu, dopasowanie wpisanej kategorii i `CategorySeeder`.
- **Rozstrzyganie kategorii z formularza** — `ProductController::resolveCategory()` i `findCategoryNamed()` (`app/Http/Controllers/ProductController.php:60-90`), razem z obsługą wyścigu: przy złapaniu `UniqueConstraintViolationException` ponawiają wyszukanie zamiast serwować 500.
- **Wzorzec formularza** — `app/Http/Requests/StoreProductRequest.php` (reguły, własne komunikaty, `attributes()` nadpisujące globalne nazwy pól) oraz `resources/views/products/create.blade.php` (komponenty `x-input-label`, `x-text-input`, `x-input-error`, `x-primary-button`, wartości przez `old()`).
- **Wzorzec listy** — `ProductController::index()` z `with('category')` i `latest()`, `resources/views/home.blade.php` z gałęzią pustego stanu i listą.
- **Nawigacja** — `resources/views/layouts/navigation.blade.php` ma odnośnik `route('home')` pod kluczem `__('Shopping list')` w czterech miejscach: logo, pozycja desktopowa, pozycja mobilna i menu użytkownika.
- **Testy** — PHPUnit na SQLite `:memory:`, 33 przechodzące, konwencja `RefreshDatabase`; wzorce w `ProductListTest`, `AddProductTest`, `CategorySeederTest`, `NameComparisonTest`.
- **Tłumaczenia** — `lang/pl.json` na teksty przez `__()`, `lang/pl/validation.php` z regułami `required`, `string`, `max`, `exists`, `unique` i sekcją `attributes`.

**Czego brakuje:**

- Zero tabel i modeli sklepów. Migracje kończą się na `2026_09_09_130100_create_products_table.php`.
- `routes/web.php` ma trzy trasy: `home`, `products.create`, `products.store`.
- Logika rozstrzygania kategorii jest **prywatna w `ProductController`** — drugi formularz nie ma jak jej użyć bez skopiowania.

## Desired End State

Po wykonaniu planu:

- W nagłówku, obok „Lista zakupów", jest druga pozycja nawigacji prowadząca do konfiguracji sklepów — widoczna także w menu mobilnym.
- `/shops` pokazuje skonfigurowane sklepy w kolejności dodania, każdy z listą przypisanych kategorii. Przy pustej bazie widać komunikat o braku sklepów i zachętę do dodania pierwszego.
- `/shops/create` przyjmuje nazwę sklepu i pozwala zaznaczyć kategorie polami wyboru; osobne pole pozwala dopisać kategorię, której na liście nie ma.
- Zapis sklepu wraca na `/shops`, gdzie nowy sklep jest widoczny razem ze swoimi kategoriami.
- Sklep bez ani jednej kategorii nie da się zapisać — walidacja odrzuca go polskim komunikatem.
- Sklep o nazwie już istniejącej (z dokładnością do wielkości liter i spacji po bokach) zostaje odrzucony.
- Ta sama kategoria nie może zostać przypisana temu samemu sklepowi dwa razy — pilnuje tego ograniczenie w bazie.
- Rozstrzyganie kategorii z formularza żyje w jednym miejscu i obsługuje oba formularze; `ProductController` zachowuje się dokładnie tak jak przed zmianą.
- Niezalogowany nie ma dostępu ani do listy sklepów, ani do formularza.
- `composer test` przechodzi w całości.

### Key Discoveries:

- **Kolejność rozstrzygania remisów musi być deterministyczna.** §Business Logic PRD mówi, że przy równej liczbie pokrytych kategorii wygrywa „sklep dodany jako pierwszy". Sortowanie po `created_at` znów remisuje, gdy dwa sklepy powstaną w tym samym ułamku sekundy (seeder, import, dwa równoległe żądania) — rekomendacja przestaje być deterministyczna przy tych samych danych, bez żadnego błędu. Klucz główny jest ściśle rosnący i nigdy nie remisuje, więc kolejność bierzemy z `id`.
- **Podwójne przypisanie kategorii przekłamuje rekomendację.** Bez ograniczenia unikalności na parze `(shop_id, category_id)` S-04 policzy tę samą kategorię dwa razy i wskaże zły sklep. To ograniczenie na poziomie bazy, bo jest to niezmiennik danych, a nie reguła formularza.
- **Duplikat nazwy sklepu rozbija jego pokrycie.** „Biedronka" wpisana dwa razy daje dwa wpisy pokrywające po połowie kategorii — oba przegrywają ze sklepem trzecim. To ta sama klasa cichego błędu, którą przegląd S-02 wykrył przy kategoriach (`CategorySeeder` dokładał „Nabiał" obok „nabiał"). Blokada idzie przez `NameComparison`, tak jak przy produktach.
- **Sklep bez kategorii jest niewidoczny dla rekomendacji.** Nigdy nic nie pokryje, więc nigdy nie wygra — a użytkownik widzi wpis wyglądający na skonfigurowany. FR-008 traktuje dodanie sklepu i przypisanie kategorii jako jedną czynność, więc walidacja też.
- **Rozstrzyganie kategorii trzeba wyciągnąć przed drugim użyciem.** `ProductController::resolveCategory()` jest prywatne. Skopiowanie go do `ShopController` dałoby dwie implementacje tej samej reguły — dokładnie konfigurację, w której przegląd S-02 znalazł błąd, gdy trzeci pisarz tabeli kategorii zapomniał o `NameComparison`. Stąd osobna faza refaktoru, przed dodaniem drugiego formularza.
- **Nawigacja ma cztery odwołania do `route('home')`**, w tym osobne warianty desktopowy i mobilny. Druga pozycja musi trafić do obu, inaczej na telefonie — czyli tam, gdzie PRD lokuje główne użycie — konfiguracja sklepów będzie nieosiągalna.

## What We're NOT Doing

- **Nie edytujemy i nie usuwamy sklepów** — to S-06 (`edycja-i-usuwanie-sklepow`), dodany do roadmapy podczas planowania tego kawałka. Pierwsze przypisanie kategorii prawie na pewno będzie niepełne, ale naprawa tego nie blokuje gwiazdy przewodniej.
- **Nie budujemy rekomendacji** — to S-04. Ten kawałek tylko dostarcza jej dane.
- **Nie zarządzamy kategoriami** — brak edycji, usuwania i scalania. Kategorie powstają z seedera oraz z obu formularzy.
- **Nie dodajemy priorytetu ani ręcznego sortowania sklepów.** Kolejność wynika z `id`; żadne FR nie przewiduje jej zmieniania.
- **Nie zapisujemy, kto dodał sklep** — tak samo jak przy produktach; §Poza zakresem odrzuca historię, a żadne FR nie pyta o autora.
- **Nie dodajemy adresu, godzin otwarcia ani innych atrybutów sklepu.** FR-008 mówi o nazwie i kategoriach.
- **Nie zmieniamy zachowania formularza produktu.** Faza 2 jest refaktorem zachowującym zachowanie — jeśli którykolwiek z istniejących testów wymaga zmiany, to sygnał, że refaktor poszedł za daleko.
- **Nie dotykamy uwierzytelniania, `Dockerfile`, `render.yaml` ani `docker/`.**

## Implementation Approach

Trzy fazy, ułożone tak, żeby ryzyko schodziło pojedynczo:

1. Najpierw model i odczyt. Kształt tabel — zwłaszcza ograniczenie unikalności na parze i decyzja o kolejności z `id` — to jedyne rzeczy w tym kawałku, które kosztują migrację na żywych danych, jeśli okażą się złe.
2. Potem refaktor rozstrzygania kategorii, w izolacji. Zmiana dotyka kodu, który już działa na produkcji, więc dostaje własną bramkę: pełny zestaw testów musi przejść **bez modyfikacji żadnego z nich**. Zmieszanie tego z nową funkcją zrobiłoby z każdej awarii zagadkę „refaktor czy nowy kod".
3. Na końcu zapis. Formularz opiera się i na modelu z fazy 1, i na wyciągniętym rozstrzyganiu z fazy 2.

## Critical Implementation Details

**Faza 2 jest refaktorem bez zmiany zachowania i to jest jej jedyne kryterium.** `ProductController::resolveCategory()` niesie nieoczywisty szczegół: obsługę `UniqueConstraintViolationException` z ponowieniem wyszukania, dodaną w przeglądzie S-02 właśnie po to, żeby dwie osoby wpisujące naraz tę samą nową kategorię nie dostały 500. Ten fragment musi przenieść się w całości — wyciągnięcie samego „znajdź albo utwórz" bez obsługi wyścigu cofnęłoby naprawioną już usterkę.

**Kolejność `id` jest kontraktem dla S-04, nie szczegółem tego kawałka.** Lista sklepów sortuje po `id` rosnąco, żeby ekran pokazywał to samo pierwszeństwo, którym S-04 rozstrzygnie remis. Rozjechanie tych dwóch sortowań dałoby sytuację, w której rekomendacja wskazuje sklep inny niż „pierwszy" widoczny na ekranie — bez żadnego błędu i bez sposobu, by użytkownik to zrozumiał.

## Phase 1: Sklepy i ich lista

### Overview

Wprowadzić tabelę sklepów i tabelę łączącą z kategoriami, pokazać listę sklepów pod `/shops` i dołożyć drugą pozycję nawigacji, żeby ekran był osiągalny.

### Changes Required:

#### 1. Migracja sklepów

**File**: `database/migrations/<timestamp>_create_shops_table.php`

**Intent**: Tabela sklepów, do których rodzina jeździ na zakupy.

**Contract**: Kolumny `id`, `name` (string, unique), `timestamps`. `unique` na `name` jest siatką bezpieczeństwa dla dokładnych duplikatów — porównanie bez wielkości liter żyje w walidacji, tak samo jak przy kategoriach i z tego samego powodu (`LOWER()` zachowuje się inaczej w SQLite testowym i w produkcyjnym Postgresie). Kolejność `id` jest kontraktem dla reguły remisu z S-04; nie dokładamy kolumny pozycji.

#### 2. Migracja przypisania kategorii

**File**: `database/migrations/<timestamp>_create_category_shop_table.php`

**Intent**: Tabela łącząca sklepy z kategoriami, które ten sklep oferuje.

**Contract**: Nazwa `category_shop`, zgodnie z konwencją Laravela (oba modele alfabetycznie, liczba pojedyncza). Kolumny: klucz obcy `category_id`, klucz obcy `shop_id`, oba wymagane. **Unikalne ograniczenie na parze `(shop_id, category_id)`** — bez niego S-04 policzy tę samą kategorię dwukrotnie. Usunięcie sklepu kasuje jego przypisania (`cascade`); usunięcie kategorii jest ograniczone (`restrict`), tak jak przy produktach. Znacznik czasu migracji musi być późniejszy niż obu tabel, które łączy.

#### 3. Model sklepu i relacja

**File**: `app/Models/Shop.php`, `app/Models/Category.php`

**Intent**: Model `Shop` z relacją wiele-do-wielu oraz odwrotna strona na `Category`, żeby widok mógł czytać kategorie sklepu bez ręcznego łączenia tabel.

**Contract**: `Shop` należy do wielu `Category` (`belongsToMany`), `Category` należy do wielu `Shop`. Pola wypełnialne przez atrybut `#[Fillable(['name'])]`, tak jak w `app/Models/Category.php:11`. Relacji nie trzeba konfigurować ręcznie — nazwa tabeli `category_shop` odpowiada domyślnej konwencji.

#### 4. Fabryka sklepu

**File**: `database/factories/ShopFactory.php`

**Intent**: Fabryka do testów, generująca sklep o unikalnej nazwie.

**Contract**: Wzorowana na `database/factories/CategoryFactory.php`. Kategorie przypisuje się w teście przez relację, nie w domyślnym stanie fabryki — sklep bez kategorii jest w bazie dopuszczalny (blokuje go dopiero walidacja formularza), a testy potrzebują obu wariantów.

#### 5. Kontroler listy i trasa

**File**: `app/Http/Controllers/ShopController.php`, `routes/web.php`

**Intent**: Osobny kontroler sklepów z akcją listy, oraz trasa w tej samej grupie `auth` co pozostałe.

**Contract**: `ShopController::index()` zwraca widok `shops.index` z kolekcją sklepów posortowaną **rosnąco po `id`** i z załadowaną relacją kategorii (`with('categories')`), żeby renderowanie nie generowało zapytania na sklep. Trasa `GET /shops` o nazwie `shops.index`, wewnątrz istniejącej grupy `Route::middleware('auth')` w `routes/web.php`.

#### 6. Widok listy sklepów

**File**: `resources/views/shops/index.blade.php`

**Intent**: Pokazać skonfigurowane sklepy z ich kategoriami, z pustym stanem, gdy nie ma jeszcze żadnego.

**Contract**: Ten sam układ co `resources/views/home.blade.php` — `<x-app-layout>`, nagłówek w slocie `header`, karta z gałęzią pustego stanu i listą. Przy każdym sklepie nazwa i jego kategorie. Nagłówek zawiera odnośnik do formularza dodawania; w tej fazie trasa jeszcze nie istnieje, więc odnośnik dochodzi w Fazie 3 — tutaj nagłówek ma sam tytuł. Układ czytelny na szerokości telefonu, bez poziomego przewijania przy sklepie z kilkunastoma kategoriami. Teksty po polsku wprost w Blade, zgodnie z konwencją dla widoków własnych.

#### 7. Druga pozycja nawigacji

**File**: `resources/views/layouts/navigation.blade.php`, `lang/pl.json`

**Intent**: Dodać odnośnik do konfiguracji sklepów obok „Lista zakupów", żeby ekran był osiągalny z interfejsu.

**Contract**: Pozycja `route('shops.index')` z `:active="request()->routeIs('shops.*')"`, dodana w **dwóch** miejscach: w bloku desktopowym (`x-nav-link`) i w rozwijanym menu mobilnym (`x-responsive-nav-link`). Pominięcie wariantu mobilnego czyni ekran nieosiągalnym na telefonie — czyli tam, gdzie PRD lokuje główne użycie. Tekst przez `__()` z wpisem w `lang/pl.json`, tak jak istniejący klucz `Shopping list`.

#### 8. Testy listy

**File**: `tests/Feature/ShopListTest.php`

**Intent**: Pokryć widoczność listy, kolejność wynikającą z `id` i odcięcie gościa.

**Contract**: Test feature z `RefreshDatabase`. Przypadki: zalogowany widzi sklep wraz z nazwami jego kategorii; przy pustej bazie widać komunikat o braku sklepów; **sklepy renderują się w kolejności `id` rosnąco** — dowód na kontrakt dla S-04, sprawdzony przez asercję na kolejności wystąpień w odpowiedzi; gość dostaje przekierowanie na `/login`.

### Success Criteria:

#### Automated Verification:

- Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- Cały zestaw testów przechodzi: `composer test`
- Trasa `shops.index` jest zarejestrowana: `php artisan route:list`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- Odnośnik do sklepów jest widoczny w nagłówku po zalogowaniu i prowadzi na `/shops`
- Ten sam odnośnik jest dostępny w menu mobilnym przy wąskim oknie
- Przy pustej bazie `/shops` pokazuje komunikat o braku sklepów
- Po dodaniu sklepu z kategoriami przez `tinker` pozycja pojawia się na liście razem z kategoriami
- Lista czyta się poprawnie na szerokości ~375 px, bez poziomego przewijania

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie, zanim przejdziesz do następnej fazy.

---

## Phase 2: Wspólne rozstrzyganie kategorii

### Overview

Wyciągnąć rozstrzyganie kategorii z `ProductController` do jednego miejsca, żeby formularz sklepu z Fazy 3 mógł go użyć zamiast kopiować. Refaktor bez zmiany zachowania.

### Changes Required:

#### 1. Wydzielony rozstrzygacz kategorii

**File**: `app/Support/CategoryResolver.php`

**Intent**: Jedno miejsce, które zamienia „wybrana kategoria albo wpisana nazwa" na rekord `Category`, używane przez oba formularze.

**Contract**: Metoda przyjmująca nazwę kategorii i zwracająca `Category` — istniejącą, jeśli `NameComparison` znajdzie pasującą, w przeciwnym razie nowo utworzoną. **Musi przenieść obsługę wyścigu w całości**: przy `UniqueConstraintViolationException` ponawia wyszukanie i zwraca rekord utworzony przez równoległe żądanie, zamiast pozwolić na 500 (`app/Http/Controllers/ProductController.php:72-82`). Wyszukiwanie po nazwie zostaje osobną, publiczną metodą, bo Faza 3 potrzebuje go także do sprawdzenia, czy dopisywana kategoria już istnieje. Umiejscowienie w `app/Support/` obok `NameComparison.php`, zgodnie z istniejącą konwencją.

#### 2. Przepięcie kontrolera produktów

**File**: `app/Http/Controllers/ProductController.php`

**Intent**: Zastąpić prywatne `resolveCategory()` i `findCategoryNamed()` wywołaniem wydzielonego rozstrzygacza, nie zmieniając zachowania.

**Contract**: Rozgałęzienie „wybrana kategoria kontra wpisana nazwa" zostaje w kontrolerze (zależy od kształtu żądania); samo znajdowanie i tworzenie kategorii idzie do `CategoryResolver`. Metody prywatne znikają wraz z importami, które przestały być potrzebne. **Żaden istniejący test nie może wymagać modyfikacji** — jeśli któryś trzeba poprawić, refaktor zmienił zachowanie i trzeba go zawęzić.

#### 3. Test wydzielonego rozstrzygacza

**File**: `tests/Feature/CategoryResolverTest.php`

**Intent**: Pokryć rozstrzygacz bezpośrednio, żeby jego kontrakt nie zależał wyłącznie od testów formularza produktu.

**Contract**: Test feature z `RefreshDatabase` (dotyka bazy, więc nie jednostkowy). Przypadki: nieznana nazwa tworzy dokładnie jedną kategorię; nazwa różniąca się wielkością liter lub spacjami po bokach zwraca istniejący rekord bez tworzenia drugiego; nazwa różniąca się polskim znakiem („nabial" wobec „nabiał") tworzy osobną kategorię — utrwalenie świadomej decyzji z S-02.

### Success Criteria:

#### Automated Verification:

- Cały zestaw testów przechodzi: `composer test`
- Testy formularza produktu przechodzą bez zmian w plikach testowych: `git diff --stat tests/Feature/AddProductTest.php` jest pusty
- `ProductController` nie zawiera już metod `resolveCategory` ani `findCategoryNamed`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- Dodanie produktu z wybraną kategorią nadal działa i wraca na stronę główną
- Dodanie produktu z nową nazwą kategorii nadal ją tworzy
- Wpisanie nazwy kategorii różniącej się tylko wielkością liter nadal nie tworzy duplikatu

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie, zanim przejdziesz do następnej fazy.

---

## Phase 3: Dodawanie sklepu z kategoriami

### Overview

Dać formularz dodawania sklepu: nazwa, pola wyboru kategorii i możliwość dopisania nowej, z blokadą duplikatu nazwy i wymogiem co najmniej jednej kategorii.

### Changes Required:

#### 1. Walidacja sklepu

**File**: `app/Http/Requests/StoreShopRequest.php`

**Intent**: Zwalidować nazwę i wybór kategorii oraz odrzucić sklep o nazwie już istniejącej.

**Contract**: `name` wymagane, tekst, rozsądny limit długości, odrzucane przez `NameComparison` przeciwko wszystkim istniejącym sklepom — komunikat po polsku mówiący, że taki sklep już jest skonfigurowany. Wybór kategorii: tablica identyfikatorów, każdy musi istnieć w `categories`; **wymagana co najmniej jedna kategoria** — z listy wyboru albo przez wpisanie nowej nazwy, więc reguła musi dopuścić pustą tablicę wtedy i tylko wtedy, gdy wpisano nową kategorię. Nazwa nowej kategorii opcjonalna, tekst, ten sam limit długości. Nazwy pól przez `attributes()` w samym żądaniu, jak w `app/Http/Requests/StoreProductRequest.php:38-46` — globalna sekcja `attributes` mapuje `name` na „imię", co jest poprawne dla formularzy konta i błędne tutaj. Porównanie nazw sklepów czyta nazwy do PHP zamiast porównywać w SQL, z tego samego powodu co przy produktach.

#### 2. Kontroler dodawania

**File**: `app/Http/Controllers/ShopController.php`, `routes/web.php`

**Intent**: Wyświetlić formularz i zapisać sklep razem z przypisaniem kategorii.

**Contract**: `create()` zwraca widok formularza z kategoriami posortowanymi alfabetycznie. `store(StoreShopRequest $request)` tworzy sklep, rozstrzyga ewentualną nową kategorię przez `CategoryResolver` z Fazy 2, po czym przypisuje komplet kategorii do sklepu jedną operacją synchronizacji relacji — to samo z siebie chroni przed podwójnym przypisaniem, niezależnie od ograniczenia w bazie. Zapis sklepu i przypisanie kategorii dzieją się w transakcji: sklep bez kategorii nie może zostać na stałe w bazie, jeśli druga część zawiedzie. Po zapisie przekierowanie na `route('shops.index')`. Trasy `GET /shops/create` o nazwie `shops.create` i `POST /shops` o nazwie `shops.store`, obie w grupie `auth`.

#### 3. Widok formularza

**File**: `resources/views/shops/create.blade.php`

**Intent**: Formularz dodawania sklepu, obsługiwalny kciukiem na telefonie.

**Contract**: Wewnątrz `<x-app-layout>`, z komponentami `x-input-label`, `x-text-input`, `x-input-error` i `x-primary-button`, wzorowany na `resources/views/products/create.blade.php`. Pole nazwy sklepu; kategorie jako **lista pól wyboru** (`checkbox`) o nazwie tablicowej, każde z etykietą powiązaną z polem, żeby dało się trafić w etykietę zamiast w sam kwadracik; pod nimi pole na nazwę nowej kategorii z wyjaśnieniem, że jest opcjonalne. Token `@csrf`. Zaznaczenia i wpisane wartości wracają przez `old()` po odrzuceniu przez walidację — przy checkboxach oznacza to sprawdzanie obecności identyfikatora w tablicy `old()`, inaczej odrzucony formularz kasuje cały wybór. Odnośnik „Anuluj" wracający na `route('shops.index')`. Teksty po polsku wprost w Blade.

#### 4. Wejście z listy do formularza

**File**: `resources/views/shops/index.blade.php`

**Intent**: Dać widoczne przejście z listy sklepów do dodawania.

**Contract**: Przycisk prowadzący do `route('shops.create')` w nagłówku widoku, tak jak przycisk „Dodaj produkt" w `resources/views/home.blade.php`. Widoczny zarówno przy pustej liście, jak i przy wypełnionej.

#### 5. Testy dodawania

**File**: `tests/Feature/AddShopTest.php`

**Intent**: Pokryć FR-008 i wszystkie cztery rozstrzygnięcia, które ten kawałek utrwala dla S-04.

**Contract**: Test feature z `RefreshDatabase`. Przypadki: zalogowany dodaje sklep z zaznaczonymi kategoriami i widzi go na liście z tymi kategoriami; dodanie z wpisaną nową kategorią tworzy ją i przypisuje; wpisanie nazwy kategorii różniącej się tylko wielkością liter od istniejącej podpina istniejącą zamiast tworzyć drugą; sklep o nazwie różniącej się od istniejącej tylko wielkością liter lub spacjami zostaje odrzucony i nie powstaje drugi rekord; sklep bez żadnej kategorii i bez wpisanej nowej zostaje odrzucony; pusta nazwa jest odrzucana; gość dostaje przekierowanie na `/login` zarówno na `GET /shops/create`, jak i na `POST /shops`.

### Success Criteria:

#### Automated Verification:

- Cały zestaw testów przechodzi: `composer test`
- Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- Trasy `shops.create` i `shops.store` są zarejestrowane: `php artisan route:list`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- Dodanie sklepu z zaznaczonymi kategoriami wraca na `/shops`, gdzie sklep jest widoczny z tymi kategoriami
- Dodanie sklepu z wpisaną nową kategorią tworzy ją i jest ona dostępna przy kolejnym sklepie oraz w formularzu produktu
- Próba dodania sklepu o nazwie już istniejącej pokazuje polski komunikat, a zaznaczone kategorie zostają w formularzu
- Próba zapisu bez żadnej kategorii pokazuje polski komunikat
- Formularz z dziesięcioma polami wyboru czyta się i obsługuje poprawnie na szerokości ~375 px
- Dwa sklepy dodane jeden po drugim pojawiają się na liście w kolejności dodania

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie.

---

## Testing Strategy

### Unit Tests:

Brak nowych. `NameComparison` ma już własny test jednostkowy z S-02, a cała logika tego kawałka dotyka bazy, więc należy do testów feature.

### Integration Tests:

Testy feature na SQLite `:memory:`, wszystkie z `RefreshDatabase`:

- `ShopListTest` — widoczność listy z kategoriami, pusty stan, kolejność po `id`, odcięcie gościa.
- `CategoryResolverTest` — utworzenie nowej kategorii, dopasowanie wariantu różniącego się wielkością liter, rozróżnienie polskich znaków.
- `AddShopTest` — dodanie z zaznaczonymi kategoriami, dodanie z nową kategorią, dopasowanie kategorii różniącej się wielkością liter, odrzucenie duplikatu nazwy sklepu, odrzucenie sklepu bez kategorii, odrzucenie pustej nazwy, odcięcie gościa na obu trasach.

Istniejące 33 testy pozostają bez zmian; w Fazie 2 ich niezmienność jest kryterium sukcesu, a nie efektem ubocznym.

### Manual Testing Steps:

1. `docker compose up -d`, potem `docker compose exec app php artisan migrate:fresh --seed`.
2. Zalogować się i sprawdzić, że w nagłówku jest druga pozycja prowadząca do sklepów — także po zwężeniu okna, w menu mobilnym.
3. Wejść na `/shops` — komunikat o braku sklepów i przycisk dodawania.
4. Dodać sklep zaznaczając kilka kategorii — po zapisie widać go na liście razem z nimi.
5. Dodać drugi sklep wpisując nową kategorię — sprawdzić, że kategoria pojawia się też w formularzu produktu.
6. Wpisać tę samą kategorię inną wielkością liter przy trzecim sklepie — sprawdzić, że nie powstał duplikat.
7. Spróbować dodać sklep o nazwie już istniejącej — polski komunikat, zaznaczenia zachowane.
8. Spróbować zapisać sklep bez żadnej kategorii — polski komunikat.
9. Sprawdzić, że sklepy na liście stoją w kolejności dodania.
10. Zwęzić okno do ~375 px i przejść listę oraz formularz z dziesięcioma polami wyboru.

## Performance Considerations

Skala z PRD to 3–5 osób i kilka sklepów, więc nie ma tu budżetu do pilnowania. Jedna rzecz warta uwagi: lista sklepów ładuje relację kategorii razem ze sklepami, bez tego renderowanie wykonałoby zapytanie na sklep. Ten sam wzorzec skopiuje S-04, które przy każdym przeliczeniu rekomendacji czyta kategorie wszystkich sklepów i wszystkich produktów naraz.

Blokada duplikatu nazwy sklepu czyta nazwy do PHP, tak jak przy produktach — przy kilku sklepach to darmowe, a różnice w zachowaniu `LOWER()` między SQLite a Postgresem czynią porównanie po stronie bazy gorszym wyborem.

## Migration Notes

Trzy nowe tabele-obiekty (`shops`, `category_shop`) tworzone od zera; nic nie migruje istniejących danych. Na produkcji uruchomią się automatycznie przy wdrożeniu (`docker/entrypoint.sh:12` wykonuje `migrate --force`).

Kolejność usuwania w `down()` musi być odwrotna do tworzenia z powodu kluczy obcych — najpierw tabela łącząca, potem `shops`.

Uwaga o danych produkcyjnych: po wdrożeniu S-02 na produkcji mogą już istnieć kategorie utworzone z formularza produktu. Formularz sklepu będzie je pokazywał obok tych z seedera — żadnej migracji to nie wymaga, ale warto o tym pamiętać przy weryfikacji na żywo.

## References

- Roadmapa, pozycje S-03, S-04 i nowo dodane S-06: `context/foundation/roadmap.md`
- Wymagania: `context/foundation/prd.md` — FR-008, US-01, §Business Logic (reguła remisu), §Access Control
- Poprzedni kawałek — wzorce widoków, walidacji, testów oraz historia błędu w `CategorySeeder`: `context/archive/2026-09-09-wspolna-lista-produktow/plan.md` i `reviews/impl-review.md`
- Reguły projektu: `context/foundation/lessons.md`
- Wzorzec FormRequest: `app/Http/Requests/StoreProductRequest.php`
- Wzorzec listy i formularza: `resources/views/home.blade.php`, `resources/views/products/create.blade.php`
- Kod do wyciągnięcia w Fazie 2: `app/Http/Controllers/ProductController.php:60-90`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Sklepy i ich lista

#### Automated

- [x] 1.1 Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- [x] 1.2 Cały zestaw testów przechodzi: `composer test`
- [x] 1.3 Trasa `shops.index` jest zarejestrowana: `php artisan route:list`
- [x] 1.4 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [ ] 1.5 Odnośnik do sklepów jest widoczny w nagłówku po zalogowaniu i prowadzi na `/shops`
- [ ] 1.6 Ten sam odnośnik jest dostępny w menu mobilnym przy wąskim oknie
- [ ] 1.7 Przy pustej bazie `/shops` pokazuje komunikat o braku sklepów
- [ ] 1.8 Po dodaniu sklepu z kategoriami przez `tinker` pozycja pojawia się na liście razem z kategoriami
- [ ] 1.9 Lista czyta się poprawnie na szerokości ~375 px, bez poziomego przewijania

### Phase 2: Wspólne rozstrzyganie kategorii

#### Automated

- [ ] 2.1 Cały zestaw testów przechodzi: `composer test`
- [ ] 2.2 Testy formularza produktu przechodzą bez zmian w plikach testowych: `git diff --stat tests/Feature/AddProductTest.php` jest pusty
- [ ] 2.3 `ProductController` nie zawiera już metod `resolveCategory` ani `findCategoryNamed`
- [ ] 2.4 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [ ] 2.5 Dodanie produktu z wybraną kategorią nadal działa i wraca na stronę główną
- [ ] 2.6 Dodanie produktu z nową nazwą kategorii nadal ją tworzy
- [ ] 2.7 Wpisanie nazwy kategorii różniącej się tylko wielkością liter nadal nie tworzy duplikatu

### Phase 3: Dodawanie sklepu z kategoriami

#### Automated

- [ ] 3.1 Cały zestaw testów przechodzi: `composer test`
- [ ] 3.2 Migracje przechodzą na czystej bazie: `php artisan migrate:fresh --seed`
- [ ] 3.3 Trasy `shops.create` i `shops.store` są zarejestrowane: `php artisan route:list`
- [ ] 3.4 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [ ] 3.5 Dodanie sklepu z zaznaczonymi kategoriami wraca na `/shops`, gdzie sklep jest widoczny z tymi kategoriami
- [ ] 3.6 Dodanie sklepu z wpisaną nową kategorią tworzy ją i jest ona dostępna przy kolejnym sklepie oraz w formularzu produktu
- [ ] 3.7 Próba dodania sklepu o nazwie już istniejącej pokazuje polski komunikat, a zaznaczone kategorie zostają w formularzu
- [ ] 3.8 Próba zapisu bez żadnej kategorii pokazuje polski komunikat
- [ ] 3.9 Formularz z dziesięcioma polami wyboru czyta się i obsługuje poprawnie na szerokości ~375 px
- [ ] 3.10 Dwa sklepy dodane jeden po drugim pojawiają się na liście w kolejności dodania
