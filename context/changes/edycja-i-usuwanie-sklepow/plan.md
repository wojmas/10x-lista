# Edycja i usuwanie sklepów (S-06) — plan wdrożenia

## Overview

Lista sklepów (`/shops`) dostaje dwie akcje w wierszu: **Edytuj** — otwiera formularz zmieniający nazwę sklepu i zestaw przypisanych mu kategorii (z polem dopisania nowej kategorii, tak jak przy dodawaniu) — oraz **Usuń**, poprzedzone natywnym `confirm()`. Dziś jedyną drogą naprawy niepełnego przypisania kategorii jest zmiana w bazie danych; ta zmiana zamyka tę lukę. Przy okazji PRD dostaje FR-009 i FR-010, które opisują te akcje — zamykając Otwarte pytanie 3 z roadmapy.

## Current State Analysis

Konfiguracja sklepów jest dziś jednokierunkowa: `ShopController` ma wyłącznie `index()`, `create()` i `store()` (`app/Http/Controllers/ShopController.php:26-71`). Po zapisaniu sklepu nic w aplikacji nie pozwala zmienić jego nazwy ani przypisanych kategorii, ani go usunąć.

Wejścia dla tej zmiany są kompletne i w kilku miejscach wprost ją przewidują:

- `ShopController::store()` już robi zapis nazwy i `sync()` kategorii jako jeden akt w transakcji — `update()` powtarza ten sam kształt.
- Pivot `category_shop` ma `cascadeOnDelete()` na `shop_id` (`database/migrations/2026_09_10_100100_create_category_shop_table.php:21`), więc usunięcie sklepu sprząta przypisania bez dodatkowego kodu. Testy biegną na SQLite z `foreign_key_constraints => true` (`config/database.php:40`), więc kaskada działa też w suite.
- `ShopRecommendation::for()` już opisuje sklep o zerowym pokryciu jako stan legalny w bazie, „i S-06 wyprodukuje taki, usuwając kategorie" (`app/Support/ShopRecommendation.php:82-86`) — decyzja, że edycja może wyzerować kategorie, trafia dokładnie w to, co reguła już obsługuje.
- `Shop::inPrecedenceOrder()` niesie ostrzeżenie napisane pod tę zmianę: „Nothing renames a shop until S-06" (`app/Models/Shop.php:41-51`). Dotyczy ono wyłącznie zapytań **bez** `ORDER BY`; oba miejsca czytające sklepy przechodzą przez ten scope, więc zmiana nazwy nie rusza precedencji remisu.
- `ProductController::destroy()` ustalił wzorzec kasowania: `int $id` zamiast route model bindingu, żeby drugie kliknięcie tej samej akcji kończyło się na liście, a nie na 404 (`app/Http/Controllers/ProductController.php:70-91`).

Czego brakuje: tras `edit` / `update` / `destroy`, metod kontrolera, `UpdateShopRequest`, widoku edycji i akcji w wierszu listy.

## Desired End State

Członek rodziny otwiera `/shops`, klika **Edytuj** przy sklepie, poprawia nazwę i odznacza/zaznacza kategorie (może też dopisać kategorię, której nie ma na liście), zapisuje i wraca na listę ze zaktualizowanym wierszem. Sklep, któremu odznaczono wszystkie kategorie, zostaje na liście z czytelnym sygnałem, że nie trafi do rekomendacji. Klik **Usuń** pyta o potwierdzenie; po potwierdzeniu sklep i jego przypisania znikają, a strona główna pokazuje rekomendację przeliczoną bez niego.

Weryfikacja: `composer test` przechodzi wraz z nowymi testami funkcjonalnymi; ręcznie — pełna ścieżka edycji i kasowania na telefonie, z widoczną zmianą rekomendacji na stronie głównej.

### Key Discoveries:

