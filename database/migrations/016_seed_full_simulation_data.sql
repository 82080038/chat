-- ============================================================================
-- 016_seed_full_simulation_data.sql
-- Purpose: Complete sample data for end-to-end simulation
-- ============================================================================

USE platform;

-- ──────────────────────────────────────────────────────────────────────────
-- 1. Economic Indicators (for Market Factor Matrix & Rupiah Pressure Score)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO fundamental.economic_indicator (indicator_id, country, indicator_type, frequency, period, value, unit, publication_date, source) VALUES
(UUID(), 'ID', 'INFLATION', 'MONTHLY', '2025-06-01', 3.18, 'PERCENT', '2025-07-01', 'BPS'),
(UUID(), 'ID', 'INFLATION', 'MONTHLY', '2025-05-01', 3.23, 'PERCENT', '2025-06-01', 'BPS'),
(UUID(), 'ID', 'INFLATION', 'MONTHLY', '2025-04-01', 3.00, 'PERCENT', '2025-05-01', 'BPS'),
(UUID(), 'ID', 'GDP_GROWTH', 'QUARTERLY', '2025-03-31', 5.02, 'PERCENT', '2025-05-15', 'BPS'),
(UUID(), 'ID', 'GDP_GROWTH', 'QUARTERLY', '2024-12-31', 5.11, 'PERCENT', '2025-02-15', 'BPS'),
(UUID(), 'ID', 'INTEREST_RATE', 'MONTHLY', '2025-06-01', 6.25, 'PERCENT', '2025-07-01', 'BI'),
(UUID(), 'ID', 'INTEREST_RATE', 'MONTHLY', '2025-05-01', 6.00, 'PERCENT', '2025-06-01', 'BI'),
(UUID(), 'ID', 'INTEREST_RATE', 'MONTHLY', '2025-04-01', 6.00, 'PERCENT', '2025-05-01', 'BI'),
(UUID(), 'ID', 'BOND_YIELD_10Y', 'DAILY', '2025-07-15', 6.85, 'PERCENT', '2025-07-15', 'Bloomberg'),
(UUID(), 'ID', 'BOND_YIELD_10Y', 'DAILY', '2025-07-14', 6.82, 'PERCENT', '2025-07-14', 'Bloomberg'),
(UUID(), 'ID', 'BOND_YIELD_10Y', 'DAILY', '2025-07-11', 6.90, 'PERCENT', '2025-07-11', 'Bloomberg'),
(UUID(), 'ID', 'PMI', 'MONTHLY', '2025-06-01', 52.10, 'INDEX', '2025-07-01', 'S&P Global'),
(UUID(), 'ID', 'PMI', 'MONTHLY', '2025-05-01', 51.20, 'INDEX', '2025-06-01', 'S&P Global'),
(UUID(), 'ID', 'CPI', 'MONTHLY', '2025-06-01', 112.50, 'INDEX', '2025-07-01', 'BPS'),
(UUID(), 'ID', 'CPI', 'MONTHLY', '2025-05-01', 112.20, 'INDEX', '2025-06-01', 'BPS'),
(UUID(), 'US', 'INTEREST_RATE', 'MONTHLY', '2025-06-01', 5.25, 'PERCENT', '2025-07-01', 'Fed'),
(UUID(), 'US', 'GDP_GROWTH', 'QUARTERLY', '2025-03-31', 2.40, 'PERCENT', '2025-05-15', 'BEA'),
(UUID(), 'US', 'INFLATION', 'MONTHLY', '2025-06-01', 3.00, 'PERCENT', '2025-07-01', 'BLS');

