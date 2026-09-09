# Logowanie i prywatny dostęp — Plan Brief

> Full plan: `context/changes/logowanie-i-prywatny-dostep/plan.md`
> Roadmapa: `context/foundation/roadmap.md` — pozycja S-01

## What & Why

Aplikacja jest wdrożona na publicznym adresie, ale nie ma uwierzytelniania — strona główna to domyślna wizytówka Laravela dostępna dla każdego. PRD stawia to jako barierę wprost: lista zakupów ma być widoczna wyłącznie dla zalogowanych członków rodziny, a konta zakłada wyłącznie właściciel, bez publicznej rejestracji. Ten kawałek zamyka dostęp i buduje polski, responsywny layout, na którym staną kolejne cztery kawałki.

## Starting Point

Szkielet Laravela 13.8 po bootstrapie, wdrożony na Render + Neon. Tabela `users` z `email`, `password` i `remember_token` już istnieje, model `User` i guard sesyjny są skonfigurowane, sesje w produkcji idą do bazy. Brakuje wszystkiego powyżej warstwy danych: żadnych tras, kontrolerów ani widoków uwierzytelniania, żadnego pakietu auth, żadnego layoutu, żadnych polskich tłumaczeń — i żadnej drogi założenia konta na produkcji, bo `docker/entrypoint.sh` wykonuje tylko `migrate --force`, nigdy `db:seed`.

## Desired End State

Wejście na `/` bez sesji przekierowuje na polski ekran logowania. Po zalogowaniu członek rodziny widzi pod `/` stronę główną z nagłówkiem, swoją nazwą, wylogowaniem i komunikatem o pustej liście zakupów — miejsce, które wypełni S-02. Konta zakłada właściciel komendą `php artisan app:user:create`, także w powłoce Render, bez umieszczania danych rodziny w publicznym repozytorium. Rejestracja, profil, reset hasła i weryfikacja e-mail nie są dostępne.

## Key Decisions Made

| Decyzja | Wybór | Dlaczego |
| --- | --- | --- |
| Sposób uwierzytelniania | Oficjalny kit Laravel Breeze (stack Blade) | Wybór właściciela; Breeze v2.4.2 obsługuje Laravel 13 i daje gotowy throttling logowania |
| Identyfikator logowania | Adres e-mail | Kolumna `email` już istnieje i jest unique — zero migracji, domyślny provider działa bez zmian |
| Zakładanie kont | Komenda `php artisan app:user:create` | Repozytorium jest publiczne — żadne hasło ani adres rodziny nie może trafić do gita; działa tak samo lokalnie i na produkcji |
| Kolumna `role` | Dodana od razu, nieużywana | Wybór właściciela wbrew rekomendacji: późniejszy panel admina nie będzie wymagał migracji na żywej bazie |
| Publiczna rejestracja | Trasy za flagą `REGISTRATION_ENABLED`, domyślnie wyłączoną; kod zostaje | Produkcja jest publiczna, więc otwarty `/register` łamie §Access Control; pełne wycięcie kodu zostaje jako zadanie poza MVP |
| Trwałość sesji | Checkbox „Zapamiętaj mnie" | PRD lokuje użycie głównie na telefonie — logowanie przy każdym produkcie zabiłoby nawyk |
| Strona główna | `/` chronione, `dashboard` usunięty | FR-004 i FR-007 mówią o „stronie głównej"; S-02 i S-04 wypełnią dokładnie ten widok |
| Polonizacja | `lang/pl.json` na teksty Breeze + `lang/pl/auth.php` i `validation.php` na błędy | Kit opakowuje teksty w `__()`, więc tłumaczenie nie wymaga przepisywania Blade'ów |
| Tailwind | Zostajemy na 4, cofamy nadpisania Breeze | Breeze Blade wymusza Tailwind 3 i nadpisuje `vite.config.js` oraz `app.css`; cofnięcie jest tańsze niż rozjazd z `CLAUDE.md` i `tech-stack.md` |

## Scope

**W zakresie:** logowanie e-mailem i hasłem z throttlingiem, wylogowanie, „zapamiętaj mnie", odcięcie niezalogowanych od `/`, chroniona strona główna z pustym stanem, wyłączenie publicznej rejestracji, komenda zakładania kont, polskie teksty i komunikaty błędów, responsywny layout, kolumna `role` bez logiki.