- `app/Support/ShopRecommendation.php:82-86` — sklep bez kategorii jest już przewidziany i odpada z rekomendacji; nie trzeba nic dodawać po stronie reguły.
- `app/Http/Requests/StoreShopRequest.php:102-117` — walidacja unikalności nazwy porównuje przez `NameComparison` po **wszystkich** sklepach. Przy edycji odrzuciłaby własną nazwę sklepu, więc wersja dla `update` musi wykluczyć edytowany rekord.
- `app/Http/Requests/StoreShopRequest.php:49` — `category_ids` jest `required_without:new_category`. Przy edycji zero kategorii jest stanem dozwolonym, więc ta reguła w `update` nie obowiązuje. To jedyna świadoma rozbieżność reguł między dodawaniem a edycją.
- `database/migrations/2026_09_10_100100_create_category_shop_table.php:21` — `cascadeOnDelete` na `shop_id`; kasowanie sklepu nie wymaga ręcznego `detach()`.
- `app/Models/Shop.php:41-51` — ostrzeżenie o kolejności precedencji dotyczy zapytań bez `ORDER BY`; `inPrecedenceOrder()` jest w obu konsumentach, więc `UPDATE` nazwy jest bezpieczny.
- `resources/views/shops/create.blade.php:37-46` — błędy kategorii trzeba czytać z dwóch kluczy (`category_ids` i `category_ids.*`), inaczej formularz wraca bez komunikatu. Ta pułapka przechodzi do formularza edycji razem z resztą pól.

## What We're NOT Doing

- **Zarządzanie kategoriami** — brak ekranu do zmiany nazwy ani usuwania kategorii. Kategoria niepowiązana z żadnym sklepem zostaje w bazie. To ryzyko zakresu wskazane wprost w roadmapie (S-06 → Risk).
- **Cofanie usunięcia / kosz** — usunięcie sklepu jest trwałe, spójnie z §Non-Goals PRD (brak historii).
- **Zmiana kolejności sklepów** — precedencja remisu wynika z `id` i ta zmiana jej nie rusza (żadnej kolumny `position`).
- **Masowe akcje** — brak kasowania wielu sklepów naraz.
- **Blokada usunięcia sklepu aktualnie rekomendowanego** — rekomendacja jest przeliczana przy każdym wejściu na stronę główną, więc nie ma czego unieważniać.
- **Panel admina / uprawnienia per rola** — każdy zalogowany członek może edytować i kasować sklepy, tak jak dodaje (§Access Control PRD).

## Implementation Approach

Trzy fazy, każda samodzielnie weryfikowalna: najpierw edycja (największa część — formularz, walidacja, widok), potem usuwanie (małe, wzorowane na produktach), na końcu dokumenty foundation (PRD + roadmapa).

Dwie decyzje konstrukcyjne warte nazwania z góry:

1. **Osobny `UpdateShopRequest`, bez wspólnej klasy bazowej ze `StoreShopRequest`.** Reguły faktycznie się różnią w dwóch punktach (unikalność z wykluczeniem siebie, kategorie nieobowiązkowe), a wspólne zostają `attributes()` i komunikaty — kilkanaście linii. Hierarchia klas dla dwóch implementacji kosztowałaby więcej niż ta duplikacja, a `StoreShopRequest` niesie długie docblocki opisujące decyzje specyficzne dla dodawania, których edycja nie dziedziczy.

2. **Formularz wyciągnięty do partiala współdzielonego przez dodawanie i edycję.** Oba formularze mają ten sam układ (nazwa, checkboxy kategorii, pole nowej kategorii, obsługa dwóch kluczy błędów) i różnią się wyłącznie `action`, metodą HTTP, etykietą przycisku i domyślnym zaznaczeniem checkboxów. Skopiowanie 70 linii z komentarzami dałoby dwa miejsca do poprawiania przy każdej zmianie formularza.

## Critical Implementation Details

**Wykluczenie siebie z reguły unikalności.** `UpdateShopRequest` czyta edytowany sklep z trasy (`$this->route('shop')` — route model binding na `{shop}`) i porównuje nazwę wyłącznie z pozostałymi rekordami. Bez tego zapis formularza bez zmiany nazwy kończy się komunikatem „Ten sklep jest już skonfigurowany".

