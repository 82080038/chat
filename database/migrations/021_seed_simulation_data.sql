-- ============================================================================
-- 021_seed_simulation_data.sql
-- Comprehensive seed data for simulation testing:
-- 20 IDX issuers, securities, instruments, listings (BBCA, TLKM, etc.)
-- 2 brokers, 3 alerts, 2 policies, 5 signals, 1 backtest
-- Run AFTER 012_seed_data.sql
-- ============================================================================

USE platform;

-- Get IDX exchange ID
SET @xidx = (SELECT exchange_id FROM market_master.exchange WHERE mic_code = 'XIDX');

-- Issuers
INSERT INTO market_master.issuer (issuer_id, legal_name, short_name, country, jurisdiction, status, sector_code, created_at, updated_at) VALUES
  (UUID(), 'Bank Central Asia Tbk', 'BCA', 'ID', 'ID', 'ACTIVE', 'BANKING', NOW(), NOW()),
  (UUID(), 'Bank Rakyat Indonesia Tbk', 'BRI', 'ID', 'ID', 'ACTIVE', 'BANKING', NOW(), NOW()),
  (UUID(), 'Bank Mandiri Tbk', 'MANDIRI', 'ID', 'ID', 'ACTIVE', 'BANKING', NOW(), NOW()),
  (UUID(), 'Telkom Indonesia Tbk', 'TELKOM', 'ID', 'ID', 'ACTIVE', 'TELECOM', NOW(), NOW()),
  (UUID(), 'Astra International Tbk', 'ASTRA', 'ID', 'ID', 'ACTIVE', 'AUTOMOTIVE', NOW(), NOW()),
  (UUID(), 'GoTo Gojek Tokopedia Tbk', 'GOTO', 'ID', 'ID', 'ACTIVE', 'TECHNOLOGY', NOW(), NOW()),
  (UUID(), 'Unilever Indonesia Tbk', 'UNVR', 'ID', 'ID', 'ACTIVE', 'CONSUMER', NOW(), NOW()),
  (UUID(), 'Indofood CBP Sukses Makmur Tbk', 'ICBP', 'ID', 'ID', 'ACTIVE', 'FOOD', NOW(), NOW()),
  (UUID(), 'Adaro Energy Indonesia Tbk', 'ADARO', 'ID', 'ID', 'ACTIVE', 'ENERGY', NOW(), NOW()),
  (UUID(), 'Aneka Tambang Tbk', 'ANTAM', 'ID', 'ID', 'ACTIVE', 'MINING', NOW(), NOW()),
  (UUID(), 'Merdeka Copper Gold Tbk', 'MDKA', 'ID', 'ID', 'ACTIVE', 'MINING', NOW(), NOW()),
  (UUID(), 'Indofood Sukses Makmur Tbk', 'INDF', 'ID', 'ID', 'ACTIVE', 'FOOD', NOW(), NOW()),
  (UUID(), 'Perusahaan Gas Negara Tbk', 'PGN', 'ID', 'ID', 'ACTIVE', 'ENERGY', NOW(), NOW()),
  (UUID(), 'Semen Indonesia Tbk', 'SIG', 'ID', 'ID', 'ACTIVE', 'MATERIALS', NOW(), NOW()),
  (UUID(), 'Kalbe Farma Tbk', 'KALBE', 'ID', 'ID', 'ACTIVE', 'HEALTHCARE', NOW(), NOW()),
  (UUID(), 'Erajaya Swasembada Tbk', 'ERAJAYA', 'ID', 'ID', 'ACTIVE', 'RETAIL', NOW(), NOW()),
  (UUID(), 'Bank BTPN Tbk', 'BTPN', 'ID', 'ID', 'ACTIVE', 'BANKING', NOW(), NOW()),
  (UUID(), 'BFI Finance Indonesia Tbk', 'BFI', 'ID', 'ID', 'ACTIVE', 'FINANCE', NOW(), NOW()),
  (UUID(), 'Japfa Comfeed Indonesia Tbk', 'JAPFA', 'ID', 'ID', 'ACTIVE', 'FOOD', NOW(), NOW()),
  (UUID(), 'Chandra Asri Pacific Tbk', 'CAP', 'ID', 'ID', 'ACTIVE', 'CHEMICAL', NOW(), NOW());

