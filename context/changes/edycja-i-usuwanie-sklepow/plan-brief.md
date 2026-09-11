# Edycja i usuwanie sklepów (S-06) — Plan Brief

> Pełny plan: `context/changes/edycja-i-usuwanie-sklepow/plan.md`

## What & Why

Lista sklepów dostaje w wierszu akcje **Edytuj** (nazwa + zestaw kategorii, z polem dopisania nowej) i **Usuń** (z potwierdzeniem). Dziś konfiguracja sklepów jest jednokierunkowa: raz przypisany zestaw kategorii da się poprawić wyłącznie zmianą w bazie danych — a pierwsze przypisanie prawie nigdy nie jest kompletne. To właśnie z tego powodu właściciel dopisał S-06 do roadmapy podczas planowania S-03.

## Starting Point

`ShopController` ma tylko `index/create/store`. Wszystkie wejścia dla tej zmiany są gotowe i w dwóch miejscach wprost ją zapowiadają: `ShopRecommendation` opisuje sklep o zerowym pokryciu jako stan legalny, „który S-06 wyprodukuje, usuwając kategorie", a docblock `Shop::inPrecedenceOrder()` notuje, że „nic nie zmienia nazwy sklepu do S-06". Pivot `category_shop` ma już `cascadeOnDelete` na `shop_id`, więc kasowanie sklepu sprząta przypisania bez dodatkowego kodu. Brakuje tras, metod kontrolera, `UpdateShopRequest`, widoku edycji i akcji w wierszu.

## Desired End State

Członek rodziny otwiera `/shops`, klika „Edytuj", poprawia nazwę i zaznaczenia kategorii (może dopisać kategorię spoza listy), zapisuje i wraca na listę. Sklep z odznaczonymi wszystkimi kategoriami zostaje na liście z czytelnym sygnałem, że nie trafi do rekomendacji. „Usuń" pyta o potwierdzenie; po nim sklep i jego przypisania znikają, a strona główna pokazuje rekomendację przeliczoną bez niego.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| Zakres edycji | Nazwa + kategorie | Literówka w nazwie inaczej zostaje na zawsze; jedyna alternatywa (skasuj i dodaj) przesuwa sklep na koniec kolejności precedencji remisu. |
| Sklep bez kategorii | Dozwolony przy edycji | Pozwala wyłączyć sklep z rekomendacji bez kasowania danych; `ShopRecommendation` już ten stan obsługuje i pomija taki sklep. |
| Potwierdzenie kasowania | Natywny `confirm()` na formularzu | Sklep to konfiguracja, nie pozycja listy — przypadkowy klik kasuje pracę. Jedna linia, a przy wyłączonym JS formularz nadal działa. |
| Wzorzec trasy kasowania | `{id}` bez route model bindingu | Wzorzec z `products.destroy`: drugie żądanie na ten sam sklep kończy się na liście, nie na 404. |
| Walidacja edycji | Osobny `UpdateShopRequest` | Reguły różnią się w dwóch punktach (unikalność z wykluczeniem siebie, kategorie nieobowiązkowe); klasa bazowa dla dwóch implementacji kosztowałaby więcej niż ta duplikacja. |
| Formularz | Partial współdzielony z dodawaniem | Oba formularze różnią się tylko akcją, metodą, etykietą i domyślnym zaznaczeniem — kopia to dwa miejsca do poprawiania. |
| Status w PRD | Dopisać FR-009 i FR-010 | Zamyka jedyną pozycję roadmapy bez pokrycia w wymaganiach (Otwarte pytanie 3). |
| Testy | Feature: edycja, kasowanie, wpływ na rekomendację | Suite nie ma dziś żadnego dowodu, że utrzymanie danych wpływa na gwiazdę przewodnią. |

## Scope

**W zakresie:** edycja nazwy i kategorii sklepu, dopisanie nowej kategorii z formularza edycji, wyzerowanie kategorii, usunięcie sklepu z potwierdzeniem, sygnał „brak kategorii" na liście, FR-009/FR-010 w PRD.

**Poza zakresem:** zarządzanie kategoriami (zmiana nazwy, kasowanie, sprzątanie osieroconych), cofanie usunięcia, zmiana kolejności sklepów, akcje masowe, blokada kasowania rekomendowanego sklepu, uprawnienia per rola.

## Architecture / Approach

Bez nowych warstw. `ShopController` dostaje `edit`/`update`/`destroy`; `update()` powtarza kształt `store()` (transakcja obejmująca nazwę i `sync()` kategorii), `destroy()` powtarza kształt `ProductController::destroy()`. Formularz dodawania przenosi się do partiala, z którego korzysta też edycja. Żadnej migracji — schemat już to pokrywa.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. Edycja sklepu | Trasy `edit`/`update`, `UpdateShopRequest`, partial formularza, widok edycji, link w wierszu, sygnał braku kategorii | Reguła unikalności nazwy musi wykluczać edytowany sklep — inaczej zapis bez zmiany nazwy odpada |
| 2. Usuwanie sklepu | Trasa `destroy`, metoda kontrolera, przycisk z `confirm()` | Pierwszy JS w projekcie; potwierdzenia nie da się pokryć testem funkcjonalnym |
| 3. PRD i roadmapa | FR-009/FR-010, `prd_version` → 2, zamknięcie Otwartego pytania 3 | Zmiana w plikach foundation poza katalogiem zmiany |

**Wymagania wstępne:** S-03 (wdrożone i zarchiwizowane). Brak innych.
**Szacowany nakład:** ~1-2 sesje; Faza 1 jest zdecydowanie największa.

## Open Risks & Assumptions

- Potwierdzenie kasowania opiera się na `confirm()`, którego suite nie sprawdza — przy wyłączonym JS sklep kasuje się bez pytania. Świadomie zaakceptowane; dowód wymagałby rozbudowy toru E2E (dziś tylko `seed.spec.js`).
- Sklep bez kategorii to pierwszy stan, którego formularz dodawania zabrania, a edycja dopuszcza — rozbieżność celowa, ale trzeba ją nazwać w docblocku `UpdateShopRequest`, bo wygląda jak przeoczenie.
- Osierocone kategorie (bez sklepu i bez produktu) zostają w bazie. Sprzątanie jest poza zakresem; `restrictOnDelete` na `category_id` chroni przed przypadkowym kasowaniem.

## Success Criteria (Summary)

- Członek rodziny poprawia zestaw kategorii sklepu z telefonu i widzi efekt w rekomendacji na stronie głównej.
- Sklep, który przestał być potrzebny, da się usunąć — z potwierdzeniem i bez śladu w przypisaniach kategorii.
- Każda funkcja w kodzie ma odpowiadające wymaganie w PRD; roadmapa nie ma już otwartego pytania o S-06.