**Dwa różne kształty parametru trasy.** `edit`/`update` używają `{shop}` z route model bindingiem, bo edycja nieistniejącego sklepu to realny błąd i 404 jest właściwą odpowiedzią. `destroy` używa `{id}` bez bindingu, bo dwa żądania kasujące ten sam sklep (dwie osoby, ta sama otwarta lista) powinny oba skończyć na liście — dokładnie ten sam powód i ten sam wzorzec co w `routes/web.php:14-17` dla produktów.

## Phase 1: Edycja sklepu

### Overview

Wiersz listy sklepów dostaje link **Edytuj**, prowadzący do formularza zmieniającego nazwę i zestaw kategorii. Sklep może po edycji zostać bez kategorii — lista sygnalizuje wtedy, że nie trafi do rekomendacji.

### Changes Required:

#### 1. Trasy

**File**: `routes/web.php`

**Intent**: Wystawić ekran edycji i zapis zmian, w grupie `auth` razem z pozostałymi trasami sklepów.

**Contract**: `GET /shops/{shop}/edit` → `ShopController@edit`, nazwa `shops.edit`; `PUT /shops/{shop}` → `ShopController@update`, nazwa `shops.update`. Parametr `{shop}` włącza route model binding (`Shop`), co jest tu pożądane — patrz „Critical Implementation Details".

#### 2. Walidacja edycji

**File**: `app/Http/Requests/UpdateShopRequest.php` (nowy)

**Intent**: Reguły zapisu edytowanego sklepu. Różnią się od `StoreShopRequest` w dwóch miejscach i docblock ma nazwać oba, bo oba wyglądają jak przeoczenie: unikalność nazwy pomija edytowany rekord, a `category_ids` nie jest `required_without` — wyzerowanie kategorii to dozwolony sposób wyłączenia sklepu z rekomendacji.

**Contract**: `authorize()` zwraca `true` (trasa już za middleware `auth`). Reguły: `name` — `required|string|max:255` plus domknięcie odrzucające nazwę zajętą przez **inny** sklep (porównanie przez `NameComparison::matches()`, rekordy czytane w PHP, nie w SQL — ten sam powód co w `StoreShopRequest`); `category_ids` — `array`; `category_ids.*` — `integer|exists:categories,id`; `new_category` — `nullable|string|max:255`. `attributes()` powtarza mapowanie z `StoreShopRequest` (w tym wpis dla `category_ids.*`, bez którego członek rodziny zobaczy surowy klucz `category_ids.0`).

#### 3. Metody kontrolera

**File**: `app/Http/Controllers/ShopController.php`

**Intent**: `edit()` pokazuje formularz wypełniony bieżącym stanem sklepu; `update()` zapisuje nazwę i przypisania jako jeden akt, powtarzając kształt `store()` — transakcja, jawne rzutowanie id na `int`, opcjonalne dopisanie nowej kategorii przez `CategoryResolver`, `sync()` na końcu.

**Contract**: `edit(Shop $shop): View` — zwraca `shops.edit` z `$shop` (kategorie załadowane) i pełną listą kategorii posortowaną po nazwie, jak w `create()`. `update(UpdateShopRequest $request, Shop $shop): RedirectResponse` — przekierowanie na `shops.index`. `sync([])` przy braku zaznaczeń odpina wszystkie kategorie; to zamierzone. Docblock `update()` ma odnotować, że transakcja jest tu z tego samego powodu co w `store()` — połowiczny zapis zostawiłby sklep z nazwą z nowego formularza i kategoriami ze starego.

#### 4. Formularz jako partial

**File**: `resources/views/shops/partials/form.blade.php` (nowy), `resources/views/shops/create.blade.php`

**Intent**: Wyciągnąć ciało formularza z `create.blade.php` do partiala, żeby edycja i dodawanie miały jeden układ i jedną obsługę błędów. `create.blade.php` po zmianie zawiera nagłówek i `@include` z parametrami.

