# Rekomendacja sklepu na stronie głównej (S-04) — Plan Brief

> Full plan: `context/changes/rekomendacja-sklepu/plan.md`

## What & Why

Strona główna pokazuje listę zakupów, ale nie odpowiada na pytanie, które
odróżnia ten produkt od Google Keep: „GDZIE jechać na te zakupy?". Ten slice
dowozi regułę z §Business Logic PRD — sklep pokrywający najwięcej różnych
kategorii z aktualnej listy, remis na korzyść sklepu dodanego pierwszego — i
panel, który ją pokazuje. Roadmapa nazywa S-04 gwiazdą przewodnią: jeśli
rekomendacja okaże się bezużyteczna, reszta funkcji nie ma znaczenia.

## Starting Point

Wszystkie wejścia reguły są wdrożone i zapięte: relacja sklep–kategoria
(`Shop::categories()`), niemożliwość podwójnego przypisania kategorii
(`ShopCategoryAssignmentTest`), spójny słownik kategorii (`CategoryResolver` +
`NameComparison`). `ProductController::index()` eager-loaduje kategorie produktów
wprost z myślą o tej fazie. Czego nie ma: samej reguły, jej ekranu i
jakiegokolwiek testu, który by ją pilnował — Faza 2 planu testów wypchnęła te
testy tutaj. Kontrakt remisu (`orderBy('id')`) żyje inline w
`ShopController::index()` i broni go wyłącznie docblock; test, który deklarował
jego pilnowanie, został w Fazie 2 skasowany jako zielony test kłamiący.

## Desired End State

Członek rodziny wchodzi na `/` i nad listą widzi panel: nazwę rekomendowanego
sklepu z licznikiem „pokrywa 3 z 4 kategorii z listy", a pod spodem alternatywę —
ale tylko wtedy, gdy drugi sklep faktycznie cokolwiek pokrywa. Pusta lista daje
komunikat zachęty, brak dopasowania — komunikat z odnośnikiem do konfiguracji
sklepów. Panel przelicza się przy każdym wejściu, więc po dodaniu produktu
(przekierowanie na `home`) użytkownik od razu widzi nowy wynik.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego | Źródło |
| --- | --- | --- | --- |
| Pusta lista zakupów | Komunikat zamiast sklepu, panel zostaje | Układ nie skacze przy pierwszym produkcie, a nowy użytkownik widzi, że funkcja istnieje | Plan |
| Zero pokrycia | Jawny komunikat „brak dopasowania" z linkiem do sklepów | Odróżnia „nie wiem" od rady bez podstawy i kieruje do jedynej naprawy w MVP | Plan |
| Ile sklepów widać | Zwycięzca + runner-up | Właściciel chce widzieć alternatywę; sortowanie jest to samo, więc nie ma drugiego kontraktu | Plan |
| Uzasadnienie na ekranie | Licznik pokrycia „X z Y" | Tłumaczy decyzję i od razu ujawnia źle skonfigurowany sklep; daje testom widoczną wyrocznię | Plan |
| Gdzie mieszka reguła | `App\Support\ShopRecommendation`, liczenie w PHP | Trzeci obywatel `App\Support`; liczenie w PHP omija rozjazd SQLite/Postgres, który kosztował już jedną fazę | Plan |
| Kontrakt precedencji | Scope `Shop::inPrecedenceOrder()`, dowód zostaje Fazie 4 | Wykonuje polecenie z §6.3 i zbija dwie kopie klauzuli do jednej, którą Faza 4 będzie miała co zapinać | Plan |
| Runner-up | Widoczny tylko przy pokryciu > 0 | „Alternatywa: Żabka (0 z 4)" to szum podważający zaufanie do panelu | Plan |
| Zakres testów | Cztery scenariusze reguły + cztery warianty panelu | Zestaw nie ma dziś ani jednego testu samej reguły; §6.3 przypisuje je wprost temu planowi | Plan |

## Scope

