---
project: lista-zakupow
researched_at: 2026-06-02
recommended_platform: Render (free web service, Docker) + Neon (free Postgres)
runner_up: Railway
context_type: mvp
tech_stack:
  language: PHP 8.3+
  framework: Laravel 13
  runtime: PHP-FPM (Docker container on Render)
  database: PostgreSQL 16 (external — Neon free tier)
---

## Recommendation

**Wdrażaj na Render (darmowy web service, kontener Docker) z bazą PostgreSQL na Neon (darmowy tier).**

Cel właściciela to hosting **w pełni za $0** dla rodzinnej apki 3–5 osób, z akceptacją cold-startów i ręcznego Dockerfile. Render daje realny darmowy web service (750h/mc, bez karty), a kontener Docker bez problemu uruchamia PHP-FPM + Laravel 13. Kluczowa decyzja architektoniczna: **baza NIE leży na darmowym Postgresie Rendera** (ten wygasa po 30 dniach i jest kasowany razem z danymi — łamie guardrail PRD „dane nie mogą się gubić"), tylko na **darmowym Neonie**, który jest trwały (scale-to-zero z wybudzeniem <0,5 s, brak wygaśnięcia). Świadomie rezygnujemy z ko-lokacji bazy (preferencja z wywiadu), bo priorytet „w pełni za darmo + brak utraty danych" jest ważniejszy. Runner-up to Railway — wygrywa na agent-friendliness i auto-detekcji Laravela, ale nie ma prawdziwego darmowego tieru ($5/mc minimum).

## Platform Comparison

Filtr twardy (runtime): stack PHP/Laravel-FPM eliminuje przed scoringiem platformy JS/edge-serverless, które nie uruchomią trwałego procesu PHP — **Cloudflare Workers, Vercel, Netlify odpadły**. Oceniane były platformy kontenerowe/PaaS uruchamiające Laravela.

| Platforma | CLI-first | Managed/Serverless | Docs dla agenta | Stabilne API deploy | MCP/Integracja | Wynik |
|---|---|---|---|---|---|---|
| **Render** | Pass | Pass | Pass | Pass | Pass | 5 Pass |
| **Railway** | Pass | Pass | Pass | Pass | Pass | 5 Pass |
| **Fly.io** | Pass | Pass | Pass | Pass | Partial | 4 Pass + 1 Partial |
| **Laravel Cloud** | Partial | Pass | Pass | Pass | Partial | 3 Pass + 2 Partial |

Noty per platforma:

- **Render** — CLI v2.10+ z agent-skills, oficjalny MCP server (GA VIII 2025, 20+ narzędzi, w tym read-only `query_render_postgres` i metryki), deploy przez git-push/deploy hooks/API, docs `render.com/docs/llm-support`. Jako jedyna na liście oferuje **realny darmowy web service bez karty**. Słabość: Laravel wymaga Dockerfile (brak auto-detekcji), a darmowy Postgres wygasa — dlatego bazę bierzemy z zewnątrz.
- **Railway** — najdojrzalsze narzędzia dla agenta (CLI + lokalny MCP + remote MCP `mcp.railway.com` + skills + delegacja `railway-agent`), auto-detekcja Laravela (php-fpm+Caddy, bez Dockerfile), usage-based billing. Przegrywa tu jednym: **brak darmowego tieru** ($5/mc minimum), co dyskwalifikuje przy twardym celu $0.
- **Fly.io** — solidne `flyctl`, docs jako MDX/llms.txt na GitHubie. MCP wbudowany we flyctl, ale eksperymentalny → Partial. Managed Postgres od ~$38/mc (drogo dla solo), brak darmowego tieru, a przewaga multi-region marnuje się przy jednym regionie.
- **Laravel Cloud** — najbardziej natywny dla stacku (oficjalna platforma Laravela, serverless Postgres na Neonie, scale-to-zero, $5/mc + $5 kredytu + 1. miesiąc gratis). Słabszy agent-ops: sterowanie głównie z dashboardu, brak dojrzałego infra-CLI/MCP → 2× Partial. Nie jest w pełni darmowy.

### Shortlisted Platforms

#### 1. Render + Neon (Recommended)

Jedyny układ realizujący twardy cel **$0/mc bez utraty danych**. Render free web (Docker/Laravel) + Neon free Postgres (trwały, scale-to-zero). 5/5 kryteriów agent-friendly po stronie compute (oficjalny MCP GA, CLI ze skills), a Neon to ten sam silnik, który napędza serverless Postgres Laravel Cloud — sprawdzony pod Laravelem. Cena: cold start web ~1 min + baza ~0,5 s (zaakceptowane przez właściciela).

#### 2. Railway

Gdyby budżet ~$5/mc był OK, Railway byłby liderem: najlepsze narzędzia dla agenta, auto-detekcja Laravela (bez Dockerfile), Postgres na miejscu (ko-lokacja), usage-based = tani idle. Luka vs rekomendacja: brak darmowego tieru i nieprzewidywalny koszt usage-based (literówka w workerze potrafi nabić rachunek).

#### 3. Laravel Cloud

Najbardziej „zero-config" dla Laravela 13 i bardzo tani sustained dzięki scale-to-zero ($5/mc + kredyt). Luka: słabszy agent-ops (dashboard zamiast CLI/MCP) i brak pełnej darmowości — przy celu $0 odpada, ale to naturalny cel migracji, gdy projekt zacznie zarabiać.

## Anti-Bias Cross-Check: Render

### Devil's Advocate — Weaknesses

1. **Laravel na Render wymaga własnego Dockerfile** (brak auto-detekcji jak dla Node/Python) — sam utrzymujesz obraz: wersja PHP, rozszerzenia, OPcache, serwer (Nginx/Caddy). Błąd w obrazie = nieudany build.
2. **Darmowy Postgres Rendera to pułapka danych** (wygasa po 30 dniach → grace 14 dni → twarda kasacja, zero backupów). Zmitygowane przez wyniesienie bazy na Neon, ale ryzyko wraca, jeśli ktoś „dla wygody" kliknie Render Postgres.
3. **Web na free usypia po 15 min** z cold-startem ~1 min — koliduje z mobilnym UX i NFR „24/7" (dostępność tak, ale pierwszy request wolny).
4. **Cennik przebudowany 23 IV 2026** — warunki darmowego tieru i przypisanie funkcji (PITR/HA/replicas) zależą od planu workspace; kwoty z tutoriali sprzed tej daty są nieaktualne.
5. **Build z Dockera bywa wolny** — przy iteracji po godzinach każdy deploy to kilka minut, co tłumi pętlę feedbacku.

### Pre-Mortem — How This Could Fail

Rodzina wdrożyła Laravela na darmowym Renderze, kuszona „prawdziwym free tierem", i na starcie wzięła też darmowy Postgres Rendera zamiast Neona, bo „był jednym kliknięciem w tym samym panelu". Pierwsze tygodnie idealne. Po miesiącu przyszło ostrzeżenie o wygaśnięciu bazy — utonęło w skrzynce. W 44. dniu Render skasował bazę: konfiguracja sklepów z kategoriami i lista zakupów przepadły — dokładnie scenariusz, przed którym chronił guardrail PRD. Odtworzenie zajęło wieczór i podkopało zaufanie reszty rodziny. Przy okazji okazało się, że darmowy web i tak usypiał, więc żona narzekała na „zawieszanie się" apki przy wyjeździe do sklepu. Dodatkowo Dockerfile sklecony naprędce miał przypiętą starą wersję PHP — aktualizacja zależności Laravela 13 wywaliła build i deploy stał dwa dni. Decyzja nie była zła technicznie; rozbiła się o dwie pułapki: nieużycie Neona (utrata danych) i nieprzypiętą wersję PHP w obrazie.

### Unknown Unknowns

- **Pułapka darmowego Postgresa Rendera jest terminalna** (kasacja danych), w przeciwieństwie do usypiania web (odzyskiwalne). To dlatego baza idzie na Neon — różnica nie jest kosmetyczna.
- **Dockerfile to Twój kontrakt z runtime** — Render nie aktualizuje wersji PHP ani rozszerzeń; aktualizacja Laravela może wymagać ręcznej zmiany obrazu. Przypnij `php:8.3` jawnie.
- **Oficjalny MCP Rendera nie kasuje** serwisów/baz, ale **potrafi nadpisywać zmienne środowiskowe** — agent może podmienić `APP_KEY`/`DATABASE_URL` i rozłączyć apkę, „nic nie kasując". MCP też „nie gwarantuje", że connection stringi nie wyciekną do kontekstu LLM.
- **Neon: scale-to-zero budzi się <0,5 s, ale free tier ma limity** (100 CU-h/mc, 0,5 GB storage) i **brak automatycznych backupów na free** — przy guardrailu „dane nie mogą się gubić" trzeba dorobić własny `pg_dump`/branching.
- **Databricks przejął Neon (V 2025)** — warunki darmowego tieru mogą się zmienić; trzymaj ścieżkę migracji (export do płatnego Postgresa Rendera lub Laravel Cloud).
- **Render 750 instance-hours/mc** — jeśli przekroczysz (np. kilka darmowych serwisów), free web zostaje zawieszony do końca miesiąca. Trzymaj jeden darmowy web.

## Operational Story

- **Preview deploys**: Render auto-deployuje przy push na podłączony branch (zgodnie z `ci_default_flow: auto-deploy-on-merge`). Pełne preview environments per-PR są ograniczone na free — na MVP wystarcza auto-deploy z `main`. Preview URL = subdomena `*.onrender.com`.
- **Secrets**: zmienne środowiskowe (`APP_KEY`, `DATABASE_URL` z Neona, `APP_URL`, `DB_CONNECTION=pgsql`, `PGSSLMODE=require`) wpisane w Render Environment (dashboard / `render.yaml` / CLI) oraz w GitHub Actions Secrets dla CI — **nigdy commitowane do repo**. Connection string Neona pobierany z panelu Neon (użyj pooled endpoint).
- **Rollback**: w panelu Render „Rollback" do poprzedniego deployu albo redeploy wcześniejszego commita (również przez CLI/API). Czas: ~1–3 min. **Uwaga: migracje DB nie cofają się automatycznie** — rollback kodu nie odwraca `php artisan migrate`; trzymaj migracje odwracalne i rób `pg_dump` przed migracją na produkcji.
- **Approval**: tylko człowiek — utworzenie/skasowanie/wymiana bazy Neon, rotacja `APP_KEY`, usunięcie serwisu Render, zmiana planu. Agent bez nadzoru może: deploy, tail logów, odczyt metryk przez MCP. **Zmianę zmiennych środowiskowych przez MCP traktuj jako wrażliwą** (może rozłączyć apkę od bazy).
- **Logs**: `render logs <service>` (CLI v2.10+) lub narzędzia MCP Rendera (logi, metryki, `query_render_postgres` read-only). Logi Laravela kieruj na `stderr` (`LOG_CHANNEL=stderr`), bo filesystem kontenera jest efemeryczny. Logi/metryki bazy w konsoli Neon.

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Ktoś użyje darmowego Postgresa Rendera zamiast Neona → kasacja danych po 30 dniach | Pre-mortem / Research finding | M | H | Baza wyłącznie na Neon; w `render.yaml`/docs repo zapisać „NIE twórz Render Postgres"; w README link do Neona |
| Brak backupów na Neon free → utrata danych (guardrail „dane nie mogą się gubić") | Devil's advocate / Research | M | H | **Częściowo zmitygowane 2026-09-11:** instant restore Neona w oknie **6 h**, procedura opisana w `context/deployment/deploy-plan.md` → „Odtworzenie bazy". Strata zauważona po ponad 6 h nadal nieodwracalna — cykliczny `pg_dump` świadomie odłożony |
| Dockerfile z nieprzypiętą wersją PHP → build pęka przy aktualizacji Laravela | Unknown unknowns / Pre-mortem | M | M | Przypiąć `php:8.3-fpm` jawnie; budować obraz w CI przed deployem |
| Cold start web ~1 min psuje mobilny UX | Devil's advocate | H | L | Zaakceptowane na MVP; jeśli uciążliwe → najtańszy płatny web (znosi spin-down) |
| MCP/agent nadpisze `APP_KEY`/`DATABASE_URL` i rozłączy apkę | Unknown unknowns | L | H | Zmiany env przez agenta wymagają review; rotacja `APP_KEY` tylko ręcznie |
| Przekroczenie 750 instance-h/mc → zawieszenie free web | Research finding | L | M | Trzymać jeden darmowy serwis; monitorować zużycie godzin |
| Neon free: limity 100 CU-h/0,5 GB lub zmiana warunków po przejęciu przez Databricks | Unknown unknowns / Research | L | M | Monitorować usage (apka 3–5 osób = znikomy); gotowa ścieżka migracji do płatnego PG |
| Migracja DB bez auto-rollbacku zostawia bazę w stanie pośrednim | Research finding | M | M | Migracje odwracalne; `pg_dump` przed migracją na prod; test migracji w CI |
| Zmiana cennika Rendera (23 IV 2026) unieważnia założenia o free tier | Research finding | L | M | Zweryfikować aktualne warunki free na render.com/pricing przed wdrożeniem |

## Getting Started

Wersje zweryfikowane pod Laravel 13 / PHP 8.3 / Render Docker / Neon (stan na 2026-06-02):

1. **Załóż darmowy projekt Neon** (neon.com) → skopiuj **pooled** connection string. Laravel czyta `DATABASE_URL`; ustaw `PGSSLMODE=require` (Neon wymaga SSL). To 1 GB / 100 CU-h free, trwały, scale-to-zero.
2. **Dodaj Dockerfile do repo** — obraz `php:8.3-fpm` z rozszerzeniami pod Laravela (`pdo_pgsql`, `pgsql`, `mbstring`, `bcmath`, `zip`) + serwer (Nginx lub Caddy) lub `php artisan serve` za proxy. Przypnij wersję PHP jawnie. (To jedyna „ręczna" część — zgodnie z Twoją akceptacją.)
3. **Push do GitHuba**, potem w Render: **New → Web Service → Docker**, podłącz repo, branch `main` (auto-deploy on merge — zgodne z `tech-stack.md`).
4. **Ustaw env w Render**: `APP_KEY` (z `php artisan key:generate --show`), `APP_ENV=production`, `APP_URL`, `DB_CONNECTION=pgsql`, `DATABASE_URL` (pooled z Neona), `PGSSLMODE=require`, `LOG_CHANNEL=stderr`.
5. **Migracje**: dodaj release/start command `php artisan migrate --force && php artisan config:cache` (lub w entrypoincie kontenera). Po pierwszym buildzie apka żyje na `https://<nazwa>.onrender.com`.
6. **(Opcjonalnie) zainstaluj Render CLI v2.10+** (`render`) i podłącz agent-skills/MCP dla `render logs`/`render deploys` z poziomu sesji.

## Out of Scope

Poniższe nie były przedmiotem tego researchu:
- Konfiguracja obrazu Docker (treść Dockerfile — tylko wskazana jako wymagana)
- Konfiguracja pipeline'u CI/CD (poza wskazaniem auto-deploy-on-merge z GitHub Actions)
- Architektura produkcyjna w skali (multi-region, HA, DR)