-- Securities
INSERT INTO market_master.security (security_id, issuer_id, security_type, currency, status)
  SELECT UUID(), issuer_id, 'EQUITY', 'IDR', 'ACTIVE'
  FROM market_master.issuer
  WHERE country = 'ID' AND status = 'ACTIVE';

-- Instruments
INSERT INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status, status_changed_at)
  SELECT UUID(), s.security_id, 'EQUITY', 'STOCK', 'IDR', 'ACTIVE', NOW()
  FROM market_master.security s
  INNER JOIN market_master.issuer i ON i.issuer_id = s.issuer_id
  WHERE s.security_type = 'EQUITY' AND s.currency = 'IDR';

-- Listings
INSERT INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, isin, currency, listing_date, status)
  SELECT
    UUID(), inst.instrument_id, @xidx, ticker.ticker, ticker.isin, 'IDR', '2010-01-01', 'ACTIVE'
  FROM market_master.instrument inst
  INNER JOIN market_master.security sec ON sec.security_id = inst.security_id
  INNER JOIN market_master.issuer iss ON iss.issuer_id = sec.issuer_id
  INNER JOIN (
    SELECT 'BCA' AS short_name, 'BBCA' AS ticker, 'ID100011600' AS isin UNION ALL
    SELECT 'BRI', 'BBRI', 'ID100011800' UNION ALL
    SELECT 'MANDIRI', 'BMRI', 'ID100011400' UNION ALL
    SELECT 'TELKOM', 'TLKM', 'ID100011900' UNION ALL
    SELECT 'ASTRA', 'ASII', 'ID100011300' UNION ALL
    SELECT 'GOTO', 'GOTO', 'ID100012800' UNION ALL
    SELECT 'UNVR', 'UNVR', 'ID100012000' UNION ALL
    SELECT 'ICBP', 'ICBP', 'ID100012100' UNION ALL
    SELECT 'ADARO', 'ADRO', 'ID100012200' UNION ALL
    SELECT 'ANTAM', 'ANTM', 'ID100012300' UNION ALL
    SELECT 'MDKA', 'MDKA', 'ID100012400' UNION ALL
    SELECT 'INDF', 'INDF', 'ID100012500' UNION ALL
    SELECT 'PGN', 'PGAS', 'ID100012600' UNION ALL
    SELECT 'SIG', 'SMGR', 'ID100012700' UNION ALL
    SELECT 'KALBE', 'KLBF', 'ID100012900' UNION ALL
    SELECT 'ERAJAYA', 'ERAA', 'ID100013000' UNION ALL
    SELECT 'BTPN', 'BTPS', 'ID100013100' UNION ALL
    SELECT 'BFI', 'BFIN', 'ID100013200' UNION ALL
    SELECT 'JAPFA', 'JPFA', 'ID100013300' UNION ALL
    SELECT 'CAP', 'TPIA', 'ID100013400'
  ) ticker ON ticker.short_name = iss.short_name;

-- Brokers
INSERT INTO trading.broker (broker_id, name, country, api_type, api_endpoint, status, created_at) VALUES
  (UUID(), 'Mirae Asset Sekuritas', 'ID', 'REST', 'https://api.miraeasset.co.id', 'ACTIVE', NOW()),
  (UUID(), 'BNI Sekuritas', 'ID', 'REST', 'https://api.bnisekuritas.co.id', 'ACTIVE', NOW());

-- Alerts
SET @bbca_inst = (SELECT instrument_id FROM market_master.listing WHERE ticker = 'BBCA' LIMIT 1);
INSERT INTO alert.alert (alert_id, alert_type, instrument_id, condition_op, threshold, is_active, description, created_at, updated_at) VALUES
  (UUID(), 'PRICE', @bbca_inst, 'GT', 9000, 1, 'BBCA above 9000', NOW(), NOW()),
  (UUID(), 'SIGNAL', NULL, 'EQ', 1, 1, 'New BUY signal generated', NOW(), NOW()),
  (UUID(), 'RISK', NULL, 'GT', 5, 1, 'Portfolio VaR above 5%', NOW(), NOW());

-- Policies
INSERT INTO governance.policy (policy_id, policy_type, name, description, rules, priority, effective_from, status, version, created_at) VALUES
  (UUID(), 'RISK', 'Max Position Size', 'No single position > 20% of portfolio', '{"max_pct":20}', 1, NOW(), 'ACTIVE', 1, NOW()),
  (UUID(), 'TRADING', 'Trading Hours Restriction', 'Only trade during IDX regular hours', '{"start":"09:00","end":"15:30"}', 2, NOW(), 'ACTIVE', 1, NOW());