**In scope:** klasa `App\Support\ShopRecommendation`; scope
`Shop::inPrecedenceOrder()` i refaktor `ShopController::index()`; panel w
`home.blade.php` z trzema wariantami; `ProductController::index()` wczytujący
sklepy; `tests/Feature/ShopRecommendationTest.php`;
`tests/Feature/HomeRecommendationTest.php`; korekta §6.3 i §3 w `test-plan.md`.

**Out of scope:** usuwanie produktów (S-05); edycja i usuwanie sklepów (S-06);
przebieg zestawu na Postgresie i dowód kontraktu kolejności z bazy (Faza 4 planu
testów); ranking wszystkich sklepów; ważenie cen/jakości; cache i przeliczanie w
tle; zmiany w formularzach.

## Architecture / Approach

Kontroler składa dwa wejścia (produkty z kategoriami, sklepy z kategoriami
uporządkowane przez scope precedencji) i oddaje je regule. Reguła liczy w PHP
liczbę różnych kategorii z listy pokrytych przez każdy sklep, sortuje malejąco po
pokryciu na kolekcji wejściowo uporządkowanej po `id` — stabilne sortowanie PHP
jest tym, co realizuje regułę remisu — i zwraca jeden obiekt, z którego Blade
wybiera jeden z trzech wariantów panelu. Zero logiki w widoku, zero liczenia po
stronie bazy.

## Phases at a Glance

| Faza | Co dowozi | Główne ryzyko |
| --- | --- | --- |
| 1. Reguła pokrycia i konsolidacja precedencji | `ShopRecommendation`, scope precedencji, cztery testy reguły | Test remisu napisany na kolejności wierszy zamiast na regule w PHP — przeszedłby także po jej złamaniu (§6.3 reguła 3) |
| 2. Panel rekomendacji na stronie głównej | Trzy warianty panelu, licznik, warunkowa alternatywa, cztery testy przez HTTP | Odmiana liczebnika po polsku („1 kategorię / 2 kategorie / 5 kategorii") — żaden test nie złapie tego za autora |

**Prerequisites:** S-02 i S-03 zarchiwizowane (są); kontener `app` działa; zestaw
zielony na `bde57e4`.
**Estimated effort:** ~1 sesja, dwie fazy, bez nowych zależności i bez migracji.

## Open Risks & Assumptions

- **Kontrakt remisu nadal nie ma pełnego dowodu.** Test z Fazy 1 obala złamanie
  tie-breaku w PHP, ale nie usunięcie `orderBy('id')` ze scope'u — tego na SQLite
  dowieść się nie da. Ta połowa zostaje przy Fazie 4 planu testów, której
  wyzwalaczem był właśnie start tego slice'u.
- **Konsolidacja klauzuli opiera się na docblocku, nie na teście.** Refaktor
  `ShopController::index()` na scope jest zmianą produkcyjną bez strażnika w
  zestawie; pilnuje go wyłącznie `ShopListTest`, który sprawdza treść, nie
  kolejność.
- **Sklep o zerowym pokryciu jest dziś nieosiągalny z UI.** Reguła go obsługuje,
  ale realnie wyprodukuje go dopiero S-06 — do tego czasu ta ścieżka jest broniona
  wyłącznie testem, nie użyciem.
- **Liczenie w PHP wczytuje wszystkie sklepy do pamięci.** Świadome przy skali
  3–5 osób; przy tysiącach sklepów reguła musi wrócić do zapytania z `COUNT`, a
  wtedy potrzebuje przebiegu na Postgresie, żeby dało się ją uczciwie zapiąć.

## Success Criteria (Summary)

- Trzy produkty w dwóch kategoriach dają pokrycie 2, nie 3 — i jest to zapięte
  testem, który upada po zamianie na liczenie produktów.
- Przy równym pokryciu strona główna wskazuje sklep dodany pierwszy, a nie
  pierwszy alfabetycznie.
- Członek rodziny z pustą listą, z listą bez dopasowania i z listą dopasowaną
  widzi trzy różne, sensowne komunikaty — żaden z nich nie jest radą bez podstawy.