**Poza zakresem:** panel administracyjny (FR-002, FR-003), egzekwowanie ról, reset hasła i weryfikacja e-mail, konfiguracja poczty, ekran profilu, usunięcie kodu rejestracji, produkty, sklepy i kategorie (S-02, S-03), zewnętrzne śledzenie błędów.

## Architecture / Approach

Breeze publikuje kontrolery, żądania, widoki i `routes/auth.php` jako zwykły kod aplikacji — po instalacji pakiet jest tylko zależnością deweloperską, więc produkcyjne `composer install --no-dev` go pomija bez wpływu na działanie. Uwierzytelnianie idzie standardową ścieżką Laravela: `LoginRequest` waliduje i ogranicza liczbę prób (5 na kluczu `email|ip`), `AuthenticatedSessionController` woła `Auth::attempt()` i regeneruje sesję, middleware `auth` chroni `/`. Sesje trafiają do tabeli `sessions` w Postgresie. Frontend zostaje na Tailwind 4 + Vite 8 — wtyczka `forms`, której wymagają komponenty Breeze, wchodzi dyrektywą `@plugin` w CSS zamiast przez `tailwind.config.js`.

## Phases at a Glance

| Faza | Co dostarcza | Główne ryzyko |
| --- | --- | --- |
| 1. Breeze na Tailwindzie 4 | Zainstalowany kit z przywróconym stackiem Tailwind 4 + Vite 8 | Instalator nadpisuje `vite.config.js` i `app.css` oraz cofa `tailwindcss` do `^3` — cofnięcie musi odtworzyć pliki z gita, nie z pamięci |
| 2. Zawężenie kitu do MVP | `/` chronione, rejestracja za wyłączoną flagą, usunięte funkcje spoza PRD | Domyślna wartość flagi rejestracji musi być `false` — inaczej brak zmiennej w Render oznacza otwartą rejestrację na publicznym adresie |
| 3. Konta rodziny | Komenda `app:user:create` + instrukcja w README | Hasło nie może być argumentem komendy (historia poleceń w powłoce Render) |
| 4. Polonizacja i responsywność | Polski interfejs i komunikaty błędów, layout sprawdzony na telefonie | Komunikaty `auth.failed` i `auth.throttle` to najczęściej widziane teksty formularza — pominięcie zostawia angielski w najbardziej widocznym miejscu |

**Prerequisites:** brak — S-01 nie ma zależności w roadmapie. Potrzebne: działający `docker-compose up -d` i dostęp do powłoki usługi na Render przy wdrożeniu.

**Estimated effort:** cztery fazy, każda kończona osobnym commitem po zielonych testach i ręcznym potwierdzeniu.

## Open Risks & Assumptions

- Kod rejestracji zostaje w repozytorium wyłączony flagą. Ochrona jest realna tylko dopóki nikt nie ustawi `REGISTRATION_ENABLED=true` w panelu Render — przerobiony `RegistrationTest` pilnuje domyślnej konfiguracji, ale nie ustawień produkcyjnych.
- Widoki Breeze zaprojektowano pod Tailwind 3. Na Tailwindzie 4 zadziałają, ale część wartości domyślnych (cienie, obwódki focusu) zmieniła się między wersjami — możliwe drobne różnice wyglądu do wyrównania.
- Kolumna `role` powstaje bez żadnego czytelnika. Jeśli panel administracyjny nie wróci, zostanie martwym polem.
- Konta na produkcji wymagają jednorazowego ręcznego kroku po wdrożeniu. Pominięcie go oznacza, że nikt się nie zaloguje — aplikacja będzie działać i nie da się z niej skorzystać.

## Success Criteria (Summary)

- Niezalogowany nie zobaczy niczego poza ekranem logowania; `/register`, `/profile` i `/dashboard` zwracają 404.
- Członek rodziny loguje się swoim adresem, zostaje zapamiętany między wizytami i widzi polską stronę główną gotową pod listę zakupów.
- Właściciel zakłada konto jedną komendą, lokalnie i na produkcji, bez umieszczania danych rodziny w publicznym repozytorium.
