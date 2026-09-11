# Usuwanie kupionych produktów (S-05) — Implementation Plan

## Overview

Wiersz listy zakupów dostaje przycisk „Kupione — usuń". Kliknięcie wysyła
`DELETE /products/{product}`, produkt znika bezpowrotnie, a strona główna
przelicza rekomendację bez niego.

To ostatni slice strumienia A z roadmapy. Domyka pełen cykl zakupowy z
§Success Criteria PRD („dodać produkty → zobaczyć rekomendowany sklep → usunąć
produkty po zakupie") i jest jedynym miejscem, w którym da się dowieść drugiego
kryterium akceptacji US-01: „usunięty produkt znika z listy i wpływa na
przeliczenie rekomendacji". Dlatego roadmapa sekwencjonuje go po S-04, a nie
razem z dodawaniem produktów.

## Current State Analysis

Co już istnieje i działa:

- **Rekomendacja przelicza się sama przy każdym żądaniu.**
  `ProductController::index()` (`app/Http/Controllers/ProductController.php:24`)
  woła `ShopRecommendation::for($products)` na świeżo odczytanej liście. Nie ma
  cache'u ani zapisanego wyniku do unieważnienia. Docblock
  `ShopRecommendation` (`app/Support/ShopRecommendation.php:16`) wprost
  zapowiada, że „S-05 przeczyta ten sam obiekt po odhaczeniu produktu".
  **Konsekwencja: przeliczenie rekomendacji nie wymaga w tym slice ani jednej
  linii kodu.** Wystarczy przekierowanie na `home`.
- **Trasy produktów stoją w grupie `auth`** (`routes/web.php:7`). Grupa jest
  jedynym mechanizmem odcinającym gości — §3 Faza 3 test-planu (przemiatanie
  tabeli tras) ma status `not started`, więc żaden test nie pilnuje, że nowa
  trasa do niej trafi.
- **Wiersz listy ma miejsce na trzeci element.** `home.blade.php:63` to
  `<li>` z `flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1`,
  dziś zawierający nazwę i kategorię.
- **Blokada duplikatu jest globalna i list-wide.**
  `StoreProductRequest::notAlreadyOnTheList()` porównuje nazwę ze wszystkimi
  produktami na liście. Nie ma `SoftDeletes`, więc usunięcie natychmiast zwalnia
  nazwę — ale nikt tego dziś nie sprawdza.
- **Wzorce testowe są ustalone.** `AddProductTest` (§6.2 test-planu) niesie
  komplet reguł: ładunek w kształcie przeglądarki, `followingRedirects()` i
  asercja na wyrenderowanej treści, dwóch członków dla gwarancji widoczności,
  `escape: false` przy polskich znakach diakrytycznych.

Czego brakuje:

- Trasy `products.destroy`, metody `ProductController::destroy()` i przycisku w
  `home.blade.php`. To cała luka — model danych, reguła rekomendacji i
  infrastruktura testowa są kompletne.

## Desired End State

Zalogowany członek rodziny widzi przy każdym produkcie na stronie głównej
tekstowy przycisk „Kupione — usuń". Jedno kliknięcie usuwa produkt bezpowrotnie:
wiersz znika z listy, a panel rekomendacji nad listą pokazuje wynik przeliczony
bez tej kategorii — jeśli usunięty produkt był jedynym w swojej kategorii,
liczba „pokrywa N z M" spada, a przy odpowiednim układzie danych zmienia się sam
rekomendowany sklep. Niezalogowany nie może usunąć niczego.

Weryfikacja: `RemoveProductTest` przechodzi w pięciu scenariuszach opisanych w
§Testing Strategy, a ręczne kliknięcie na telefonie usuwa właściwy wiersz.

### Key Discoveries:

- **Przeliczenie rekomendacji jest darmowe** — `ProductController.php:38` liczy
  ją przy każdym wejściu na `/`, a usunięcie kończy się przekierowaniem tam.
  Nie dopisuj żadnego kodu przeliczającego.
- **Precedencja sklepów żyje w `Shop::inPrecedenceOrder()`** i nie jest
  pilnowana testem do §3 Fazy 4 test-planu. Ten slice jej nie dotyka i nie
  próbuje zapinać — test na przeliczoną rekomendację musi być tak dobrany, żeby
  nie zależał od rozstrzygania remisu.
- **Ryzyko #4 test-planu nie ma dziś przemiatacza tras** — każda nowa trasa
  przynosi własny dowód dostępu, tak jak
  `AddProductTest::test_guests_can_not_reach_either_route()`.
- **Polskie znaki diakrytyczne wymagają `assertSee(..., escape: false)`** —
  pułapka udokumentowana w `ShopListTest` i `HomeRecommendationTest:20`.
- **`ConvertEmptyStringsToNull` i `TrimStrings` działają globalnie na ścieżce
  HTTP** — nieistotne w tym slice (brak pól formularza), ale wyjaśniają, czemu
  ten slice nie wnosi żadnego `FormRequest`.

## What We're NOT Doing

- **Brak soft delete, archiwum i historii zakupów** — §Non-Goals PRD: „usunięty
  produkt znika na zawsze".
- **Brak funkcji „Cofnij"** — wymagałaby soft delete albo trzymania produktu w
  sesji, czyli dokładnie tego, czego zakazuje §Non-Goals.
- **Brak potwierdzenia przed usunięciem** (okno `confirm()`, tryb edycji,
  modal) — decyzja właściciela: czynność wykonuje się kilkanaście razy pod rząd
  przy kasie, na telefonie.
- **Brak komunikatów flash i komponentu do nich** — projekt nie ma dziś żadnej
  infrastruktury flash poza `auth-session-status`; ten slice jej nie wprowadza.
- **Brak usuwania zbiorczego** („wyczyść listę", zaznaczanie wielu) — żadne FR
  tego nie opisuje.
- **Brak sprzątania osieroconych kategorii** — kategoria bez produktów zostaje.
  Jest nadal przypisana sklepom i nadal wybierana w formularzu dodawania.
- **Brak edycji produktu** — żadne FR tego nie opisuje; poza zakresem S-05.
- **Brak zapinania kolejności sklepów** — należy do §3 Fazy 4 test-planu.

## Implementation Approach

Najkrótsza ścieżka, która realizuje FR-006: jedna trasa, jedna metoda
kontrolera, jeden przycisk w istniejącym wierszu listy, jeden plik testowy.
Zero migracji, zero zmian w modelu, zero nowej logiki domenowej.

Trzy decyzje kształtują implementację:

1. **Usuwanie po id, bez route model bindingu.** Wspólna lista rodziny oznacza,
   że dwie osoby mogą mieć otwartą tę samą stronę — a podwójne kliknięcie na
   wolnym łączu daje ten sam efekt. Drugie żądanie ma osiągnąć stan, którego
   użytkownik chciał (produktu nie ma na liście), a nie stronę błędu. `404` za
   czynność, która zadziałała, to komunikat nieprawdziwy.
2. **Przekierowanie na `home` zamiast komunikatu.** Lista jest komunikatem:
   wiersz zniknął, panel rekomendacji pokazuje nową liczbę. Zgodnie z §6.2
   test-planu dowód i tak stoi na wyrenderowanej treści, nie na flashu.
3. **Etykieta „Kupione — usuń", nie ikona kosza.** Tekst nazywa jednocześnie
   intencję użytkownika i nieodwracalny skutek, jest czytelny dla czytnika
   ekranu bez `aria-label` i daje duży cel dotykowy — co przy braku
   potwierdzenia jest istotne. Projekt nie ma dziś żadnego zestawu ikon.

## Critical Implementation Details

**Route model binding wywróciłby decyzję o cichym powrocie.** Type-hint
`Product $product` w sygnaturze `destroy()` sprawia, że Laravel sam zwraca 404,
gdy wiersza nie ma — czyli dokładnie zachowanie odrzucone w §Implementation
Approach. Parametr musi przyjść jako zwykły `int`/`string`, a usunięcie musi
przejść przez `Product::destroy($id)` (zwraca liczbę usuniętych wierszy, nie
rzuca przy braku). To jedyna nieoczywista rzecz w całej zmianie i jedyny powód,
dla którego `RemoveProductTest` pinuje scenariusz wyścigu — bez tego testu
pierwszy refaktor „na bindingu" cofnie decyzję przy zielonym zestawie.

## Phase 1: Usuwanie produktu end-to-end

### Overview

Trasa, kontroler, przycisk i komplet testów w jednym przejściu. Rozbicie nie ma
sensu: trasa bez przycisku jest nieosiągalna z UI, a przycisk bez trasy wybucha.

### Changes Required:

#### 1. Trasa usuwania

**File**: `routes/web.php`

**Intent**: Udostępnić usuwanie produktu członkom rodziny i nikomu poza nimi.

**Contract**: `Route::delete('/products/{product}', [ProductController::class,
'destroy'])->name('products.destroy')`, dopisane **wewnątrz** istniejącej grupy
`Route::middleware('auth')`, obok pozostałych tras produktów. Grupa jest jedynym
mechanizmem odcinającym gości (ryzyko #4 test-planu); trasa poza nią przecieka.

#### 2. Metoda kontrolera

**File**: `app/Http/Controllers/ProductController.php`

**Intent**: Usunąć produkt po id i wrócić na stronę główną, gdzie rekomendacja
przeliczy się sama. Nieistniejący id nie jest błędem — użytkownik chciał, żeby
produktu nie było na liście, i nie ma go.

**Contract**: `public function destroy(int $id): RedirectResponse` — **bez**
type-hintu `Product` (patrz §Critical Implementation Details).
`Product::destroy($id)`, następnie `redirect()->route('home')`. Zwracana wartość
`destroy()` jest celowo ignorowana.

Docblock metody musi nieść dwie decyzje, bo obie są niewidoczne w kodzie i obie
łatwo cofnąć: (a) dlaczego nie ma tu route model bindingu, (b) dlaczego nie ma
przeliczania rekomendacji — robi to `index()` po przekierowaniu.

#### 3. Przycisk w wierszu listy

**File**: `resources/views/home.blade.php`

**Intent**: Dać każdemu wierszowi listy akcję „kupione", która usuwa produkt
jednym kliknięciem.

**Contract**: Wewnątrz `<li>` (linia 63), jako trzeci element po nazwie i
kategorii — formularz `method="POST"` z `@csrf` i `@method('DELETE')`,
kierowany na `route('products.destroy', $product)`, z `<button type="submit">` o
treści `Kupione — usuń`.

Styl przycisku idzie za istniejącym linkiem tekstowym z panelu rekomendacji
(`home.blade.php:35`: `text-indigo-600 underline hover:text-indigo-500`),
rozmiar tekstu jak przy kategorii (`text-sm`). Klasy `flex-wrap` i `gap-y-1` na
`<li>` już obsługują zawinięcie na wąskim telefonie — nie zmieniaj układu
wiersza poza dopisaniem elementu.

#### 4. Testy

**File**: `tests/Feature/RemoveProductTest.php` (nowy)

**Intent**: Zapiąć oba kryteria akceptacji US-01 dotyczące usuwania, obie
bariery PRD (widoczność dla rodziny, prywatność) oraz obie decyzje tego planu,
które inaczej żyłyby wyłącznie w komentarzu.

**Contract**: `Tests\TestCase` + `RefreshDatabase`, pięć metod opisanych w
§Testing Strategy. Reguły z §6.2 test-planu obowiązują w całości — w
szczególności `followingRedirects()` przed asercją na treści i `escape: false`
przy polskich znakach diakrytycznych.

### Success Criteria:

#### Automated Verification:

- Zestaw Feature przechodzi: `docker compose exec app php vendor/bin/phpunit --testsuite Feature`
- Cały zestaw przechodzi: `docker compose exec app composer test`
- Formatowanie czyste: `docker compose exec app php artisan pint --test`
- Próba obalenia (§6.3 reguła 2): zamiana `int $id` na `Product $product` w
  sygnaturze `destroy()` wywraca **dokładnie** test wyścigu i żaden inny
- Próba obalenia: usunięcie `@method('DELETE')` z formularza w `home.blade.php`
  wywraca testy usuwania

#### Manual Verification:

- Kliknięcie „Kupione — usuń" usuwa właściwy wiersz — sprawdzone na liście z co
  najmniej trzema produktami o podobnych nazwach
- Panel rekomendacji nad listą pokazuje po usunięciu niższe „pokrywa N z M", a
  przy odpowiednich danych innym sklepem
- Wiersz z długą nazwą produktu i długą nazwą kategorii zawija się czytelnie na
  telefonie (szerokość 375 px) — przycisk pozostaje dotykalny
- Usunięcie ostatniego produktu z listy pokazuje stan pusty listy oraz komunikat
  „Dodaj produkty, żeby zobaczyć rekomendowany sklep."
- Produkt usunięty w jednej sesji znika z listy w drugiej sesji po odświeżeniu

**Implementation Note**: Po przejściu automatycznej weryfikacji zatrzymaj się i
poczekaj na potwierdzenie ręcznych testów. Zgodnie z
`context/foundation/lessons.md` każde odstępstwo od tego planu podjęte w trakcie
implementacji dopisz do planu **przed** commitem fazy, z jednozdaniowym
uzasadnieniem.

---

## Testing Strategy

Pięć testów w `tests/Feature/RemoveProductTest.php`. Warstwa integracyjna po
HTTP, bo każde z tych ryzyk jest ryzykiem na ścieżce użytkownika, a nie regułą
dającą się zawołać bez bazy (§6.1 test-planu odsyła takie do `tests/Feature/`).

### Integration Tests:

1. **`test_removing_a_product_recalculates_the_recommendation`** — dowodzi
   drugiego kryterium akceptacji US-01 i jest powodem, dla którego ten slice
   stoi po S-04. Dane kontrolne muszą być tak dobrane, żeby usunięcie zmieniło
   **zwycięzcę**, a nie tylko liczbę: dwa sklepy o różnym pokryciu, gdzie
   usunięcie jednego produktu odwraca ranking. Asercja przed usunięciem i po
   usunięciu, na nazwie sklepu i na tekście „Pokrywa N z M kategorii z listy."
   **Ranking musi być rozstrzygnięty pokryciem, nie remisem** — precedencja
   sklepów nie jest pilnowana żadnym testem do §3 Fazy 4 test-planu, więc test
   oparty na remisie przechodziłby z niewłaściwego powodu.

2. **`test_a_product_removed_by_one_member_is_gone_for_another`** — druga
   połowa bariery „dane nie mogą się gubić" (ryzyko #2), dziś zapięta wyłącznie
   dla dodawania. Jeden `User` wysyła `DELETE`, drugi osobnym żądaniem czyta `/`
   i nie widzi produktu (§6.2 reguła 3: dwóch użytkowników, inaczej test nie
   dotyka zapisu).

3. **`test_guests_can_not_remove_a_product`** — ryzyko #4. Niezalogowany wysyła
   `DELETE` i dostaje przekierowanie na `/login`; asercja, że produkt nadal
   istnieje. Wzorzec z
   `AddProductTest::test_guests_can_not_reach_either_route()`.

4. **`test_a_removed_name_can_be_added_again`** — produkt usunięty, ta sama
   nazwa dodana ponownie przez formularz, przechodzi i ląduje na liście. Pinuje
   konsekwencję braku soft delete: gdyby ktoś dopisał `SoftDeletes` do
   `Product`, globalna blokada duplikatu ze `StoreProductRequest` cicho
   zablokowałaby cotygodniowe „mleko" bez żadnego czerwonego testu.

5. **`test_removing_a_product_that_is_already_gone_returns_to_the_list`** —
   pinuje decyzję „cicho wraca". Usuń produkt, wyślij `DELETE` na to samo id
   drugi raz, oczekuj przekierowania na `home` (nie 404). Ten test jest jedynym
   dowodem na brak route model bindingu; próba obalenia z §Success Criteria
   musi trafić dokładnie w niego.

### Manual Testing Steps:

1. Zaloguj się, dodaj cztery produkty w trzech różnych kategoriach.
2. Skonfiguruj dwa sklepy o różnym pokryciu tych kategorii; zapamiętaj, który
   jest rekomendowany i z jaką liczbą.
3. Kliknij „Kupione — usuń" przy produkcie będącym jedynym w swojej kategorii —
   sprawdź, że zniknął właściwy wiersz i że liczba „pokrywa N z M" spadła.
4. Usuń kolejne produkty aż do opróżnienia listy — sprawdź stan pusty i
   komunikat rekomendacji.
5. Dodaj ponownie produkt o nazwie właśnie usuniętej — ma przejść.
6. Powtórz krok 3 na telefonie (albo przy szerokości 375 px) z produktem o
   długiej nazwie.
7. Wyloguj się i spróbuj wejść na `/` — przekierowanie na logowanie.

## Performance Considerations

Brak. Usunięcie to jeden `DELETE` po kluczu głównym, a przeliczenie
rekomendacji przy przekierowaniu jest tym samym zapytaniem, które strona główna
wykonuje przy każdym wejściu. Skala to 3–5 osób i kilkadziesiąt produktów.

## Migration Notes

Brak migracji. Schemat bazy nie zmienia się w tym slice. Wdrożenie to zwykły
deploy — nie ma stanu do przeniesienia ani ścieżki wycofania innej niż revert
commita.

## References

- Roadmapa: `context/foundation/roadmap.md` §Slices → S-05
- PRD: FR-006, US-01 (kryteria akceptacji), §Success Criteria, §Non-Goals
- Wzorce testowe: `context/foundation/test-plan.md` §6.2, §6.3; ryzyka #2, #4
- Reguła rekomendacji: `app/Support/ShopRecommendation.php:16` (docblock
  zapowiada tego konsumenta)
- Wzorzec testu formularza: `tests/Feature/AddProductTest.php`
- Poprzedni slice: `context/archive/2026-09-11-rekomendacja-sklepu/plan.md`

## Deviations Taken During Implementation

Cztery odstępstwa od tego planu, podjęte w trakcie Fazy 1.

1. **Test przeliczonej rekomendacji usuwa dwa produkty, nie jeden.** Plan
   wymagał danych kontrolnych, w których *jedno* usunięcie odwraca ranking
   sklepów. To arytmetycznie niemożliwe: jedno usunięcie obniża pokrycie
   dowolnego sklepu najwyżej o 1, więc różnica między dwoma sklepami zmienia się
   najwyżej o 1 — za mało, by przejść z wygranej ścisłej na przegraną ścisłą.
   Jedyny jednousunięciowy wariant zmieniający wskazany sklep przechodzi przez
   remis, a remisu ten plan wprost zakazuje (precedencja sklepów nie jest
   pilnowana testem do §3 Fazy 4 test-planu). Test robi dwa usunięcia; oba końce
   są ścisłe — 3 vs 2 przed, 1 vs 2 po.

2. **Pint uruchamiany jako `vendor/bin/pint`, nie `php artisan pint`.** Komenda
   z planu (przepisana z `CLAUDE.md`) nie istnieje — `artisan` zwraca
   `Command "pint" is not defined.`. Kryterium 1.3 zaliczone przebiegiem
   `docker compose exec app php vendor/bin/pint --test` (69 plików, PASS).
   `CLAUDE.md` niesie ten sam błędny zapis i zasługuje na poprawkę poza tą
   zmianą.

3. **Dopisany szósty test: `test_the_list_renders_a_working_removal_button`.**
   Przy pierwszej próbie obalenia z kryterium 1.5 okazało się, że usunięcie
   `@method('DELETE')` nie wywraca niczego — pięć zaplanowanych testów woła
   `DELETE` wprost po HTTP, więc formularz w `home.blade.php` nie był pod
   testem w ogóle, a klik w przycisk dawałby `POST /products/{id}` i 405.
   Szósty test asercjuje przycisk, adres trasy i ukryte `_method`. Asercja na
   znaczniku normalnie łamie §6.3 regułę 4 test-planu; wyjątek uzasadniony tym,
   że ukryte pole nie jest detalem widoku, tylko kontraktem między formularzem a
   czasownikiem HTTP, pod którym zarejestrowana jest trasa — HTML nie ma innego
   sposobu, żeby go wyrazić. Decyzja właściciela podjęta w trakcie fazy. Po
   dopisaniu kryterium 1.5 spełnia się zgodnie z pierwotnym brzmieniem.

4. **Przycisk jest czerwony z białym napisem, nie linkiem tekstowym.** Plan
   kazał iść za stylem istniejącego linku z panelu rekomendacji
   (`text-indigo-600 underline`). Po obejrzeniu na ekranie właściciel odrzucił
   ten wygląd — akcja niszcząca ma wyglądać na niszczącą. Finalnie:
   `bg-red-600` + `text-white`, hover rozjaśnia do `red-500`. Wybór odcienia nie
   jest kosmetyczny: czarny napis na czerwieni tej głębi daje kontrast ~3,4:1,
   poniżej progu WCAG AA dla małego tekstu, więc para „czerwony + biały" jest tu
   jedyną przechodzącą. Dwie rzeczy kupione przy okazji, obie warte zapamiętania:
   - **`cursor-pointer` musi stać jawnie w klasach.** Tailwind 4 zmienił
     domyślny kursor `<button>` na `default`, więc bez tej klasy najechanie nie
     zmienia wskaźnika. Klasa wygląda na zbędną i łatwo ją usunąć „przy
     sprzątaniu" — w widoku stoi przy niej komentarz.
   - **Nowa klasa Tailwinda wymaga `npm run build`.** Produkcyjny CSS niesie
     wyłącznie klasy widziane przy ostatniej kompilacji, a czerwonych nie było
     dotąd nigdzie w projekcie. Pierwsza wersja przycisku renderowała się jako
     biały napis bez tła na białej karcie — niewidoczny. `public/build/` jest w
     `.gitignore`, więc do commita nic z tego nie trafia; wdrożenie musi
     zbudować front samo.

## Progress

> Konwencja: `- [ ]` oczekujące, `- [x]` zrobione. Dopisz ` — <commit sha>`, gdy
> krok wyląduje. Nie zmieniaj tytułów kroków.

### Phase 1: Usuwanie produktu end-to-end

#### Automated

- [x] 1.1 Zestaw Feature przechodzi: `docker compose exec app php vendor/bin/phpunit --testsuite Feature` — e493636
- [x] 1.2 Cały zestaw przechodzi: `docker compose exec app composer test` — e493636
- [x] 1.3 Formatowanie czyste: `docker compose exec app php artisan pint --test` — e493636
- [x] 1.4 Próba obalenia: `Product $product` w sygnaturze `destroy()` wywraca dokładnie test wyścigu — e493636
- [x] 1.5 Próba obalenia: usunięcie `@method('DELETE')` wywraca testy usuwania — e493636

#### Manual

- [x] 1.6 Kliknięcie usuwa właściwy wiersz przy podobnych nazwach — e493636
- [x] 1.7 Panel rekomendacji pokazuje przeliczony wynik bez usuniętego produktu — e493636
- [x] 1.8 Długa nazwa zawija się czytelnie na szerokości 375 px — e493636
- [x] 1.9 Usunięcie ostatniego produktu pokazuje stan pusty i komunikat rekomendacji — e493636
- [x] 1.10 Produkt usunięty w jednej sesji znika w drugiej po odświeżeniu — e493636