-- ──────────────────────────────────────────────────────────────────────────
-- 2. News Items (for Sentiment Engine & AI Engine)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO fundamental.news_item (news_id, title, content_summary, source, published_at, sentiment_score, sentiment_label, language) VALUES
(UUID(), 'Bank Central Asia (BBCA) Reports Strong Q2 2025 Earnings', 'BBCA posted net profit of Rp 12.8 trillion in Q2 2025, up 18% YoY, driven by robust loan growth and improved NIM.', 'Kontan', NOW() - INTERVAL 1 DAY, 0.75, 'POSITIVE', 'en'),
(UUID(), 'Bank Rakyat Indonesia (BBRI) Expands Digital Banking', 'BBRI launches new digital banking platform targeting SME segment, expected to improve operational efficiency.', 'Bisnis Indonesia', NOW() - INTERVAL 2 DAY, 0.60, 'POSITIVE', 'en'),
(UUID(), 'Telkom Indonesia (TLKM) 5G Rollout Accelerates', 'TLKM accelerates 5G deployment in 10 cities, positioning for market share growth in telecom sector.', 'Detik Finance', NOW() - INTERVAL 3 DAY, 0.55, 'POSITIVE', 'en'),
(UUID(), 'Astra International (ASII) Faces Auto Market Headwinds', 'ASII reports declining auto sales due to competitive pressure and weakening consumer demand.', 'Kompas', NOW() - INTERVAL 4 DAY, -0.45, 'NEGATIVE', 'en'),
(UUID(), 'GoTo Gojek Tokopedia (GOTO) Path to Profitability', 'GOTO achieves positive EBITDA in Q2 2025, ahead of guidance, signaling improved unit economics.', 'CNBC Indonesia', NOW() - INTERVAL 5 DAY, 0.70, 'POSITIVE', 'en'),
(UUID(), 'Unilever Indonesia (UNVR) Revenue Growth Slows', 'UNVR reports slower revenue growth as competition intensifies in FMCG sector.', 'Kontan', NOW() - INTERVAL 6 DAY, -0.30, 'NEGATIVE', 'en'),
(UUID(), 'Indofood CBP (ICBP) Benefits from Commodity Price Decline', 'ICBP sees margin improvement from lower raw material costs, maintaining market leadership.', 'Bisnis Indonesia', NOW() - INTERVAL 7 DAY, 0.50, 'POSITIVE', 'en'),
(UUID(), 'Adaro Energy (ADRO) Coal Price Concerns', 'ADRO faces pressure from declining coal prices amid global energy transition concerns.', 'Reuters', NOW() - INTERVAL 8 DAY, -0.55, 'NEGATIVE', 'en'),
(UUID(), 'Antam (ANTM) Gold Sales Surge', 'ANTM reports record gold sales as investors seek safe haven amid global uncertainty.', 'Kontan', NOW() - INTERVAL 9 DAY, 0.65, 'POSITIVE', 'en'),
(UUID(), 'Bank Mandiri (BMRI) Digital Transformation Pays Off', 'BMRI digital banking transactions grow 40% YoY, contributing to improved cost efficiency.', 'CNBC Indonesia', NOW() - INTERVAL 10 DAY, 0.60, 'POSITIVE', 'en'),
(UUID(), 'BI Holds Rate at 6.25%, Signals Stability', 'Bank Indonesia maintains benchmark rate, citing manageable inflation and stable rupiah.', 'Bloomberg', NOW() - INTERVAL 1 DAY, 0.40, 'POSITIVE', 'en'),
(UUID(), 'Rupiah Strengthens Against USD on Capital Inflows', 'IDR appreciates 0.8% against USD as foreign investors return to Indonesian bonds.', 'Reuters', NOW() - INTERVAL 2 DAY, 0.55, 'POSITIVE', 'en'),
(UUID(), 'IDX Composite Index Reaches New High', 'IHSG hits 7,500 level driven by banking and consumer stocks rally.', 'Detik Finance', NOW() - INTERVAL 3 DAY, 0.70, 'POSITIVE', 'en'),
(UUID(), 'Global Market Volatility Increases on Fed Uncertainty', 'Asian markets face volatility as Fed rate cut timing remains uncertain.', 'Bloomberg', NOW() - INTERVAL 4 DAY, -0.40, 'NEGATIVE', 'en'),
(UUID(), 'Indonesia GDP Growth Exceeds Expectations', 'Indonesia Q2 2025 GDP grows 5.02%, beating market consensus of 4.9%.', 'Kontan', NOW() - INTERVAL 5 DAY, 0.65, 'POSITIVE', 'en');