**Contract**: Partial przyjmuje `$action` (URL), `$method` (`null` przy dodawaniu, `'PUT'` przy edycji), `$submitLabel` i `$shop` (`null` przy dodawaniu). Zaznaczenie checkboxów: `old('category_ids', $shop?->categories->pluck('id')->all() ?? [])` — po błędzie walidacji wygrywa `old()`, przy pierwszym wejściu w edycję bieżący stan sklepu. Wartość pola nazwy analogicznie: `old('name', $shop?->name)`. Odczyt błędów kategorii z dwóch kluczy (`category_ids` i spłaszczone `category_ids.*`) przechodzi do partiala bez zmian, razem z komentarzem wyjaśniającym.

#### 5. Widok edycji

**File**: `resources/views/shops/edit.blade.php` (nowy)

**Intent**: Ekran edycji: nagłówek z nazwą sklepu i formularz z partiala.

**Contract**: `@include('shops.partials.form')` z `action = route('shops.update', $shop)`, `method = 'PUT'`, `submitLabel = 'Zapisz zmiany'`, `shop = $shop`. Link „Anuluj" prowadzi na `shops.index`, jak w formularzu dodawania.

#### 6. Akcja w wierszu listy i sygnał braku kategorii

**File**: `resources/views/shops/index.blade.php`

**Intent**: Wiersz dostaje link **Edytuj**. Sklep bez kategorii — stan, który dopiero ta faza umożliwia — dostaje zamiast pustego rzędu etykiet zdanie mówiące, że nie trafi do rekomendacji; bez tego wiersz wygląda na uszkodzony.

**Contract**: Układ wiersza pozostaje responsywny (`flex-wrap`, `gap`), zgodnie z wymaganiem PRD o telefonie. Komunikat przy pustych kategoriach w rodzaju „Brak kategorii — ten sklep nie trafi do rekomendacji." Link „Edytuj" celuje w `route('shops.edit', $shop)`.

#### 7. Testy

**File**: `tests/Feature/EditShopTest.php` (nowy)

**Intent**: Przypiąć zachowanie edycji wzorcem `AddShopTest` / `ShopListTest` (`RefreshDatabase`, `actingAs`, fabryki).

**Contract**: Przypadki: (a) zapis zmienia nazwę i zestaw kategorii, przekierowuje na `/shops`; (b) zapis bez zmiany nazwy przechodzi — reguła unikalności nie łapie samej siebie; (c) nazwa zajęta przez inny sklep jest odrzucana, także w innej wielkości liter (`NameComparison`); (d) odznaczenie wszystkich kategorii zapisuje się i sklep znika z rekomendacji na stronie głównej, a wcześniej rekomendowany ustępuje innemu; (e) dopisanie nowej kategorii z formularza edycji tworzy ją i przypina; (f) niezalogowany dostaje przekierowanie na `/login` na `GET edit` i na `PUT update`.

### Success Criteria:

#### Automated Verification:

- Testy przechodzą: `composer test`
- Nowe testy edycji przechodzą: `php artisan test --filter=EditShopTest`
- Istniejące testy sklepów bez regresji: `php artisan test --filter='AddShopTest|ShopListTest|ShopRecommendationTest|HomeRecommendationTest'`
- Formatowanie zgodne: `./vendor/bin/pint --test`

#### Manual Verification:

- Edycja nazwy i kategorii działa z telefonu — wiersz listy się zmienia, układ się nie rozjeżdża
- Sklep z odznaczonymi wszystkimi kategoriami pokazuje na liście komunikat o braku kategorii i nie pojawia się jako rekomendacja na stronie głównej
- Błąd walidacji (zajęta nazwa) wraca do formularza z zachowanymi zaznaczeniami kategorii

**Implementation Note**: Po przejściu automatycznej weryfikacji zatrzymaj się i poczekaj na potwierdzenie ręcznych testów, zanim ruszysz Fazę 2.

---

## Phase 2: Usuwanie sklepu

### Overview

Wiersz listy dostaje przycisk **Usuń** z natywnym potwierdzeniem. Usunięcie kasuje sklep i jego przypisania kategorii; rekomendacja na stronie głównej przelicza się bez niego.

### Changes Required:

#### 1. Trasa

**File**: `routes/web.php`

