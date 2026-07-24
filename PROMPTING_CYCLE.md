# PROMPTING CYCLE — Autonomous Development Protocol

> File ini adalah prompt yang dapat digunakan untuk melanjutkan pengembangan aplikasi secara autonomous.
> Update file ini setiap cycle selesai dengan menambahkan hasil ke HISTORY dan mengubah CURRENT TASK.
> AI assistant membaca file ini di awal sesi untuk mengetahui konteks dan tugas berikutnya.

---

## CARA PENGGUNAAN

1. Buka file ini di awal sesi pengembangan
2. Baca section PROJECT CONTEXT untuk memahami arsitektur
3. Baca section CURRENT TASK untuk tugas yang harus dikerjakan
4. Kerjakan tugas mengikuti section EXECUTION PROTOCOL
5. Setelah selesai, update section HISTORY dan CURRENT TASK
6. Commit dan push perubahan
7. Ulangi cycle untuk tugas berikutnya

---

## PROJECT CONTEXT

### Aplikasi
**Platform:** Global & Indonesia Capital Market Intelligence, Decision, Risk & Execution Platform
**Arsitektur:** Modular Monolith (PHP 8.3+, PSR-12)
**Database:** MySQL (56 tables, schema designed) + TimescaleDB (planned)
**Cache:** Redis (fail-open)
**Auth:** JWT HS256, single-owner, bearer token

### Repository
- **Path:** `/opt/lampp/htdocs/chat/`
- **GitHub:** `https://github.com/82080038/chat.git`
- **Branch:** `main`

### Key Files
- `MASTER_BLUEPRINT.md` — Blueprint lengkap (536 sections)
- `DEVELOPMENT_ROADMAP.md` — Roadmap dengan checkbox status
- `api/API_CONTRACT.md` — API contract utama (conventions + endpoints)
- `api/API_CONTRACT_BATCH2.md` — Fundamental & Analytics endpoints
- `api/API_CONTRACT_BATCH3.md` — Portfolio & Risk endpoints
- `api/API_CONTRACT_BATCH4.md` — Trading & Settlement endpoints
- `api/API_CONTRACT_BATCH5.md` — Governance, Config & Cross-cutting endpoints
- `api/SERVICE_BOUNDARY_SPEC.md` — Service interface spec (10 services)
- `public/index.php` — Application bootstrap & route registration
- `src/Core/` — BaseService, Application, Router, ServiceHub, ApiException
- `database/migrations/` — SQL schema (001-009)
- `tests/` — PHPUnit tests (60 tests, 118 assertions)

### Implemented Services (10/10)
1. IdentityService — auth, JWT, session, preferences
2. ConfigService — config, feature flags, system params, storage, logs
3. MarketMasterService — exchanges, issuers, instruments, listings, corporate actions, indices, calendar
4. FundamentalService — financial statements, metrics, economic indicators, news
5. AnalyticsService — features, signals, forecasts, recommendations, scores, models, backtests
6. RiskService — risk profiles, limits, assessments, events
7. PortfolioService — portfolios, positions, cash, targets, accounts
8. TradingService — brokers, decisions, order intents, orders, executions
9. SettlementService — settlements, reconciliations
10. GovernanceService — audit log, approvals, workflows, policies

### Cross-Service Wiring
- ServiceHub: pre-trade risk check, auto-settlement, audit logging
- Health: /health, /health/ready, /health/live, /metrics

### Coding Standards
- PSR-12 (validated with phpcs)
- `declare(strict_types=1)` di semua file
- Interface → Service → Routes → Tests pattern
- BaseService untuk DB access, UUID, timestamps, pagination
- ApiException untuk error handling
- Bearer JWT middleware di semua authenticated routes

---

## EXECUTION PROTOCOL

### Setiap task harus mengikuti langkah berikut:

1. **Analisis** — Baca blueprint, roadmap, API contract, dan service boundary spec yang relevan
2. **Implementasi** — Tulis kode mengikuti pattern existing (BaseService, ApiException, dll)
3. **Test** — Tambah/update PHPUnit tests
4. **Validasi** — Jalankan:
   ```bash
   php vendor/bin/phpunit --no-coverage
   php vendor/bin/phpcs --standard=PSR12 src/ tests/
   php -l <new_files>
   ```