-- ──────────────────────────────────────────────────────────────────────────
-- 3. Financial Statements (for Fundamental & Valuation Engine)
-- ──────────────────────────────────────────────────────────────────────────
-- BBCA
INSERT INTO fundamental.financial_statement (financial_statement_id, issuer_id, statement_type, fiscal_period_type, fiscal_year, fiscal_quarter, period_start, period_end, publication_date, currency, unit, source, status) VALUES
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673'), 'INCOME', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673'), 'BALANCE', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673'), 'CASHFLOW', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
-- BBRI
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673'), 'INCOME', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673'), 'BALANCE', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
-- TLKM
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673'), 'INCOME', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673'), 'BALANCE', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
-- ASII
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aadcb-876f-11f1-8fa9-b42e99811673'), 'INCOME', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
-- UNVR
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aae72-876f-11f1-8fa9-b42e99811673'), 'INCOME', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED'),
-- ICBP
(UUID(), (SELECT s.issuer_id FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id WHERE i.instrument_id = '7c0aaec0-876f-11f1-8fa9-b42e99811673'), 'INCOME', 'FY', 2024, NULL, '2024-01-01', '2024-12-31', '2025-03-15', 'IDR', 'BILLION', 'IDX Filing', 'PUBLISHED');

-- ──────────────────────────────────────────────────────────────────────────
-- 4. Signals (for StockDetail signals tab)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.signal (signal_id, instrument_id, signal_type, direction, strength, timeframe, model_version, valid_from) VALUES
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'RSI_OVERSOLD', 'BULLISH', 0.65, 'DAILY', 'v1', NOW() - INTERVAL 1 DAY),
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'MACD_CROSSOVER', 'BULLISH', 0.70, 'DAILY', 'v1', NOW() - INTERVAL 1 DAY),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'TREND_FOLLOWING', 'BULLISH', 0.55, 'WEEKLY', 'v1', NOW() - INTERVAL 2 DAY),
(UUID(), '7c0aad72-876f-11f1-8fa9-b42e99811673', 'MEAN_REVERSION', 'NEUTRAL', 0.40, 'DAILY', 'v1', NOW() - INTERVAL 3 DAY),
(UUID(), '7c0aadcb-876f-11f1-8fa9-b42e99811673', 'MOMENTUM', 'BEARISH', 0.60, 'DAILY', 'v1', NOW() - INTERVAL 2 DAY),
(UUID(), '7c0aae22-876f-11f1-8fa9-b42e99811673', 'BREAKOUT', 'BULLISH', 0.75, 'DAILY', 'v1', NOW() - INTERVAL 1 DAY),
(UUID(), '7c0aae72-876f-11f1-8fa9-b42e99811673', 'RSI_OVERBOUGHT', 'BEARISH', 0.55, 'DAILY', 'v1', NOW() - INTERVAL 4 DAY),
(UUID(), '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'BOLLINGER_SQUEEZE', 'NEUTRAL', 0.45, 'DAILY', 'v1', NOW() - INTERVAL 5 DAY),
(UUID(), '7c0aaf11-876f-11f1-8fa9-b42e99811673', 'ADX_STRONG_TREND', 'BEARISH', 0.68, 'DAILY', 'v1', NOW() - INTERVAL 2 DAY),
(UUID(), '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 'VOLUME_SPIKE', 'BULLISH', 0.72, 'DAILY', 'v1', NOW() - INTERVAL 1 DAY);

-- ──────────────────────────────────────────────────────────────────────────
-- 5. Scores (for StockDetail & Screening)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.score (score_id, instrument_id, score_type, value, model_version) VALUES
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 61.25, 'v1'),
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'FUNDAMENTAL', 78.50, 'v1'),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 55.00, 'v1'),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'FUNDAMENTAL', 72.00, 'v1'),
(UUID(), '7c0aad15-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 58.00, 'v1'),
(UUID(), '7c0aad72-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 42.00, 'v1'),
(UUID(), '7c0aadcb-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 38.00, 'v1'),
(UUID(), '7c0aae22-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 65.00, 'v1'),
(UUID(), '7c0aae72-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 50.00, 'v1'),
(UUID(), '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'TECHNICAL', 63.00, 'v1');

-- ──────────────────────────────────────────────────────────────────────────
-- 6. Recommendations (for StockDetail recommendations tab)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.recommendation (recommendation_id, instrument_id, action, thesis, confidence, confidence_level, horizon, model_version, status) VALUES
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'BUY', 'Strong fundamentals with ROE 22.5%, bullish MACD signal, and attractive valuation at P/E 18.5x', 0.75, 'HIGH', '3M', 'v1', 'ACTIVE'),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'BUY', 'Solid banking franchise with ROE 18.2%, reasonable P/E 15.3x, and digital banking growth', 0.70, 'HIGH', '3M', 'v1', 'ACTIVE'),
(UUID(), '7c0aad15-876f-11f1-8fa9-b42e99811673', 'BUY', 'Mandiri digital transformation driving efficiency, ROE 16.8%, P/E 16x', 0.65, 'MEDIUM', '3M', 'v1', 'ACTIVE'),
(UUID(), '7c0aad72-876f-11f1-8fa9-b42e99811673', 'HOLD', 'Stable telecom operator with moderate growth, P/E 12x but high D/E 0.9x', 0.55, 'MEDIUM', '6M', 'v1', 'ACTIVE'),
(UUID(), '7c0aadcb-876f-11f1-8fa9-b42e99811673', 'HOLD', 'Diversified conglomerate facing auto headwinds, P/E 14x reasonable but growth slowing', 0.50, 'MEDIUM', '6M', 'v1', 'ACTIVE'),
(UUID(), '7c0aae22-876f-11f1-8fa9-b42e99811673', 'BUY', 'Path to profitability achieved, revenue growth 25%, high risk but high reward', 0.60, 'MEDIUM', '12M', 'v1', 'ACTIVE'),
(UUID(), '7c0aae72-876f-11f1-8fa9-b42e99811673', 'HOLD', 'Defensive stock with ROE 19%, but P/E 20x is premium and growth only 6%', 0.50, 'MEDIUM', '6M', 'v1', 'ACTIVE'),
(UUID(), '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'BUY', 'Strong FMCG player with ROE 20%, margin improvement from lower commodity costs', 0.68, 'HIGH', '3M', 'v1', 'ACTIVE'),
(UUID(), '7c0aaf11-876f-11f1-8fa9-b42e99811673', 'HOLD', 'Coal sector facing ESG headwinds, but P/E 10x is cheap and ROE 15%', 0.45, 'LOW', '6M', 'v1', 'ACTIVE'),
(UUID(), '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 'SELL', 'High D/E 2.0x, P/E 25x expensive for ROE 8%, gold price dependency', 0.55, 'MEDIUM', '3M', 'v1', 'ACTIVE');

-- ──────────────────────────────────────────────────────────────────────────
-- 7. Forecasts (for StockDetail forecasts tab)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.forecast (forecast_id, instrument_id, target_variable, horizon, prediction_value, confidence_interval_low, confidence_interval_high, confidence, model_version) VALUES
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'PRICE', '1M', 9550.00, 9200.00, 9900.00, 0.70, 'v1'),
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'PRICE', '3M', 10200.00, 9500.00, 10900.00, 0.65, 'v1'),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'PRICE', '1M', 4750.00, 4600.00, 4900.00, 0.68, 'v1'),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'PRICE', '3M', 5100.00, 4800.00, 5400.00, 0.62, 'v1'),
(UUID(), '7c0aad72-876f-11f1-8fa9-b42e99811673', 'PRICE', '1M', 2850.00, 2750.00, 2950.00, 0.60, 'v1'),
(UUID(), '7c0aae22-876f-11f1-8fa9-b42e99811673', 'PRICE', '1M', 65.00, 58.00, 72.00, 0.55, 'v1'),
(UUID(), '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'PRICE', '3M', 10800.00, 10200.00, 11400.00, 0.65, 'v1'),
(UUID(), '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 'PRICE', '1M', 1750.00, 1650.00, 1850.00, 0.50, 'v1');

-- ──────────────────────────────────────────────────────────────────────────
-- 8. Portfolio Positions (for Portfolio view)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO portfolio.position (position_id, portfolio_id, instrument_id, quantity, average_cost, unrealized_pnl, position_type, status, opened_at)
SELECT UUID(), '7c0fa29a-876f-11f1-8fa9-b42e99811673', p.instrument_id, p.qty, p.avg_cost, (p.close - p.avg_cost) * p.qty, 'LONG', 'OPEN', NOW() - INTERVAL 30 DAY
FROM (
    SELECT '7c0aa99f-876f-11f1-8fa9-b42e99811673' AS instrument_id, 1000.0 AS qty, 8500.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
    UNION ALL
    SELECT '7c0aac8f-876f-11f1-8fa9-b42e99811673' AS instrument_id, 2000.0 AS qty, 4200.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
    UNION ALL
    SELECT '7c0aaec0-876f-11f1-8fa9-b42e99811673' AS instrument_id, 500.0 AS qty, 9500.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aaec0-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
    UNION ALL
    SELECT '7c0aae72-876f-11f1-8fa9-b42e99811673' AS instrument_id, 1500.0 AS qty, 3900.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aae72-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
) AS p;

-- Dividend Portfolio
INSERT INTO portfolio.position (position_id, portfolio_id, instrument_id, quantity, average_cost, unrealized_pnl, position_type, status, opened_at)
SELECT UUID(), '7c0fa5b4-876f-11f1-8fa9-b42e99811673', p.instrument_id, p.qty, p.avg_cost, (p.close - p.avg_cost) * p.qty, 'LONG', 'OPEN', NOW() - INTERVAL 45 DAY
FROM (
    SELECT '7c0aad72-876f-11f1-8fa9-b42e99811673' AS instrument_id, 3000.0 AS qty, 2600.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
    UNION ALL
    SELECT '7c0aadcb-876f-11f1-8fa9-b42e99811673' AS instrument_id, 800.0 AS qty, 4800.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aadcb-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
    UNION ALL
    SELECT '7c0aaf11-876f-11f1-8fa9-b42e99811673' AS instrument_id, 2000.0 AS qty, 2400.0 AS avg_cost, (SELECT close FROM data_ingestion.ohlcv_daily WHERE instrument_id = '7c0aaf11-876f-11f1-8fa9-b42e99811673' ORDER BY trade_date DESC LIMIT 1) AS close
) AS p;

-- ──────────────────────────────────────────────────────────────────────────
-- 9. Portfolio Accounts (required for orders)
-- ──────────────────────────────────────────────────────────────────────────
SET @acct1 = UUID();
SET @acct2 = UUID();
SET @acct3 = UUID();
INSERT INTO portfolio.portfolio_account (account_id, portfolio_id, broker_id, account_type, currency, status, opened_at) VALUES
(@acct1, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0bfed0-876f-11f1-8fa9-b42e99811673', 'CASH', 'IDR', 'ACTIVE', NOW() - INTERVAL 30 DAY),
(@acct2, '7c0fa5b4-876f-11f1-8fa9-b42e99811673', '7c0c00b8-876f-11f1-8fa9-b42e99811673', 'CASH', 'IDR', 'ACTIVE', NOW() - INTERVAL 30 DAY),
(@acct3, '7c0fa66f-876f-11f1-8fa9-b42e99811673', '7c0bfed0-876f-11f1-8fa9-b42e99811673', 'MARGIN', 'IDR', 'ACTIVE', NOW() - INTERVAL 30 DAY);

-- ──────────────────────────────────────────────────────────────────────────
-- 10. Trading: Decisions → Order Intents → Orders → Executions
-- ──────────────────────────────────────────────────────────────────────────
-- Decision 1: BUY BBCA
SET @dec1 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec1, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'BUY', 500, 9100, 'Strong fundamentals and bullish technical signals', 0.75, 'APPROVED', 'APPROVED', NOW() - INTERVAL 5 DAY);

-- Order Intent 1
SET @intent1 = UUID();
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent1, @dec1, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'BUY', 500, 9100, 'MARKET', 'Composite score 61.25, recommendation BUY', 'APPROVED', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY);

-- Order 1
SET @order1 = UUID();
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order1, 'ORD-2025-0001', @intent1, '7c0fa29a-876f-11f1-8fa9-b42e99811673', @acct1, '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'BUY', 'MARKET', 500, 500, 0, NULL, 'DAY', 'FILLED', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 4 DAY);

-- Execution 1
SET @exec1 = UUID();
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec1, 'EXEC-2025-0001', @order1, '7c0aa99f-876f-11f1-8fa9-b42e99811673', 500, 9111.33, 4555665, 4555.67, 227.78, 4555665, 'IDR', 'SETTLED', NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY);

-- Settlement 1
INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec1, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'T_PLUS_2', CURDATE() - INTERVAL 4 DAY, CURDATE() - INTERVAL 2 DAY, 500, 9111.33, 4555665, 4555.67, 227.78, 4555665, 'IDR', 'SETTLED', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 4 DAY);

-- Decision 2: BUY ICBP
SET @dec2 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec2, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'BUY', 200, 10000, 'Margin improvement and strong FMCG position', 0.68, 'APPROVED', 'APPROVED', NOW() - INTERVAL 3 DAY);

