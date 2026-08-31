# Logowanie i prywatny dostęp — Implementation Plan

## Overview

Realizacja S-01 z `context/foundation/roadmap.md`: członek rodziny loguje się adresem e-mail i hasłem, a niezalogowany zostaje przekierowany na stronę logowania. Strona główna `/` przestaje być publiczną wizytówką Laravela i staje się chronionym, polskim, responsywnym widokiem z pustym stanem — miejscem, które wypełnią kolejne kawałki (S-02 lista produktów, S-04 rekomendacja sklepu).

Podstawą jest oficjalny kit **Laravel Breeze (stack Blade)**, przycięty do granic wyznaczonych przez PRD: bez publicznej rejestracji, bez resetu hasła, bez weryfikacji e-mail, bez ekranu profilu.

## Current State Analysis

Repozytorium to szkielet Laravela 13.8 po `/10x-bootstrapper`, wdrożony na Render (`https://lista-zakupow-acl5.onrender.com`), bez ani jednej linii kodu domenowego.

**Co już jest i czego nie trzeba budować:**

- Tabela `users` z kolumnami `name`, `email` (unique), `password`, `remember_token`, plus tabele `sessions` i `password_reset_tokens` — `database/migrations/0001_01_01_000000_create_users_table.php`.
- Model `App\Models\User` rozszerzający `Authenticatable`, z castem `password => hashed` i `#[Fillable(['name','email','password'])]` — `app/Models/User.php`.
- Domyślny guard `web` (sesyjny) i provider `users` (Eloquent na `App\Models\User`) — `config/auth.php:38-70`.
- `UserFactory` — `database/factories/UserFactory.php`.
- Sesje na bazie w produkcji (`SESSION_DRIVER=database`) z `SESSION_SECURE_COOKIE=true` — `render.yaml`. Tabela `sessions` istnieje, nic nie trzeba dokładać.
- Zaufanie do proxy Render (`trustProxies(at: '*')`) — `bootstrap/app.php:15`. Bez tego secure cookies i https URL-e byłyby zepsute za reverse-proxy.
- Testy na SQLite `:memory:` — `phpunit.xml:24-25`.

**Czego brakuje:**

- Zero tras, kontrolerów i widoków uwierzytelniania. `routes/web.php` ma jedną trasę-domknięcie zwracającą `welcome`. `app/Http/Controllers/` zawiera wyłącznie pusty `Controller.php`.
- Zero pakietów auth w `composer.json` (tylko `laravel/framework` i `laravel/tinker`).
- Zero polskich tłumaczeń — `vendor/laravel/framework/src/Illuminate/Translation/lang/` ma wyłącznie `en`, a lokalny `.env` ma `APP_LOCALE=en` (produkcja ma `pl` z `render.yaml`).
- Zero layoutu — jedyny widok to domyślny `resources/views/welcome.blade.php` (223 linie marketingu Laravela).
- **Brak drogi zakładania kont na produkcji.** `docker/entrypoint.sh:12` wykonuje `php artisan migrate --force`, ale nigdy `db:seed`. PRD zakłada konta zakładane wstępnie poza aplikacją, ale nic takiego nie istnieje — bez tego nikt się nie zaloguje na produkcji.

## Desired End State

Po wykonaniu planu:

- Wejście na `/` bez sesji przekierowuje na `/login`. Wejście na `/` z sesją pokazuje polską stronę główną z nagłówkiem, nazwą zalogowanej osoby, przyciskiem wylogowania i komunikatem o pustej liście zakupów.
- Formularz logowania przyjmuje e-mail i hasło, ma checkbox „Zapamiętaj mnie", a wszystkie jego teksty i komunikaty błędów (w tym „Podane dane logowania są nieprawidłowe" i komunikat o zbyt wielu próbach) są po polsku.
- `/register` zwraca 404, dopóki `REGISTRATION_ENABLED` nie zostanie ustawione na `true`. Kod rejestracji zostaje w repozytorium jako zadanie poza MVP.
- `/dashboard`, `/profile`, `/forgot-password`, `/reset-password`, `/verify-email` i `/confirm-password` nie istnieją.
- `php artisan app:user:create` zakłada konto z linii poleceń — lokalnie i w powłoce Render — bez umieszczania jakichkolwiek danych rodziny w repozytorium.
- Frontend nadal stoi na Tailwind CSS 4 + Vite 8 z `@tailwindcss/vite`, zgodnie z `CLAUDE.md` i `context/foundation/tech-stack.md`.
- `composer test` przechodzi w całości.

### Key Discoveries:

