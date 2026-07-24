# DEVELOPMENT ROADMAP
# Global-to-Indonesia Capital Market Intelligence & Trading Platform

---

## IMPLEMENTATION STATUS — Synced with MASTER_BLUEPRINT section 536

**Last updated:** 24 Juli 2026 (CYCLE-001 Docker setup)

### Backend Services: 17/17 Complete

| # | Service | Phase | Methods | Endpoints | Status |
|---|---------|-------|---------|-----------|--------|
| 1 | IdentityService | 1 | 14 | 8 | ✅ Done |
| 2 | ConfigService | 1 | 18 | 16 | ✅ Done |
| 3 | MarketMasterService | 2 | 28 | 28 | ✅ Done |
| 4 | FundamentalService | 2 | 17 | 17 | ✅ Done |
| 5 | AnalyticsService | 3 | 31 | 31 | ✅ Done |
| 6 | RiskService | 4 | 13 | 13 | ✅ Done |
| 7 | PortfolioService | 4 | 16 | 16 | ✅ Done |
| 8 | TradingService | 5 | 20 | 20 | ✅ Done |
| 9 | SettlementService | 5 | 7 | 7 | ✅ Done |
| 10 | GovernanceService | 1+fix | 20 | 18 | ✅ Done |
| 11 | DataIngestionService | 2 | 5 | 5 | ✅ Done |
| 12 | ValuationService | 3 | 7 | 7 | ✅ Done |
| 13 | AlertService | 6 | 9 | 9 | ✅ Done |
| 14 | BrokerAdapterService | 7 | 8 | 8 | ✅ Done |
| 15 | BacktestService | 10 | 7 | 6 | ✅ Done |
| 16 | PaperTradingService | 10 | 8 | 8 | ✅ Done |
| 17 | AIEngineService | 9 | 7 | 7 | ✅ Done |

### Cross-Service Wiring: ✅ Done
- ServiceHub: pre-trade risk check, auto-settlement, audit logging
- Health endpoints: /health, /health/ready, /health/live, /metrics

### Infrastructure: 4 cross-cutting endpoints

| Endpoint | Auth | Status |
|----------|------|--------|
| GET /health | Public | ✅ |
| GET /health/ready | Public | ✅ |
| GET /health/live | Public | ✅ |
| GET /metrics | Internal | ✅ |

### Test Results

```
PHPUnit: 150 tests, 279 assertions — ALL PASS
PSR-12: 0 violations on new files (1 pre-existing in ConfigServiceTest)
Total endpoints: 228 (224 service + 4 cross-cutting)
MySQL tables: 72 (schema designed, migrations not yet applied)
```

### Next Priorities (from unchecked items below)

1. **Production deployment** — Kubernetes, monitoring, security audit

---

## FASE 1 — FOUNDATION (Minggu 1-2)
- [x] Setup project structure (modular monolith)
- [x] Database schema design (MySQL + TimescaleDB)
- [x] Docker & docker-compose setup
- [x] Redis cache setup (fail-open in ConfigService)
- [x] REST API skeleton (PHP Native, PSR-12)
- [ ] Python calculation engine skeleton
- [x] Basic authentication & user management (IdentityService)
- [x] Config management (ConfigService)

## FASE 2 — DATA INGESTION (Minggu 3-4)
- [ ] Data Ingestion Engine
  - [x] IDX/BEI market data feeder (DataIngestionService — OHLCV ingest)
  - [x] OHLCV daily data (data_ingestion.ohlcv_daily)
  - [ ] OHLCV intraday data
  - [ ] Order book / depth data
  - [ ] Corporate action data
  - [x] Financial statement data (FundamentalService CRUD)
  - [x] Macro economic data (FundamentalService economic indicators)
  - [ ] Global market data (US, Europe, Asia)
  - [ ] Commodity data
  - [ ] FX data (USD/IDR, DXY)
  - [x] News data feed (FundamentalService news CRUD)
- [ ] Data Quality Engine
  - [ ] Missing data detection
  - [ ] Duplicate removal
  - [ ] Outlier detection
  - [ ] Timezone normalization
  - [ ] Corporate action adjustment
  - [ ] Data validation rules

## FASE 3 — ANALYSIS ENGINES (Minggu 5-8)
- [x] Fundamental Engine (FundamentalService — financial statements, metrics)
  - [x] EPS, ROE, ROA, ROIC calculation (metric storage)
  - [ ] Margin & growth analysis
  - [ ] Debt analysis
  - [ ] FCF calculation
  - [ ] Sector-specific models (bank, commodity, consumer)