**Intent**: Wystawić kasowanie sklepu, tym samym wzorcem co kasowanie produktu.

**Contract**: `DELETE /shops/{id}` → `ShopController@destroy`, nazwa `shops.destroy`. Parametr celowo nazywa się `{id}`, nie `{shop}` — komentarz przy trasie ma powiedzieć dlaczego, tak jak przy `products.destroy`: druga nazwa podpowiada type-hint `Shop` i włącza binding z 404 przy drugim żądaniu.

#### 2. Metoda kontrolera

**File**: `app/Http/Controllers/ShopController.php`

**Intent**: Usunąć sklep i wrócić na listę. Przypisania kategorii znikają same — kaskada na pivocie. Kategorie zostają w bazie, także osierocone; kasowanie kategorii jest poza zakresem i `restrictOnDelete` na `category_id` to egzekwuje.

**Contract**: `destroy(int $id): RedirectResponse`, `Shop::destroy($id)`, przekierowanie na `shops.index`. `Shop::destroy()` nie rzuca wyjątku przy nieistniejącym id, więc dwa żądania kasujące ten sam sklep kończą się identycznie. Docblock ma nazwać oba powody — idempotencję i kaskadę — oraz odnotować, że rekomendacja nie jest tu przeliczana, bo `ProductController::index()` liczy ją przy każdym wejściu.

#### 3. Przycisk z potwierdzeniem

**File**: `resources/views/shops/index.blade.php`

**Intent**: Dołożyć do wiersza formularz `DELETE` z przyciskiem „Usuń" i potwierdzeniem przed wysłaniem. W odróżnieniu od produktów potwierdzenie jest tu konieczne: sklep to konfiguracja z przypisanymi kategoriami, a przypadkowy klik kasuje pracę, której nie odtwarza samo wpisanie nazwy.

**Contract**: `<form method="POST">` z `@csrf` i `@method('DELETE')`, `onsubmit="return confirm('…')"` z komunikatem nazywającym sklep i skutek. Jest to pierwszy JS w projekcie — przy wyłączonym JS formularz nadal wysyła się poprawnie (kasuje bez pytania), więc nie wprowadza zależności od JS. Przycisk powtarza style czerwonego przycisku z `home.blade.php:82-86`, włącznie z jawnym `cursor-pointer` (Tailwind 4 zmienił domyślny kursor przycisku).

#### 4. Testy

**File**: `tests/Feature/RemoveShopTest.php` (nowy)

**Intent**: Przypiąć zachowanie kasowania wzorcem `RemoveProductTest`.

**Contract**: Przypadki: (a) sklep znika z bazy i z listy, przekierowanie na `/shops`; (b) przypisania kategorii w `category_shop` znikają razem z nim, a same kategorie zostają; (c) skasowanie rekomendowanego sklepu zmienia rekomendację na stronie głównej na kolejny w kolejności pokrycia; (d) drugie żądanie `DELETE` na ten sam id kończy się przekierowaniem, nie 404; (e) niezalogowany dostaje przekierowanie na `/login`.

### Success Criteria:

#### Automated Verification:

- Testy przechodzą: `composer test`
- Nowe testy kasowania przechodzą: `php artisan test --filter=RemoveShopTest`
- Formatowanie zgodne: `./vendor/bin/pint --test`

#### Manual Verification:

- Klik „Usuń" pokazuje potwierdzenie; anulowanie nie kasuje niczego
- Po potwierdzeniu sklep znika z listy, a strona główna pokazuje rekomendację przeliczoną bez niego
- Układ wiersza z dwiema akcjami trzyma się na telefonie

**Implementation Note**: Po przejściu automatycznej weryfikacji zatrzymaj się i poczekaj na potwierdzenie ręcznych testów, zanim ruszysz Fazę 3.

---

## Phase 3: PRD i roadmapa

### Overview

Ta zmiana jest jedyną pozycją roadmapy bez pokrycia w wymaganiach funkcjonalnych (Otwarte pytanie 3). PRD dostaje FR-009 i FR-010, roadmapa odnotowuje rozstrzygnięcie.

### Changes Required:

#### 1. Wymagania funkcjonalne

**File**: `context/foundation/prd.md`

**Intent**: Dopisać do §Konfiguracja sklepów dwa wymagania opisujące to, co powstało w Fazach 1-2, i podbić `version` we frontmatterze.

**Contract**: Po FR-008 (`context/foundation/prd.md:80`) dochodzą FR-009 („Członek może zmienić nazwę sklepu i zestaw przypisanych mu kategorii", must-have) i FR-010 („Członek może usunąć sklep", must-have), w tej samej konwencji co sąsiednie wpisy — z linią `> Socrates:` notującą kontrargument i rezolucję. Frontmatter `version: 1` → `2`.

#### 2. Roadmapa

**File**: `context/foundation/roadmap.md`

**Intent**: Zamknąć Otwarte pytanie 3 i zaktualizować odwołania do FR w pozycji S-06, żeby roadmapa przestała opisywać ją jako lukę.

**Contract**: Pytanie 3 (`context/foundation/roadmap.md:174`) przekreślone i oznaczone jako rozstrzygnięte 2026-09-11 z decyzją (PRD dostaje FR-009/FR-010, `prd_version` → 2). Pola `PRD refs` w tabeli „At a glance" i w bloku S-06 wskazują FR-009, FR-010. Frontmatter: `prd_version: 2`, `updated: 2026-09-11`. Pola `Status` nie ruszamy w tej fazie — prowadzi je `/10x-implement` i `/10x-archive`.

### Success Criteria:

#### Automated Verification:

- Testy nadal przechodzą: `composer test`

#### Manual Verification:

- FR-009 i FR-010 opisują to, co faktycznie działa w aplikacji po Fazach 1-2
- Roadmapa nie zawiera już otwartego pytania o pokrycie S-06 w PRD

---

## Testing Strategy

### Unit Tests:

Brak nowych. Ta zmiana nie wprowadza nowej reguły domenowej — `NameComparison` i `ShopRecommendation` mają już testy jednostkowe/funkcjonalne, a edycja i kasowanie tylko podają im inne dane.

### Integration Tests:

- `EditShopTest` — zapis nazwy i kategorii, unikalność z wykluczeniem siebie, wyzerowanie kategorii, dopisanie nowej kategorii, dostęp niezalogowanego.
- `RemoveShopTest` — kasowanie sklepu i przypisań, wpływ na rekomendację, idempotencja drugiego żądania, dostęp niezalogowanego.

Oba scenariusze dotykające rekomendacji czytają stronę główną (`GET /`) i sprawdzają wyrenderowaną nazwę sklepu — tak samo jak `HomeRecommendationTest`, żeby dowód szedł przez tę samą ścieżkę, którą widzi użytkownik.

### Manual Testing Steps:

1. Na `/shops` kliknij „Edytuj" przy sklepie, zmień nazwę i odznacz jedną kategorię — zapisz, sprawdź wiersz na liście.
2. Dodaj produkt w kategorii, którą właśnie odznaczono, i sprawdź na stronie głównej, że rekomendacja to uwzględnia.
3. Odznacz w edycji wszystkie kategorie — sprawdź komunikat na liście i zniknięcie sklepu z rekomendacji.
4. W edycji wpisz nazwę zajętą przez inny sklep (inną wielkością liter) — sprawdź komunikat błędu i zachowane zaznaczenia.
5. Kliknij „Usuń", anuluj potwierdzenie — sklep zostaje. Kliknij ponownie i potwierdź — sklep znika, rekomendacja się zmienia.
6. Powtórz kroki 1 i 5 na telefonie (albo w wąskim viewporcie) — sprawdź układ wiersza z dwiema akcjami.

## Performance Considerations

Bez zmian istotnych dla wydajności. `edit()` ładuje kategorie sklepu i pełną listę kategorii — dwa zapytania, jak `create()`. Lista sklepów już eager-loaduje kategorie, więc dołożenie akcji w wierszu nie dokłada zapytań.

## Migration Notes

