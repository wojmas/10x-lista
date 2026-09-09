# Wspólna lista produktów — Plan Brief

> Full plan: `context/changes/wspolna-lista-produktow/plan.md`
> Roadmapa: `context/foundation/roadmap.md` — pozycja S-02

## What & Why

Aplikacja ma działające logowanie i chronioną stronę główną, ale strona ta pokazuje wyłącznie komunikat „lista jest pusta" — bo nie ma czego pokazywać. Ten kawałek daje rodzinie pierwszą realną funkcję: dodawanie produktów z kategorią i wspólną listę widoczną dla wszystkich zalogowanych. Powstaje tu też model kategorii, którego S-03 użyje do opisania sklepów, a S-04 do policzenia, który sklep pokrywa najwięcej kategorii z listy.

## Starting Point

Po S-01 baza ma wyłącznie `users` (z nieużywaną kolumną `role`), `cache` i `jobs` — zero tabel domenowych i zero kontrolerów poza uwierzytelnianiem. `/` to trasa statyczna `Route::view('/', 'home')`, więc nie ma jak przekazać do widoku żadnych danych. Gotowe są za to: layout, komponenty formularza z Breeze, konwencja FormRequest z `LoginRequest`, polskie tłumaczenia walidacji i testy na SQLite `:memory:`.

## Desired End State

Zalogowany członek rodziny wchodzi na `/` i widzi wspólną listę produktów — nazwa i kategoria przy każdej pozycji, najnowsze na górze — niezależnie od tego, kto je dodał. Przycisk prowadzi na osobny ekran, gdzie wpisuje nazwę i wybiera kategorię z listy albo dopisuje nową. Próba dodania produktu, który już jest na liście, albo kategorii różniącej się od istniejącej tylko wielkością liter, zostaje odrzucona polskim komunikatem.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| Model kategorii | Osobna tabela plus możliwość dopisania nowej z formularza produktu | Rozstrzyga otwarte pytanie nr 1 z roadmapy; S-04 porównuje identyfikatory, nie napisy, ale rodzina nie musi znać wszystkich kategorii z góry |
| Porównywanie nazw | Bez wielkości liter i spacji po bokach; polskie znaki znaczące | „Nabiał" i „nabiał" to jedna kategoria — bez tego wybrany wariant z dopisywaniem po cichu rozjeżdża regułę z S-04 |
| Usuwanie produktów (FR-006) | Zostaje w S-05 | Kryterium akceptacji „usunięty produkt wpływa na przeliczenie rekomendacji" da się zweryfikować dopiero po S-04 |
| Formularz dodawania | Osobny ekran `/products/create` | Czystszy podział; strona główna w S-04 dostanie jeszcze rekomendację |
| Duplikaty produktów | Blokowane | Dwie osoby nie dopiszą niezależnie tego samego; blokada łapie dokładne trafienie po normalizacji |
| Autor produktu | Nie zapisujemy | §Poza zakresem odrzuca historię, żadne FR nie pyta „kto dodał"; kolumna bez czytelnika to zarzut, który przegląd S-01 postawił przy `role` |
| Kolejność listy | Najnowsze na górze, płaska lista | FR-004 mówi tylko „zobaczyć listę"; grupowanie po kategoriach zostaje na później |
| Adresy tras | Angielskie (`/products/create`, `POST /products`) | Zgodnie z `/login` i `/logout` z S-01; nazwa trasy `home` zostaje nietknięta |

## Scope

**W zakresie:** tabele `categories` i `products` z relacją, modele, fabryki, seeder startowych kategorii, kontroler i widok listy na stronie głównej, osobny ekran dodawania, walidacja z blokadą duplikatów po obu stronach, polskie komunikaty, testy feature i jeden test jednostkowy reguły porównania.

**Poza zakresem:** usuwanie i edycja produktów, sklepy i rekomendacja (S-03, S-04), ekran zarządzania kategoriami, ilości i jednostki, zapis autora produktu, grupowanie i sortowanie listy wybierane przez użytkownika, zmiany w uwierzytelnianiu i konfiguracji wdrożenia.

## Architecture / Approach

`Product` należy do `Category`; lista czytana jest jednym zapytaniem z załadowaną relacją, bez filtrowania po użytkowniku — wspólna widoczność wynika wprost z §Kryteriów sukcesu, a PRD wyklucza multitenancy. `/` przechodzi z `Route::view` na `ProductController@index`, zachowując nazwę trasy `home`, od której zależy nawigacja, oba kontrolery uwierzytelniania i trzy istniejące testy. Zapis idzie przez `StoreProductRequest`; rozstrzygnięcie kategorii (istniejąca kontra nowa) siedzi w `store()`. Normalizacja nazw jest jedną funkcją używaną w dwóch miejscach — przy duplikacie produktu i przy dopasowaniu kategorii — żeby obie ścieżki nie rozjechały się w czasie.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. Wspólna lista na stronie głównej | Tabele, modele, seeder kategorii i widoczna lista pod `/` | Zamiana `/` z trasy statycznej na kontroler dotyka pięciu miejsc odwołujących się do `route('home')` — dokładnie ten tryb awarii, który przegląd S-01 znalazł jako F1 |
| 2. Dodawanie produktu z kategorią | Ekran dodawania, walidacja, ścieżka nowej kategorii | Reguła porównania nazw użyta w dwóch miejscach; rozjechanie ich deduplikuje produkty, a kategorie nie — i po cichu psuje regułę z S-04 |

**Prerequisites:** S-01 (`logowanie-i-prywatny-dostep`) — zrobione i wdrożone. Potrzebne: działający `docker compose up -d`.

**Estimated effort:** dwie fazy, każda kończona osobnym commitem po zielonych testach i ręcznym potwierdzeniu.

## Open Risks & Assumptions

- Wybrany wariant kategorii pozwala dopisywać nowe z formularza, więc jedyną ochroną przed rozjazdem jest reguła porównania. Łapie ona wielkość liter i spacje, ale nie literówki („nabial" obok „nabiał" to zgodnie z decyzją dwie różne kategorie) — przy większym rozjeździe potrzebny będzie ekran scalania kategorii, którego MVP nie przewiduje.
- Blokada duplikatu produktu jest walidacyjna, nie bazodanowa. Przy pięciu osobach dodających przez formularz to wystarcza; zapis z pominięciem walidacji (seeder, tinker) duplikat przepuści.
- Kategorie z seedera nie trafią na produkcję automatycznie — `entrypoint.sh` celowo nie woła `db:seed`. Pierwsze kategorie trzeba dopisać z formularza albo uruchomić seeder ręcznie.
- Lista nie ma paginacji. Przy skali z PRD (3–5 osób, mała objętość danych) to bez znaczenia, ale bardzo długa lista wyrenderuje się w całości.

## Success Criteria (Summary)

- Członek rodziny dodaje produkt z kategorią i widzi go na stronie głównej razem z produktami dodanymi przez pozostałych.
- Kategorie nie duplikują się przez różnicę w wielkości liter, więc S-03 i S-04 dostają spójny zbiór do porównywania.
- Ten sam produkt nie trafia na listę dwa razy przez przypadek, a odrzucenie tłumaczy się po polsku i nie kasuje wpisanych wartości.
