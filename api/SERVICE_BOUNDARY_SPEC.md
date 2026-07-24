# Service Boundary Specification

> Platform: Global & Indonesia Capital Market Intelligence, Decision, Risk & Execution Platform
> Architecture: Modular Monolith
> Pattern: Internal service classes with clear interfaces, deployable as separate services later

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     API Gateway / Router                     │
│                   (PHP Router, JWT Auth)                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ Identity │ │ Market   │ │Fundamental│ │ Analytics│       │
│  │ Service  │ │ Master   │ │ Service  │ │ Service  │       │
│  │          │ │ Service  │ │          │ │          │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
│                                                              │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ Portfolio│ │ Risk     │ │ Trading  │ │Settlement│       │
│  │ Service  │ │ Service  │ │ Service  │ │ Service  │       │
│  │          │ │          │ │          │ │          │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
│                                                              │
│  ┌──────────┐ ┌──────────┐                                  │
│  │Governance│ │ Config   │                                  │
│  │ Service  │ │ Service  │                                  │
│  │          │ │          │                                  │
│  └──────────┘ └──────────┘                                  │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                    Shared Infrastructure                     │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐   │
│  │ MySQL  │ │Postgres│ │ Redis  │ │RabbitMQ│ │  S3    │   │
│  │(txn)   │ │(TS)    │ │(cache) │ │(events)│ │(files) │   │
│  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Service Interface Contract

Each service exposes:
- **Inbound**: REST endpoints (via API Router)
- **Internal**: PHP interface (called by other services in-process)
- **Outbound**: Events (published to RabbitMQ)

### Service Interface Template

```php
interface XxxServiceInterface
{
    // CRUD operations
    public function create(array $data): array;
    public function getById(string $id): ?array;
    public function list(array $filters, int $page, int $perPage): array;
    public function update(string $id, array $data): array;
    
    // Domain-specific operations
    // ...
}
```

---

## 3. Service Catalog

### 3.1 IdentityService

```
Responsibility:
  - One-time owner account setup
  - Owner authentication and JWT issuance
  - Password change and account lock handling
  - Owner preferences

Database: MySQL (identity schema)

Dependencies: None (root service)

Events Published:
  - owner.created
  - owner.login
  - owner.password_changed
  - owner.account_locked

Internal Interface:
  IdentityServiceInterface
    - setupOwner(array $data): array
    - authenticate(string $email, string $password): array
    - verifyToken(string $jwt): array
    - getOwner(): ?array
    - updatePreferences(array $data): array
```

### 3.2 MarketMasterService

```
Responsibility:
  - Exchange, issuer, security, instrument, listing management
  - Corporate actions
  - Index master & membership
  - Market calendar

Database: MySQL (market_master schema)
Time Series: PostgreSQL (ohlcv, tick, quote schemas — read proxy)

Dependencies: None (root service)

Events Published:
  - instrument.created
  - instrument.status_changed
  - corporate_action.announced
  - listing.added
  - listing.delisted

Events Consumed:
  - (none)

Internal Interface:
  MarketMasterServiceInterface
    - getInstrumentById(string $id): ?array
    - getInstrumentByTicker(string $exchangeMic, string $ticker): ?array
    - getInstrumentByIsin(string $isin): ?array
    - getActiveListingsByExchange(string $exchangeId): array
    - getCorporateActions(string $instrumentId, string $fromDate, string $toDate): array
    - getIndexMembers(string $indexId, string $asOfDate): array
    - isTradingDay(string $exchangeId, string $date): bool
```

### 3.3 FundamentalService

```
Responsibility:
  - Financial statement ingestion & retrieval
  - Financial metric calculation
  - Economic indicator management
  - News ingestion & tagging

Database: MySQL (fundamental schema)
Object Storage: S3 (source documents, news content)

Dependencies:
  - MarketMasterService (issuer, instrument lookup)
  - ConfigService (storage_object)

Events Published:
  - financial_statement.published
  - financial_statement.revised
  - financial_metric.calculated
  - economic_indicator.published
  - news.ingested

Events Consumed:
  - instrument.created (for news_instrument tagging)

Internal Interface:
  FundamentalServiceInterface
    - getFinancialStatements(array $filters): array
    - getLatestFinancialStatement(string $issuerId, string $type): ?array
    - getFinancialMetrics(string $issuerId, string $metricType): array
    - getEconomicIndicators(string $country, string $indicatorType): array
    - getNewsByInstrument(string $instrumentId, int $limit): array
```