- **Breeze v2.4.2 obsługuje Laravel 13** — `illuminate/support: ^11.0|^12.0|^13.0`, wydany 2026-05-14 (packagist). Instalator wykrywa wersję ≥ 13 i sam usuwa nieistniejący `import './bootstrap';` z `resources/js/app.js` (`src/Console/InstallsBladeStack.php:78-80`).
- **Breeze Blade to nadal Tailwind 3.** `installBladeStack()` wymusza `tailwindcss: ^3.1.0` w `package.json`, kopiuje `tailwind.config.js` i `postcss.config.js` oraz **nadpisuje** `vite.config.js` i `resources/css/app.css` (`src/Console/InstallsBladeStack.php:16-27, 66-76`). Projekt jest na Tailwind 4 — te nadpisania trzeba cofnąć, to jest sedno Fazy 1.
- **Rejestracji nie da się wyłączyć konfiguracją kitu.** Breeze publikuje `routes/auth.php` jako zwykły plik aplikacji z trasami `register` wpisanymi na sztywno. Flagę dokładamy sami.
- **Throttling jest w pudełku.** `LoginRequest::ensureIsNotRateLimited()` blokuje po 5 nieudanych próbach na kluczu `email|ip` i rzuca `trans('auth.throttle')`. Nie trzeba pisać własnego ograniczania prób.
- **Wszystkie teksty Breeze idą przez `__('...')`** (np. `resources/views/auth/login.blade.php`), więc polonizacja kitu to jeden plik `lang/pl.json`, bez przepisywania Blade'ów. Własne widoki piszemy po polsku wprost — mniej pośrednictwa tam, gdzie i tak nikt nie tłumaczy.
- **Breeze celuje w `dashboard`.** `AuthenticatedSessionController::store()` kończy się `redirect()->intended(route('dashboard', absolute: false))`, a `tests/Feature/Auth/AuthenticationTest.php` asercją `assertRedirect(route('dashboard', absolute: false))`. Oba wymagają zmiany na `/`.
- **Istniejący `tests/Feature/ExampleTest.php` przestanie przechodzić.** Robi `$this->get('/')` i oczekuje 200; po objęciu `/` middlewarem `auth` dostanie 302.
- **`route:cache` z trasą-domknięciem działa** — Laravel 13 serializuje domknięcia przez `SerializableClosure` (`vendor/laravel/framework/src/Illuminate/Routing/Route.php:1426`). Produkcyjny `docker/entrypoint.sh` nie ma tu problemu; sprawdzone, żeby wykluczyć fałszywy trop.

## What We're NOT Doing

- **Nie budujemy panelu administracyjnego** (FR-002, FR-003) — odłożone jako nice-to-have, patrz `## Parked` w roadmapie.
- **Nie usuwamy kodu rejestracji** — decyzja właściciela: trasy wyłączone flagą, kontroler i widok zostają jako zadanie poza MVP.
- **Nie egzekwujemy ról.** Kolumna `role` powstaje (decyzja właściciela), ale nic jej nie czyta. Żadnych bramek autoryzacji.
- **Nie konfigurujemy poczty** — brak resetu hasła i weryfikacji e-mail wynika wprost z braku mailera i z tego, że PRD ich nie wymaga.
- **Nie dodajemy produktów, sklepów ani kategorii** — to S-02 i S-03.
- **Nie dotykamy `Dockerfile`, `render.yaml` ani `docker/`** poza tym, czego wymaga uruchomienie komendy zakładania konta (a ta nie wymaga zmian w obrazie).
- **Nie dodajemy zewnętrznego śledzenia błędów** — świadomie odłożone w roadmapie.

## Implementation Approach

Cztery fazy, każda kończąca się zielonym `composer test`, ułożone tak, żeby ryzyko malało z każdą:

1. Najpierw zdejmujemy jedyne twarde ryzyko techniczne — konflikt Tailwind 3 vs 4 — i dopiero na stabilnym froncie budujemy dalej.
2. Potem przycinamy kit do granic PRD, bo im dłużej w repo żyją trasy `/profile` i `/dashboard`, tym więcej rzeczy zdąży się do nich podpiąć.
3. Następnie dostarczamy komendę zakładania kont, bo od niej zależy jakakolwiek ręczna weryfikacja logowania prawdziwym kontem — także na produkcji.
4. Na końcu polonizacja i responsywność, czyli warstwa, którą najłatwiej sprawdzić dopiero wtedy, gdy da się przejść całą ścieżkę logowania.

## Critical Implementation Details

