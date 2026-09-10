# Test Plan

> Fazowe wdrożenie testów w tym projekcie. Strategia jest zamrożona u góry
> (§1–§5); wzorce kucharskie na dole (§6) wypełniają się w miarę, jak kolejne
> fazy schodzą z taśmy. Przeczytaj, zanim napiszesz jakikolwiek nowy test.
>
> Odświeżenie: uruchom `/10x-test-plan --refresh`, gdy plan się zestarzeje (patrz §8).
>
> Last updated: 2026-09-10 (§2 zaktualizowane backportem z researchu Fazy 2)

## 1. Strategy

Testy w tym projekcie podlegają trzem nienegocjowalnym zasadom:

1. **Koszt × sygnał.** Wygrywa najtańszy test, który daje realny sygnał dla
   danego ryzyka. Nie awansuj do e2e dlatego, że e2e „wydaje się
   bezpieczniejsze". Nie kładź modelu wizyjnego na deterministycznej różnicy,
   która i tak łapie regresję.
2. **Obawy użytkownika to dowód pierwszej kategorii.** Ryzyko zakotwiczone w
   „zespół boi się X, a awaria wyszłaby gdzieś w obszarze Y" waży tyle samo,
   co linia z PRD albo dane o częstotliwości zmian.
3. **Ryzyka to scenariusze, nie lokalizacje w kodzie.** Ten plan dokumentuje,
   *co może zawieść* i *dlaczego uważamy to za prawdopodobne* — na podstawie
   dokumentów, wywiadu i *sygnału* z repozytorium (churn, struktura, stan bazy
   testowej). NIE twierdzi, że wie, która linia odpowiada za awarię. Tę wiedzę
   produkuje `/10x-research` w trakcie każdej fazy wdrożenia. Jeśli plan i
   research nie zgadzają się co do tego, gdzie mieszka awaria, prawdą jest
   research.

Zakres skanu hot-spot użyty do ważenia prawdopodobieństwa: `app/`,
`resources/`, `routes/`, `database/`, `tests/` — z wyłączeniem `vendor/`,
`node_modules/`, `public/build/`, `context/`. Churn z jednorazowego scaffoldu
Laravel Breeze (`resources/views/auth`, `resources/views/components`,
`tests/Feature/Auth`) potraktowany jako szum narzędzia, nie autorstwa.

## 2. Risk Map

Najważniejsze scenariusze awarii, przed którymi ten projekt musi się bronić,
uporządkowane według ryzyka = impact × likelihood. Ryzyka są scenariuszami
awarii w kategoriach użytkownika i biznesu, nie nazwami testów. Kolumna Źródło
cytuje *dowód, który wyniósł to ryzyko na wierzch* — nigdy konkretnego pliku
jako „miejsca, gdzie mieszka awaria" (to zadanie researchu, patrz §1 zasada 3).

