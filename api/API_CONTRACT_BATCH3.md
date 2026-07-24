---

## 6. Portfolio Context — Endpoints

### Portfolios

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/portfolios` | List portfolios (tenant-scoped) | Bearer |
| POST | `/portfolios` | Create portfolio | Bearer |
| GET | `/portfolios/{id}` | Get portfolio with summary | Bearer |
| PUT | `/portfolios/{id}` | Update portfolio | Bearer |
| DELETE | `/portfolios/{id}` | Archive portfolio (soft) | Bearer |
| GET | `/portfolios/{id}/summary` | Get portfolio summary (NAV, P&L) | Bearer |
| GET | `/portfolios/{id}/positions` | List current positions | Bearer |
| GET | `/portfolios/{id}/positions/history` | Position snapshots over time | Bearer |
| GET | `/portfolios/{id}/cash-balances` | List cash balances | Bearer |
| GET | `/portfolios/{id}/cash-transactions` | List cash transactions | Bearer |
| POST | `/portfolios/{id}/cash-transactions` | Record manual cash txn | Bearer |
| GET | `/portfolios/{id}/targets` | List portfolio targets | Bearer |
| POST | `/portfolios/{id}/targets` | Set portfolio target | Bearer |
| PUT | `/portfolios/{id}/targets/{targetId}` | Update target | Bearer |
| DELETE | `/portfolios/{id}/targets/{targetId}` | Remove target | Bearer |
| GET | `/portfolios/{id}/accounts` | List portfolio accounts | Bearer |
| POST | `/portfolios/{id}/accounts` | Link broker account | Bearer |

#### POST /portfolios

```json
// Request
{
  "name": "My IDR Portfolio",
  "description": "Long-term Indonesian equity portfolio",
  "base_currency": "IDR",
  "portfolio_type": "PAPER",
  "inception_date": "2026-07-24",
  "benchmark_id": "0192a3b4-...",
  "risk_profile_id": "0192a3b5-..."
}

// Response 201
{
  "data": {
    "portfolio_id": "0192a3b6-...",
    "name": "My IDR Portfolio",
    "base_currency": "IDR",
    "portfolio_type": "PAPER",
    "status": "ACTIVE",
    "inception_date": "2026-07-24",
    "created_at": "2026-07-24T06:00:00.000000Z"
  }
}
```

#### GET /portfolios/{id}/summary

```json
// Response 200
{
  "data": {
    "portfolio_id": "0192a3b6-...",
    "nav": 105000000,
    "currency": "IDR",
    "total_pnl": 5000000,
    "total_pnl_pct": 0.05,
    "realized_pnl": 2000000,
    "unrealized_pnl": 3000000,
    "cash_balance": 10000000,
    "positions_value": 95000000,
    "position_count": 8,
    "as_of": "2026-07-24T06:00:00.000000Z",
    "benchmark_return": 0.03,
    "alpha": 0.02,
    "beta": 1.15
  }
}
```

#### GET /portfolios/{id}/positions

```json
// Response 200
{
  "data": [
    {
      "position_id": "0192a3c0-...",
      "instrument_id": "0192a3c1-...",
      "ticker": "BBRI",
      "quantity": 10000,
      "average_cost": 4750.00,
      "market_price": 5000.00,
      "market_value": 50000000,
      "unrealized_pnl": 2500000,
      "unrealized_pnl_pct": 0.0526,
      "weight": 0.4762,
      "position_type": "LONG",
      "status": "OPEN",
      "as_of": "2026-07-24T06:00:00.000000Z"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 8, "total_pages": 1 }
}
```

---

## 7. Risk Context — Endpoints

### Risk Profiles

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/risk-profiles` | List risk profiles | Bearer |
| POST | `/risk-profiles` | Create risk profile | Bearer |
| GET | `/risk-profiles/{id}` | Get risk profile | Bearer |
| PUT | `/risk-profiles/{id}` | Update risk profile | Bearer |

### Risk Limits

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/portfolios/{id}/risk-limits` | List portfolio risk limits | Bearer |
| POST | `/portfolios/{id}/risk-limits` | Set risk limit | Bearer |
| PUT | `/risk-limits/{limitId}` | Update risk limit | Bearer |
| DELETE | `/risk-limits/{limitId}` | Remove risk limit | Bearer |

### Risk Assessments

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/portfolios/{id}/risk-assessments` | List risk assessments | Bearer |
| POST | `/portfolios/{id}/risk-assessments` | Trigger risk assessment | Bearer |
| GET | `/risk-assessments/{id}` | Get risk assessment detail | Bearer |

#### GET /portfolios/{id}/risk-assessments

```json
// Response 200
{
  "data": [
    {
      "risk_assessment_id": "0192a3b4-...",
      "portfolio_id": "0192a3b5-...",
      "assessment_type": "VAR",
      "var_95": 5000000,
      "var_99": 8000000,
      "expected_shortfall": 6500000,
      "portfolio_beta": 1.15,
      "sharpe_ratio": 1.42,
      "sortino_ratio": 1.85,
      "max_drawdown": -0.08,
      "volatility": 0.15,
      "concentration_index": 0.32,
      "currency": "IDR",
      "as_of": "2026-07-24T06:00:00.000000Z",
      "model_version": "v1.0.0"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 30, "total_pages": 1 }
}
```

### Risk Events

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/risk-events` | List risk events | Bearer |
| GET | `/portfolios/{id}/risk-events` | List portfolio risk events | Bearer |
| GET | `/risk-events/{id}` | Get risk event detail | Bearer |
| POST | `/risk-events/{id}/acknowledge` | Acknowledge risk event | Bearer |
| POST | `/risk-events/{id}/resolve` | Resolve risk event | Bearer |

#### POST /risk-events/{id}/resolve

```json
// Request
{
  "resolution": "Reduced position size to comply with limit"
}

// Response 200
{
  "data": {
    "risk_event_id": "0192a3b4-...",
    "status": "RESOLVED",
    "resolved_at": "2026-07-24T06:30:00.000000Z"
  }
}
```