**Kolejność w Fazie 1 jest jednokierunkowa.** `breeze:install` uruchamia `npm install` i `npm run build` na końcu swojego działania — czyli buduje na już nadpisanym, Tailwindowym-3 froncie. Przywrócenie plików Tailwind 4 musi nastąpić **po** instalatorze i **przed** jakąkolwiek oceną wyglądu; build uruchomiony przez instalatora należy uznać za nieistotny i przebudować ręcznie. Wartości `vite.config.js` i `resources/css/app.css` sprzed instalacji trzeba odtworzyć z gita (`git checkout -- <plik>`), a nie pisać z pamięci — obecny `app.css` zawiera blok `@theme` z fontem Instrument Sans i cztery dyrektywy `@source`, a `vite.config.js` konfigurację fontów `bunny`.

**Blokada rejestracji musi być domyślnie zamknięta.** Flaga czytana bez wartości domyślnej `false` oznacza otwartą rejestrację na publicznym URL-u produkcyjnym w momencie, gdy zmienna nie jest ustawiona w Render — czyli dokładnie w stanie po pierwszym wdrożeniu.

## Phase 1: Breeze na Tailwindzie 4

### Overview

Zainstalować kit i natychmiast cofnąć jego ingerencję we frontend, tak żeby projekt wyszedł z tej fazy z pełnym uwierzytelnianiem Breeze i nietkniętym stackiem Tailwind 4 + Vite 8.

### Changes Required:

#### 1. Instalacja pakietu

**File**: `composer.json`, `composer.lock`

**Intent**: Dodać Breeze jako zależność deweloperską i uruchomić instalator w wariancie Blade bez trybu ciemnego.

**Contract**: `composer require laravel/breeze --dev` (oczekiwana wersja `^2.4`), następnie `php artisan breeze:install blade`. Bez flagi `--dark` — instalator sam wytnie klasy dark-mode z widoków. Breeze ląduje w `require-dev`, więc produkcyjny `composer install --no-dev` z `Dockerfile` go pominie; opublikowany kod (kontrolery, widoki, trasy) to zwykły kod aplikacji i działa bez pakietu.

#### 2. Przywrócenie frontendu Tailwind 4

**File**: `vite.config.js`, `resources/css/app.css`, `tailwind.config.js`, `postcss.config.js`, `package.json`

**Intent**: Cofnąć cztery nadpisania wykonane przez instalator i usunąć dwa pliki konfiguracyjne Tailwinda 3, których projekt nie używa.

**Contract**: `vite.config.js` i `resources/css/app.css` wracają do stanu sprzed instalacji (przez `git checkout --`). `tailwind.config.js` i `postcss.config.js` zostają usunięte. W `package.json` `tailwindcss` wraca na `^4.0.0`, a `autoprefixer` i `postcss` (dodane przez instalatora dla Tailwinda 3) znikają. `alpinejs` **zostaje** — używa go rozwijane menu w `resources/views/layouts/navigation.blade.php`. Zamiast pakietowej wtyczki `@tailwindcss/forms` z `tailwind.config.js` włączamy ją składnią Tailwinda 4 bezpośrednio w CSS, obok istniejącego `@import`:

```css
@plugin '@tailwindcss/forms';
```

#### 3. Przebudowa i weryfikacja

**File**: — (bez zmian w plikach)

**Intent**: Przebudować assety na przywróconym stacku i potwierdzić, że widoki Breeze renderują się na Tailwindzie 4.

**Contract**: `npm install && npm run build` kończy się bez błędu; `public/build/manifest.json` powstaje.

### Success Criteria:

#### Automated Verification:

- Instalacja zależności przechodzi: `composer install`
- Build frontendu przechodzi: `npm run build`
- `tailwind.config.js` i `postcss.config.js` nie istnieją
- `package.json` deklaruje `tailwindcss` w wersji `^4` i nadal `@tailwindcss/vite`
- Testy uwierzytelniania Breeze przechodzą: `php artisan test --filter=AuthenticationTest`

#### Manual Verification:

- `/login` renderuje się poprawnie na Tailwindzie 4 — pola formularza mają obramowanie i widoczny stan focus (dowód, że wtyczka `forms` działa przez `@plugin`)
- Rozwijane menu w nagłówku otwiera się (dowód, że Alpine.js przetrwał przywracanie frontendu)

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie, zanim przejdziesz do następnej fazy.

---

## Phase 2: Zawężenie kitu do MVP

### Overview

Przyciąć Breeze do tego, co dopuszcza PRD: jedna chroniona strona główna pod `/`, logowanie i wylogowanie, rejestracja za wyłączoną flagą, reszta usunięta.