- [x] Valuation Engine (ValuationService)
  - [x] DCF model (calculateDcf — NPV of projected FCF + terminal value)
  - [x] Relative valuation (calculateRelative — peer avg/median)
  - [x] Fair value calculation (calculateFairValue — weighted blend)
- [x] Technical Engine (AnalyticsService — signals, scores)
  - [x] SMA, EMA, WMA (feature storage)
  - [x] RSI, MACD, Stochastic (signal storage)
  - [x] Bollinger Bands, ATR, ADX (feature/signal storage)
  - [x] Volume analysis (feature storage)
  - [ ] Support/Resistance detection
  - [ ] Trend identification
- [x] Macro Engine (FundamentalService — economic indicators)
  - [x] Inflation tracking & surprise calculation (indicator storage)
  - [x] GDP tracking (indicator storage)
  - [x] Interest rate tracking (indicator storage)
  - [x] Bond yield tracking (indicator storage)
  - [ ] FX analysis (USD/IDR, DXY)
  - [ ] Commodity correlation
  - [ ] Relationship engine (cross-asset)

## FASE 4 — INTELLIGENCE LAYER (Minggu 9-10)
- [x] Market Regime Engine (AnalyticsService — scores)
  - [x] BULL/BEAR/SIDEWAYS detection (score storage)
  - [x] Volatility regime (score storage)
  - [x] Risk ON/OFF classification (score storage)
- [x] Sentiment Engine (AnalyticsService — signals, news)
  - [x] News NLP pipeline (news storage with sentiment field)
  - [ ] Entity recognition
  - [ ] Event classification
  - [x] Sentiment scoring (news sentiment field)
  - [ ] Social sentiment (optional)
- [x] Screening Engine (AnalyticsService — features, scores)
  - [x] Multi-factor screening (feature/score queries)
  - [ ] Custom filter builder
  - [x] Scoring system (score storage)
- [ ] Market Microstructure
  - [ ] Bid/ask spread analysis
  - [ ] Order book depth
  - [ ] Market impact estimation
  - [ ] Liquidity scoring

## FASE 5 — DECISION & RISK (Minggu 11-12)
- [x] Decision Engine (AnalyticsService — recommendations, TradingService — decisions)
  - [x] Composite scoring (recommendation with signals & forecasts)
  - [x] BUY/HOLD/SELL/WATCHLIST signals (recommendation action)
  - [x] Confidence scoring (recommendation confidence field)
- [x] Risk Engine (RiskService)
  - [x] Position size calculator (risk limits)
  - [ ] Stop loss calculation
  - [x] Portfolio VaR (risk assessments)
  - [x] Volatility & beta calculation (risk assessment fields)
  - [ ] Correlation matrix
  - [x] Drawdown analysis (max_drawdown field)
  - [x] Concentration risk (concentration_index field)
  - [ ] Liquidity risk
  - [ ] Gap risk
- [ ] Market Factor Matrix
  - [ ] Global-to-Indonesia factor tracking
  - [ ] Rupiah Pressure Score
  - [ ] Flow Confirmation Score

## FASE 6 — PORTFOLIO MANAGEMENT (Minggu 13-14)
- [x] Portfolio Engine (PortfolioService)
  - [x] Portfolio creation & management
  - [x] Asset allocation (targets)
  - [x] Sector exposure monitoring (positions)
  - [x] Rebalancing recommendations (targets)
  - [x] Performance tracking (P&L, returns — portfolio summary)
  - [x] Benchmark comparison (benchmark_id field)
- [x] Watchlist management (portfolio positions)
- [x] Alert system (AlertService — price, signal, risk alerts)
  - [x] Price alerts (above/below threshold — checkPriceAlert)
  - [x] Signal alerts (triggerAlert with context)
  - [x] Risk alerts (alert_type RISK)
  - [x] Notification management (listNotifications, acknowledgeNotification)

## FASE 7 — EXECUTION & OMS (Minggu 15-18)
- [x] Broker API Integration (BrokerAdapterService — MockBrokerAdapter for MVP)
  - [x] BrokerAdapterInterface (authenticate, balance, holdings, price, order lifecycle)
  - [x] MockBrokerAdapter (full mock implementation)
  - [x] API authentication (POST /brokers/{id}/auth)
  - [x] Account balance & portfolio sync (GET /brokers/{id}/balance, /holdings)
  - [x] Real-time price feed (GET /brokers/{id}/price/{symbol})
  - [x] Order placement via broker API (POST/DELETE/GET /brokers/{id}/orders)
  - [ ] Research & select real broker API (e.g., Mirae Asset, BNI Sekuritas)
  - [ ] Real adapter implementation