| # | Ryzyko (scenariusz awarii) | Impact | Likelihood | Źródło (dowód — nie kotwica) |
|---|---|---|---|---|
| 1 | Rekomendacja wskazuje inny sklep, niż wynika z reguły — remis rozstrzygnięty niedeterministycznie albo kategoria policzona dwukrotnie; rodzina jedzie nie tam i nic nie zgłasza błędu | High | High | PRD FR-007, §Business Logic (remis → sklep dodany pierwszy); roadmapa S-04 = następny slice; `context/archive/2026-09-09-konfiguracja-sklepow/plan.md` §Key Discoveries |
| 2 | Członek dodaje produkt, zapis pozornie przechodzi, ale osoba jadąca do sklepu go nie widzi i kupuje bez niego | High | Medium | Wywiad Q1; PRD §Guardrails („dane nie mogą się gubić"); PRD US-01 kryteria akceptacji; hot-spot `app/Http/Controllers` — 27 commitów/30 dni |
| 3 | Kategoria rozdwaja się na wariancie zapisu, więc sklep pokrywa połowę swojego asortymentu i cicho przegrywa rekomendację | High | Low | `context/archive/2026-09-09-wspolna-lista-produktow/reviews/impl-review.md` (seeder dokładał wariant różniący się wielkością liter); plan S-03 §Key Discoveries; wywiad Q3 (obszar sąsiedni); research Fazy 2 — przeliczone po konsolidacji pisarzy |
| 4 | Niezalogowany dociera do listy zakupów rodziny, bo nowa trasa powstała poza grupą uwierzytelniającą | High | Medium | PRD §Access Control; PRD §Guardrails (prywatność); hot-spot `routes/web.php` — 6 commitów/30 dni, każdy slice tam dokłada |
| 5 | Blokada duplikatu produktu przepuszcza dwa te same produkty albo odrzuca produkt, który duplikatem nie jest | Medium | High | Wywiad Q3; przegląd implementacji S-02; hot-spot `app/Http/Requests` — 6 commitów/30 dni |
| 6 | Zachowanie różniące się między silnikiem testowym a produkcyjnym przechodzi na produkcję przy zielonym zestawie testów | High | Low | `context/archive/2026-09-09-konfiguracja-sklepow/reviews/impl-review.md` ustalenie F1 (awaria odtworzona wykonaniem); wywiad Q4 ocenia jako nieistotne |

Wiersz 4 to soczewka nadużyć (autoryzacja i dostęp). Pozostałe trzy klasy
sprawdzono i nie dostają wierszy: wstrzyknięcia i XSS — silnik szablonów
escape'uje domyślnie, a przegląd S-03 potwierdził brak surowego wyjścia i
surowego SQL; wyciek sekretów — należy do rejestru ryzyk w
`context/foundation/infrastructure.md`, nie do testów; nadużycie zasobów —
pięciu zaufanych użytkowników, brak kosztownych operacji.

Ryzyko 3 zostało przeliczone w researchu Fazy 2 (2026-09-10) z Medium na Low i
zostaje w mapie na swoim miejscu, żeby nie przenumerować odwołań. Powód: trzeci
pisarz kategorii, na którym stało uzasadnienie, już nie istnieje — zapisy
skonsolidowały się w S-03 do jednej reguły równości, przez którą przechodzą obaj
dzisiejsi pisarze produkcyjni, i obaj mają test. Ryzyko zostaje jako ochrona
przed regresją tej konsolidacji, nie jako otwarta luka; scenariusz wymaga
dopisania nowego pisarza, który regułę ominie. Kolejność wierszy nie odpowiada
już ściśle iloczynowi impact × likelihood — porządkowanie należy do `--refresh`.

Ryzyko 6 ma Impact High × Likelihood Low i zwykle taki układ należy do
obserwowalności, nie do testu. Zostaje w mapie mimo oceny właściciela („nie
martwi mnie"), bo jako jedyny wiersz w tej mapie opiera się na awarii
odtworzonej wykonaniem, a nie na przewidywaniu. Trafia do ostatniej fazy.

### Risk Response Guidance

| Ryzyko | Co dowodzi ochrony | Co zakwestionować | Kontekst do ugruntowania przez `/10x-research` | Najtańsza warstwa | Antywzorzec |
|---|---|---|---|---|---|
| #1 | Przy danej liście i zestawie sklepów wskazany jest ten sklep, który wynika z reguły; remis rozstrzyga się na pierwszy dodany, powtarzalnie i przy identycznych znacznikach czasu | „posortowane znaczy deterministyczne" — research Fazy 2 potwierdził i wzmocnił: brak jawnej klauzuli porządkującej daje wynik zależny od silnika, od tego czy zaszła aktualizacja indeksowanej kolumny, i od tego które kolumny wybrano; „liczba dopasowań równa się liczbie różnych kategorii" — po stronie sklepu pilnuje tego unikalność przypisania, po stronie produktów należy do S-04 | Jak uporządkowane są sklepy, czy unikalność przypisania trzyma, jak liczone jest pokrycie, co przy pustej liście i przy zerowym pokryciu | integracyjna na zapytaniu sklepów, z danymi kontrolnymi zawierającymi realny remis — **ale połowa „remis" nie może upaść na sterowniku dzisiejszego zestawu**; dowód wymaga przebiegu na silniku produkcyjnym, co wiąże to ryzyko z #6 i Fazą 4 | Dane kontrolne, w których każdy sklep ma inny wynik, więc remis nigdy nie zostaje wykonany. Antywzorzec ostrzejszy: dane kontrolne z poprawnym remisem, uruchomione na silniku, na którym rozbieżność jest niewidoczna — test przechodzi po cofnięciu reguły, którą rzekomo pinuje |
| #2 | Produkt zapisany przez jednego członka jest czytelny w sesji innego, po przekierowaniu i przy świeżym żądaniu | „302 znaczy, że się zapisało"; „wiersz jest w bazie, więc użytkownik go widzi" | Gdzie następuje odczyt listy i czy jest zawężony, granice transakcji przy zapisie, co realnie odczytuje przekierowanie | integracyjna po HTTP, ładunek w kształcie formularza, z podążeniem za przekierowaniem | Asercja na wiersz w bazie zamiast na wyrenderowaną listę; wysyłanie typów, których przeglądarka nigdy nie wyśle |
| #3 | Kategoria wpisana w dowolnym wariancie zapisu daje jeden rekord, a pokrycie sklepu odzwierciedla cały zbiór, który członek zamierził | Że formularz jest jedynym pisarzem — trzecim pisarzem był seeder i to on się pomylił. Research Fazy 2: zarzut był trafny w S-02 i **został już zamknięty konsolidacją** — dziś pisarzy produkcyjnych jest dwóch, obaj przez jedną regułę równości, obaj otestowani. Nowe pytanie do zakwestionowania: czy pokrycie sklepu odzwierciedla zamiar, gdy jedno zgłoszenie wskaże tę samą kategorię dwiema drogami naraz | Wszystkie ścieżki zapisu do zbioru kategorii, obsługa wyścigu przy równoległym dopisaniu, granice transakcji | integracyjna przez każdego pisarza — **istnieje**; niepokryta zostaje wyłącznie konsekwencja po stronie pokrycia sklepu przy kolizji dwóch dróg w jednym zgłoszeniu | Test wyłącznie ścieżki formularza z założeniem, że pozostali pisarze się zgadzają. Drugi antywzorzec: dopisywanie testów do luki, która jest już zamknięta, zamiast do jedynej pozostałej |
| #4 | Każda trasa czytająca lub zapisująca dane rodziny przekierowuje niezalogowanego na logowanie — także ta dodana jutro | „jest w grupie uwierzytelniającej, bo ją tam wstawiłem" — gwarancja ma wynikać z asercji nad tabelą tras, nie z pamięci autora | Rejestracja tras, grupy pośredniczące, które trasy są celowo publiczne | jedna integracyjna, przemiatająca zarejestrowane trasy | Jeden test gościa na plik funkcji — milcząco pomija następną dodaną trasę |
| #5 | Dwie nazwy, które rodzina uważa za tę samą rzecz, są blokowane; dwie nazwy, które uważa za różne, przechodzą obie | Że normalizacja to to samo co poprawność — polskie znaki diakrytyczne są znaczące z decyzji podjętej w S-02 | Jedyna definicja równości nazw i komplet jej wołających; czy porównanie odbywa się w aplikacji czy w bazie | jednostkowa na regule równości plus jedna integracyjna na odrzuceniu z formularza | Problem wyroczni: pary oczekiwań wyprowadzone z implementacji normalizacji zamiast z tego, co rodzina rozumie przez „ten sam produkt" |
| #6 | Zachowanie rozjeżdżające się między silnikami jest przynajmniej raz wykonane na silniku produkcyjnym | „zielony zestaw znaczy bezpiecznie", gdy sterownik zestawu nie jest sterownikiem produkcji | Które zachowania się rozjeżdżają: semantyka przerwania transakcji, porównywanie tekstu, uporządkowanie wyników | drugi przebieg istniejącego zestawu na innym sterowniku | Przepisywanie zestawu pod drugi silnik — tanim ruchem jest drugi przebieg, nie drugi zestaw |

## 3. Phased Rollout

Każdy wiersz to odrębna faza wdrożenia, która otworzy własny folder zmiany
przez `/10x-new`. Status przesuwa się od lewej do prawej po wartościach ze
słownika poniżej; orchestrator aktualizuje Status, gdy artefakty pojawiają się
na dysku. Słownik statusów pozostaje po angielsku, bo czyta go parser.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Rdzeń listy zakupów pod kształtem formularza | Dowieść, że produkt dodany przez jednego członka jest widoczny dla drugiego, a blokada duplikatu trafia w obie strony | #2, #5 | integration, unit | complete | `context/changes/testing-lista-i-duplikaty/` |
| 2 | Kontrakty rekomendacji przed S-04 | Dowieść, że kolejność rozstrzygania remisu i tożsamość przypisania kategorii trzymają, zanim powstanie reguła, która na nich stanie | #1, #3 | integration, unit | researched | `context/changes/testing-kontrakty-rekomendacji/` |
| 3 | Bramka dostępu na poziomie tabeli tras | Dowieść, że żadna trasa z danymi rodziny nie przecieka do niezalogowanego — także dodana w przyszłości | #4 | integration | not started | — |
| 4 | Przebieg zestawu na silniku produkcyjnym | Dowieść, że zestaw daje się wykonać na Postgresie, nie tylko na SQLite | #6 | konfiguracja przebiegu | not started | — |

Uzasadnienie kolejności:

- **Faza 1** bierze obie odpowiedzi właściciela z wywiadu (Q1, Q3), na kodzie
  już wdrożonym, najtańszą warstwą i bez nowej infrastruktury. Przegląd
  implementacji S-03 pokazał, że istniejące testy mają dokładnie te luki.
- **Faza 2** jest ograniczona czasem: S-04 to następny slice w roadmapie, a
  faza utrwala jego wejścia. Zakres obejmuje **tylko to, co da się przetestować
  dziś** — same kontrakty. Testy samej reguły rekomendacji należą do planu
  S-04; §6 powie mu, jak je napisać.
- **Faza 3** jest samodzielna i tania. Stoi trzecia wyłącznie dlatego, że nic
  jej nie goni — można ją przesunąć do przodu w dowolnym momencie.
- **Faza 4** stoi na końcu, bo właściciel ocenił to ryzyko jako nieistotne.
  Nie obejmuje wiring CI (patrz §7).

**Brak fazy AI-native — to decyzja, nie przeoczenie.** Aplikacja to cztery
ekrany CRUD dla pięciu osób; każde ryzyko w §2 ma tańszy test
deterministyczny. Jedyny obszar, w którym model multimodalny miałby sens, to
warstwa wizualna, wykluczona wprost w wywiadzie Q5. Pod kryterium koszt ×
sygnał żaden wiersz AI-native nie przechodzi.

## 4. Stack

Klasyczna baza testowa tego projektu. Profil bazy w chwili pisania planu:
**sparse** — PHPUnit skonfigurowany, 13 plików testowych (49 testów)
pokrywających każdy wdrożony slice, ale `tests/Unit/` zawiera jeden realny
test, a bramki CI nie istnieją.

| Warstwa | Narzędzie | Wersja | Uwagi |
|---|---|---|---|
| unit + integration | PHPUnit | 12.5 | Skonfigurowany w `phpunit.xml`; konwencja `RefreshDatabase` w testach funkcjonalnych |
| framework aplikacji | Laravel | 13.11 | PHP 8.4 w kontenerze; testy funkcjonalne idą przez pełny stos HTTP |
| baza w testach | SQLite `:memory:` | — | Ustawiona w `phpunit.xml`; rozjeżdża się z produkcyjnym Postgresem — patrz ryzyko #6 i §3 Faza 4 |
| baza produkcyjna | PostgreSQL | 16 | Lokalnie w Dockerze, na produkcji Neon (patrz `context/foundation/infrastructure.md`) |
| formatowanie | Laravel Pint | 1.27 | `vendor/bin/pint --test` |
| mockowanie HTTP | brak | — | Brak zależności zewnętrznych do zamockowania — aplikacja nie woła żadnego API |
| e2e | brak | — | Świadomie brak; żadne ryzyko z §2 nie wymaga pełnego kształtu wdrożonego |
| dostępność | brak | — | Poza zakresem — patrz §7 |
| AI-native | brak | — | Nie uzasadnione pod koszt × sygnał — patrz §3 |

**Stack grounding tools (current session):**
- Docs: brak — żaden docs-MCP nie jest wystawiony w tej sesji; wersje odczytane z `composer.json` i z uruchomionego kontenera; checked: 2026-09-10
- Search: brak — żaden search-MCP nie jest wystawiony w tej sesji; rekomendacje narzędziowe nie były weryfikowane wobec bieżących docsów dostawców; checked: 2026-09-10
- Runtime/browser: brak jako MCP — przeglądarkę bezgłową dało się uruchomić lokalnie przez pakiet npm przy weryfikacji ręcznej S-03, ale nie jest to narzędzie sesji i plan na nim nie polega; checked: 2026-09-10
- Provider/platform: tylko Atlassian, bez związku z bramkami jakości; brak MCP do Render, Neon ani GitHuba; checked: 2026-09-10

## 5. Quality Gates

Komplet bramek, które muszą przejść, zanim zmiana dotrze na produkcję.
„Required after §3 Phase N" znaczy, że bramka zaczyna obowiązywać, gdy ta faza
wdrożenia wyląduje; wcześniej ma status planowany.

| Bramka | Gdzie | Wymagana? | Co łapie |
|---|---|---|---|
| formatowanie (Pint) | lokalnie | required | dryf stylu, rozjazd z konwencją Laravela |
| unit + integration | lokalnie | required | regresje logiki na wdrożonych slice'ach |
| migracje na czystej bazie | lokalnie | required | migracja nieodwracalna albo zależna od stanu |
| przemiatacz tabeli tras | lokalnie | required after §3 Phase 3 | trasa z danymi rodziny dodana poza grupą uwierzytelniającą |
| przebieg zestawu na Postgresie | lokalnie | required after §3 Phase 4 | zachowanie rozjeżdżające się między silnikami |
| weryfikacja ręczna wg planu zmiany | przed commitem fazy | required | to, czego zestaw strukturalnie nie widzi — dwukrotnie w S-03 to zadziałało |

Bramki nie są wpięte w CI, bo CI w tym projekcie nie istnieje i właściciel
świadomie tego nie priorytetyzuje (wywiad Q4). Każdy wiersz powyżej jest
uruchamiany lokalnie i wskazany w planach zmian. Gdyby CI kiedyś powstało,
kolumna „Gdzie" jest jedyną, którą trzeba zmienić.

## 6. Cookbook Patterns

Jak dodawać nowe testy w tym projekcie. Każdy podrozdział wypełnia się, gdy
odpowiednia faza wdrożenia wyląduje; wcześniej czyta się jako
„TBD — patrz §3 Faza N".

### 6.1 Dodanie testu jednostkowego

Dla reguły, która daje się zawołać bez bazy i bez HTTP.

- **Gdzie**: `tests/Unit/`
- **Klasa bazowa**: `PHPUnit\Framework\TestCase` — **nie** `Tests\TestCase`.
  Konwencja tego projektu to „Unit = bez kontenera Laravela", nie „Unit = jedna
  klasa". Test, który potrzebuje bazy, idzie do `tests/Feature/`, nawet jeśli
  woła kod wprost i nie dotyka HTTP (tak stoją `CategoryResolverTest` i
  `CategorySeederTest`).
- **Test referencyjny**: `tests/Unit/NameComparisonTest.php`
- **Przebieg**: `docker compose exec app php vendor/bin/phpunit --testsuite Unit`
- **Nazwy metod opisują, co rodzina uważa za tę samą rzecz**, nie jak działa
  implementacja: `test_a_doubled_space_inside_a_name_is_the_same_product`, nie
  `test_normalize_collapses_whitespace`. Jeśli nazwy testu nie da się napisać bez
  zajrzenia do implementacji, para oczekiwań pochodzi z niewłaściwego źródła —
  patrz problem wyroczni w §2.
- **Pary oczekiwań wyprowadza się z decyzji, nie z kodu.** Decyzje spisane do
  dziś: wielkość liter nieznacząca, otaczające i wewnętrzne odstępy nieznaczące,
  polskie znaki diakrytyczne **znaczące**. Nowa decyzja tej klasy wymaga
  rozstrzygnięcia właściciela, zanim powstanie test.

### 6.2 Dodanie testu integracyjnego formularza

Dla ścieżki „wyślij formularz i zobacz wynik na ekranie".

- **Gdzie**: `tests/Feature/`
- **Klasa bazowa**: `Tests\TestCase` + cecha `RefreshDatabase`
- **Test referencyjny**: `tests/Feature/AddProductTest.php`
- **Przebieg**: `docker compose exec app php vendor/bin/phpunit --testsuite Feature`

Cztery reguły, każda kupiona konkretną pomyłką:

1. **Wysyłaj ładunek w kształcie, który wysyła przeglądarka.** Wartości pól to
   tekst (`(string) $category->id`), a pole zostawione puste przychodzi jako `''`,
   nie jest nieobecne. Test wysyłający liczbę całkowitą albo pomijający pole
   sprawdza ścieżkę, której formularz nigdy nie wykona. Uwaga: globalny
   `ConvertEmptyStringsToNull` zamienia `''` na `null`, a `prohibits` nie strzela
   na pustym polu — na tym stoi to, że oba warianty pola kategorii przechodzą.
2. **Podążaj za przekierowaniem i asercjuj na wyrenderowanej treści.** `302` mówi,
   że żądanie zostało przyjęte, nie że cokolwiek dotarło na listę, którą czyta
   rodzina. Asercja na wiersz w bazie jest jeszcze słabsza.
3. **Gwarancję widoczności dowodzi się dwoma członkami.** Jeden `User` wysyła
   formularz, drugi osobnym żądaniem czyta stronę. Test z jednym użytkownikiem
   albo z produktem utworzonym fabryką nie dotyka zapisu i nie dowodzi ryzyka #2.
4. **Nigdy nie wołaj `assertSessionHasErrors()` przed `followingRedirects()`** —
   asercja postarza dane flash, więc widok nie ma już czego wyrenderować i test
   staje się pusty (ustalenie F4 przeglądu S-03). Sprawdzaj albo błąd sesji, albo
   wyrenderowany komunikat, w osobnych żądaniach.

Do tego: **przycięcia nie da się przetestować na tej warstwie.** Globalny
`TrimStrings` przycina ładunek, zanim walidacja go zobaczy, więc test wysyłający
`'  mleko '` dowodzi middleware'u, nie naszej reguły. Przycięcie i sprowadzanie
odstępów pinuje `NameComparisonTest` (§6.1), gdzie są osiągalne.

### 6.3 Dodanie testu kontraktu, na którym stanie kolejny slice

- TBD — patrz §3 Faza 2 (wzorzec dla kolejności rozstrzygania remisu i
  tożsamości przypisania kategorii).

### 6.4 Dodanie testu dostępu dla nowej trasy

- TBD — patrz §3 Faza 3 (wzorzec przemiatania tabeli tras zamiast jednego
  testu gościa na funkcję).

### 6.5 Uruchomienie zestawu na silniku produkcyjnym

- TBD — patrz §3 Faza 4.

### 6.6 Notatki z poszczególnych faz

(Wypełniane po wylądowaniu każdej fazy: dwie-trzy linie o tym, co faza
nauczyła — dane kontrolne do ponownego użycia, pułapka narzędzia, decyzja
warta zapamiętania.)

**Faza 1 — Rdzeń listy zakupów pod kształtem formularza** (2026-09-10,
`d76ba83`, `b085fd6`):

- **Efektywna reguła równości nazw jest złożeniem dwóch rzeczy**, a tylko jedna
  z nich jest nasza: globalny `TrimStrings` Laravela (przycina, także unicode)
  i `NameComparison::normalize()` (obniża wielkość liter, sprowadza odstępy
  ASCII). Zmiana `bootstrap/app.php` mogłaby po cichu zmienić blokadę
  duplikatu i nic tego nie pilnuje — świadomie, bo cena pilnowania cudzego
  frameworka przewyższa ryzyko przy pięciu użytkownikach.
- **`assertSessionHasErrors()` przed `followingRedirects()` czyni asercję
  pustą.** Kosztowało to ustalenie w przeglądzie S-03 i wróciło w tej fazie.
  Jest w §6.2 jako reguła; tu zapisane, żeby nie wróciło po raz trzeci.
- **`Product::latest()` nie rozstrzyga remisu** (`order by created_at desc`,
  bez klucza wtórnego). Przy identycznych znacznikach czasu kolejność zależy od
  silnika, więc asercja na kolejność produktów byłaby chwiejna między SQLite a
  Postgresem. Sklepy tego problemu nie mają — S-03 uporządkował je po `id`
  właśnie dlatego. **Korekta z researchu Fazy 2 (2026-09-10)**: to notatka o
  wyglądzie listy, nie trop dla ryzyka #1. Rekomendacja liczy różne kategorie z
  listy, a nie kolejność produktów, więc remis produktów jej nie dotyczy. Trop
  dla ryzyka #1 prowadzi wyłącznie do uporządkowania sklepów.
- **Sonda przed planem opłaciła się.** Research wykonał obie ścieżki
  jednorazowym plikiem testowym i ustalił, że kod działa — dzięki temu faza
  była o dowodach, nie o naprawach, a trzy pytania o wyrocznię trafiły do
  właściciela zamiast zostać zgadnięte z implementacji.
- **Każdy nowy test przeszedł próbę obalenia**: cofnięcie reguły, którą test
  pinuje, i sprawdzenie, że test upada. Cztery próby, cztery trafienia w
  zamierzony test. Warto to powtarzać — obie poprzednie fazy projektu znalazły
  testy przechodzące z niewłaściwego powodu.

## 7. What We Deliberately Don't Test

Wyłączenia uzgodnione podczas wdrożenia (wywiad Faza 2, Q4 i Q5). Kolejni
kontrybutorzy powinni ich przestrzegać, dopóki nie zmieni się założenie, na
którym stoją.

- **Wygląd i wierność pikselowa** — właściciela interesuje funkcjonalność, nie
  wygląd. Brak testów migawkowych, brak deterministycznych różnic wizualnych,
  brak przeglądu multimodalnego. Rozważyć ponownie, gdyby aplikacja wyszła
  poza rodzinę albo gdyby regresja układu zablokowała realne użycie na
  telefonie. (Źródło: wywiad Q5.)
- **Wiring CI** — właściciel ufa stackowi i ścieżce wdrożeniowej; bramki z §5
  biegają lokalnie. Rozważyć ponownie, gdyby do repozytorium dołączyła druga
  osoba albo gdyby zmiana weszła na produkcję bez uruchomienia zestawu.
  (Źródło: wywiad Q4.)
- **Ekrany uwierzytelniania i profilu z Laravel Breeze** — to kod scaffoldu
  frameworka z własnym pokryciem, przycięty w S-01 do granic PRD. Testujemy
  granicę dostępu (ryzyko #4), nie same formularze. Rozważyć ponownie przy
  własnej modyfikacji tych ekranów.
- **Trwałość danych produkcyjnych** — brak automatycznych backupów na darmowym
  Neonie i brak automatycznego wycofania migracji to realne ryzyka wysokiego
  wpływu, ale należą do runbooka i obserwowalności, nie do zestawu testów.
  Właścicielem jest pozycja F-01 w `context/foundation/roadmap.md`. (Źródło:
  rejestr ryzyk w `context/foundation/infrastructure.md`; kalibracja impact ×
  likelihood.)
- **Odstępy unicode w nazwach** — NBSP, spacja zerowej szerokości i znacznik
  kolejności bajtów są usuwane przez globalny `TrimStrings` na ścieżce HTTP i
  przez nikogo poza nią; `NameComparison` ich nie widzi. Wszyscy dzisiejsi
  pisarze nazw idą przez HTTP albo przez stałe w kodzie, więc rozjazd jest
  nieosiągalny, a zamykanie go kosztowałoby regułę zależną od unicode'u tam,
  gdzie właściciel akceptuje najwyżej dwa produkty. Rozważyć ponownie, gdyby
  powstał pisarz nazw czytający z zewnątrz i omijający HTTP — import,
  integracja, seeder na danych z pliku. (Źródło: decyzja właściciela przy
  planowaniu §3 Fazy 1.)
- **Warianty NFD znaków diakrytycznych** — `bąk` zapisane jako `a` z łączonym
  ogonkiem nie równa się `bąk` prekomponowanemu, w żadnym z czterech wołających
  reguły równości. Klawiatura telefonu produkuje NFC, więc ścieżka jest
  osiągalna praktycznie tylko przez wklejenie; zamknięcie jej wymagałoby
  rozszerzenia `intl` w produkcji i w teście. Rozważyć ponownie, gdyby rodzina
  zaczęła wklejać nazwy z zewnętrznych źródeł. (Źródło: decyzja właściciela
  przy planowaniu §3 Fazy 1.)
- **Usuwanie produktów i edycja sklepów** — funkcje nie istnieją (roadmapa
  S-05 i S-06). Ich testy należą do ich własnych planów, nie do tego
  wdrożenia; §6 powie im, jak je napisać.

## 8. Freshness Ledger

- Strategia (§1–§5) ostatnio przeglądana: 2026-09-10
- Wersje stacku ostatnio zweryfikowane: 2026-09-10
- Odniesienia do narzędzi AI-native ostatnio zweryfikowane: 2026-09-10 (brak
  takich narzędzi w planie)
- Rejestr ryzyk infrastruktury (`infrastructure.md`) pochodzi z 2026-06-02 —
  ponad trzy miesiące; zweryfikować warunki darmowych tierów przed kolejnym
  wdrożeniem produkcyjnym

Odśwież (`/10x-test-plan --refresh`), gdy:

- z roadmapy albo archiwum wyjdzie nowe ryzyko z pierwszej trójki,
- data `checked:` któregoś narzędzia przekroczy trzy miesiące,
- zmieni się stack projektu (nowy framework, nowy runner testów),
- §7 przestanie odpowiadać temu, w co zespół wierzy — w szczególności gdyby
  CI albo testy wizualne przestały być świadomie wyłączone.