-- Signals
SET @tlkm_inst = (SELECT instrument_id FROM market_master.listing WHERE ticker = 'TLKM' LIMIT 1);
SET @asii_inst = (SELECT instrument_id FROM market_master.listing WHERE ticker = 'ASII' LIMIT 1);
SET @bbri_inst = (SELECT instrument_id FROM market_master.listing WHERE ticker = 'BBRI' LIMIT 1);
SET @bmri_inst = (SELECT instrument_id FROM market_master.listing WHERE ticker = 'BMRI' LIMIT 1);
INSERT INTO analytics.signal (signal_id, instrument_id, signal_type, direction, strength, timeframe, model_version, valid_from) VALUES
  (UUID(), @bbca_inst, 'TECHNICAL', 'BULLISH', 85.00, '1D', 'v1.0', NOW()),
  (UUID(), @tlkm_inst, 'MOMENTUM', 'BULLISH', 72.00, '1D', 'v1.0', NOW()),
  (UUID(), @asii_inst, 'MEAN_REVERSION', 'BEARISH', 65.00, '1D', 'v1.0', NOW()),
  (UUID(), @bbri_inst, 'TECHNICAL', 'BULLISH', 78.00, '1D', 'v1.0', NOW()),
  (UUID(), @bmri_inst, 'MOMENTUM', 'NEUTRAL', 50.00, '1D', 'v1.0', NOW());

-- Backtest
INSERT INTO backtesting.backtest_run (run_id, strategy_name, instrument_id, start_date, end_date, initial_capital, status, created_at) VALUES
  (UUID(), 'BBCA Momentum Strategy', @bbca_inst, '2025-01-01', '2025-06-30', 100000000, 'COMPLETED', NOW());

-- ----------------------------------------------------------------------------
-- Risk Profile (1)
-- ----------------------------------------------------------------------------
INSERT INTO risk.risk_profile (risk_profile_id, name, risk_tolerance, max_single_position, max_sector_exposure, max_portfolio_beta, max_var_pct, max_drawdown_pct, status) VALUES
  (UUID(), 'Conservative Growth', 'MODERATE', 15.000000, 40.000000, 1.2000, 5.000000, 10.000000, 'ACTIVE');

-- ----------------------------------------------------------------------------
-- Portfolios (3)
-- ----------------------------------------------------------------------------
SET @risk_id = (SELECT risk_profile_id FROM risk.risk_profile LIMIT 1);
INSERT INTO portfolio.portfolio (portfolio_id, name, description, base_currency, portfolio_type, status, inception_date, risk_profile_id) VALUES
  (UUID(), 'Growth Portfolio', 'Long-term growth focused on blue-chip IDX stocks', 'IDR', 'LIVE', 'ACTIVE', '2025-01-01', @risk_id),
  (UUID(), 'Dividend Portfolio', 'Income-focused portfolio with high-dividend stocks', 'IDR', 'LIVE', 'ACTIVE', '2025-01-15', @risk_id),
  (UUID(), 'Speculative Portfolio', 'High-risk momentum trading', 'IDR', 'PAPER', 'ACTIVE', '2025-03-01', @risk_id);

-- ----------------------------------------------------------------------------
-- Paper Trading Account (1) + Positions
-- ----------------------------------------------------------------------------
INSERT INTO paper_trading.paper_account (account_id, name, initial_cash, cash_balance, status, created_at) VALUES
  (UUID(), 'Simulasi IDX', 100000000, 82300000, 'ACTIVE', NOW());

-- ----------------------------------------------------------------------------
-- Risk Assessment (1)
-- ----------------------------------------------------------------------------
SET @port_id = (SELECT portfolio_id FROM portfolio.portfolio WHERE name = 'Growth Portfolio' LIMIT 1);
INSERT INTO risk.risk_assessment (risk_assessment_id, portfolio_id, assessment_type, var_95, max_drawdown, portfolio_beta, sharpe_ratio, currency, as_of, model_version) VALUES
  (UUID(), @port_id, 'PORTFOLIO', 4500000, -8.5, 1.15, 1.35, 'IDR', NOW(), 'v1.0');
