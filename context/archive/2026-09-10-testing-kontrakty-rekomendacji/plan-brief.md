# Kontrakty rekomendacji przed S-04 — Plan Brief

> Full plan: `context/changes/testing-kontrakty-rekomendacji/plan.md`
> Research: `context/changes/testing-kontrakty-rekomendacji/research.md`

## What & Why

Faza 2 wdrożenia testów miała zapinować dwa kontrakty, na których stanie S-04
(rekomendacja sklepu): kolejność rozstrzygania remisu sklepów i tożsamość
przypisania kategorii. Research ustalił, że tylko jeden z nich da się dziś
uczciwie zapinować — rozbieżność kolejności widać wyłącznie na Postgresie, a
zestaw biegnie na SQLite. Faza dowozi więc kontrakt pokrycia, kasuje test, który
kłamie o kontrakcie kolejności, i przekazuje ten drugi Fazie 4.

## Starting Point

52 zielone testy na `a570c06`. Kod produkcyjny działa zgodnie z PRD na obu
silnikach — research sprawdził sondą. Problem jest w zestawie:
`ShopListTest:45` deklaruje pinowanie precedencji S-04, a przechodzi po usunięciu
`orderBy('id')` z kontrolera na SQLite **i** na Postgresie. Inwariant
`unique(['shop_id','category_id'])` żyje tylko w docblocku migracji. Ryzyko #3
okazało się w większości historyczne — trzeci pisarz kategorii został
skonsolidowany w S-03; zostaje jedna luka: kolizja „checkbox + wpisana nazwa".

## Desired End State

Zestaw pinuje, że dwukrotne przypisanie tej samej kategorii do sklepu jest
niemożliwe i że jedno zgłoszenie wskazujące kategorię dwiema drogami daje jedno
pokrycie. Test kłamiący o precedencji nie istnieje. Plan testów przypisuje
odroczoną połowę ryzyka #1 Fazie 4, a §6.3 mówi autorowi S-04 wprost, gdzie
kończy się dowód.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego | Źródło |
| --- | --- | --- | --- |
| Kontrakt remisu wobec SQLite | Odroczyć do Fazy 4 / S-06 | Do S-06 rozbieżność jest nieosiągalna z UI; test na SQLite i tak by nie upadł | Plan |
| Precedencja w kontrolerze | Zostawić inline | Scope bez testu, który go uzasadnia, to zmiana produkcyjna na wiarę; wrócić przy Fazie 4 | Plan |
| Formularz sklepu (`prohibits`) | Zostawić, zapinać jako kontrakt | Asymetria wobec formularza produktu jest zamierzona i to ta ścieżka jest jedyną luką ryzyka #3 | Plan |
| `ShopListTest:45` | Usunąć | Nie da się go obalić bez Postgresa; zielony test, który kłamie, jest gorszy niż jego brak | Plan |
| Zakres ryzyka #3 | Tylko luka + unikalność pivota | Konsolidacja pisarzy zamknięta w S-03; dopisywanie testów do zamkniętej luki to antywzorzec z §2 | Research + Plan |
| Zapis odroczenia | Faza 4 przejmuje #1, §6.3 wypełnione | Odroczenie staje się zaplanowaną pracą z właścicielem, a nie luką bez właściciela | Plan |
| Ryzyko #3 Medium → Low | Przeliczone, zostaje w mapie | Trzeci pisarz nie istnieje; ryzyko chroni przed regresją konsolidacji | Research (backport zrobiony) |
| `Product::latest()` jako trop #1 | Odrzucone | Rekomendacja liczy kategorie, nie kolejność produktów | Research (backport zrobiony) |

## Scope

**In scope:** test unikalności pary sklep–kategoria (bez HTTP); test kolizji
checkbox + wpisana nazwa (po HTTP); docblock asymetrii w `StoreShopRequest`;
kasacja `ShopListTest::test_shops_are_listed_in_ascending_id_order`; docblock
kontraktu-długu w `ShopController::index()`; korekta §3 i wypełnienie §6.3/§6.6
w `test-plan.md`.

**Out of scope:** test remisu sklepów; konfiguracja przebiegu na Postgresie;
`Shop::inPrecedenceOrder()`; `prohibits` na formularzu sklepu; testy regresji
dla `CategoryResolver`/`CategorySeeder`; test sklepu o zerowym pokryciu; testy
samej reguły rekomendacji (plan S-04).

## Architecture / Approach

Zero zmian w logice produkcyjnej — dwa docblocki i pliki testowe. Kolejność faz
jest wymuszona: Faza 2 zabiera z zestawu asercję i nie oddaje nic w zamian, więc
jest uzasadniona wyłącznie tym, że Faza 3 przenosi ten dowód do Fazy 4 w planie.

## Phases at a Glance

| Faza | Co dowozi | Główne ryzyko |
| --- | --- | --- |
| 1. Kontrakty pokrycia sklepu | Dwa testy upadające dziś na SQLite + docblock asymetrii formularzy | Test kolizji napisany na `sync()` zamiast na widocznym pokryciu — pinowałby implementację, nie zamiar |
| 2. Kasacja testu kłamiącego | Usunięty `ShopListTest:45`, kontrakt precedencji nazwany długiem w kontrolerze | Zestaw przestaje pilnować kolejności sklepów **czymkolwiek** — także sortowania po nazwie, które stary test łapał |
| 3. Domknięcie planu testów | §3 przypisuje #1 Fazie 4, §6.3 bez `TBD`, notatka §6.6 | Bez tej fazy kasacja z Fazy 2 zostawia lukę bez właściciela |

**Prerequisites:** Faza 1 wdrożenia zamknięta (jest); kontener `app` działa;
zestaw zielony na `a570c06`.
**Estimated effort:** ~1 sesja, trzy fazy, żadnej nowej infrastruktury.

## Open Risks & Assumptions

- **Kolejność sklepów zostaje niepilnowana do Fazy 4.** Świadomy koszt decyzji
  „nie trzymamy zielonych testów, które kłamią" — ale realny: regresja typu
  „ktoś zmienia `orderBy('id')` na `orderBy('name')`" przechodzi bez sygnału.
- **Odroczenie stoi na założeniu, że S-06 przyjdzie po S-04.** Jeśli kolejność
  slice'ów się odwróci, ekran edycji sklepu uczyni rozbieżność osiągalną, zanim
  Faza 4 wyląduje.
- **`orderBy('id')` w kontrolerze nie zmusza S-04 do niczego.** S-04 może
  napisać własne zapytanie i kontrakt zniknie. Po tej fazie broni go wyłącznie
  docblock i wiersz Fazy 4 w planie.
- **Roadmapa nie ma pozycji o Change ID `testing-kontrakty-rekomendacji`** —
  synchronizacja statusu pominięta, to zmiana wdrożeniowa, nie slice produktowy.

## Success Criteria (Summary)

- Usunięcie `unique(['shop_id','category_id'])` z migracji wywala test — inwariant S-04 ma strażnika w zestawie, nie tylko w komentarzu.
- Zaznaczenie „Nabiał" i wpisanie „NABIAŁ" w jednym zgłoszeniu daje sklep pokrywający tę kategorię raz, i jest to zapinowane.
- Żaden zielony test w repozytorium nie twierdzi, że pilnuje kolejności sklepów; kto zacznie S-04, przeczyta w §6.3 i w kontrolerze, gdzie kończy się dowód.
