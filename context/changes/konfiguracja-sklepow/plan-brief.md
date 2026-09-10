# Konfiguracja sklepów — Plan Brief

> Full plan: `context/changes/konfiguracja-sklepow/plan.md`
> Roadmapa: `context/foundation/roadmap.md` — pozycja S-03

## What & Why

Aplikacja umie już prowadzić wspólną listę zakupów z kategoriami, ale nie wie nic o sklepach — czyli nie ma z czego policzyć odpowiedzi na pytanie „gdzie jechać", które jest jedyną rzeczą odróżniającą ten produkt od Google Keep. Ten kawałek dodaje drugą stronę modelu kategorii: sklepy z przypisanym asortymentem. Utrwala też dwa kontrakty, na których stanie reguła rekomendacji z S-04 — kolejność rozstrzygania remisów i tożsamość przypisania kategorii.

## Starting Point

Po S-02 istnieją kategorie (tabela z unikalną nazwą, seeder z dziesięcioma pozycjami), produkty, `NameComparison` jako jedyna definicja „ta sama nazwa" z trzema użytkownikami, oraz ustalone wzorce listy, formularza, walidacji i testów. Sklepów nie ma w żadnej postaci. Logika rozstrzygania kategorii z formularza siedzi prywatnie w `ProductController` — drugi formularz nie ma jak jej użyć bez skopiowania.

## Desired End State

W nagłówku, obok „Lista zakupów", pojawia się druga pozycja prowadząca do `/shops`, gdzie widać skonfigurowane sklepy z ich kategoriami — w kolejności dodania, czyli tej samej, którą S-04 rozstrzygnie remis. Osobny formularz przyjmuje nazwę sklepu i zaznaczenie kategorii polami wyboru, z opcjonalnym dopisaniem nowej. Sklep bez kategorii albo o nazwie już istniejącej zostaje odrzucony polskim komunikatem.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| Kolejność przy remisie | Rosnąco po `id` | Klucz jest ściśle rosnący i nigdy nie remisuje; `created_at` remisuje przy zapisach w tym samym ułamku sekundy i czyni rekomendację niedeterministyczną |
| Duplikat nazwy sklepu | Blokowany przez `NameComparison` | Dwie „Biedronki" rozbijają pokrycie kategorii na pół i obie przegrywają z trzecim sklepem — ta sama klasa cichego błędu, którą przegląd S-02 znalazł w `CategorySeeder` |
| Sklep bez kategorii | Niedozwolony, wymagana co najmniej jedna | Nic nie pokryje, więc nigdy nie zostanie zarekomendowany — powstałby wpis wyglądający na skonfigurowany, a dla S-04 nieistniejący |
| Podwójne przypisanie kategorii | Unikalne ograniczenie na parze w bazie | Bez niego S-04 policzy tę samą kategorię dwa razy i wskaże zły sklep |
| Edycja i usuwanie sklepów | Poza zakresem → nowy kawałek S-06 | Nie blokuje gwiazdy przewodniej; roadmapa rozszerzona o `edycja-i-usuwanie-sklepow` |
| Układ ekranów | Lista `/shops` plus osobny formularz `/shops/create` | Ten sam układ co produkty w S-02; lista jest też miejscem, w które S-06 wpisze edycję bez przebudowy |
| Nowa kategoria z formularza sklepu | Tak, tą samą ścieżką co przy produkcie | Konfigurując sklep myśli się asortymentem, więc naturalnie wychodzą kategorie, których jeszcze nie ma |
| Rozstrzyganie kategorii | Wyciągnięte do `app/Support/CategoryResolver.php` | Dwa formularze tworzące kategorie to dwa miejsca do rozjechania — dokładnie konfiguracja, w której przegląd S-02 znalazł błąd |
| Dostęp do ekranu | Odnośnik w nawigacji, desktop i mobile | Konfiguracja sklepów jest równorzędna liście, nie jej podstroną; pominięcie wariantu mobilnego czyni ją nieosiągalną na telefonie |
| Kontrolka kategorii | Pola wyboru (checkboxy) | Działa natywnie na dotyku bez JavaScriptu; `select multiple` na telefonie jest nieprzewidywalny |

## Scope

