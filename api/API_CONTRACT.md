# API Contract — REST Endpoint Specification

> Platform: Global & Indonesia Capital Market Intelligence, Decision, Risk & Execution Platform
> Version: 1.0.0
> Architecture: Modular Monolith (PHP 8+ backend)

---

## 1. API Conventions

### Base URL

```
Development:  http://localhost:8000/api/v1
Production:   https://api.platform.com/api/v1
```

### Authentication

```
Scheme:     Bearer Token (JWT)
Header:     Authorization: Bearer <jwt_token>
Subject:    Single owner account
Scope:      Every authenticated token has full owner access
```

### Request Format

```
Content-Type: application/json
Accept: application/json
```

### Pagination

```
Query Parameters:
  page      (int, default: 1)
  per_page  (int, default: 50, max: 200)
  sort      (string, e.g., "created_at:desc")
  search    (string, full-text search)

Response Envelope:
{
  "data": [...],
  "meta": {
    "page": 1,
    "per_page": 50,
    "total": 1250,
    "total_pages": 25
  }
}
```

### Error Response

```
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Human-readable message",
    "field_errors": {
      "email": ["Email is required"]
    },
    "correlation_id": "uuid-v7"
  }
}
```

### HTTP Status Codes

```
200 OK              — Successful GET, PUT, PATCH
201 Created         — Successful POST
204 No Content      — Successful DELETE
400 Bad Request     — Validation error
401 Unauthorized    — Missing/invalid token
403 Forbidden       — Owner account is locked or operation is disabled
404 Not Found       — Resource not found
409 Conflict        — Duplicate resource
422 Unprocessable   — Business rule violation
429 Too Many Requests — Rate limit exceeded
500 Internal Error  — Server error
503 Service Unavailable — Dependency down
```

### Rate Limiting

```
Headers:
  X-RateLimit-Limit:     60
  X-RateLimit-Remaining: 58
  X-RateLimit-Reset:     1721816400

Default: 60 requests/minute per owner session
Burst:   10 requests/second
```

### Common Query Parameters (List Endpoints)

```
?fields=id,name,status          — Field selection (sparse fieldset)
?include=portfolio,positions    — Eager loading relations
?filter[status]=ACTIVE          — Filtering
?sort=-created_at,name          — Sorting (- prefix = descending)
```

---

## 2. Single-Owner Identity Context — Endpoints

The application supports exactly one owner account. There are no tenants, additional users, roles, permissions, or API clients.

### Owner Authentication

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| POST | `/auth/setup` | Create the only owner account; available once | Public/First-run |
| POST | `/auth/login` | Owner login, returns JWT | Public |
| POST | `/auth/refresh` | Rotate refresh token and issue a new JWT | Refresh token |
| POST | `/auth/logout` | Revoke current owner token | Bearer |
| GET | `/auth/me` | Get owner profile | Bearer |
| POST | `/auth/change-password` | Change owner password | Bearer |
| GET | `/auth/preferences` | Get owner preferences | Bearer |
| PUT | `/auth/preferences` | Update owner preferences | Bearer |

#### POST /auth/setup

```json
// Request — rejected with 409 after the owner exists
{
  "email": "owner@example.com",
  "password": "SecurePass123!",
  "legal_name": "Owner Name",
  "display_name": "Owner",
  "phone": "+628123456789"
}

// Response 201
{
  "data": {
    "owner_id": "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b",
    "email": "owner@example.com",
    "display_name": "Owner",
    "created_at": "2026-07-24T06:00:00.000000Z"
  }
}
```

#### POST /auth/login

```json
// Request
{
  "email": "owner@example.com",
  "password": "SecurePass123!"
}

// Response 200
{
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "refresh_token": "ref_0192a3b4...",
    "owner": {
      "owner_id": "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b",
      "email": "owner@example.com",
      "display_name": "Owner"
    }
  }
}
```

---

## 3. Market Master Context — Endpoints