### 3.4 AnalyticsService

```
Responsibility:
  - Feature definition & value management
  - Signal generation
  - Forecast generation
  - Recommendation generation
  - Score calculation
  - Model registry
  - Backtest execution

Database: MySQL (analytics schema)
Time Series: PostgreSQL (factor, technical, valuation schemas)
Object Storage: S3 (model artifacts, backtest results)

Dependencies:
  - MarketMasterService (instrument lookup)
  - PortfolioService (backtest portfolio)
  - ConfigService (storage_object, model artifacts)

Events Published:
  - signal.generated
  - signal.invalidated
  - forecast.generated
  - recommendation.generated
  - recommendation.executed
  - backtest.started
  - backtest.completed
  - model.deployed

Events Consumed:
  - financial_statement.published (trigger feature recalculation)
  - news.ingested (sentiment feature input)

Internal Interface:
  AnalyticsServiceInterface
    - getActiveSignals(string $instrumentId): array
    - getLatestRecommendation(string $instrumentId): ?array
    - getFeatureValues(string $featureId, string $instrumentId, string $from, string $to): array
    - getLatestForecast(string $instrumentId, string $targetVariable): ?array
    - getScore(string $instrumentId, string $scoreType): ?array
```

### 3.5 PortfolioService

```
Responsibility:
  - Portfolio CRUD
  - Portfolio account management
  - Position tracking (open, update, close)
  - Position snapshots (daily)
  - Cash balance & transactions
  - Portfolio targets

Database: MySQL (portfolio schema)

Dependencies:
  - MarketMasterService (instrument, benchmark)
  - RiskService (risk_profile)
  - TradingService (broker via account, execution via cash_transaction)

Events Published:
  - portfolio.created
  - position.opened
  - position.updated
  - position.closed
  - cash.deposited
  - cash.withdrawn
  - portfolio_target.set

Events Consumed:
  - execution.filled (update position, cash balance)
  - settlement.settled (finalize cash, position)

Internal Interface:
  PortfolioServiceInterface
    - getPortfolioById(string $id): ?array
    - getPortfolioSummary(string $id): array
    - getPositions(string $portfolioId): array
    - getPosition(string $portfolioId, string $instrumentId): ?array
    - getCashBalance(string $portfolioId, string $currency): ?array
    - recordCashTransaction(string $portfolioId, array $data): array
    - getPortfolioTargets(string $portfolioId): array
```

### 3.6 RiskService

```
Responsibility:
  - Risk profile management
  - Risk limit management & enforcement
  - Risk assessment (VaR, ES, beta, Sharpe, etc.)
  - Risk event detection & tracking

Database: MySQL (risk schema)

Dependencies:
  - PortfolioService (portfolio, positions)

Events Published:
  - risk_limit.breached
  - risk_limit.warning
  - risk_event.resolved
  - risk_assessment.completed

Events Consumed:
  - position.updated (trigger limit check)
  - position.opened (trigger limit check)
  - execution.filled (trigger limit check)

Internal Interface:
  RiskServiceInterface
    - getRiskProfile(string $id): ?array
    - checkLimits(string $portfolioId, array $proposedTrade): array
    - getLatestAssessment(string $portfolioId): ?array
    - triggerAssessment(string $portfolioId): array
    - getActiveRiskEvents(string $portfolioId): array
```

### 3.7 TradingService

```
Responsibility:
  - Broker management
  - Decision generation & approval
  - Order intent management
  - Order submission & tracking
  - Execution recording
  - Trading lifecycle state machine

Database: MySQL (trading schema)

Dependencies:
  - PortfolioService (portfolio, account)
  - MarketMasterService (instrument)
  - AnalyticsService (recommendation)
  - RiskService (risk_assessment)
  - GovernanceService (policy evaluation, owner confirmation)

Events Published:
  - decision.created
  - decision.approved
  - decision.rejected
  - order_intent.created
  - order_intent.approved
  - order.submitted
  - order.filled
  - order.cancelled
  - order.rejected
  - execution.filled

Events Consumed:
  - recommendation.generated (trigger decision)
  - risk_limit.breached (block new orders)
  - approval.approved (proceed with order)
  - approval.rejected (cancel order intent)

Internal Interface:
  TradingServiceInterface
    - createDecision(array $data): array
    - approveDecision(string $id): array
    - createOrderIntent(string $decisionId, array $data): array
    - submitOrder(string $intentId, array $data): array
    - cancelOrder(string $orderId): array
    - getOrderById(string $id): ?array
    - recordExecution(array $data): array
```

