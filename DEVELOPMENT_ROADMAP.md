# DEVELOPMENT ROADMAP
# Global-to-Indonesia Capital Market Intelligence & Trading Platform

## FASE 1 — FOUNDATION (Minggu 1-2)
- [ ] Setup project structure (microservices)
- [ ] Database schema design (MySQL + TimescaleDB)
- [ ] Docker & docker-compose setup
- [ ] Redis cache setup
- [ ] REST API skeleton (PHP Native)
- [ ] Python calculation engine skeleton
- [ ] Basic authentication & user management
- [ ] Config management

## FASE 2 — DATA INGESTION (Minggu 3-4)
- [ ] Data Ingestion Engine
  - [ ] IDX/BEI market data feeder
  - [ ] OHLCV daily data
  - [ ] OHLCV intraday data
  - [ ] Order book / depth data
  - [ ] Corporate action data
  - [ ] Financial statement data
  - [ ] Macro economic data
  - [ ] Global market data (US, Europe, Asia)
  - [ ] Commodity data
  - [ ] FX data (USD/IDR, DXY)
  - [ ] News data feed
- [ ] Data Quality Engine
  - [ ] Missing data detection
  - [ ] Duplicate removal
  - [ ] Outlier detection
  - [ ] Timezone normalization
  - [ ] Corporate action adjustment
  - [ ] Data validation rules

## FASE 3 — ANALYSIS ENGINES (Minggu 5-8)
- [ ] Fundamental Engine
  - [ ] EPS, ROE, ROA, ROIC calculation
  - [ ] Margin & growth analysis
  - [ ] Debt analysis
  - [ ] FCF calculation
  - [ ] Sector-specific models (bank, commodity, consumer)
- [ ] Valuation Engine
  - [ ] P/E, P/BV, EV/EBITDA, PEG
  - [ ] Dividend Yield, FCF Yield
  - [ ] DCF model
  - [ ] Relative valuation
  - [ ] Fair value calculation
- [ ] Technical Engine
  - [ ] SMA, EMA, WMA
  - [ ] RSI, MACD, Stochastic
  - [ ] Bollinger Bands, ATR, ADX
  - [ ] Volume analysis
  - [ ] Support/Resistance detection
  - [ ] Trend identification
- [ ] Macro Engine
  - [ ] Inflation tracking & surprise calculation
  - [ ] GDP tracking
  - [ ] Interest rate tracking (BI-Rate, Fed Rate)
  - [ ] Bond yield tracking
  - [ ] FX analysis (USD/IDR, DXY)
  - [ ] Commodity correlation
  - [ ] Relationship engine (cross-asset)

## FASE 4 — INTELLIGENCE LAYER (Minggu 9-10)
- [ ] Market Regime Engine
  - [ ] BULL/BEAR/SIDEWAYS detection
  - [ ] Volatility regime
  - [ ] Risk ON/OFF classification
- [ ] Sentiment Engine
  - [ ] News NLP pipeline
  - [ ] Entity recognition
  - [ ] Event classification
  - [ ] Sentiment scoring
  - [ ] Social sentiment (optional)
- [ ] Screening Engine
  - [ ] Multi-factor screening
  - [ ] Custom filter builder
  - [ ] Scoring system
- [ ] Market Microstructure
  - [ ] Bid/ask spread analysis
  - [ ] Order book depth
  - [ ] Market impact estimation
  - [ ] Liquidity scoring

## FASE 5 — DECISION & RISK (Minggu 11-12)
- [ ] Decision Engine
  - [ ] Composite scoring (fundamental + valuation + technical + macro + sentiment)
  - [ ] BUY/HOLD/SELL/WATCHLIST signals
  - [ ] Confidence scoring
- [ ] Risk Engine
  - [ ] Position size calculator
  - [ ] Stop loss calculation
  - [ ] Portfolio VaR
  - [ ] Volatility & beta calculation
  - [ ] Correlation matrix
  - [ ] Drawdown analysis
  - [ ] Concentration risk
  - [ ] Liquidity risk
  - [ ] Gap risk
- [ ] Market Factor Matrix
  - [ ] Global-to-Indonesia factor tracking
  - [ ] Rupiah Pressure Score
  - [ ] Flow Confirmation Score

## FASE 6 — PORTFOLIO MANAGEMENT (Minggu 13-14)
- [ ] Portfolio Engine
  - [ ] Portfolio creation & management
  - [ ] Asset allocation
  - [ ] Sector exposure monitoring
  - [ ] Rebalancing recommendations
  - [ ] Performance tracking (P&L, returns)
  - [ ] Benchmark comparison (IHSG)
- [ ] Watchlist management
- [ ] Alert system (price, signal, risk)

## FASE 7 — EXECUTION & OMS (Minggu 15-18)
- [ ] Broker API Integration
  - [ ] Research & select broker API (e.g., Mirae Asset, BNI Sekuritas, etc.)
  - [ ] API authentication
  - [ ] Account balance & portfolio sync
  - [ ] Real-time price via broker
- [ ] Execution Engine
  - [ ] Pre-trade risk check
  - [ ] Order validation
  - [ ] Order routing
  - [ ] Execution confirmation
  - [ ] Post-trade processing
- [ ] OMS (Order Management System)
  - [ ] New order creation
  - [ ] Order modify
  - [ ] Order cancel
  - [ ] Partial fill handling
  - [ ] Full fill handling
  - [ ] Rejected/expired handling
  - [ ] Order history
- [ ] Clearing & Settlement tracking
  - [ ] T+2 settlement tracking
  - [ ] Cash reconciliation
  - [ ] Securities reconciliation

## FASE 8 — AUDIT & COMPLIANCE (Minggu 19)
- [ ] Audit Engine
  - [ ] User activity logging
  - [ ] Timestamp & IP tracking
  - [ ] Signal & decision logging
  - [ ] Order & execution logging
  - [ ] Immutable audit trail
- [ ] Compliance checks
  - [ ] Pre-trade limits
  - [ ] Capital/credit thresholds
  - [ ] Duplicate order detection
  - [ ] Erroneous order detection

## FASE 9 — AI ENGINE (Minggu 20-22)
- [ ] NLP/AI for news analysis
- [ ] Pattern recognition
- [ ] Predictive models (optional)
- [ ] Anomaly detection
- [ ] Smart alerts

## FASE 10 — BACKTESTING & PAPER TRADING (Minggu 23-24)
- [ ] Backtesting framework
  - [ ] Historical data replay
  - [ ] Strategy testing
  - [ ] Performance metrics (Sharpe, Sortino, Max DD)
- [ ] Paper trading
  - [ ] Simulated execution
  - [ ] Virtual portfolio
  - [ ] Signal validation

## FASE 11 — FRONTEND & UI (Minggu 25-28)
- [ ] Dashboard (market overview, portfolio summary)
- [ ] Stock detail page (chart, fundamental, technical, valuation)
- [ ] Screening & scanning interface
- [ ] Decision & signal panel
- [ ] Portfolio management view
- [ ] Order entry & OMS interface
- [ ] Risk monitor
- [ ] Alert & notification center
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

### MVP (Minimum Viable Product) — Fase 1-3
Fokus pada: data ingestion + analysis engines
Target: User dapat melihat data saham, chart, fundamental, technical

### V2 — Fase 4-5
Fokus pada: intelligence + decision + risk
Target: User mendapat rekomendasi dan risk assessment

### V3 — Fase 6-7
Fokus pada: portfolio + execution
Target: User dapat manage portfolio dan transaksi via broker API

### V4 — Fase 8-12
Fokus pada: audit, AI, backtesting, UI polish, deployment
Target: Production-ready platform