### Exchanges

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/exchanges` | List exchanges | Bearer |
| POST | `/exchanges` | Create exchange | Bearer |
| GET | `/exchanges/{id}` | Get exchange | Bearer |
| PUT | `/exchanges/{id}` | Update exchange | Bearer |
| GET | `/exchanges/{id}/calendar` | Get market calendar | Bearer |
| GET | `/exchanges/{id}/instruments` | List instruments on exchange | Bearer |

### Issuers

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/issuers` | List issuers | Bearer |
| POST | `/issuers` | Create issuer | Bearer |
| GET | `/issuers/{id}` | Get issuer | Bearer |
| PUT | `/issuers/{id}` | Update issuer | Bearer |
| GET | `/issuers/{id}/securities` | List issuer's securities | Bearer |
| GET | `/issuers/{id}/financial-statements` | List issuer's financials | Bearer |

### Securities

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/securities` | List securities | Bearer |
| GET | `/securities/{id}` | Get security | Bearer |

### Instruments

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/instruments` | List instruments (filterable) | Bearer |
| POST | `/instruments` | Create instrument | Bearer |
| GET | `/instruments/{id}` | Get instrument with listings | Bearer |
| PUT | `/instruments/{id}` | Update instrument | Bearer |
| GET | `/instruments/{id}/listings` | Get instrument listings | Bearer |
| GET | `/instruments/{id}/corporate-actions` | Get corporate actions | Bearer |
| GET | `/instruments/{id}/ohlcv` | Get OHLCV (proxies to TimescaleDB) | Bearer |

#### GET /instruments

```
Query Parameters:
  ?filter[asset_class]=EQUITY
  ?filter[status]=ACTIVE
  ?filter[exchange]=XIDX
  ?search=BBRI
  ?include=latest_listing,issuer
  ?fields=id,asset_class,instrument_type,status
  ?sort=instrument_type
  ?page=1&per_page=50
```

```json
// Response 200
{
  "data": [
    {
      "instrument_id": "0192a3b4-...",
      "asset_class": "EQUITY",
      "instrument_type": "COMMON_STOCK",
      "currency": "IDR",
      "status": "ACTIVE",
      "latest_listing": {
        "listing_id": "0192a3b5-...",
        "exchange": { "mic_code": "XIDX", "name": "Indonesia Stock Exchange" },
        "ticker": "BBRI",
        "isin": "ID1000103801"
      },
      "issuer": {
        "issuer_id": "0192a3b6-...",
        "legal_name": "PT Bank Rakyat Indonesia Tbk",
        "short_name": "BRI"
      }
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 850, "total_pages": 17 }
}
```

### Listings

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/listings` | List listings (filterable) | Bearer |
| POST | `/listings` | Create listing | Bearer |
| GET | `/listings/{id}` | Get listing | Bearer |
| GET | `/listings/by-ticker/{exchange}/{ticker}` | Lookup by ticker | Bearer |
| GET | `/listings/by-isin/{isin}` | Lookup by ISIN | Bearer |

### Corporate Actions

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/corporate-actions` | List corporate actions | Bearer |
| POST | `/corporate-actions` | Create corporate action | Bearer |
| GET | `/corporate-actions/{id}` | Get corporate action | Bearer |

### Index Master

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/indices` | List indices | Bearer |
| POST | `/indices` | Create index | Bearer |
| GET | `/indices/{id}` | Get index | Bearer |
| GET | `/indices/{id}/members` | Get index members (as of date) | Bearer |

#### GET /indices/{id}/members

```
Query Parameters:
  ?as_of=2026-07-24    (default: today)
```

```json
// Response 200
{
  "data": [
    {
      "instrument_id": "0192a3b4-...",
      "ticker": "BBRI",
      "weight": 0.1523,
      "shares": 12500000000,
      "effective_date": "2026-01-01",
      "end_date": null
    }
  ],
  "meta": { "as_of": "2026-07-24", "index_id": "0192...", "total_members": 55 }
}
```

### Market Calendar

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/calendar` | Get calendar (multi-exchange) | Bearer |
| GET | `/calendar/{exchangeId}` | Get exchange calendar | Bearer |
| POST | `/calendar` | Create calendar entry | Bearer |

```
Query Parameters:
  ?from=2026-01-01&to=2026-12-31
```