### 3.8 SettlementService

```
Responsibility:
  - Settlement processing (T+2, T+1, T+0)
  - Reconciliation (position, cash, execution)
  - Settlement status tracking

Database: MySQL (settlement schema)

Dependencies:
  - TradingService (execution)
  - PortfolioService (portfolio, position, cash)
  - MarketMasterService (instrument)

Events Published:
  - settlement.created
  - settlement.settled
  - settlement.failed
  - reconciliation.mismatch_detected
  - reconciliation.resolved

Events Consumed:
  - execution.filled (create settlement record)
  - corporate_action.announced (adjust settlement for CA)

Internal Interface:
  SettlementServiceInterface
    - getSettlementByExecution(string $executionId): ?array
    - getPendingSettlements(string $portfolioId): array
    - processSettlement(string $settlementId): array
    - createReconciliation(array $data): array
    - resolveReconciliation(string $id, string $resolution): array
```

### 3.9 GovernanceService

```
Responsibility:
  - Audit logging (append-only)
  - Approval workflow (single-step)
  - Multi-step workflow management
  - Policy management & evaluation
  - Policy enforcement engine

Database: MySQL (governance schema)

Dependencies:
  - IdentityService (authenticated owner context)

Events Published:
  - audit.logged
  - approval.requested
  - approval.approved
  - approval.rejected
  - workflow.started
  - workflow.step_completed
  - workflow.completed
  - policy.created
  - policy.evaluation_completed

Events Consumed:
  - (all events — for audit logging)
  - decision.created (trigger approval if required)
  - order_intent.created (trigger approval if required)

Internal Interface:
  GovernanceServiceInterface
    - auditLog(array $data): void
    - requestApproval(string $entityType, string $entityId, string $approvalType): array
    - approve(string $approvalId): array
    - reject(string $approvalId, string $reason): array
    - evaluatePolicy(string $policyId, string $entityType, string $entityId): array
    - evaluateAllPolicies(string $entityType, string $entityId): array
    - startWorkflow(string $type, string $entityType, string $entityId): array
```

### 3.10 ConfigService

```
Responsibility:
  - Global personal configuration management
  - Boolean feature flag management
  - Storage object metadata
  - System parameters
  - API access logging
  - Owner activity logging

Database: MySQL (config schema)
Object Storage: S3 (actual file storage)

Dependencies:
  - IdentityService (authenticated owner context)

Events Published:
  - configuration.changed
  - feature_flag.toggled
  - storage_object.registered

Events Consumed:
  - (none, but receives audit events for logging)

Internal Interface:
  ConfigServiceInterface
    - getConfig(string $key): ?array
    - setConfig(string $key, string $value): array
    - isFeatureEnabled(string $flagKey): bool
    - registerStorageObject(array $data): array
    - getStorageObject(string $id): ?array
    - getSystemParameter(string $key): ?array
    - logApiAccess(array $data): void
    - logOwnerActivity(array $data): void
```

---

## 4. Event Flow Map

### Trading Lifecycle Event Flow

```
AnalyticsService
  → recommendation.generated
    → TradingService.createDecision()
      → GovernanceService.evaluateAllPolicies()
        → policy.evaluation_completed
      → decision.created
      → GovernanceService.requestApproval() (if required)
        → approval.requested
        → (owner confirms via API)
        → approval.approved
      → decision.approved
      → TradingService.createOrderIntent()
        → order_intent.created
        → GovernanceService.requestApproval() (if required)
        → approval.approved
      → TradingService.submitOrder()
        → order.submitted
        → (broker fills)
        → execution.filled
          → PortfolioService.updatePosition()
            → position.updated
          → PortfolioService.updateCashBalance()
          → RiskService.checkLimits()
            → risk_limit.breached (if applicable)
              → risk_event detected
          → SettlementService.createSettlement()
            → settlement.created
            → (T+2)
            → settlement.settled
              → PortfolioService.finalizeCash()
```

### Data Ingestion Event Flow