### Changes Required:

#### 1. Strona główna pod `/`

**File**: `routes/web.php`, `resources/views/home.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/welcome.blade.php`

**Intent**: Zastąpić trasę-domknięcie i `dashboard` jedną chronioną trasą `/` o nazwie `home`, renderującą własny widok z pustym stanem listy zakupów. Usunąć oba widoki, które ta trasa zastępuje.

**Contract**: `Route::view('/', 'home')->middleware('auth')->name('home')` (albo równoważna trasa z kontrolerem, jeśli implementujący woli — S-02 i tak podmieni ją na kontroler). Trasy `/profile` znikają z `routes/web.php`, `require __DIR__.'/auth.php'` zostaje. Widok `home.blade.php` korzysta z `<x-app-layout>` i zawiera polski komunikat o pustej liście — treść docelowo wypełni S-02. Bez middleware `verified` (weryfikacja e-mail jest usuwana w tej samej fazie).

#### 2. Przekierowanie po zalogowaniu

**File**: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

**Intent**: Skierować użytkownika po zalogowaniu na stronę główną zamiast na nieistniejący `dashboard`.

**Contract**: `redirect()->intended(route('home', absolute: false))` w metodzie `store()`. Metoda `destroy()` już przekierowuje na `/` i zostaje bez zmian.

#### 3. Flaga rejestracji

**File**: `routes/auth.php`, `config/app.php`, `.env.example`

**Intent**: Uczynić publiczną rejestrację wyłączalną jedną zmienną środowiskową, domyślnie wyłączoną, bez usuwania kodu.

**Contract**: W `config/app.php` powstaje klucz `registration_enabled` czytający `REGISTRATION_ENABLED` z domyślną wartością `false`. Obie trasy `register` w `routes/auth.php` trafiają za warunek — przy wyłączonej fladze nie są w ogóle rejestrowane, więc `/register` zwraca 404. Wartość domyślna **musi** być `false`, żeby brak zmiennej w panelu Render nie oznaczał otwartej rejestracji. Do `.env.example` trafia zakomentowana lub ustawiona na `false` pozycja `REGISTRATION_ENABLED` z komentarzem wskazującym FR-002/FR-003 jako zadanie poza MVP:

```php
if (config('app.registration_enabled')) {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
}
```

#### 4. Usunięcie funkcji spoza MVP

**File**: `routes/auth.php`, `app/Http/Controllers/Auth/`, `app/Http/Controllers/ProfileController.php`, `app/Http/Requests/ProfileUpdateRequest.php`, `resources/views/auth/`, `resources/views/profile/`, `resources/views/layouts/navigation.blade.php`

**Intent**: Usunąć reset hasła, weryfikację e-mail, potwierdzanie hasła i ekran profilu wraz z ich trasami, kontrolerami, żądaniami i widokami — żadna z tych funkcji nie ma pokrycia w PRD, a reset hasła i weryfikacja e-mail wymagałyby skonfigurowanego mailera, którego nie ma.

**Contract**: Z `routes/auth.php` znikają trasy `password.request`, `password.email`, `password.reset`, `password.store`, `password.confirm`, `password.update`, `verification.notice`, `verification.verify`, `verification.send`. Zostają: `login` (GET/POST), `logout` (POST) i warunkowa `register`. Usuwane kontrolery: `NewPasswordController`, `PasswordResetLinkController`, `PasswordController`, `ConfirmablePasswordController`, `EmailVerificationPromptController`, `EmailVerificationNotificationController`, `VerifyEmailController`, `ProfileController`. Usuwane widoki: `auth/forgot-password`, `auth/reset-password`, `auth/confirm-password`, `auth/verify-email` oraz cały katalog `profile/`. Z `layouts/navigation.blade.php` znikają odnośniki do profilu i do `dashboard`; zostaje odnośnik do strony głównej i wylogowanie. `auth/login.blade.php` traci blok `@if (Route::has('password.request'))` z linkiem „Forgot your password?".

#### 5. Kolumna `role`

**File**: `database/migrations/<timestamp>_add_role_to_users_table.php`, `app/Models/User.php`

**Intent**: Dodać kolumnę `role` na przyszły panel administracyjny (decyzja właściciela), bez żadnej logiki, która by ją czytała.

**Contract**: Migracja dodaje do `users` kolumnę `role` typu string z wartością domyślną `member`. W `User` atrybut `role` dochodzi do `#[Fillable]`. Nic tej kolumny nie sprawdza — to świadomie martwe pole do czasu FR-002/FR-003.