**W zakresie:** tabele `shops` i `category_shop` z unikalną parą, model `Shop` z relacją wiele-do-wielu, fabryka, lista sklepów pod `/shops`, druga pozycja nawigacji w obu wariantach, wyciągnięcie `CategoryResolver` i przepięcie na niego formularza produktu, formularz dodawania sklepu z checkboxami i polem nowej kategorii, walidacja, testy feature.

**Poza zakresem:** edycja i usuwanie sklepów (S-06), rekomendacja (S-04), zarządzanie kategoriami, priorytet i ręczne sortowanie sklepów, adres i godziny otwarcia, zapis autora sklepu, zmiany w zachowaniu formularza produktu.

## Architecture / Approach

`Shop` należy do wielu `Category` przez tabelę `category_shop` z unikalną parą `(shop_id, category_id)`. Lista czytana jednym zapytaniem z załadowaną relacją, sortowana po `id` — tą samą kolejnością, którą S-04 rozstrzygnie remis, żeby ekran pokazywał realne pierwszeństwo. Zapis idzie przez `StoreShopRequest`; utworzenie sklepu i przypisanie kategorii dzieje się w transakcji, a kategorie podpina jedna operacja synchronizacji relacji. Rozstrzyganie „istniejąca kategoria kontra wpisana nazwa" przenosi się z `ProductController` do `app/Support/CategoryResolver.php` i obsługuje oba formularze — razem z obsługą wyścigu na unikalnym indeksie, dodaną w przeglądzie S-02.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. Sklepy i ich lista | Tabele, model, lista pod `/shops`, pozycja w nawigacji | Kształt tabel to jedyna decyzja kosztująca migrację na żywych danych; pominięcie wariantu mobilnego nawigacji czyni ekran nieosiągalnym na telefonie |
| 2. Wspólne rozstrzyganie kategorii | `CategoryResolver` używany przez formularz produktu | Refaktor kodu działającego na produkcji — obsługa wyścigu z `UniqueConstraintViolationException` musi przenieść się w całości, inaczej wraca naprawiona już usterka |
| 3. Dodawanie sklepu z kategoriami | Formularz, walidacja, przypisanie kategorii | Cztery reguły naraz (duplikat nazwy, wymagana kategoria, dopasowanie nowej kategorii, brak podwójnego przypisania) — każda cicho psuje S-04, jeśli zawiedzie |

**Prerequisites:** S-02 (`wspolna-lista-produktow`) — zrobione, przejrzane i zarchiwizowane. Potrzebne: działający `docker compose up -d`.

**Estimated effort:** trzy fazy, każda kończona osobnym commitem po zielonych testach i ręcznym potwierdzeniu.

## Open Risks & Assumptions

- Kolejność `id` jest niezmienna. Gdyby rodzina chciała kiedyś ręcznie ustawić, który sklep wygrywa remisy, potrzebna będzie osobna kolumna i migracja — świadomie odłożone, bo żadne FR tego nie wymaga.
- Blokada duplikatu nazwy sklepu jest walidacyjna, nie bazodanowa (unikalny indeks łapie tylko dokładne trafienie). Zapis z pominięciem walidacji — seeder, tinker — duplikat przepuści.
- Dopisywanie kategorii z dwóch formularzy oznacza dwa miejsca wołające `CategoryResolver`. Ochroną jest to, że oba wołają tę samą klasę; trzecim pisarzem pozostaje `CategorySeeder`, poprawiony w przeglądzie S-02.
- Dwa realnie różne sklepy o tej samej nazwie (dwa oddziały sieci) wymagają rozróżnienia w nazwie — konsekwencja blokady duplikatów.
- S-06 nie ma pokrycia w żadnym FR. Albo PRD dostanie nowe wymaganie, albo pozycja zostaje jako świadome rozszerzenie zakresu przez właściciela.

## Success Criteria (Summary)

- Członek rodziny dodaje sklep z zaznaczonymi kategoriami i widzi go na osobnym ekranie, osiągalnym z nawigacji także na telefonie.
- Kategorie nie duplikują się przez różnicę w wielkości liter, a sklep nie może powstać bez asortymentu ani z nazwą, która już jest — S-04 dostaje dane, na których jego reguła da jednoznaczny wynik.
- Formularz produktu z S-02 zachowuje się dokładnie tak jak przed refaktorem, co potwierdza brak zmian w jego testach.