Brak migracji. Schemat pokrywa tę zmianę w całości: kaskada na `category_shop.shop_id` obsługuje kasowanie, a unikalny indeks na `shops.name` pozostaje backstopem walidacji nazwy.

## References

- Roadmapa, pozycja S-06 i Otwarte pytanie 3: `context/foundation/roadmap.md:144-156`, `context/foundation/roadmap.md:174`
- Wzorzec dodawania sklepu: `app/Http/Controllers/ShopController.php:53-71`, `app/Http/Requests/StoreShopRequest.php`
- Wzorzec kasowania: `app/Http/Controllers/ProductController.php:70-91`, `routes/web.php:14-17`, `resources/views/home.blade.php:63-95`
- Reguła rekomendacji i sklep o zerowym pokryciu: `app/Support/ShopRecommendation.php:59-97`
- Kontrakt kolejności precedencji: `app/Models/Shop.php:30-57`
- Poprzednia zmiana w tej rodzinie: `context/archive/2026-09-09-konfiguracja-sklepow/plan.md`

## Odstępstwa od planu (dopisywane w trakcie wdrożenia)

- **Faza 1** — logika składania listy kategorii z formularza (rzutowanie id + opcjonalna nowa kategoria przez `CategoryResolver`) wyjechała ze `store()` do prywatnej metody `ShopController::assignedCategoryIds()`, współdzielonej ze `update()`. Plan wymieniał tylko dopisanie metod; powód zmiany w `store()`: inaczej pułapka z `intval()` i obsługa pola „nowa kategoria" istniałyby w dwóch kopiach, które mogą się rozjechać.
- **Faza 2** — nazwa sklepu w komunikacie `confirm()` idzie przez `@js($shop->name)`, nie przez `{{ }}`. Plan nie przewidywał tego szczegółu: apostrof w nazwie sklepu zamknąłby łańcuch JS wewnątrz atrybutu `onsubmit` i zepsułby potwierdzenie.

## Progress

> Konwencja: `- [ ]` do zrobienia, `- [x]` zrobione. Dopisz ` — <commit sha>`, gdy krok wyląduje. Nie zmieniaj tytułów kroków.

### Phase 1: Edycja sklepu

#### Automated

- [x] 1.1 Testy przechodzą: `composer test` — 46dc18d
- [x] 1.2 Nowe testy edycji przechodzą: `php artisan test --filter=EditShopTest` — 46dc18d
- [x] 1.3 Istniejące testy sklepów bez regresji: `php artisan test --filter='AddShopTest|ShopListTest|ShopRecommendationTest|HomeRecommendationTest'` — 46dc18d
- [x] 1.4 Formatowanie zgodne: `./vendor/bin/pint --test` — 46dc18d

#### Manual

- [ ] 1.5 Edycja nazwy i kategorii działa z telefonu — wiersz listy się zmienia, układ się nie rozjeżdża
- [ ] 1.6 Sklep bez kategorii pokazuje komunikat na liście i nie pojawia się jako rekomendacja
- [ ] 1.7 Błąd walidacji wraca do formularza z zachowanymi zaznaczeniami kategorii

### Phase 2: Usuwanie sklepu

#### Automated

- [x] 2.1 Testy przechodzą: `composer test`
- [x] 2.2 Nowe testy kasowania przechodzą: `php artisan test --filter=RemoveShopTest`
- [x] 2.3 Formatowanie zgodne: `./vendor/bin/pint --test`

#### Manual

- [ ] 2.4 Klik „Usuń" pokazuje potwierdzenie; anulowanie nie kasuje niczego
- [ ] 2.5 Po potwierdzeniu sklep znika z listy, a rekomendacja przelicza się bez niego
- [ ] 2.6 Układ wiersza z dwiema akcjami trzyma się na telefonie

### Phase 3: PRD i roadmapa

#### Automated

- [ ] 3.1 Testy nadal przechodzą: `composer test`

#### Manual

- [ ] 3.2 FR-009 i FR-010 opisują to, co faktycznie działa po Fazach 1-2
- [ ] 3.3 Roadmapa nie zawiera już otwartego pytania o pokrycie S-06 w PRD
