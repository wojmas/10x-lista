---
project: lista-zakupow
created_at: 2026-06-03
status: deployed
platform: Render (free web, Docker) + Neon (free Postgres)
production_url: https://lista-zakupow-acl5.onrender.com
repo: git@github.com:wojmas/10x-lista.git
source_of_truth: context/foundation/infrastructure.md
---

# Deploy plan — lista-zakupow (Render + Neon)

Artefakt audytowy „co miało się wydarzyć" przy pierwszym wdrożeniu. Powstał z planu zatwierdzonego
w Plan Mode i odzwierciedla stan po wykonaniu **Fazy A** (kod + smoke test + push). Fazy C–D
(Neon, Render) to bramki ręczne właściciela. Źródło decyzji platformowej: `infrastructure.md`.

## Decyzje (potwierdzone)

- Obraz produkcyjny: **nginx + php-fpm + supervisor** w jednym kontenerze, serwer na `$PORT`.
- Deploy: **natywny auto-deploy Render** z gałęzi `main` (bez GitHub Actions na MVP).
- Konfiguracja: **`render.yaml` (Blueprint)** w repo; sekrety wpisywane ręcznie w panelu.

## Luki wykryte w audycie i ich naprawy

| # | Problem | Naprawa | Status |
|---|---------|---------|--------|
| L1 | `infrastructure.md` każe `DATABASE_URL`/`PGSSLMODE`, ale repo czyta `DB_URL`/`DB_SSLMODE` (`config/database.php:89,99`) | Używamy `DB_URL` + `DB_SSLMODE=require` | ✅ w `render.yaml` |
| L2 | Dockerfile dev-only (port 9000, brak build vendora/assetów, wolumeny) | Multi-stage prod Dockerfile (build Vite + nginx+fpm+supervisor) | ✅ |
| L3 | infra doc pinuje `php:8.3`, ale `composer.lock` ciągnie Symfony 8 (`php >=8.4`) | **Pinujemy `php:8.4-fpm`** (spełnia tech-stack „PHP 8.3+", zgodne z lockiem) | ✅ |
| L4 | Brak `trustProxies` — za proxy Render złe https URL-e / secure cookies | `$middleware->trustProxies(at: '*')` w `bootstrap/app.php` | ✅ |
| L5 | `APP_LOCALE=en` mimo wymogu PL | `APP_LOCALE=pl`, `APP_FALLBACK_LOCALE=pl` | ✅ w `render.yaml` |
| L6 | Brak repo git / GitHub | `git init` + push do `wojmas/10x-lista` | ✅ Faza B |
| L7 | nginx `fastcgi_pass app:9000` (inny kontener) | `127.0.0.1:9000` + `listen ${PORT}` przez `envsubst` | ✅ |
| L8 | Brak backupów (Neon free bez auto-backupów) | Instant restore Neona w oknie 6 h + opisana procedura (sekcja „Odtworzenie bazy") — cykliczny `pg_dump` świadomie odłożony | ✅ z ograniczeniem |

## Pliki utworzone / zmienione

- `Dockerfile` — multi-stage: `node:22` build assetów → `php:8.4-fpm` runtime (composer `--no-dev`, nginx+supervisor).
- `docker/nginx/prod.conf.template` — `listen ${PORT}`, `fastcgi_pass 127.0.0.1:9000`.
- `docker/supervisor/supervisord.conf` — programy `php-fpm` + `nginx`, logi na stdout/stderr.
- `docker/entrypoint.sh` — `envsubst` portu → `migrate --force` → `config/route/view:cache` → `supervisord`.
- `render.yaml` — Blueprint (web/docker/free/frankfurt, `healthCheckPath: /up`, env vars; sekrety `sync: false`).
- `bootstrap/app.php` — `trustProxies`.
- `.dockerignore` — dodane `tests`, `.github`, `storage/logs/*`, `draft.md`, `.env.example`.
- `README.md` — sekcja Deploy + ostrzeżenie „NIE twórz Render Postgres".

## Konta (bramki ręczne właściciela)

1. **GitHub** — repo `git@github.com:wojmas/10x-lista.git` (**publiczne**). ✅
2. **Render** — konto (rejestracja e-mail), GitHub podpięty do deployu. ✅
3. **Neon** — projekt założony, **pooled** connection string zachowany u właściciela. ✅

## Sekrety i zmienne (Render → Environment)

| Klucz | Typ | Wartość / źródło |
|-------|-----|------------------|
| `APP_KEY` | **sekret** | `php artisan key:generate --show` (świeży) |
| `DB_URL` | **sekret** | pooled Neon: `postgresql://USER:PASS@ep-...-pooler.<region>.aws.neon.tech/neondb?sslmode=require&channel_binding=require` |
| `APP_URL` | uzupełniany | `https://<nazwa>.onrender.com` po nadaniu nazwy |
| `APP_ENV` | jawna (render.yaml) | `production` |
| `APP_DEBUG` | jawna | `false` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | jawna | `pl` / `pl` |
| `LOG_CHANNEL` | jawna | `stderr` |
| `DB_CONNECTION` / `DB_SSLMODE` | jawna | `pgsql` / `require` |
| `SESSION_DRIVER` / `SESSION_SECURE_COOKIE` | jawna | `database` / `true` |
| `CACHE_STORE` / `QUEUE_CONNECTION` | jawna | `database` / `sync` |

> Pułapka: ustawienie `DATABASE_URL` zamiast `DB_URL` = Laravel w tym repo go nie odczyta.
> Neon SNI: na php 8.4 (bookworm) działa; gdyby błąd `Endpoint ID is not specified` — wbij `endpoint=ep-xxxx;` w hasło.

## Sekwencja wdrożenia

- **Faza A — kod (agent):** pliki wyżej; build obrazu; smoke test (postgres sidecar) → `/up`=200, `/`=200, 9 tabel utworzonych, assety Vite w obrazie; Pint czysty; PHPUnit 2/2. ✅
- **Faza B — git (agent + właściciel):** klucz SSH w agencie; `git init -b main` → commit → push do `origin`. ✅
- **Faza C — Neon (właściciel):** pooled `DB_URL` gotowy. ✅
- **Faza D — Render (właściciel):** New → Blueprint → wskaż `wojmas/10x-lista` → wpisz sekrety `APP_KEY`, `DB_URL` → po nadaniu URL uzupełnij `APP_URL` → redeploy.
- **Faza E — weryfikacja (niżej).**

## Weryfikacja produkcji (po Fazie D)

- `curl -i https://<nazwa>.onrender.com/up` → `200`.
- Strona ma style Tailwind (assety Vite), renderuje się po polsku.
- Logi Render: udane `migrate --force`, brak błędów PDO. W konsoli Neon widać tabele.
- Cookie sesji ma flagę `Secure` (trust proxy + HTTPS).
- Test auto-deployu: drobny commit na `main` → automatyczny build+deploy.
- Cold start po 15 min bezczynności ~1 min (zaakceptowane na MVP).

## Produkcja — weryfikacja na żywo (2026-06-03)

URL: **https://lista-zakupow-acl5.onrender.com**

```
/up   → HTTP 200 (0.14s)
/     → HTTP 200
HTTPS → HTTP/2 (Render/Cloudflare)
cookie laravel-session → secure; httponly  (trust proxy + SESSION_SECURE_COOKIE działa)
baza  → sesja zapisana = połączenie z Neon OK, brak 500
```

Pozostało (opcjonalnie): ustawić `APP_URL=https://lista-zakupow-acl5.onrender.com` w panelu Render
(dla absolutnych URL-i generowanych poza kontekstem żądania) → Save → redeploy.

## Smoke test — wynik (2026-06-03)

```
/up        → HTTP 200
/          → HTTP 200
DB tables  → cache, cache_locks, failed_jobs, job_batches, jobs,
             migrations, password_reset_tokens, sessions, users (9)
assets     → public/build/manifest.json + assets/ obecne w obrazie
Pint       → PASS (0 plików do poprawy)
PHPUnit    → OK (2 tests, 2 assertions)
```

## Odtworzenie bazy (F-01, guardrail „dane nie mogą się gubić")

### Co mamy, a czego nie

Na darmowym tierze Neona baza **nie ma automatycznych backupów** — harmonogramy zrzutów są funkcją planów płatnych. Zamiast nich działa **instant restore**: Neon trzyma ciągłą historię zmian (WAL) i pozwala cofnąć gałąź do dowolnego punktu w tym oknie albo odtworzyć dane obok, bez ruszania produkcji.

| Parametr | Wartość na free | Skutek |
|----------|-----------------|--------|
| Okno historii | **6 godzin** (płatne: 1 dzień domyślnie, do 30 dni) | Wpadka zauważona później niż po 6 h jest nie do odkręcenia |
| Limit historii zmian | 1 GB | Nieosiągalny przy liście zakupów rodziny |
| Limit storage | 0,5 GB | Powyżej zapisy zaczynają się wywalać — monitorować, nie ignorować |
| Automatyczny backup | brak | Jedyna siatka to okno wyżej |

Okno konfiguruje się w panelu Neona: **Settings → Instant restore**.

### Świadomie przyjęte ryzyko

Sześć godzin to całe zabezpieczenie. Pomyłka zauważona następnego dnia — omyłkowe `migrate:fresh` na produkcji, kasacja sklepów w piątek zauważona w niedzielę — oznacza bezpowrotną utratę danych. Właściciel przyjął to 2026-09-11, wybierając wariant „udokumentuj i przeklikaj" zamiast budowy cyklicznego `pg_dump`. **To jest ten moment, w którym wraca się po `pg_dump`**, jeśli kiedyś jakakolwiek strata przejdzie niezauważona dłużej niż pół dnia.

### Procedura — odtworzenie danych obok produkcji (domyślna)

Wariant bezpieczny: produkcyjna gałąź zostaje nietknięta, dane wracają na nowej gałęzi, z której wyciągasz to, czego brakuje.

1. Panel Neona → projekt listy zakupów → **Backup & restore**.
2. Wybierz moment sprzed wpadki (selektor daty i godziny; musi mieścić się w oknie 6 h).
3. **Preview data** — zajrzyj w tabele `shops`, `category_shop`, `products`, zanim cokolwiek zrobisz. Jeśli danych tam nie ma, cofasz się za daleko albo za blisko.
4. Odtwórz **na nową gałąź** (w konsoli: snapshot → *Restore* → *Multi-step restore*; w CLI: `neon branches restore` / `neon snapshots restore` z `--target-branch`). Oryginalna gałąź zostaje bez zmian.
5. Podłącz się do nowej gałęzi (własny connection string) i przenieś brakujące wiersze do produkcji — albo, jeśli strata jest całościowa, przełącz `DB_URL` w panelu Render na nową gałąź i zrób redeploy.

### Procedura — cofnięcie produkcji (ostateczność)

Gdy strata jest całościowa i nie ma czego scalać:

1. **Backup & restore** → wybierz moment → **Restore**.
2. Neon sam zachowuje stan sprzed operacji jako gałąź `<branch>_old_<timestamp>` — to jest Twoja droga powrotna, jeśli cofniesz się źle.
3. Wszystko, co rodzina zapisała **po** wybranym punkcie, znika. Przy liście zakupów to zwykle kilka pozycji; przy konfiguracji sklepów to może być cała praca z S-03/S-06.

### Dowód, że to działa

Ścieżkę trzeba przeklikać raz, na sucho, zanim będzie potrzebna pod presją — inaczej jest deklaracją, nie zabezpieczeniem. Test wariantem bezpiecznym (gałąź z przeszłości), więc produkcja nie jest dotykana:

- [ ] W panelu Neona utworzona gałąź z punktu sprzed ~1 godziny
- [ ] Na tej gałęzi widać dane: sklepy z kategoriami i produkty
- [ ] Gałąź testowa skasowana po sprawdzeniu (limit 10 gałęzi na free)
- [ ] Nazwy przycisków i ścieżka w panelu zgadzają się z opisem wyżej — jeśli Neon przemianował UI, poprawić tę sekcję

### Obserwacja produkcyjna (2026-09-11)

Aplikacja działa na produkcji od 2026-06-03. Po ponad trzech miesiącach dane rodziny — konta, sklepy z kategoriami, lista produktów — są na miejscu; właściciel potwierdził to ręcznym przeglądem. To dowodzi **trwałości** wybranej platformy (decyzja z `infrastructure.md`, żeby nie brać darmowego Postgresa Rendera, obroniła się: pułapka „kasacja po 30 dniach" nie miała jak zadziałać). Nie dowodzi **odtwarzalności** — od tego jest procedura i test wyżej.

## Follow-up (po MVP)

- **Cykliczny `pg_dump` (dawne L8):** GitHub Actions z harmonogramem → zrzut do artefaktu/R2. Odłożone 2026-09-11 na rzecz instant restore; wrócić, gdy okno 6 h okaże się za krótkie (patrz „Świadomie przyjęte ryzyko").
- **Render CLI/MCP:** opcjonalnie dla `render logs`/metryk z sesji agenta.
- **Granice produkcji:** token Render zawężony do projektu; rotacja `APP_KEY` i kasowanie bazy — wyłącznie ręcznie.
- Risk Register w `infrastructure.md` pozostaje źródłem prawdy dla ryzyk operacyjnych.