SET @intent2 = UUID();
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent2, @dec2, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'BUY', 200, 10000, 'LIMIT', 'ROE 20%, P/E 17x, BUY recommendation', 'APPROVED', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY);

SET @order2 = UUID();
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order2, 'ORD-2025-0002', @intent2, '7c0fa29a-876f-11f1-8fa9-b42e99811673', @acct1, '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'BUY', 'LIMIT', 200, 200, 0, 10000, 'DAY', 'FILLED', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY);

SET @exec2 = UUID();
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec2, 'EXEC-2025-0002', @order2, '7c0aaec0-876f-11f1-8fa9-b42e99811673', 200, 10050, 2010000, 2010, 100.50, 2010000, 'IDR', 'SETTLED', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY);

INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec2, '7c0fa29a-876f-11f1-8fa9-b42e99811673', '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'T_PLUS_2', CURDATE() - INTERVAL 2 DAY, CURDATE(), 200, 10050, 2010000, 2010, 100.50, 2010000, 'IDR', 'SETTLED', NOW(), NOW() - INTERVAL 2 DAY);

-- Decision 3: SELL ANTM
SET @dec3 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec3, '7c0fa66f-876f-11f1-8fa9-b42e99811673', '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 'SELL', 1000, 1800, 'High D/E and expensive P/E for low ROE', 0.55, 'APPROVED', 'APPROVED', NOW() - INTERVAL 1 DAY);

