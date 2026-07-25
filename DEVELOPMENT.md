# Development Guide

This guide helps new developers set up and work on the Capital Market Platform.

## Quick Setup (5 Steps)

### Step 1: Clone & Install

```bash
git clone https://github.com/82080038/chat.git capital-market-platform
cd capital-market-platform

# PHP dependencies
composer install --ignore-platform-req=ext-sockets

# Frontend dependencies
cd frontend && npm install && cd ..

# Playwright (E2E tests)
npm install
npx playwright install chromium
```

### Step 2: Configure Environment

```bash
cp .env.example .env
```

Edit `.env` and set:
- `DB_PASS` — your MySQL password
- `JWT_SECRET` — generate with: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`
- `APP_ENCRYPTION_KEY` — generate with: `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`

### Step 3: Database Setup

```bash
# Run migrations (creates database, schemas, tables)
# On Windows (XAMPP): use bash via Git Bash or WSL
./database/migrate.sh up

# Seed base data (exchanges, system parameters)
./database/migrate.sh seed

# Seed simulation data (instruments, brokers, signals, alerts, portfolios)
./database/migrate.sh seed-sim
```

**Windows (XAMPP) without bash:**
```powershell
# Run each migration file manually
Get-Content database/migrations/001_create_database_and_schemas.sql | C:\xampp\mysql\bin\mysql.exe -u root -proot
Get-Content database/migrations/002_identity_schema.sql | C:\xampp\mysql\bin\mysql.exe -u root -proot platform
# ... continue for each migration (skip 011, 013, and seed files)
# Then seed:
Get-Content database/migrations/012_seed_data.sql | C:\xampp\mysql\bin\mysql.exe -u root -proot platform
Get-Content database/migrations/021_seed_simulation_data.sql | C:\xampp\mysql\bin\mysql.exe -u root -proot platform
```

### Step 4: Create Owner Account

```bash
curl -X POST http://localhost:8080/auth/setup \
  -H "Content-Type: application/json" \
  -d '{"email":"owner@platform.local","password":"Test@1234567","legal_name":"Owner"}'
```

### Step 5: Build & Run

```bash
# Build frontend
cd frontend && npm run build && cd ..

# Start PHP server
php -S localhost:8080 -t public public/router.php
```

Open `http://localhost:8080/dashboard/` in your browser.

## Windows (XAMPP) Setup

If using XAMPP on Windows, use full paths:

```powershell
# PHP
C:\xampp\php\php.exe -S 0.0.0.0:8080 -t public public\router.php

# MySQL
C:\xampp\mysql\bin\mysql.exe -u root -proot platform

# Composer
C:\xampp\php\php.exe composer.phar install --ignore-platform-req=ext-sockets

# PHPUnit
C:\xampp\php\php.exe vendor\bin\phpunit
```

## Running Tests

### PHPUnit (Backend)

```bash
vendor/bin/phpunit
# Expected: 159 tests, 305 assertions, all OK
```

### Playwright (E2E)

```bash
# All tests (headed mode)
npx playwright test --headed

# Specific test file
npx playwright test tests/e2e-simulation.spec.ts --headed

# Headless mode (CI)
npx playwright test
```

**Test files:**
| File | Tests | Description |
|------|-------|-------------|
| `e2e-simulation.spec.ts` | 7 | Login, dashboard, navigation, API monitoring, logout |
| `playwright_simulation.spec.ts` | 1 | Full API simulation (24 endpoints) |
| `month_simulation.spec.ts` | 1 | 22-day trading simulation (~2 min) |

## Architecture Overview

```
Request → public/index.php → Router → Middleware (auth, rate-limit) → Route Handler → Service → MySQL
                                                                  ↓
                                                              Response (JSON { data: ... })
```

- **No framework**: Pure PHP with PSR-4 autoloading
- **18 services** registered in `public/index.php`
- **Each service** has: `Service.php` (logic), `Routes.php` (endpoints), `ServiceInterface.php` (contract)
- **Cross-service wiring** via `ServiceHub` singleton (pre-trade risk checks, auto-settlement, audit)
- **Event bus** (RabbitMQ) for async events — fail-safe (logs if unavailable)
- **Redis** for caching and rate limiting — fail-open (continues without cache)

## Adding a New Service

1. Create `src/YourService/YourService.php` (extends `BaseService`)
2. Create `src/YourService/YourServiceInterface.php`
3. Create `src/YourService/YourRoutes.php` (static `register(Router)` method)
4. Register in `public/index.php`:
   ```php
   $app->registerService('your_service', new YourService());
   YourRoutes::register($router);
   ```
5. Create migration: `database/migrations/0XX_your_schema.sql`
6. Write tests: `tests/YourService/YourServiceTest.php`

## Frontend Development

```bash
cd frontend
npm run dev    # Dev server with hot reload (port 5173, proxies API to :8080)
npm run build  # Production build to ../public/dashboard/
```

- **Base path**: `/dashboard/` (configured in `vite.config.ts`)
- **API client**: `src/lib/api.ts` — unwraps `{ data: ... }` envelope, sends `credentials: "include"`
- **Auth**: `src/lib/auth.tsx` — JWT stored in localStorage + HttpOnly cookie (set by backend)
- **Routing**: `src/main.tsx` — React Router with `basename="/dashboard"`

## Database Migrations

Migrations are numbered `001` through `028`:
- `001-010`: Schema creation (identity, market_master, fundamental, analytics, risk, portfolio, trading, governance, config)
- `011`: PostgreSQL/TimescaleDB (skipped on MySQL)
- `012`: Base seed data
- `013`: Drop all (for reset)
- `014-025`: Additional schemas (OHLCV, valuation, alert, broker_adapter, backtest, paper_trading, AI engine, kill switch, order modify, microstructure)
- `021`: Simulation seed data
- `026-028`: Additional seed data (sample, full simulation, month simulation)

**Rules:**
- Never reuse a migration number
- Schema migrations and seed migrations are separate files
- `migrate.sh` skips seed files during `up` (use `seed` or `seed-sim` commands)

## Security Features

| Feature | Implementation |
|---------|---------------|
| JWT Auth | HS256, revocable sessions, HttpOnly cookies + Bearer header fallback |
| Rate Limiting | 60 req/min API, 5 req/min auth (Redis-based, fail-open) |
| CORS | Configurable via `CORS_ALLOWED_ORIGINS` env var |
| HTTPS | Auto-redirect in production (skipped when `APP_ENV=development`) |
| Security Headers | `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection` |
| Kill Switch | Emergency account lock via `/auth/kill-switch` |
| Audit Log | Immutable audit trail in `governance.audit_log` |

## Python Analytics Bridge (Optional)

The Python bridge provides ML/AI computations. To enable:

1. Install Python 3.8+ and dependencies:
   ```bash
   pip install numpy pandas
   ```

2. Set in `.env`:
   ```
   PYTHON_ENABLED=true
   PYTHON_PATH=python3  # or full path on Windows
   ```

3. Available functions: `calculate_indicators`, `generate_signals`, `generate_forecast`, `analyze_sentiment`, `run_backtest`

When disabled or unavailable, the PHP backend uses fallback calculations.