5. **Dokumentasi** — Update MASTER_BLUEPRINT.md (tambah section), DEVELOPMENT_ROADMAP.md (centang item), API_CONTRACT jika perlu
6. **Commit & Push** — Commit dengan pesan deskriptif, push ke GitHub
7. **Update file ini** — Tambah HISTORY entry, update CURRENT TASK

### Aturan Penting
- Jangan hapus atau weaken tests yang sudah ada
- Jangan break API contract yang sudah published
- Selalu gunakan `declare(strict_types=1)`
- Maximum 120 chars per line (PSR-12)
- Setiap class di file terpisah
- Import di top of file
- Minimal changes — jangan over-engineer
- Jika butuh DB dan MySQL tidak running, gunakan MockPdo pattern di tests

---

## CURRENT TASK

```
TASK ID: CYCLE-003
TITLE: Valuation Engine — DCF, relative valuation, fair value
PRIORITY: High
FASE: 3 (Analysis Engines)

DESCRIPTION:
Tambah methods ke FundamentalService atau buat ValuationService baru:
- DCF model (NPV dari projected FCF)
- Relative valuation (P/E, P/BV peer comparison)
- Fair value calculation (weighted blend)
- Endpoints: POST /valuations, GET /valuations/{id}, GET /valuations/instrument/{instrumentId}
- Gunakan BaseService, ApiException, declare(strict_types=1)

FILES TO CREATE:
- src/Valuation/ValuationServiceInterface.php
- src/Valuation/ValuationService.php
- src/Valuation/ValuationRoutes.php
- database/migrations/015_valuation_schema.sql
- tests/Valuation/ValuationServiceTest.php

VALIDATION:
- php vendor/bin/phpunit --no-coverage (all pass)
- php vendor/bin/phpcs --standard=PSR12 src/ tests/ (0 violations on new files)
- php -l <new_files> (syntax OK)
```

---

## TASK QUEUE (After Current Task)

```
CYCLE-004: Alert System — price, signal, risk alerts
  - Buat AlertService di src/Alert/
  - Schema: tabel alert, alert_rule, alert_notification
  - Price alerts (above/below threshold)
  - Signal alerts (new signal created)
  - Risk alerts (risk event triggered)
  - Endpoints: CRUD alerts, GET notifications, POST acknowledge
  - FASE: 6

CYCLE-005: Frontend & UI — Dashboard
  - Pilih stack: React + TailwindCSS + shadcn/ui
  - Dashboard: market overview, portfolio summary, recent signals
  - Login page (JWT auth)
  - API client service (axios/fetch wrapper)
  - FASE: 11

CYCLE-006: Broker API Real Integration
  - Pilih broker (Mirae Asset / BNI Sekuritas / lainnya)
  - Implement BrokerAdapterInterface
  - API authentication, account balance, portfolio sync
  - Real-time price feed
  - FASE: 7

CYCLE-007: Backtesting Framework
  - Historical data replay engine
  - Strategy testing interface
  - Performance metrics (Sharpe, Sortino, Max DD)
  - FASE: 10

CYCLE-008: Paper Trading
  - Simulated execution engine
  - Virtual portfolio
  - Signal validation workflow
  - FASE: 10

CYCLE-009: AI Engine — NLP, pattern recognition
  - News NLP pipeline (sentiment, entity, event)
  - Pattern recognition (chart patterns)
  - Anomaly detection
  - FASE: 9

CYCLE-010: Production Deployment
  - Docker/Kubernetes deployment
  - Load testing
  - Security audit
  - Monitoring & logging (Prometheus + Grafana)
  - FASE: 12
```

---

## HISTORY