#### 6. Porządki w testach

**File**: `tests/Feature/Auth/`, `tests/Feature/ProfileTest.php`, `tests/Feature/ExampleTest.php`

**Intent**: Dopasować zestaw testów do zawężonego zakresu i zamienić test rejestracji w strażnika bariery prywatności.

**Contract**: Usuwane: `PasswordResetTest`, `PasswordUpdateTest`, `PasswordConfirmationTest`, `EmailVerificationTest`, `ProfileTest`. W `AuthenticationTest` asercja `assertRedirect(route('dashboard', absolute: false))` zmienia się na `route('home', absolute: false)`. `RegistrationTest` zostaje, ale zmienia sens: zamiast sprawdzać, że rejestracja działa, sprawdza że `/register` zwraca 404 przy domyślnej konfiguracji — to jest test bariery „brak publicznej rejestracji" z §Access Control i jednocześnie sygnał ostrzegawczy, gdyby ktoś przypadkiem włączył flagę. `ExampleTest::test_the_application_returns_a_successful_response` zmienia się w test przekierowania gościa: `$this->get('/')` oczekuje przekierowania na `/login`.

### Success Criteria:

#### Automated Verification:

- Cały zestaw testów przechodzi: `composer test`
- Gość jest przekierowywany ze strony głównej — pokryte przerobionym `ExampleTest`
- `/register` zwraca 404 przy domyślnej konfiguracji — pokryte przerobionym `RegistrationTest`
- Zalogowanie przekierowuje na `route('home')` — pokryte poprawionym `AuthenticationTest`
- Migracje przechodzą na czystej bazie: `php artisan migrate:fresh`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- Wejście na `/` bez sesji ląduje na `/login`
- Po zalogowaniu widać stronę główną pod adresem `/`, bez śladu `/dashboard`
- W nagłówku nie ma odnośnika do profilu; wylogowanie działa i wraca na ekran logowania
- Ustawienie `REGISTRATION_ENABLED=true` w lokalnym `.env` przywraca `/register` (dowód, że kod rejestracji przetrwał i da się go włączyć) — po sprawdzeniu przywrócić `false`

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie, zanim przejdziesz do następnej fazy.

---

## Phase 3: Konta rodziny

### Overview

Dać właścicielowi drogę założenia konta bez umieszczania danych rodziny w publicznym repozytorium — lokalnie i w powłoce Render.

### Changes Required:

#### 1. Komenda zakładania konta

**File**: `app/Console/Commands/CreateUserCommand.php`

**Intent**: Komenda artisan zakładająca pojedyncze konto: przyjmuje nazwę i e-mail argumentami lub pyta o nie interaktywnie, hasło pobiera zawsze ukrytym promptem, waliduje dane i odmawia utworzenia duplikatu adresu.

**Contract**: Sygnatura `app:user:create {name?} {email?}`. Hasło **nigdy** nie jest argumentem ani opcją — w powłoce Render argumenty trafiają do historii poleceń. Walidacja: `name` wymagane, `email` wymagane i unikalne w tabeli `users`, hasło wymagane z minimalną długością (domyślna reguła `Password::defaults()`). Przy próbie założenia konta na istniejącym adresie komenda kończy się kodem błędu i czytelnym komunikatem, nie nadpisuje istniejącego konta — to bezpośrednio chroni barierę „dane nie mogą się gubić". Hasło zapisuje się przez przypisanie do `password` (model ma cast `hashed`, więc żadne ręczne hashowanie nie jest potrzebne).

#### 2. Test komendy

**File**: `tests/Feature/CreateUserCommandTest.php`

**Intent**: Pokryć obie ścieżki komendy — utworzenie konta i odmowa przy duplikacie adresu.

**Contract**: Test feature z `RefreshDatabase`, korzystający z `$this->artisan(...)->expectsQuestion(...)`. Sprawdza, że po udanym przebiegu istnieje użytkownik o podanym adresie, jego hasło jest zahaszowane (a nie zapisane jawnie), a próba powtórzenia tego samego adresu kończy się niezerowym kodem wyjścia i nie tworzy drugiego rekordu.

#### 3. Instrukcja w README

**File**: `README.md`

**Intent**: Opisać w sekcji deploymentu jednorazowy krok zakładania kont po pierwszym wdrożeniu, żeby nie był to wiedza plemienna.

