---

## 8. Trading Context — Endpoints

### Brokers

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/brokers` | List brokers | Bearer |
| POST | `/brokers` | Register broker | Bearer |
| GET | `/brokers/{id}` | Get broker | Bearer |
| PUT | `/brokers/{id}` | Update broker | Bearer |

### Decisions

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/decisions` | List decisions | Bearer |
| POST | `/decisions` | Create decision | Bearer |
| GET | `/decisions/{id}` | Get decision detail | Bearer |
| POST | `/decisions/{id}/approve` | Approve decision (policy) | Bearer |
| POST | `/decisions/{id}/reject` | Reject decision (policy) | Bearer |
| POST | `/decisions/{id}/override` | Human override | Bearer |

#### POST /decisions

```json
// Request
{
  "portfolio_id": "0192a3b4-...",
  "instrument_id": "0192a3b5-...",
  "action": "BUY",
  "intended_quantity": 1000,
  "intended_price": 5000.00,
  "reason": "Strong momentum signal, undervalued vs peers",
  "confidence": 0.85,
  "recommendation_id": "0192a3b6-..."
}

// Response 201
{
  "data": {
    "decision_id": "0192a3b7-...",
    "portfolio_id": "0192a3b4-...",
    "instrument_id": "0192a3b5-...",
    "action": "BUY",
    "intended_quantity": 1000,
    "intended_price": 5000.00,
    "policy_result": "APPROVED",
    "policy_checks": {
      "max_single_position": "PASS",
      "max_sector_exposure": "PASS",
      "trading_hours": "PASS"
    },
    "status": "APPROVED",
    "created_at": "2026-07-24T06:00:00.000000Z"
  }
}
```

#### POST /decisions/{id}/override

```json
// Request
{
  "override_reason": "Manual override: market dip opportunity"
}

// Response 200
{
  "data": {
    "decision_id": "0192a3b7-...",
    "human_override": true,
    "status": "APPROVED",
    "updated_at": "2026-07-24T06:05:00.000000Z"
  }
}
```

### Order Intents

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/order-intents` | List order intents | Bearer |
| POST | `/order-intents` | Create order intent from decision | Bearer |
| GET | `/order-intents/{id}` | Get order intent | Bearer |
| POST | `/order-intents/{id}/approve` | Approve intent | Bearer |
| POST | `/order-intents/{id}/reject` | Reject intent | Bearer |

#### POST /order-intents

```json
// Request
{
  "decision_id": "0192a3b7-...",
  "side": "BUY",
  "target_quantity": 1000,
  "target_price": 5000.00,
  "strategy": "LIMIT",
  "reason": "Execute approved decision"
}

// Response 201
{
  "data": {
    "order_intent_id": "0192a3b9-...",
    "decision_id": "0192a3b7-...",
    "side": "BUY",
    "target_quantity": 1000,
    "target_price": 5000.00,
    "status": "DRAFT",
    "created_at": "2026-07-24T06:10:00.000000Z"
  }
}
```

### Orders

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/orders` | List orders | Bearer |
| POST | `/orders` | Submit order from intent | Bearer |
| GET | `/orders/{id}` | Get order with executions | Bearer |
| POST | `/orders/{id}/cancel` | Cancel pending order | Bearer |
| GET | `/orders/{id}/executions` | List executions for order | Bearer |

```
Query Parameters (list):
  ?filter[portfolio_id]={id}
  ?filter[status]=SUBMITTED
  ?filter[instrument_id]={id}
  ?filter[from_date]=2026-07-01
  ?filter[to_date]=2026-07-24
  ?sort=-created_at
```

#### POST /orders

```json
// Request
{
  "order_intent_id": "0192a3b9-...",
  "order_type": "LIMIT",
  "quantity": 1000,
  "limit_price": 5000.00,
  "time_in_force": "DAY"
}

// Response 201
{
  "data": {
    "order_id": "0192a3ba-...",
    "order_ref": "ORD-20260724-00001",
    "order_intent_id": "0192a3b9-...",
    "portfolio_id": "0192a3b4-...",
    "instrument_id": "0192a3b5-...",
    "side": "BUY",
    "order_type": "LIMIT",
    "quantity": 1000,
    "filled_quantity": 0,
    "remaining_quantity": 1000,
    "limit_price": 5000.00,
    "time_in_force": "DAY",
    "status": "PENDING",
    "created_at": "2026-07-24T06:15:00.000000Z"
  }
}
```

#### GET /orders/{id}

```json
// Response 200
{
  "data": {
    "order_id": "0192a3ba-...",
    "order_ref": "ORD-20260724-00001",
    "side": "BUY",
    "order_type": "LIMIT",
    "quantity": 1000,
    "filled_quantity": 1000,
    "remaining_quantity": 0,
    "limit_price": 5000.00,
    "status": "FILLED",
    "submitted_at": "2026-07-24T06:15:05.000000Z",
    "filled_at": "2026-07-24T06:15:10.000000Z",
    "executions": [
      {
        "execution_id": "0192a3bb-...",
        "execution_ref": "EXE-20260724-00001",
        "fill_quantity": 1000,
        "fill_price": 4975.00,
        "fill_value": 4975000,
        "commission": 4975,
        "fees": 500,
        "net_value": 4970475,
        "currency": "IDR",
        "executed_at": "2026-07-24T06:15:10.000000Z",
        "status": "PENDING_SETTLEMENT"
      }
    ]
  }
}
```

### Executions

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/executions` | List executions | Bearer |
| GET | `/executions/{id}` | Get execution detail | Bearer |

---

## 9. Settlement Context — Endpoints

### Settlements

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/settlements` | List settlements | Bearer |
| GET | `/settlements/{id}` | Get settlement detail | Bearer |
| GET | `/portfolios/{id}/settlements` | List portfolio settlements | Bearer |

```
Query Parameters (list):
  ?filter[portfolio_id]={id}
  ?filter[status]=PENDING
  ?filter[settlement_date_from]=2026-07-24
  ?filter[settlement_date_to]=2026-07-26
  ?sort=settlement_date
```

### Reconciliation

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/reconciliations` | List reconciliation records | Bearer |
| GET | `/reconciliations/{id}` | Get reconciliation detail | Bearer |
| POST | `/reconciliations/{id}/resolve` | Resolve mismatch | Bearer |
| GET | `/portfolios/{id}/reconciliations` | List portfolio reconciliations | Bearer |

#### POST /reconciliations/{id}/resolve

```json
// Request
{
  "resolution": "Adjusted internal record to match broker statement. Discrepancy was due to partial fill not reflected in internal system."
}

// Response 200
{
  "data": {
    "reconciliation_id": "0192a3b4-...",
    "status": "RESOLVED",
    "resolved_at": "2026-07-24T08:00:00.000000Z"
  }
}
```