```
[2026-07-24] Phase 1 — IdentityService + ConfigService (commit 4793af9)
  - 8+16 endpoints, 56 MySQL tables, 8 tests/13 assertions

[2026-07-24] Phase 2 — MarketMasterService + FundamentalService (commit f62f8f5)
  - 28+17 endpoints, 5 tests/5 assertions + 6 tests/6 assertions

[2026-07-24] Phase 3 — AnalyticsService (commit 9da8f67)
  - 31 endpoints, 6 tests/6 assertions

[2026-07-24] Phase 4 — RiskService + PortfolioService (commit 39eb472)
  - 13+16 endpoints, 4 tests/4 assertions + 5 tests/5 assertions

[2026-07-24] Phase 5 — TradingService + SettlementService (commit 008cfb8)
  - 20+7 endpoints, 5 tests/5 assertions + 2 tests/2 assertions

[2026-07-24] Governance Fix + Integration Tests (commit 0617a7d)
  - 6 methods + 5 routes added, 12 integration tests with MockPdo
  - Blueprint 533 sections, 60 tests/118 assertions

[2026-07-24] Cross-Service Wiring + Health/Metrics (commit 0267c90)
  - ServiceHub: pre-trade risk, auto-settlement, audit logging
  - /metrics endpoint, roadmap updated
  - Blueprint 536 sections, 60 tests/118 assertions

[2026-07-24] CYCLE-001: Docker & docker-compose setup
  - docker-compose.yml: MySQL 8.x, Redis 7.x, PHP 8.3 Apache, PhpMyAdmin
  - Dockerfile: PHP 8.3 + pdo_mysql, redis, mbstring, xml, curl, zip, opcache
  - .dockerignore, .env.docker, docker/php-init.sh (auto-migrate on startup)
  - 60 tests/118 assertions still pass, PSR-12 clean
  - Docker not installed on dev machine — compose file ready, untested live

[2026-07-24] CYCLE-002: Data Ingestion — IDX/BEI market data feeder
  - DataIngestionService: ingestOhlcv, getOhlcv, listOhlcv, getOhlcvHistory, getIngestionStatus
  - 5 endpoints: POST/GET /ingestion/ohlcv, GET /ingestion/ohlcv/{id}, GET /ingestion/ohlcv/instrument/{instrumentId}, GET /ingestion/status
  - Schema: data_ingestion.ohlcv_daily + ingestion_log (2 tables, 58 total)
  - 7 tests/12 assertions, 67 tests/130 assertions total
  - Service #11 registered, migrate.sh updated for 011-014

[NEXT] CYCLE-003: Valuation Engine — DCF, relative valuation, fair value
  - Status: NOT STARTED
```

---

## QUICK REFERENCE — Commands

```bash
# Run tests
php vendor/bin/phpunit --no-coverage

# PSR-12 check
php vendor/bin/phpcs --standard=PSR12 src/ tests/

# PHP syntax check
php -l <file>

# Git commit & push
git add -A && git commit -m "<message>" && git push origin main

# Start dev server (if not using Docker)
php -S localhost:8000 -t public/

# Check git status
git status --short

# Check git log
git log --oneline -5
```

---

## QUICK REFERENCE — Architecture Pattern

```
src/
  Core/
    BaseService.php          — abstract, PDO, uuid(), now(), paginate()
    Application.php          — singleton, config, service registry
    ServiceHub.php           — cross-service wiring (risk, settlement, audit)
    Http/
      Router.php             — route registration, dispatch, middleware
      Request.php            — HTTP request wrapper
      Response.php           — HTTP response (ok, created, error, noContent)
    Middleware/
      AuthMiddleware.php     — bearer JWT verification
    Exceptions/
      ApiException.php       — structured API error
  <Context>/
    <Context>ServiceInterface.php   — interface
    <Context>Service.php            — implementation (extends BaseService)
    <Context>Routes.php             — route registration + handlers
tests/
  <Context>/
    <Context>ServiceTest.php        — unit tests
  Integration/
    IntegrationTest.php             — cross-service integration tests
    MockPdo.php                     — in-memory DB mock
    MockPdoStatement.php            — mock PDO statement
```

---

> File ini diupdate setiap development cycle selesai.
> Baca dari atas ke bawah setiap memulai sesi baru.
> Current task = tugas yang harus dikerjakan sekarang.
> Task queue = tugas berikutnya setelah current task selesai.
