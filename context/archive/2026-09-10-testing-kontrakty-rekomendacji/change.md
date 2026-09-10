---
change_id: testing-kontrakty-rekomendacji
title: Kontrakty rekomendacji przed S-04 — faza 2 wdrożenia testów
status: archived
created: 2026-09-10
updated: 2026-09-10
archived_at: 2026-09-10T12:54:39Z
---

## Notes

Rollout Phase 2 of `context/foundation/test-plan.md`: "Kontrakty rekomendacji przed S-04".

Risks covered: #3, #1 (część: podwójne policzenie kategorii). Test types: integration.

Co zostało dowiedzione:

- **#3 — domknięte.** Sklep pokrywa kategorię raz, którąkolwiek z dwóch dróg zostanie wskazana: jedno zgłoszenie formularza z zaznaczonym checkboxem i wpisaną nazwą w innym zapisie daje jedno przypisanie (`AddShopTest`). Inwariant `unique(['shop_id','category_id'])` ma strażnika w zestawie, nie tylko w docblocku migracji (`ShopCategoryAssignmentTest`).
- **#1 — połowa.** Podwójne policzenie kategorii jest zablokowane i zapinowane (jak wyżej). Rozstrzyganie remisu sklepów **nie zostało dowiedzione** i przeszło do Fazy 4 planu testów — rozbieżność jest odtwarzalna wyłącznie na Postgresie, a zestaw biegnie na SQLite, więc test napisany dziś przechodziłby także po cofnięciu reguły, którą miałby pinować.

Pierwotna intencja fazy zakładała ponadto dowód „jeden rekord przez KAŻDEGO pisarza". Research ustalił, że trzeci pisarz kategorii został skonsolidowany już w S-03 — dziś są dwaj, obaj przez `NameComparison`, obaj otestowani. Dopisywanie tam testów byłoby pracą na luce, której nie ma.

Skutek uboczny: zniknął `ShopListTest::test_shops_are_listed_in_ascending_id_order`, który deklarował pinowanie precedencji S-04, a przechodził po usunięciu `orderBy('id')` na obu silnikach. Do czasu Fazy 4 kolejność sklepów nie jest pilnowana żadnym testem — kontrakt nazwany długiem w docblocku `ShopController::index()`.

Zakres fazy to wyłącznie kontrakty, na których stanie S-04 — sama reguła rekomendacji jeszcze nie istnieje i jej testy należą do planu S-04.
