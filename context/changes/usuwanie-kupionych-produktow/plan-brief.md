# Usuwanie kupionych produktów (S-05) — Plan Brief

> Pełny plan: `context/changes/usuwanie-kupionych-produktow/plan.md`

## What & Why

Wiersz listy zakupów dostaje przycisk „Kupione — usuń"; kliknięcie usuwa produkt
bezpowrotnie, a strona główna przelicza rekomendację bez niego. To domyka pełen
cykl zakupowy z §Success Criteria PRD (dodaj → zobacz sklep → usuń po zakupie) i
jest jedynym miejscem, w którym da się dowieść drugiego kryterium akceptacji
US-01: „usunięty produkt znika z listy i wpływa na przeliczenie rekomendacji".

## Starting Point

Wszystkie wejścia są gotowe: `ProductController::index()` liczy rekomendację
przy każdym wejściu na `/`, więc nie ma cache'u do unieważnienia; model
`Product`, relacja do kategorii i reguła `ShopRecommendation` są wdrożone i
otestowane. Brakuje wyłącznie trasy `DELETE`, metody kontrolera i przycisku w
`home.blade.php:63`.

## Desired End State

Członek rodziny odhacza kupione produkty jednym kliknięciem przy kasie, na
telefonie. Wiersz znika, panel rekomendacji nad listą pokazuje wynik bez tej
kategorii — przy odpowiednim układzie danych zmienia się sam rekomendowany
sklep. Niezalogowany nie może usunąć niczego.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| Potwierdzenie przed usunięciem | Brak — jeden klik usuwa | Czynność wykonuje się kilkanaście razy pod rząd przy kasie; każde dodatkowe okno przeszkadza |
| Informacja zwrotna | Brak flasha — wiersz znika, rekomendacja się przelicza | Lista jest komunikatem; projekt nie ma dziś infrastruktury flash i ten slice jej nie wprowadza |
| Nieistniejący produkt (wyścig, podwójny klik) | Cicho wraca na listę, bez route model bindingu | Użytkownik chciał, żeby produktu nie było, i nie ma go — 404 za czynność, która zadziałała, to komunikat nieprawdziwy |
| Akcja w wierszu | Tekstowy przycisk „Kupione — usuń" | Nazywa naraz intencję i nieodwracalny skutek; czytelny dla czytnika ekranu bez `aria-label`, duży cel dotykowy — istotne przy braku potwierdzenia |
| Zakres testów | 5 testów integracyjnych | Trzy pokrywają kryteria US-01 i bariery PRD; dwa pinują decyzje tego planu (brak soft delete, ciche powroty), które inaczej żyłyby tylko w komentarzu |

## Scope

**W zakresie:** trasa `DELETE /products/{product}` w grupie `auth`;
`ProductController::destroy()` bez model bindingu; przycisk w wierszu
`home.blade.php`; `tests/Feature/RemoveProductTest.php` z pięcioma testami.

**Poza zakresem:** soft delete i historia zakupów (§Non-Goals PRD); „Cofnij";
potwierdzenie przed usunięciem; komunikaty flash; usuwanie zbiorcze; sprzątanie
osieroconych kategorii; edycja produktu; zapinanie kolejności sklepów (§3 Faza 4
test-planu).

## Architecture / Approach

Formularz `POST` z `@method('DELETE')` w wierszu listy → trasa w istniejącej
grupie `auth` → `Product::destroy($id)` → `redirect()->route('home')` →
`index()` czyta listę na nowo i woła `ShopRecommendation::for()`.

Przeliczenie rekomendacji nie wymaga ani jednej linii nowego kodu — wynika z
przekierowania na stronę, która i tak liczy ją przy każdym żądaniu.

## Phases at a Glance

| Faza | Co dowozi | Główne ryzyko |
| --- | --- | --- |
| 1. Usuwanie produktu end-to-end | Trasa, kontroler, przycisk i 5 testów | Odruchowe użycie route model bindingu — cofa decyzję o cichym powrocie i daje 404 za czynność, która zadziałała |

**Wymagania wstępne:** S-04 wdrożony i zarchiwizowany (spełnione 2026-09-11).
**Szacowany wysiłek:** jedna sesja; trzy pliki produkcyjne, jeden testowy, zero
migracji.

## Open Risks & Assumptions

- **Brak potwierdzenia + trwałe usunięcie** — pomyłkowe kliknięcie oznacza
  ręczne wpisanie produktu od nowa. Świadomie zaakceptowane; etykieta „Kupione —
  usuń" i tekstowy (duży) cel dotykowy to jedyne złagodzenie.
- **Test przeliczonej rekomendacji nie może opierać się na remisie** —
  precedencja sklepów nie jest pilnowana żadnym testem do §3 Fazy 4 test-planu,
  więc taki test przechodziłby z niewłaściwego powodu.
- **Kategorie bez produktów zostają w bazie** — nadal przypisane sklepom i nadal
  widoczne w formularzu dodawania. Świadome, poza zakresem.

## Success Criteria (Summary)

- Członek rodziny usuwa kupiony produkt jednym kliknięciem, a wiersz znika z
  listy wszystkich zalogowanych.
- Panel rekomendacji pokazuje po usunięciu wynik przeliczony bez tego produktu.
- Niezalogowany nie może usunąć produktu.