```
External Data Provider
  → MarketMasterService.ingestInstrument()
    → instrument.created
  → FundamentalService.ingestFinancialStatement()
    → financial_statement.published
      → AnalyticsService.recalculateFeatures()
        → feature_value.calculated
        → AnalyticsService.generateSignals()
          → signal.generated
          → AnalyticsService.generateForecast()
            → forecast.generated
            → AnalyticsService.generateRecommendation()
              → recommendation.generated
```

---

## 5. Service Dependency Matrix

```
              │ Idn │ Mkt │ Fun │ Anl │ Ptf │ Rsk │ Trd │ Set │ Gov │ Cfg │
──────────────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┤
Identity      │  —  │     │     │     │     │     │     │     │     │     │
Market Master │     │  —  │     │     │     │     │     │     │     │     │
Fundamental   │     │  ✓  │  —  │     │     │     │     │     │     │  ✓  │
Analytics     │     │  ✓  │     │  —  │  ✓  │     │     │     │     │  ✓  │
Portfolio     │  ✓  │  ✓  │     │     │  —  │  ✓  │  ✓  │     │     │     │
Risk          │  ✓  │     │     │     │  ✓  │  —  │     │     │     │     │
Trading       │  ✓  │  ✓  │     │  ✓  │  ✓  │  ✓  │  —  │     │  ✓  │     │
Settlement    │  ✓  │  ✓  │     │     │  ✓  │     │  ✓  │  —  │     │     │
Governance    │  ✓  │     │     │     │     │     │     │     │  —  │     │
Config        │  ✓  │     │     │     │     │     │     │     │     │  —  │
```

Legend: ✓ = depends on, — = self

---

## 6. Deployment Boundary (Future Microservices)

### Phase 1: Modular Monolith (Current)

```
Single PHP application
  - All 10 services in one codebase
  - In-process calls via interfaces
  - Shared MySQL connection (different schemas)
  - Shared RabbitMQ connection
```

### Phase 2: Split Candidates (When Scale Demands)

```
High-traffic, stateless:
  - MarketMasterService → standalone (read-heavy, cacheable)
  - AnalyticsService → standalone (compute-heavy, Python integration)

Write-heavy, transactional:
  - TradingService → standalone (order processing)
  - PortfolioService → standalone (position management)

Support:
  - GovernanceService → standalone (audit, policy)
  - ConfigService → standalone (config, feature flags)

Stay in monolith:
  - IdentityService (tightly coupled to auth middleware)
  - FundamentalService (coupled to MarketMaster)
  - RiskService (coupled to Portfolio)
  - SettlementService (coupled to Trading + Portfolio)
```

### Split Protocol

```
1. Extract interface to separate package
2. Replace in-process calls with HTTP/gRPC calls
3. Add circuit breaker & retry logic
4. Move database schema to separate database
5. Update cross-DB FKs to logical FKs
6. Deploy independently
```

---

## 7. Shared Infrastructure Services

### 7.1 Event Bus (RabbitMQ)

```
Exchange: platform.events (topic)
  Routing key pattern: {context}.{entity}.{action}
  Example: trading.order.submitted

Queues:
  - q.governance.audit (all events — for audit logging)
  - q.portfolio.execution (execution.filled — for position update)
  - q.risk.position (position.* — for limit check)
  - q.settlement.execution (execution.filled — for settlement)
  - q.analytics.fundamental (financial_statement.* — for feature recalculation)

DLQ: dlq.platform.events
```

### 7.2 Cache (Redis)

```
Namespaces:
  - cache:instrument:{id} — instrument lookup (TTL: 1h)
  - cache:listing:ticker:{exchange}:{ticker} — ticker lookup (TTL: 1h)
  - cache:portfolio:{id}:summary — portfolio summary (TTL: 5m)
  - cache:owner:profile — owner profile (TTL: 15m)
  - cache:config:{key} — configuration (TTL: 5m)
  - cache:feature_flag:{key} — feature flags (TTL: 1m)
  - session:{token} — JWT session (TTL: 1h)
  - lock:order:{orderId} — order processing lock (TTL: 30s)
```

### 7.3 Object Storage (S3)

```
Buckets:
  - platform-raw-documents (financial statement PDFs, news articles)
  - platform-artifacts (model weights, backtest results)
  - platform-exports (owner data exports, reports)
  - platform-backups (database backup snapshots)

Lifecycle:
  - raw-documents: Standard → IA after 90d → Glacier after 1y
  - artifacts: Standard → IA after 30d
  - exports: Standard → delete after 7d
  - backups: Standard → Glacier after 7d → delete after 90d
```
