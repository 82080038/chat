# Capital Market Platform

> Global & Indonesia Capital Market Intelligence, Decision, Risk & Execution Platform
>
> Personal single-owner application. It is not multi-tenant and has no user, role, or permission management.

## Quick Start

```bash
# 1. Install dependencies
composer install

# 2. Copy environment file
cp .env.example .env

# 3. Run database migrations
./database/migrate.sh up

# 4. Seed default data
./database/migrate.sh seed

# 5. Start development server
php -S localhost:8000 -t public/
```

## Architecture

- **Access model**: One owner account with password + JWT
- **Backend**: PHP 8.2+ (Modular Monolith)
- **Database**: MySQL 8+ (transactional) + PostgreSQL/TimescaleDB (time series)
- **Cache**: Redis
- **Messaging**: RabbitMQ
- **Storage**: S3-compatible Object Storage

## Project Structure

```
├── composer.json
├── .env.example
├── public/
│   └── index.php              # Application entry point
├── src/
│   ├── Core/
│   │   ├── Application.php
│   │   ├── BaseService.php
│   │   ├── Database/
│   │   │   └── MySqlConnection.php
│   │   ├── Http/
│   │   │   ├── Request.php
│   │   │   ├── Response.php
│   │   │   ├── Router.php
│   │   │   └── RequestParamsTrait.php
│   │   └── Middleware/
│   │       └── AuthMiddleware.php
│   └── Governance/
│       ├── GovernanceServiceInterface.php
│       ├── GovernanceService.php
│       └── GovernanceRoutes.php
├── database/
│   ├── migrate.sh
│   └── migrations/
│       ├── 001_create_database_and_schemas.sql
│       ├── 002_identity_schema.sql
│       ├── ... (010_config_schema.sql)
│       ├── 011_postgresql_timescaledb_schema.sql
│       ├── 012_seed_data.sql
│       └── 013_drop_all.sql
├── api/
│   ├── API_CONTRACT.md
│   ├── API_CONTRACT_BATCH2.md
│   ├── API_CONTRACT_BATCH3.md
│   ├── API_CONTRACT_BATCH4.md
│   ├── API_CONTRACT_BATCH5.md
│   └── SERVICE_BOUNDARY_SPEC.md
├── tests/
│   └── Governance/
│       └── GovernanceServiceTest.php
├── MASTER_BLUEPRINT.md         # Complete blueprint (493 sections)
└── DEVELOPMENT_ROADMAP.md
```

## API Endpoints

Base URL: `http://localhost:8000/api/v1`

See `api/API_CONTRACT.md` for the owner-only specification (138 endpoints across 10 contexts).

## Blueprint

See `MASTER_BLUEPRINT.md` for the complete system blueprint including:
- System Constitution
- Architecture Contradiction Audit
- Technology Decision Record
- Domain Model & Bounded Contexts
- Canonical Data Model
- Canonical Data Contract (15 items)
- Logical ERD corrected for single-owner operation (10 contexts, 55 MySQL tables)
- Physical SQL Schema (MySQL + PostgreSQL)
- API Contract (138 owner-authenticated endpoints)
- Service Boundary Specification