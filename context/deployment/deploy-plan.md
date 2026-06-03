---
project: lista-zakupow
created_at: 2026-06-03
status: executed-phase-A
platform: Render (free web, Docker) + Neon (free Postgres)
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
| L8 | Brak backupów (Neon free bez auto-backupów) | Cron `pg_dump` — **follow-up po MVP** | ⏳ |

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

## Follow-up (po MVP)

- **Backupy (L8):** cron `pg_dump` (GitHub Actions scheduled lub Neon branching) — guardrail „dane nie mogą się gubić".
- **Render CLI/MCP:** opcjonalnie dla `render logs`/metryk z sesji agenta.
- **Granice produkcji:** token Render zawężony do projektu; rotacja `APP_KEY` i kasowanie bazy — wyłącznie ręcznie.
- Risk Register w `infrastructure.md` pozostaje źródłem prawdy dla ryzyk operacyjnych.