SET @intent3 = UUID();
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent3, @dec3, '7c0fa66f-876f-11f1-8fa9-b42e99811673', '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 'SELL', 1000, 1800, 'MARKET', 'SELL recommendation, risk management', 'APPROVED', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY);

SET @order3 = UUID();
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order3, 'ORD-2025-0003', @intent3, '7c0fa66f-876f-11f1-8fa9-b42e99811673', @acct3, '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 'SELL', 'MARKET', 1000, 1000, 0, NULL, 'DAY', 'FILLED', NOW() - INTERVAL 1 DAY, NOW(), NOW() - INTERVAL 1 DAY, NOW());

SET @exec3 = UUID();
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec3, 'EXEC-2025-0003', @order3, '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 1000, 1795, 1795000, 1795, 89.75, 1795000, 'IDR', 'PENDING_SETTLEMENT', NOW(), NOW());

-- ──────────────────────────────────────────────────────────────────────────
-- 10. Risk Limits & Events
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO risk.risk_limit (risk_limit_id, portfolio_id, limit_type, limit_value, limit_unit, time_horizon, confidence_level, status, effective_from) VALUES
(UUID(), '7c0fa29a-876f-11f1-8fa9-b42e99811673', 'MAX_SINGLE_POSITION', 25.00, 'PERCENT', 'DAILY', 0.95, 'ACTIVE', NOW() - INTERVAL 30 DAY),
(UUID(), '7c0fa29a-876f-11f1-8fa9-b42e99811673', 'MAX_VAR', 5.00, 'PERCENT', 'DAILY', 0.99, 'ACTIVE', NOW() - INTERVAL 30 DAY),
(UUID(), '7c0fa29a-876f-11f1-8fa9-b42e99811673', 'MAX_DRAWDOWN', 15.00, 'PERCENT', 'MONTHLY', 0.95, 'ACTIVE', NOW() - INTERVAL 30 DAY),
(UUID(), '7c0fa5b4-876f-11f1-8fa9-b42e99811673', 'MAX_SINGLE_POSITION', 20.00, 'PERCENT', 'DAILY', 0.95, 'ACTIVE', NOW() - INTERVAL 30 DAY),
(UUID(), '7c0fa66f-876f-11f1-8fa9-b42e99811673', 'MAX_SINGLE_POSITION', 30.00, 'PERCENT', 'DAILY', 0.90, 'ACTIVE', NOW() - INTERVAL 30 DAY);

