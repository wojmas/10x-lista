---
project: "Lista Zakupów"
context_type: greenfield
created: 2026-05-19
updated: 2026-05-19

checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "kategoria bólu"
      decision: "oba problemy równoważne — lista zakupów + rekomendacja sklepu to dwa filary MVP"
    - topic: "insight vs istniejące rozwiązania"
      decision: "współdzielone listy istnieją, ale żadna nie mówi GDZIE jechać; plus użytkownik chce zbudować coś własnego"
    - topic: "zakres persony"
      decision: "konkretna rodzina użytkownika — buduje dla siebie i bliskich"
    - topic: "model auth"
      decision: "login + hasło, konta tworzy admin, brak rejestracji"
    - topic: "rozdzielenie ról"
      decision: "admin (zarządzanie kontami) + członek (wszystko inne); produkty, sklepy, kategorie dostępne dla obu ról"
    - topic: "zakres MVP"
      decision: "dwa flowy: lista zakupów z rekomendacją + konfiguracja sklepów/kategorii; plus usuwanie produktów po zakupie"
    - topic: "timeline"
      decision: "3 tygodnie po godzinach"
    - topic: "usuwanie produktów"
      decision: "must-have w MVP — oznaczanie jako kupione = usunięcie z listy"
  frs_drafted: 8
  quality_check_status: accepted
  product_type: web-app
  target_scale:
    users: small
    qps: low
    data_volume: small
  timeline_budget:
    mvp_weeks: 3
    hard_deadline: null
    after_hours_only: true
---

## Vision & Problem Statement

Członkowie rodziny mają dwa równoważne problemy z zakupami. Po pierwsze — zapominanie o produktach: ktoś potrzebuje czegoś do kupienia, ale osoba jadąca do sklepu o tym nie wie lub zapomina. Po drugie — jazda do nieodpowiedniego sklepu: brak podpowiedzi który sklep jest najlepszy dla danego zestawu produktów pod względem jakości, ceny i asortymentu.

Współdzielone listy (Google Keep, WhatsApp, kartka na lodówce) rozwiązują tylko pierwszy problem — żadna nie odpowiada na pytanie „GDZIE jechać na te zakupy?". To jest insight: połączenie listy z rekomendacją sklepu. Dodatkowo użytkownik chce zbudować własne narzędzie zamiast korzystać z gotowca.

## User & Persona

### Primary persona
**Członek rodziny robiący zakupy** — osoba w konkretnej, własnej rodzinie twórcy. Jest jednocześnie twórcą i głównym użytkownikiem produktu. Sięga po produkt w momencie gdy planuje wyjazd do sklepu i potrzebuje wiedzieć: co kupić i gdzie jechać. Pozostali członkowie rodziny wpisują produkty do kupienia na bieżąco.

## Access Control

Dwie role: **Admin** i **Członek**.

- **Logowanie**: login + hasło. Brak publicznej rejestracji — konta tworzy wyłącznie admin.
- **Admin**: tworzy i usuwa konta członków rodziny. Poza tym ma te same uprawnienia co członek.
- **Członek**: dodaje produkty do listy, dodaje sklepy, przypisuje kategorie do sklepów, przegląda rekomendacje.
- **Niezalogowany użytkownik**: brak dostępu — przekierowanie do strony logowania.

## Success Criteria

### Primary
- Członek rodziny może dodać produkty z kategorią, zobaczyć rekomendowany sklep na homepage i usunąć produkty po zakupie — pełen cykl zakupowy działa end-to-end.
- Konfiguracja sklepów z przypisaniem kategorii produktów działa i wpływa na rekomendację.

### Secondary
- (brak — usuwanie produktów przeniesione do primary)

### Guardrails
- Dane nie mogą się gubić — dodany produkt musi być widoczny dla wszystkich zalogowanych członków rodziny.
- Prywatność — lista zakupów widoczna wyłącznie dla zalogowanych członków rodziny.
- Aplikacja musi działać na telefonie — responsywny UI.

## Functional Requirements

### Autentykacja
- FR-001: Członek może zalogować się do aplikacji za pomocą loginu i hasła. Priority: must-have
  > Socrates: Kontrargument: link z tokenem byłby prostszy dla 3-5 osób. Rezolucja: odrzucony — użytkownik chce pełne logowanie dla bezpieczeństwa i identyfikacji.