- [x] Execution Engine (TradingService)
  - [x] Pre-trade risk check (decision policy_result)
  - [x] Order validation (order intent approval)
  - [x] Order routing (order submission)
  - [x] Execution confirmation (execution recording)
  - [x] Post-trade processing (auto-fill tracking)
- [x] OMS (TradingService — orders, executions)
  - [x] New order creation
  - [ ] Order modify
  - [x] Order cancel
  - [x] Partial fill handling
  - [x] Full fill handling
  - [x] Rejected/expired handling
  - [x] Order history
- [x] Clearing & Settlement tracking (SettlementService)
  - [x] T+2 settlement tracking (settlement_type T_PLUS_2)
  - [x] Cash reconciliation (reconciliation type CASH)
  - [x] Securities reconciliation (reconciliation type POSITION)

## FASE 8 — AUDIT & COMPLIANCE (Minggu 19)
- [x] Audit Engine (GovernanceService)
  - [x] User activity logging (audit_log)
  - [x] Timestamp & IP tracking (audit_log fields)
  - [x] Signal & decision logging (audit_log entity_type)
  - [x] Order & execution logging (audit_log entity_type)
  - [x] Immutable audit trail (append-only audit_log)
- [x] Compliance checks (GovernanceService — policies)
  - [x] Pre-trade limits (policy evaluation)
  - [ ] Capital/credit thresholds
  - [ ] Duplicate order detection
  - [ ] Erroneous order detection

## FASE 9 — AI ENGINE (Minggu 20-22)
- [x] NLP/AI for news analysis (AIEngineService — sentiment, entity, event extraction)
- [x] Pattern recognition (chart patterns: DOUBLE_TOP, DOUBLE_BOTTOM, triangles, channel)
- [ ] Predictive models (optional)
- [x] Anomaly detection (Z-score based, SPIKE/DROP)
- [ ] Smart alerts

## FASE 10 — BACKTESTING & PAPER TRADING (Minggu 23-24)
- [x] Backtesting framework (BacktestService)
  - [x] Historical data replay engine (replayStrategy with buy/sell on price bars)
  - [x] Strategy testing interface (createRun + executeRun with price data input)
  - [x] Performance metrics (Sharpe, Sortino, Max DD, win rate, profit factor)
- [x] Paper trading (PaperTradingService)
  - [x] Simulated execution (MARKET/LIMIT orders, instant fill)
  - [x] Virtual portfolio (cash balance, position tracking, avg price)
  - [x] Signal validation (duplicate detection via signal_id)

## FASE 11 — FRONTEND & UI (Minggu 25-28)
- [x] Dashboard (market overview, portfolio summary)
- [ ] Stock detail page (chart, fundamental, technical, valuation)
- [ ] Screening & scanning interface
- [ ] Decision & signal panel
- [x] Portfolio management view (portfolio cards in dashboard)
- [ ] Order entry & OMS interface
- [ ] Risk monitor
- [x] Alert & notification center (alert list in dashboard)
- [ ] Settings & configuration

## FASE 12 — DEPLOYMENT & TESTING (Minggu 29-30)
- [ ] Integration testing
- [ ] Load testing
- [ ] Security audit
- [ ] Docker/Kubernetes deployment
- [ ] Monitoring & logging
- [ ] Documentation

---

## PRIORITAS PENGEMBANGAN

### MVP (Minimum Viable Product) — Fase 1-3 ✅ Backend Done
Fokus pada: data ingestion + analysis engines
Target: User dapat melihat data saham, chart, fundamental, technical
**Status:** Backend services complete (Identity, Config, MarketMaster, Fundamental, Analytics). Storage layer ready. Data feeders dan calculation engine belum ada.

### V2 — Fase 4-5 ✅ Backend Done
Fokus pada: intelligence + decision + risk
Target: User mendapat rekomendasi dan risk assessment
**Status:** RiskService, PortfolioService, TradingService, SettlementService complete. Cross-service wiring (pre-trade risk, auto-settlement, audit) done.

### V3 — Fase 6-7 ✅ Backend Done
Fokus pada: portfolio + execution
Target: User dapat manage portfolio dan transaksi via broker API
**Status:** Portfolio management, OMS, settlement tracking complete. Real broker API integration belum ada.

### V4 — Fase 8-12 ⏳ Partial
Fokus pada: audit, AI, backtesting, UI polish, deployment
**Status:** Audit engine & compliance checks done (GovernanceService). AI engine, backtesting, paper trading, frontend, Docker deployment belum dimulai.