INSERT INTO risk.risk_event (risk_event_id, portfolio_id, event_type, severity, description, current_value, limit_value, detected_at, status) VALUES
(UUID(), '7c0fa29a-876f-11f1-8fa9-b42e99811673', 'WARNING', 'MEDIUM', 'BBCA position approaching 20% concentration limit', 18.5, 25.00, NOW() - INTERVAL 2 DAY, 'ACKNOWLEDGED'),
(UUID(), '7c0fa66f-876f-11f1-8fa9-b42e99811673', 'LIMIT_BREACH', 'HIGH', 'Single position exceeded 30% limit for ANTM', 32.5, 30.00, NOW() - INTERVAL 1 DAY, 'OPEN');

-- ──────────────────────────────────────────────────────────────────────────
-- 11. AI Analysis (for AI Engine)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO ai_engine.ai_analysis (analysis_id, analysis_type, instrument_id, sentiment_score, sentiment_label, entities, events, pattern_type, pattern_confidence, anomaly_score, anomaly_type, summary, created_at) VALUES
(UUID(), 'SENTIMENT', '7c0aa99f-876f-11f1-8fa9-b42e99811673', 0.75, 'POSITIVE', '["BBCA","BCA","Q2 2025"]', '["EARNINGS_BEAT"]', NULL, NULL, NULL, NULL, 'BBCA Q2 2025 earnings beat expectations with 18% YoY profit growth', NOW() - INTERVAL 1 DAY),
(UUID(), 'PATTERN', '7c0aa99f-876f-11f1-8fa9-b42e99811673', NULL, NULL, NULL, NULL, 'DOUBLE_BOTTOM', 0.72, NULL, NULL, 'Double bottom pattern detected at 8950 support level', NOW() - INTERVAL 2 DAY),
(UUID(), 'ANOMALY', '7c0aae22-876f-11f1-8fa9-b42e99811673', NULL, NULL, NULL, NULL, NULL, NULL, 3.5, 'SPIKE', 'Volume spike detected: 3.5 standard deviations above mean', NOW() - INTERVAL 1 DAY),
(UUID(), 'SENTIMENT', '7c0aadcb-876f-11f1-8fa9-b42e99811673', -0.45, 'NEGATIVE', '["ASII","Astra","auto market"]', '["SALES_DECLINE"]', NULL, NULL, NULL, NULL, 'ASII faces declining auto sales amid competitive pressure', NOW() - INTERVAL 3 DAY),
(UUID(), 'PATTERN', '7c0aaf5e-876f-11f1-8fa9-b42e99811673', NULL, NULL, NULL, NULL, 'DOUBLE_TOP', 0.68, NULL, NULL, 'Double top pattern detected at 1850 resistance level', NOW() - INTERVAL 4 DAY),
(UUID(), 'ANOMALY', '7c0aa99f-876f-11f1-8fa9-b42e99811673', NULL, NULL, NULL, NULL, NULL, NULL, 2.8, 'SPIKE', 'Price spike detected: 2.8 standard deviations on positive earnings', NOW() - INTERVAL 1 DAY);

