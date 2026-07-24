# API Reference — Capital Market Platform

**Version:** 1.0.0  
**Base URL:** `https://api.yourdomain.com`  
**Auth:** JWT Bearer token (except `/auth/*` and `/health`)

---

## Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/setup` | Public | One-time owner setup (email, password) |
| POST | `/auth/login` | Public | Login, returns access_token + refresh_token |
| POST | `/auth/refresh` | Bearer | Refresh access token |
| POST | `/auth/logout` | Bearer | Revoke current session |
| GET | `/auth/me` | Bearer | Get current owner profile |
| POST | `/auth/change-password` | Bearer | Change password (revokes all sessions) |

## Configuration

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/PUT | `/config/{key}` | Bearer | Get/set config value |
| GET | `/config/features` | Bearer | List feature flags |
| PUT | `/config/features/{key}` | Bearer | Toggle feature flag |
| GET | `/config/system-params` | Bearer | List readonly system params |

## Market Master

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/instruments` | Bearer | List/create instruments |
| GET/PUT/DELETE | `/instruments/{id}` | Bearer | CRUD single instrument |
| GET/POST | `/exchanges` | Bearer | List/create exchanges |
| GET/POST | `/indices` | Bearer | List/create indices |
| GET/POST | `/instrument-prices` | Bearer | Price history queries |

## Fundamental

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/fundamentals` | Bearer | List/create fundamental data |
| GET | `/fundamentals/{id}` | Bearer | Get fundamental record |
| GET | `/fundamentals/instrument/{id}` | Bearer | Get fundamentals by instrument |

## Analytics

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/features` | Bearer | Factor features |
| GET/POST | `/scores` | Bearer | Composite scores |
| GET/POST | `/recommendations` | Bearer | Buy/sell recommendations |
| GET/POST | `/screening` | Bearer | Screening queries |

## Risk

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/risk-assessments` | Bearer | Risk assessments |
| GET | `/risk-assessments/{id}` | Bearer | Get assessment |
| GET/POST | `/risk-limits` | Bearer | Risk limits |

## Portfolio

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/portfolios` | Bearer | List/create portfolios |
| GET/PUT/DELETE | `/portfolios/{id}` | Bearer | CRUD single portfolio |
| GET/POST | `/portfolios/{id}/positions` | Bearer | Portfolio positions |
| GET | `/portfolios/{id}/summary` | Bearer | Portfolio summary |

## Trading

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/brokers` | Bearer | List/create brokers |
| GET/POST | `/decisions` | Bearer | Trading decisions |
| GET/POST | `/orders` | Bearer | Order management |
| GET | `/orders/{id}` | Bearer | Get order |
| GET/POST | `/executions` | Bearer | Execution records |

## Settlement

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/settlements` | Bearer | Settlement records |
| GET | `/settlements/{id}` | Bearer | Get settlement |

## Governance

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/policies` | Bearer | Compliance policies |
| GET/POST | `/audit-logs` | Bearer | Audit log queries |

## Data Ingestion

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/ingestion/upload` | Bearer | Upload data file |
| GET | `/ingestion/sources` | Bearer | List data sources |
| GET | `/ingestion/jobs` | Bearer | List ingestion jobs |

## Valuation

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/valuations/dcf` | Bearer | DCF valuation |
| POST | `/valuations/relative` | Bearer | Relative valuation |
| GET | `/valuations/{id}` | Bearer | Get valuation |
| GET | `/valuations/instrument/{id}` | Bearer | Valuations by instrument |

## Alerts

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET/POST | `/alerts` | Bearer | List/create alerts |
| GET/PUT/DELETE | `/alerts/{id}` | Bearer | CRUD single alert |
| POST | `/alerts/{id}/trigger` | Bearer | Trigger alert |
| POST | `/alerts/check-price/{instrumentId}` | Bearer | Check price alert |
| GET | `/alerts/notifications` | Bearer | List notifications |
| POST | `/alerts/notifications/{id}/acknowledge` | Bearer | Acknowledge notification |

## Broker Adapter

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/brokers/{brokerId}/auth` | Bearer | Authenticate with broker |
| GET | `/brokers/{brokerId}/balance` | Bearer | Get account balance |
| GET | `/brokers/{brokerId}/holdings` | Bearer | Get portfolio holdings |
| GET | `/brokers/{brokerId}/price/{symbol}` | Bearer | Get real-time price |
| POST | `/brokers/{brokerId}/orders` | Bearer | Place order |
| DELETE | `/brokers/{brokerId}/orders/{orderId}` | Bearer | Cancel order |
| GET | `/brokers/{brokerId}/orders/{orderId}` | Bearer | Get order status |
| GET | `/brokers/api-logs` | Bearer | List API call logs |

## Backtesting

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/backtests` | Bearer | Create backtest run |
| GET | `/backtests` | Bearer | List backtest runs |
| GET | `/backtests/{id}` | Bearer | Get backtest run |
| POST | `/backtests/{id}/execute` | Bearer | Execute backtest with price data |
| GET | `/backtests/{id}/trades` | Bearer | Get backtest trades |
| GET | `/backtests/{id}/metrics` | Bearer | Get performance metrics |

## Paper Trading

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/paper/accounts` | Bearer | Create paper trading account |
| GET | `/paper/accounts/{accountId}` | Bearer | Get account |
| POST | `/paper/accounts/{accountId}/orders` | Bearer | Place simulated order |
| DELETE | `/paper/accounts/{accountId}/orders/{orderId}` | Bearer | Cancel order |
| GET | `/paper/accounts/{accountId}/orders` | Bearer | List orders |
| GET | `/paper/accounts/{accountId}/positions` | Bearer | Get positions |
| GET | `/paper/accounts/{accountId}/summary` | Bearer | Get account summary |
| POST | `/paper/validate-signal/{signalId}/{accountId}` | Bearer | Validate signal |

## AI Engine

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/ai/sentiment` | Bearer | Analyze text sentiment |
| POST | `/ai/pattern` | Bearer | Recognize chart pattern |
| POST | `/ai/anomaly` | Bearer | Detect anomalies |
| GET | `/ai/analyses` | Bearer | List analyses |
| GET | `/ai/analyses/{id}` | Bearer | Get analysis |
| POST | `/ai/model-runs` | Bearer | Create model run |
| PATCH | `/ai/model-runs/{id}` | Bearer | Update model run |

## Health & Infrastructure

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/health` | Public | Health check (services, timestamp) |
| GET | `/health/ready` | Public | Readiness probe |
| GET | `/health/live` | Public | Liveness probe |
| GET | `/metrics` | Public | Platform metrics |

---

## Pagination

All list endpoints support:
- `page` (default: 1)
- `per_page` (default: 50, max: 200)

Response includes `meta` object:
```json
{
  "data": [...],
  "meta": {
    "total": 100,
    "page": 1,
    "per_page": 50,
    "total_pages": 2
  }
}
```

## Error Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Field X is required",
    "details": { "X": "Required" }
  }
}
```

---

**Total: 17 services, 228 endpoints, 72 MySQL tables, 150 tests**
