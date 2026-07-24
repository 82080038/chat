---

## 4. Fundamental Context — Endpoints

### Financial Statements

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/financial-statements` | List financial statements | Bearer |
| POST | `/financial-statements` | Create financial statement | Admin |
| GET | `/financial-statements/{id}` | Get statement with lines | Bearer |
| GET | `/financial-statements/{id}/lines` | Get statement line items | Bearer |
| GET | `/financial-statements/{id}/revisions` | Get revision history | Bearer |
| POST | `/financial-statements/{id}/revise` | Create revised version | Admin |

#### GET /financial-statements

```
Query Parameters:
  ?filter[issuer_id]={id}
  ?filter[statement_type]=INCOME
  ?filter[fiscal_year]=2025
  ?filter[status]=PUBLISHED
  ?include=lines,issuer
  ?sort=-fiscal_year,-fiscal_quarter
```

```json
// Response 200
{
  "data": [
    {
      "financial_statement_id": "0192a3b4-...",
      "issuer_id": "0192a3b5-...",
      "statement_type": "INCOME",
      "fiscal_period_type": "Q4",
      "fiscal_year": 2025,
      "fiscal_quarter": 4,
      "period_start": "2025-10-01",
      "period_end": "2025-12-31",
      "publication_date": "2026-02-28",
      "available_time": "2026-02-28T10:00:00.000000Z",
      "currency": "IDR",
      "version": 1,
      "status": "PUBLISHED",
      "lines": [
        {
          "line_id": "0192a3c0-...",
          "line_item_code": "REVENUE",
          "line_item_name": "Total Revenue",
          "value": 150000000000000,
          "unit": "IDR",
          "order_position": 1
        }
      ]
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 120, "total_pages": 3 }
}
```

### Financial Metrics

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/financial-metrics` | List metrics | Bearer |
| GET | `/financial-metrics/{id}` | Get metric | Bearer |
| GET | `/issuers/{id}/metrics` | Get issuer metrics (latest) | Bearer |

```
Query Parameters:
  ?filter[issuer_id]={id}
  ?filter[metric_type]=PE_RATIO
  ?filter[fiscal_year]=2025
  ?sort=-fiscal_year,-fiscal_quarter
```

### Economic Indicators

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/economic-indicators` | List indicators | Bearer |
| GET | `/economic-indicators/{id}` | Get indicator | Bearer |

```
Query Parameters:
  ?filter[country]=ID
  ?filter[indicator_type]=CPI
  ?filter[period]=2026-01-01
  ?sort=-period,-revision_number
```

### News

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/news` | List news items | Bearer |
| GET | `/news/{id}` | Get news item | Bearer |
| GET | `/instruments/{id}/news` | Get news for instrument | Bearer |

```
Query Parameters:
  ?filter[instrument_id]={id}
  ?filter[sentiment]=POSITIVE
  ?search=earnings+beat
  ?sort=-published_at
  ?page=1&per_page=20
```

---

## 5. Analytics Context — Endpoints

### Features

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/features` | List feature definitions | Bearer |
| POST | `/features` | Create feature definition | Admin |
| GET | `/features/{id}` | Get feature definition | Bearer |
| GET | `/features/{id}/values` | Get feature values (time series) | Bearer |
| POST | `/features/{id}/values` | Ingest feature values | Admin |

#### GET /features/{id}/values

```
Query Parameters:
  ?filter[instrument_id]={id}
  ?filter[from]=2026-01-01
  ?filter[to]=2026-07-24
  ?sort=timestamp
```

### Signals

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/signals` | List signals | Bearer |
| GET | `/signals/{id}` | Get signal | Bearer |
| GET | `/instruments/{id}/signals` | Get signals for instrument | Bearer |

```
Query Parameters:
  ?filter[instrument_id]={id}
  ?filter[signal_type]=MOMENTUM
  ?filter[direction]=BULLISH
  ?filter[status]=ACTIVE
  ?sort=-created_at
```

```json
// Response 200
{
  "data": [
    {
      "signal_id": "0192a3b4-...",
      "instrument_id": "0192a3b5-...",
      "signal_type": "MOMENTUM",
      "direction": "BULLISH",
      "strength": 0.82,
      "timeframe": "1D",
      "model_version": "v2.1.0",
      "created_at": "2026-07-24T06:00:00.000000Z",
      "valid_from": "2026-07-24T06:00:00.000000Z",
      "valid_until": null,
      "status": "ACTIVE"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 35, "total_pages": 1 }
}
```

### Forecasts

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/forecasts` | List forecasts | Bearer |
| GET | `/forecasts/{id}` | Get forecast | Bearer |
| GET | `/instruments/{id}/forecasts` | Get forecasts for instrument | Bearer |

```
Query Parameters:
  ?filter[instrument_id]={id}
  ?filter[target_variable]=PRICE_1D
  ?filter[model_version]=v2.1.0
  ?sort=-created_at
```

### Recommendations

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/recommendations` | List recommendations | Bearer |
| GET | `/recommendations/{id}` | Get recommendation with signals & forecasts | Bearer |
| GET | `/instruments/{id}/recommendations` | Get recommendations for instrument | Bearer |

```
Query Parameters:
  ?filter[instrument_id]={id}
  ?filter[action]=BUY
  ?filter[status]=ACTIVE
  ?filter[min_confidence]=0.7
  ?include=signals,forecasts
  ?sort=-created_at
```

```json
// Response 200
{
  "data": [
    {
      "recommendation_id": "0192a3b4-...",
      "instrument_id": "0192a3b5-...",
      "action": "BUY",
      "thesis": "Strong momentum signal combined with undervaluation...",
      "confidence": 0.85,
      "confidence_level": "HIGH",
      "horizon": "1W",
      "model_version": "v2.1.0",
      "created_at": "2026-07-24T06:00:00.000000Z",
      "valid_until": "2026-07-31T06:00:00.000000Z",
      "status": "ACTIVE",
      "signals": [...],
      "forecasts": [...]
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 12, "total_pages": 1 }
}
```

### Scores

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/scores` | List scores | Bearer |
| GET | `/scores/{id}` | Get score | Bearer |
| GET | `/instruments/{id}/scores` | Get scores for instrument | Bearer |

```
Query Parameters:
  ?filter[instrument_id]={id}
  ?filter[score_type]=QUALITY
  ?sort=-created_at
```

### Model Registry

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/models` | List model registry entries | Bearer |
| POST | `/models` | Register new model | Admin |
| GET | `/models/{id}` | Get model details | Bearer |
| PUT | `/models/{id}` | Update model (e.g., deploy/retire) | Admin |

### Backtests

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/backtests` | List backtest runs | Bearer |
| POST | `/backtests` | Start backtest run | Bearer |
| GET | `/backtests/{id}` | Get backtest results | Bearer |
| GET | `/backtests/{id}/status` | Poll backtest status | Bearer |

#### POST /backtests

```json
// Request
{
  "strategy_name": "momentum_mean_reversion",
  "strategy_version": "v1.2.0",
  "model_id": "0192a3b4-...",
  "portfolio_id": "0192a3b5-...",
  "start_date": "2024-01-01",
  "end_date": "2026-06-30",
  "initial_capital": 100000000,
  "parameters": {
    "lookback_days": 20,
    "threshold": 0.05
  }
}

// Response 202
{
  "data": {
    "backtest_id": "0192a3b6-...",
    "status": "PENDING",
    "created_at": "2026-07-24T06:00:00.000000Z"
  }
}
```