-- ──────────────────────────────────────────────────────────────────────────
-- 12. Corporate Actions
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO market_master.corporate_action (corporate_action_id, instrument_id, action_type, announcement_date, ex_date, record_date, payment_date, effective_date, ratio, amount, currency, source) VALUES
(UUID(), '7c0aa99f-876f-11f1-8fa9-b42e99811673', 'DIVIDEND', '2025-04-15', '2025-05-10', '2025-05-12', '2025-05-20', '2025-05-20', NULL, 500.00, 'IDR', 'IDX Filing'),
(UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673', 'DIVIDEND', '2025-04-20', '2025-05-15', '2025-05-17', '2025-05-25', '2025-05-25', NULL, 250.00, 'IDR', 'IDX Filing'),
(UUID(), '7c0aad72-876f-11f1-8fa9-b42e99811673', 'DIVIDEND', '2025-04-10', '2025-05-05', '2025-05-07', '2025-05-15', '2025-05-15', NULL, 150.00, 'IDR', 'IDX Filing'),
(UUID(), '7c0aae22-876f-11f1-8fa9-b42e99811673', 'STOCK_SPLIT', '2025-03-01', '2025-03-15', '2025-03-17', NULL, '2025-03-20', 2.00000000, NULL, 'IDR', 'IDX Filing'),
(UUID(), '7c0aaec0-876f-11f1-8fa9-b42e99811673', 'DIVIDEND', '2025-04-25', '2025-05-20', '2025-05-22', '2025-05-30', '2025-05-30', NULL, 300.00, 'IDR', 'IDX Filing');

-- ──────────────────────────────────────────────────────────────────────────
-- 13. Index Membership (IHSG / IDX30)
-- ──────────────────────────────────────────────────────────────────────────
-- Get or create an index_id
SET @idx_id = (SELECT index_id FROM market_master.index_master LIMIT 1);

INSERT INTO market_master.index_membership (index_id, instrument_id, effective_date, weight, shares)
SELECT @idx_id, instrument_id, '2025-01-01', weight, shares FROM (
    SELECT '7c0aa99f-876f-11f1-8fa9-b42e99811673' AS instrument_id, 15.50 AS weight, 50000.0 AS shares
    UNION ALL SELECT '7c0aac8f-876f-11f1-8fa9-b42e99811673', 12.30, 80000.0
    UNION ALL SELECT '7c0aad15-876f-11f1-8fa9-b42e99811673', 10.80, 60000.0
    UNION ALL SELECT '7c0aad72-876f-11f1-8fa9-b42e99811673', 8.50, 100000.0
    UNION ALL SELECT '7c0aadcb-876f-11f1-8fa9-b42e99811673', 7.20, 40000.0
    UNION ALL SELECT '7c0aae72-876f-11f1-8fa9-b42e99811673', 6.80, 50000.0
    UNION ALL SELECT '7c0aaec0-876f-11f1-8fa9-b42e99811673', 6.50, 30000.0
    UNION ALL SELECT '7c0aaf11-876f-11f1-8fa9-b42e99811673', 5.40, 45000.0
    UNION ALL SELECT '7c0aaf5e-876f-11f1-8fa9-b42e99811673', 4.20, 35000.0
    UNION ALL SELECT '7c0aae22-876f-11f1-8fa9-b42e99811673', 3.80, 200000.0
) AS m
WHERE @idx_id IS NOT NULL
ON DUPLICATE KEY UPDATE weight = VALUES(weight);

-- ──────────────────────────────────────────────────────────────────────────
-- 14. Feature Values (for technical features storage)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.feature_value (feature_value_id, feature_id, instrument_id, value, quality_score, model_version, calculated_at)
SELECT UUID(), fd.feature_id, '7c0aa99f-876f-11f1-8fa9-b42e99811673', 9678.53, 95, 'v1', NOW()
FROM analytics.feature_definition fd
WHERE fd.feature_name = 'SMA_20'
LIMIT 1;

INSERT INTO analytics.feature_value (feature_value_id, feature_id, instrument_id, value, quality_score, model_version, calculated_at)
SELECT UUID(), fd.feature_id, '7c0aa99f-876f-11f1-8fa9-b42e99811673', 39.01, 90, 'v1', NOW()
FROM analytics.feature_definition fd
WHERE fd.feature_name = 'RSI_14'
LIMIT 1;