**Contract**: Krótki fragment w istniejącej sekcji „Deploy (Render + Neon)": uruchomienie `php artisan app:user:create` w powłoce usługi na Render, z uwagą, że `docker/entrypoint.sh` celowo nie seeduje kont i że repozytorium jest publiczne, więc dane rodziny nigdy do niego nie trafiają.

### Success Criteria:

#### Automated Verification:

- Cały zestaw testów przechodzi: `composer test`
- Komenda jest widoczna w rejestrze: `php artisan list` zawiera `app:user:create`
- Formatowanie zgodne: `php artisan pint --test`

#### Manual Verification:

- `php artisan app:user:create` uruchomione lokalnie zakłada konto, a tym kontem da się zalogować przez formularz
- Powtórzenie komendy z tym samym adresem odmawia i nie tworzy drugiego konta
- Hasło nie pojawia się w echu terminala podczas wpisywania

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie, zanim przejdziesz do następnej fazy.

---

## Phase 4: Polonizacja i responsywność

### Overview

Doprowadzić całą widoczną ścieżkę — ekran logowania, komunikaty błędów, nagłówek, stronę główną — do polskiego i sprawdzić ją na szerokości telefonu, bo tam PRD lokuje główne użycie.

### Changes Required:

#### 1. Tłumaczenia tekstów Breeze

**File**: `lang/pl.json`

**Intent**: Przetłumaczyć wszystkie napisy widoków Breeze bez dotykania samych plików Blade, korzystając z tego, że kit opakowuje je w `__('...')`.

**Contract**: Plik JSON z parami klucz-tłumaczenie dla tekstów faktycznie występujących w pozostawionych widokach — m.in. `Email`, `Password`, `Remember me`, `Log in`, `Log Out`. Zakres ustala przegląd `resources/views/auth/login.blade.php` i `resources/views/layouts/navigation.blade.php` po czystkach z Fazy 2; nie tłumaczymy kluczy z widoków, które już nie istnieją.

#### 2. Komunikaty uwierzytelniania i walidacji

**File**: `lang/pl/auth.php`, `lang/pl/validation.php`

**Intent**: Spolszczyć komunikaty rzucane przez `LoginRequest` i walidator, czyli teksty, które użytkownik zobaczy przy pierwszej pomyłce w haśle.

**Contract**: `lang/pl/auth.php` musi zawierać co najmniej klucze `failed` i `throttle` — to dokładnie te dwa, których używa `LoginRequest` (`trans('auth.failed')`, `trans('auth.throttle')`); klucz `throttle` zachowuje symbole zastępcze `:seconds` i `:minutes`. `lang/pl/validation.php` powstaje przez `php artisan lang:publish` i przetłumaczenie reguł faktycznie używanych na tym ekranie (`required`, `email`, `string`), plus sekcja `attributes` mapująca `email` i `password` na polskie nazwy pól. Pełne tłumaczenie wszystkich reguł nie jest potrzebne — pozostałe zdania mogą zostać po angielsku do czasu, aż jakiś formularz ich użyje.

#### 3. Ustawienie polskiej lokalizacji poza produkcją

**File**: `.env.example`, `.env`

**Intent**: Wyrównać lokalizację lokalną z produkcyjną — dziś `pl` jest tylko w `render.yaml`, więc na maszynie deweloperskiej interfejs byłby po angielsku i nikt by nie zauważył braków w tłumaczeniach.

**Contract**: `APP_LOCALE=pl` i `APP_FALLBACK_LOCALE=pl` w `.env.example` oraz w lokalnym `.env`. `render.yaml` zostaje bez zmian — ma już poprawne wartości.

#### 4. Polski, responsywny layout i strona główna

**File**: `resources/views/home.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/guest.blade.php`, `resources/views/layouts/navigation.blade.php`

**Intent**: Napisać po polsku teksty własnych widoków, poprawić tytuł strony i sprawdzić, że nagłówek oraz formularz logowania działają na wąskim ekranie.

**Contract**: `home.blade.php` dostaje polski nagłówek i komunikat pustego stanu listy zakupów. `layouts/guest.blade.php` traci sztywny odnośnik do fontu Figtree z bunny.net — projekt ładuje Instrument Sans przez konfigurację `vite.config.js`, więc to martwe zewnętrzne żądanie. Atrybut `lang` w obu layoutach wynika z `app()->getLocale()` i po zmianie z punktu 3 sam stanie się `pl` — nie trzeba go wpisywać na sztywno. Nazwa aplikacji (`APP_NAME`) widoczna w tytule strony ustawiona po polsku.

### Success Criteria:

#### Automated Verification:

- Cały zestaw testów przechodzi: `composer test`
- Build frontendu przechodzi: `npm run build`
- Formatowanie zgodne: `php artisan pint --test`
- `lang/pl/auth.php` zawiera klucze `failed` i `throttle`

#### Manual Verification:

- Ekran logowania jest w całości po polsku — etykiety, przycisk, checkbox „Zapamiętaj mnie"
- Logowanie błędnym hasłem pokazuje polski komunikat, nie angielski
- Sześć nieudanych prób pod rząd daje polski komunikat o zbyt wielu próbach z liczbą sekund
- Formularz logowania i nagłówek strony głównej wyglądają poprawnie na szerokości ~375 px, bez poziomego przewijania
- Zaznaczenie „Zapamiętaj mnie", zamknięcie i ponowne otwarcie przeglądarki zachowuje zalogowanie
- Źródło strony ma `<html lang="pl">`

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się i poczekaj na potwierdzenie od człowieka, że weryfikacja ręczna wypadła pomyślnie.

---

## Testing Strategy

### Unit Tests:

Brak. W tym kawałku nie powstaje logika domenowa nadająca się do testu jednostkowego — całość to trasy, sesja i widoki, czyli materiał na testy feature. Reguła rekomendacji sklepu (S-04) będzie pierwszym miejscem, gdzie testy jednostkowe zarobią na siebie.

### Integration Tests:

Testy feature na SQLite `:memory:` (`phpunit.xml`), wszystkie z `RefreshDatabase` zgodnie z konwencją z `CLAUDE.md`:

- `AuthenticationTest` (z Breeze, poprawiony) — renderowanie ekranu logowania, udane logowanie z przekierowaniem na `route('home')`, odrzucenie błędnego hasła, wylogowanie.
- `RegistrationTest` (z Breeze, przerobiony) — `/register` zwraca 404 przy domyślnej konfiguracji.
- `ExampleTest` (przerobiony) — gość wchodzący na `/` jest przekierowany na `/login`.
- `CreateUserCommandTest` (nowy) — utworzenie konta i odmowa przy duplikacie adresu.

### Manual Testing Steps:

1. Uruchomić środowisko: `docker-compose up -d`, wejść na `http://localhost:8080`.
2. Sprawdzić, że wejście na `/` bez sesji przekierowuje na `/login`.
3. Założyć konto: `php artisan app:user:create`, podać dane, hasło wpisać w ukrytym promptcie.
4. Zalogować się tym kontem — powinna pojawić się strona główna pod `/` z polskim komunikatem o pustej liście.
5. Wylogować się, spróbować zalogować z błędnym hasłem — sprawdzić polski komunikat.
6. Powtórzyć błędne logowanie sześć razy — sprawdzić polski komunikat o zbyt wielu próbach.
7. Zalogować się z zaznaczonym „Zapamiętaj mnie", zamknąć i otworzyć przeglądarkę — sesja ma przetrwać.
8. Wejść na `/register`, `/profile`, `/dashboard`, `/forgot-password` — wszystkie mają zwrócić 404.
9. Zwęzić okno do ~375 px i przejść ścieżkę logowania — bez poziomego przewijania i uciętych elementów.

## Performance Considerations

Brak istotnych. Skala to 3–5 osób (`target_scale.users: small` w PRD). Sesje w produkcji idą do bazy, co przy każdym żądaniu oznacza jedno dodatkowe zapytanie do Neona — przy tym ruchu bez znaczenia, a alternatywa (sesje w plikach) źle znosi restarty kontenera na darmowym planie Render.

Warto odnotować jedno: darmowy plan Render usypia usługę, a Neon ma scale-to-zero. Pierwsze logowanie po przerwie odczuje zimny start (kilka sekund). To znany koszt wybranej platformy, opisany w `context/foundation/infrastructure.md`, nie problem do rozwiązania w tym kawałku.

## Migration Notes

Baza produkcyjna nie zawiera jeszcze żadnych danych — jedyne migracje to domyślne tabele Laravela. Nowa migracja dodająca `role` uruchomi się automatycznie przy wdrożeniu (`docker/entrypoint.sh:12` wykonuje `migrate --force`), a kolumna ma wartość domyślną, więc nie wymaga backfillu.

Po pierwszym wdrożeniu tej zmiany konieczny jest **jeden ręczny krok**: uruchomienie `php artisan app:user:create` w powłoce usługi na Render dla każdego członka rodziny. Bez tego nikt nie zaloguje się na produkcji — `entrypoint.sh` celowo nie seeduje kont.

