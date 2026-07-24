---

## 10. Governance Context — Endpoints

### Audit Log

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/audit-logs` | List audit logs | Bearer |
| GET | `/audit-logs/{id}` | Get audit log detail | Bearer |
| GET | `/audit-logs/entity/{entityType}/{entityId}` | Get entity audit trail | Bearer |

```
Query Parameters:
  ?filter[actor_type]=OWNER
  ?filter[entity_type]=ORDER
  ?filter[entity_id]={id}
  ?filter[from_date]=2026-07-01
  ?filter[to_date]=2026-07-24
  ?sort=-created_at
```

### Approvals

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/approvals` | List approvals (pending/all) | Bearer |
| POST | `/approvals` | Request approval | Bearer |
| GET | `/approvals/{id}` | Get approval detail | Bearer |
| POST | `/approvals/{id}/approve` | Approve request | Bearer |
| POST | `/approvals/{id}/reject` | Reject request | Bearer |

```
Query Parameters:
  ?filter[status]=PENDING
  ?filter[approval_type]=ORDER
  ?filter[entity_type]=ORDER
  ?sort=-created_at
```

#### POST /approvals/{id}/approve

```json
// Response 200
{
  "data": {
    "approval_id": "0192a3b4-...",
    "status": "APPROVED",
    "approved_at": "2026-07-24T07:00:00.000000Z"
  }
}
```

### Workflows

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/workflows` | List workflows | Bearer |
| POST | `/workflows` | Start workflow | Bearer |
| GET | `/workflows/{id}` | Get workflow with steps | Bearer |
| GET | `/workflows/{id}/steps` | List workflow steps | Bearer |
| POST | `/workflows/{id}/steps/{stepId}/complete` | Complete workflow step | Bearer |
| POST | `/workflows/{id}/cancel` | Cancel workflow | Bearer |

### Policies

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/policies` | List policies | Bearer |
| POST | `/policies` | Create policy | Bearer |
| GET | `/policies/{id}` | Get policy with rules | Bearer |
| PUT | `/policies/{id}` | Update policy (creates new version) | Bearer |
| GET | `/policies/{id}/evaluations` | List policy evaluations | Bearer |
| POST | `/policies/{id}/evaluate` | Evaluate policy against entity | Bearer |

#### POST /policies

```json
// Request
{
  "policy_type": "TRADING",
  "name": "Max Single Position 10%",
  "description": "No single position may exceed 10% of portfolio NAV",
  "rules": {
    "max_single_position_pct": 0.10,
    "enforcement": "PRE_TRADE"
  },
  "priority": 10
}

// Response 201
{
  "data": {
    "policy_id": "0192a3b4-...",
    "policy_type": "TRADING",
    "name": "Max Single Position 10%",
    "version": 1,
    "status": "DRAFT",
    "created_at": "2026-07-24T06:00:00.000000Z"
  }
}
```

---

## 11. Config Context — Endpoints

### Configuration

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/configurations` | List configurations | Bearer |
| POST | `/configurations` | Create configuration | Bearer |
| GET | `/configurations/{id}` | Get configuration | Bearer |
| PUT | `/configurations/{id}` | Update configuration (new version) | Bearer |
| GET | `/configurations/key/{key}` | Get config by key | Bearer |

### Feature Flags

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/feature-flags` | List feature flags | Bearer |
| POST | `/feature-flags` | Create feature flag | Bearer |
| GET | `/feature-flags/{id}` | Get feature flag | Bearer |
| PUT | `/feature-flags/{id}` | Update feature flag | Bearer |
| GET | `/feature-flags/key/{key}` | Check flag by key | Bearer |

### System Parameters

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/system-parameters` | List system parameters | Bearer |
| GET | `/system-parameters/{key}` | Get parameter by key | Bearer |
| PUT | `/system-parameters/{key}` | Update parameter (if not readonly) | Bearer |

### Storage Objects

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/storage-objects` | List storage objects | Bearer |
| GET | `/storage-objects/{id}` | Get storage object metadata | Bearer |
| POST | `/storage-objects` | Register storage object | Bearer |
| DELETE | `/storage-objects/{id}` | Soft delete storage object | Bearer |

### API Access Logs

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api-access-logs` | List API access logs | Bearer |

### Owner Activity Logs

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/owner-activity-logs` | List owner activity logs | Bearer |

---

## 12. Cross-Cutting Endpoints

### Health

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/health` | Service health check | Public |
| GET | `/health/ready` | Readiness check (DB, Redis, etc.) | Public |
| GET | `/health/live` | Liveness check | Public |

### Metrics

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/metrics` | Prometheus metrics | Internal |

### API Info

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/` | API info, version, endpoints | Public |

```json
// Response 200
{
  "name": "Capital Market Platform API",
  "version": "1.0.0",
  "environment": "development",
  "timestamp": "2026-07-24T06:00:00.000000Z"
}
```

---

## 13. Endpoint Summary

### Total Endpoint Count

| Context | Endpoints |
|---------|-----------|
| Single-Owner Identity | 8 |
| Config | 16 |
| Market Master | 28 |
| Fundamental | 17 |
| Analytics | 31 |
| Portfolio | 16 |
| Risk | 13 |
| Trading | 20 |
| Settlement | 7 |
| Governance | 18 |
| Cross-Cutting | 4 |
| **Total** | **178** |

### HTTP Method Distribution

| Method | Count |
|--------|-------|
| GET | 104 |
| POST | 42 |
| PUT | 22 |
| DELETE | 6 |
| **Total** | **174** |

### Auth Requirement Distribution

| Auth Level | Endpoints |
|------------|-----------|
| Public/First-run | 2 |
| Bearer | 166 |
| Public health/info | 3 |
| Internal | 1 |
