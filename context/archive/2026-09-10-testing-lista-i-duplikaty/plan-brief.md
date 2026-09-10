# Rdzeń listy zakupów pod kształtem formularza — Plan Brief

> Full plan: `context/changes/testing-lista-i-duplikaty/plan.md`
> Research: `context/changes/testing-lista-i-duplikaty/research.md`
> Plan testów: `context/foundation/test-plan.md` §3 Faza 1

## What & Why

Pierwsza faza wdrożenia testów. Ma dowieść dwóch rzeczy, których dzisiejszy
zestaw nie dowodzi: że produkt dodany przez jednego członka rodziny jest widoczny
dla drugiego (ryzyko #2), i że blokada duplikatu trafia w obie strony — blokuje
to, co rodzina uważa za ten sam produkt, i przepuszcza to, co uważa za różne
(ryzyko #5). Oba ryzyka wyszły z wywiadu z właścicielem, nie z lektury kodu.

## Starting Point

Kod działa — research wykonał obie ścieżki sondą i nie znalazł błędu na ścieżce
szczęśliwej. Zestaw ma 49 testów, zielonych, ale trzy z nich dowodzą mniej, niż
obiecują ich nazwy: `AddProductTest` asercjuje wiersz w bazie zamiast
wyrenderowanej listy, wysyła typy, których przeglądarka nigdy nie wyśle, a test
duplikatu opiera się na przycięciu, które robi za niego middleware. Szew „członek
A wysyła formularz → członek B odświeża stronę" nie istnieje nigdzie.

## Desired End State

Zestaw odpowiada twierdząco na cztery pytania, na które dziś nie odpowiada:
produkt wysłany przez A pojawia się na wyrenderowanej liście B; ładunek w
kształcie przeglądarki przechodzi w obu wariantach pola kategorii; komunikat o
duplikacie dociera na ekran po polsku; reguła równości blokuje to, co rodzina
uważa za ten sam produkt. `test-plan.md` §6.1 i §6.2 przestają być `TBD` i
zaczynają odpowiadać na pytanie „jak dodam tu test dla X".

## Key Decisions Made

| Decyzja | Wybór | Dlaczego | Source |
| --- | --- | --- | --- |
| `Mleko  2%` vs `Mleko 2%` | Jeden produkt — `normalize()` sprowadza wewnętrzne odstępy ASCII | Podwójna spacja to literówka, nie rozróżnienie; jedna linia, działa też na kategorie i sklepy | Plan |
| Odstępy unicode (NBSP, ZWSP) | Bez zmian — do §7 jako świadome wykluczenie | Po HTTP łapie je `TrimStrings`; poza HTTP ścieżka dziś nieosiągalna. Właściciel akceptuje dwa produkty w skrajnym przypadku | Plan |
| Warianty NFD | Bez zmian — do §7 | Klawiatura telefonu produkuje NFC; normalizacja wymagałaby rozszerzenia `intl` dla ścieżki, której nikt tu nie użyje | Plan |
| Ta sama nazwa w dwóch kategoriach | Jeden produkt — blokada zostaje globalna | Lista zakupów jest jedna, mleko kupuje się raz. Decyzja istniała w kodzie, ale nie była zapisana ani przetestowana | Plan |
| Słabe testy w `AddProductTest` | Poprawione na miejscu, nie dublowane | Tak zrobił przegląd S-03 z `AddShopTest` (F6, F9) — zestaw zostaje jednym źródłem prawdy, bez testu-widma obok | Plan |
| Granice transakcji w `store()` | Bez zmian | Osierocona kategoria po awarii to dodatkowa pozycja na liście wyboru, nie utrata danych | Research |
| Kolejność produktów na liście | Nie asercjowana | `Product::latest()` sortuje po `created_at` bez rozstrzygnięcia remisu — asercja byłaby chwiejna między silnikami | Research |

## Scope

**In scope:**
- Jedna linia w `NameComparison::normalize()` plus docblock o granicach reguły
- Testy jednostkowe reguły równości nazw
- Poprawka trzech słabych przypadków w `AddProductTest` na miejscu
- Nowe testy: szew dwóch członków, komunikat duplikatu na ekranie, globalny zakres blokady
- Zawężenie nazwy i docbloka jednego testu w `ProductListTest`
- `test-plan.md`: §6.1, §6.2, §6.6, dwa wpisy w §7, status §3 wiersz 1

**Out of scope:**
- Ryzyka #1, #3, #4, #6 — mają własne fazy w §3
- Normalizacja unicode i NFD
- Transakcja wokół `ProductController::store()`
- Wpinanie czegokolwiek w CI (§7 wyklucza świadomie)
- `AddShopTest`, `ShopListTest`, `CategoryResolverTest`, `CategorySeederTest`

## Architecture / Approach

Od najtańszej warstwy w górę, jak każe `test-plan.md` §1 zasada 1. Reguła
równości najpierw, w izolacji — bez bazy i bez kontenera Laravela, najszybsza
pętla w projekcie. Potem zachowanie po HTTP, czyli to, czego rodzina realnie
doświadcza: ładunek w kształcie formularza, podążenie za przekierowaniem, dwóch
różnych członków w dwóch żądaniach. Na końcu książka kucharska.

Każdy nowy test przechodzi **próbę obalenia**: tymczasowo cofnąć regułę, którą
pinuje, i zobaczyć, że test upada. Ten krok jest w kryteriach sukcesu, nie w
dobrych intencjach — przeglądy S-02 i S-03 obie znalazły testy przechodzące z
niewłaściwego powodu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Reguła równości nazw | Sprowadzanie odstępów w `normalize()` + testy jednostkowe | Zmiana dotyka czterech wołających naraz — również kategorii i sklepów |
| 2. Ścieżka formularza po HTTP | Poprawione stare przypadki, szew dwóch członków, komunikat na ekranie, zakres blokady | `assertSessionHasErrors()` przed `followingRedirects()` czyni asercję pustą (F4 z S-03) |
| 3. Domknięcie planu testów | §6.1, §6.2, §6.6, dwa wpisy §7, status §3 | Wzorce napisane jako streszczenie fazy zamiast jako instrukcja |

**Prerequisites:** działający `docker compose` (usługi `app`, `db`), zestaw
zielony na `main` (49 testów, 150 asercji, commit `320ece2`).
**Estimated effort:** jedna sesja, trzy fazy; Faza 1 i 3 krótkie, Faza 2 jest
ciężarem.

## Open Risks & Assumptions

- Zmiana `normalize()` dotyka kategorii i sklepów, nie tylko produktów. Wszystkie
  dzisiejsze dane kontrolne mają pojedyncze spacje, więc żaden istniejący test
  nie zmienia wyniku — ale to założenie warto potwierdzić pełnym przebiegiem, nie
  samym `--testsuite Unit`.
- Reguła równości pozostaje rozdzielona na dwa miejsca: `NameComparison` i
  globalny `TrimStrings` w stosie Laravela. Zmiana `bootstrap/app.php` mogłaby po
  cichu zmienić blokadę duplikatu. Faza 3 zapisuje to w §6.6; nic tego nie pilnuje
  automatycznie i to jest świadoma decyzja.
- Testy na `Product::latest()` bez rozstrzygnięcia remisu byłyby chwiejne — plan
  ich nie pisze, ale nic nie broni przed dopisaniem takiej asercji w przyszłości.

## Success Criteria (Summary)

- Zestaw zielony i liczniejszy niż 49 testów; każdy nowy test daje się obalić
  przez cofnięcie reguły, którą pinuje
- Członek rodziny dodający duplikat widzi polski komunikat na formularzu, a nie
  cichy powrót na listę
- `test-plan.md` §6.1 i §6.2 dają się wykonać przez kogoś, kto nie brał udziału w
  tej fazie