- FR-002: Admin może stworzyć konto członka rodziny z poziomu UI. Priority: nice-to-have
  > Socrates: Kontrargument: rodzina 3-5 osób, konta można założyć ręcznie w bazie. Rezolucja: przyjęty — na MVP konta zakładane przez seed/SQL, panel admina odłożony.
- FR-003: Admin może usunąć konto członka rodziny z poziomu UI. Priority: nice-to-have
  > Socrates: Kontrargument: jw. Rezolucja: przyjęty — degradacja do nice-to-have razem z FR-002.

### Lista zakupów
- FR-004: Członek może zobaczyć listę produktów do kupienia na stronie głównej. Priority: must-have
  > Socrates: Brak kontrargumentu — serce produktu.
- FR-005: Członek może dodać produkt do listy podając nazwę i kategorię. Priority: must-have
  > Socrates: Kontrargument: ręczna kategoria spowalnia dodawanie. Rezolucja: odrzucony — bez kategorii rekomendacja nie działa, kategoria jest wymagana.
- FR-006: Członek może usunąć produkt z listy (oznaczenie jako kupiony). Priority: must-have
  > Socrates: Brak kontrargumentu — zamyka cykl zakupowy.

### Rekomendacja sklepu
- FR-007: Członek może zobaczyć rekomendowany sklep na stronie głównej, obliczany na podstawie aktualnej listy produktów i konfiguracji sklepów. Priority: must-have
  > Socrates: Kontrargument: prosta logika (ilość produktów) nie uwzględnia wagi zakupów. Rezolucja: odrzucony — prosta logika wystarczy na MVP, wagi/priorytety to przyszłość.

### Konfiguracja sklepów
- FR-008: Członek może dodać sklep i przypisać mu kategorie produktów w osobnym widoku. Priority: must-have
  > Socrates: Brak kontrargumentu — bez tego rekomendacja nie ma danych.

## User Stories

### US-01: Planowanie zakupów z rekomendacją sklepu

- **Given** zalogowany członek rodziny z co najmniej jednym skonfigurowanym sklepem
- **When** dodaje produkty do listy zakupów (nazwa + kategoria)
- **Then** widzi na stronie głównej kompletną listę produktów oraz rekomendowany sklep, który aktualizuje się po każdym dodaniu produktu

#### Acceptance Criteria
- Rekomendacja wskazuje sklep z największą liczbą pasujących kategorii produktów z listy
- Przy remisie wygrywwa pierwszy sklep z listy
- Dodany produkt jest natychmiast widoczny dla wszystkich zalogowanych członków rodziny
- Usunięty produkt znika z listy i wpływa na przeliczenie rekomendacji

## Business Logic

System rekomenduje sklep do którego jechać na podstawie tego, który sklep pokrywa najwięcej kategorii produktów z aktualnej listy zakupów.

Dane wejściowe reguły: aktualna lista produktów (każdy z przypisaną kategorią) oraz konfiguracja sklepów (każdy sklep ma przypisane kategorie produktów które oferuje). System liczy ile kategorii z listy zakupów pokrywa dany sklep i wskazuje ten z najwyższym wynikiem. Przy remisie (równa liczba pokrytych kategorii) wygrywa sklep dodany jako pierwszy. Użytkownik widzi rekomendację na stronie głównej obok listy produktów — aktualizuje się automatycznie po każdej zmianie listy.

## Non-Functional Requirements

- Aplikacja działa responsywnie na telefonie i desktopie — planowanie zakupów odbywa się głównie z telefonu.
- Aplikacja dostępna 24/7 — członkowie rodziny dodają produkty o różnych porach dnia i nocy.
- Interfejs użytkownika w języku polskim — członkowie rodziny nie muszą znać angielskiego.

## Non-Goals

- **Brak obsługi wielu rodzin** — aplikacja obsługuje wyłącznie jedną rodzinę, brak multitenancy. Skala to 3-5 osób.
- **Brak automatycznego kategoryzowania produktów** — na MVP kategorie nadawane ręcznie. AI/automat to potencjalna przyszłość, nie MVP.
- **Brak historii zakupów** — usunięty produkt znika na zawsze. Żadnego archiwum co kupowano.
- **Brak złożonego systemu decyzyjnego** — bez ocen sklepów, jakości per produkt, list wykluczeń asortymentu. Prosta reguła „więcej kategorii = lepszy sklep" wystarczy.