Wycofanie: kolumna `role` ma metodę `down()` usuwającą pole; reszta zmian to kod, więc cofnięcie to rewert commita i ponowne wdrożenie.

## References

- Roadmapa, pozycja S-01: `context/foundation/roadmap.md`
- Wymagania: `context/foundation/prd.md` — FR-001, US-01, §Access Control, §Non-Functional Requirements
- Stack: `context/foundation/tech-stack.md`, `CLAUDE.md`
- Deployment i sekrety: `context/deployment/deploy-plan.md`, `render.yaml`, `docker/entrypoint.sh`
- Breeze v2.4.2 — instalator: `src/Console/InstallsBladeStack.php`; trasy: `stubs/default/routes/auth.php`; throttling: `stubs/default/app/Http/Requests/Auth/LoginRequest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Breeze na Tailwindzie 4

#### Automated

- [x] 1.1 Instalacja zależności przechodzi: `composer install` — 5a18c60
- [x] 1.2 Build frontendu przechodzi: `npm run build` — 5a18c60
- [x] 1.3 `tailwind.config.js` i `postcss.config.js` nie istnieją — 5a18c60
- [x] 1.4 `package.json` deklaruje `tailwindcss` w wersji `^4` i nadal `@tailwindcss/vite` — 5a18c60
- [x] 1.5 Testy uwierzytelniania Breeze przechodzą: `php artisan test --filter=AuthenticationTest` — 5a18c60

#### Manual

- [x] 1.6 `/login` renderuje się poprawnie na Tailwindzie 4 — pola formularza mają obramowanie i widoczny stan focus — 5a18c60
- [x] 1.7 Rozwijane menu w nagłówku otwiera się (Alpine.js przetrwał przywracanie frontendu) — 5a18c60

### Phase 2: Zawężenie kitu do MVP

#### Automated

- [x] 2.1 Cały zestaw testów przechodzi: `composer test`
- [x] 2.2 Gość jest przekierowywany ze strony głównej — pokryte przerobionym `ExampleTest`
- [x] 2.3 `/register` zwraca 404 przy domyślnej konfiguracji — pokryte przerobionym `RegistrationTest`
- [x] 2.4 Zalogowanie przekierowuje na `route('home')` — pokryte poprawionym `AuthenticationTest`
- [x] 2.5 Migracje przechodzą na czystej bazie: `php artisan migrate:fresh`
- [x] 2.6 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [x] 2.7 Wejście na `/` bez sesji ląduje na `/login`
- [x] 2.8 Po zalogowaniu widać stronę główną pod adresem `/`, bez śladu `/dashboard`
- [x] 2.9 W nagłówku nie ma odnośnika do profilu; wylogowanie działa i wraca na ekran logowania
- [x] 2.10 `REGISTRATION_ENABLED=true` przywraca `/register`, po sprawdzeniu przywrócone `false`

### Phase 3: Konta rodziny

#### Automated

- [ ] 3.1 Cały zestaw testów przechodzi: `composer test`
- [ ] 3.2 Komenda jest widoczna w rejestrze: `php artisan list` zawiera `app:user:create`
- [ ] 3.3 Formatowanie zgodne: `php artisan pint --test`

#### Manual

- [ ] 3.4 `php artisan app:user:create` zakłada konto, którym da się zalogować przez formularz
- [ ] 3.5 Powtórzenie komendy z tym samym adresem odmawia i nie tworzy drugiego konta
- [ ] 3.6 Hasło nie pojawia się w echu terminala podczas wpisywania

### Phase 4: Polonizacja i responsywność

#### Automated

- [ ] 4.1 Cały zestaw testów przechodzi: `composer test`
- [ ] 4.2 Build frontendu przechodzi: `npm run build`
- [ ] 4.3 Formatowanie zgodne: `php artisan pint --test`
- [ ] 4.4 `lang/pl/auth.php` zawiera klucze `failed` i `throttle`

#### Manual

- [ ] 4.5 Ekran logowania jest w całości po polsku — etykiety, przycisk, checkbox „Zapamiętaj mnie"
- [ ] 4.6 Logowanie błędnym hasłem pokazuje polski komunikat
- [ ] 4.7 Sześć nieudanych prób daje polski komunikat o zbyt wielu próbach z liczbą sekund
- [ ] 4.8 Formularz logowania i nagłówek wyglądają poprawnie na szerokości ~375 px, bez poziomego przewijania
- [ ] 4.9 „Zapamiętaj mnie" zachowuje zalogowanie po ponownym otwarciu przeglądarki
- [ ] 4.10 Źródło strony ma `<html lang="pl">`
