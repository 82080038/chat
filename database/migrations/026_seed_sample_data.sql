-- ============================================================================
-- 015_seed_sample_data.sql
-- Purpose: Seed sample OHLCV data + financial metrics for testing
-- ============================================================================

USE platform;

-- ──────────────────────────────────────────────────────────────────────────
-- Seed OHLCV daily data: 90 trading days for 10 instruments
-- ──────────────────────────────────────────────────────────────────────────

-- BBCA
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID() AS ohlcv_id,
    '7c0aa99f-876f-11f1-8fa9-b42e99811673' AS instrument_id,
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY) AS trade_date,
    ROUND(9000 + (seq.n * 15) + (RAND() * 200 - 100), 4) AS open,
    ROUND(9050 + (seq.n * 15) + (RAND() * 200), 4) AS high,
    ROUND(8950 + (seq.n * 15) + (RAND() * 200 - 200), 4) AS low,
    ROUND(9000 + (seq.n * 16) + (RAND() * 150 - 50), 4) AS close,
    FLOOR(5000000 + RAND() * 10000000) AS volume,
    'SEED' AS source,
    NOW(6) AS created_at
FROM (
    SELECT @i := @i + 1 AS n
    FROM information_schema.columns a
    CROSS JOIN (SELECT @i := 0) AS init
    LIMIT 90
) AS seq
WHERE seq.n > 0;

-- BBRI
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aac8f-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(4500 + (seq.n * 8) + (RAND() * 100 - 50), 4),
    ROUND(4550 + (seq.n * 8) + (RAND() * 100), 4),
    ROUND(4450 + (seq.n * 8) + (RAND() * 100 - 100), 4),
    ROUND(4500 + (seq.n * 9) + (RAND() * 80 - 30), 4),
    FLOOR(8000000 + RAND() * 15000000),
    'SEED', NOW(6)
FROM (SELECT @j := @j + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @j := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- BMRI
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aad15-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(5500 + (seq.n * 10) + (RAND() * 120 - 60), 4),
    ROUND(5550 + (seq.n * 10) + (RAND() * 120), 4),
    ROUND(5450 + (seq.n * 10) + (RAND() * 120 - 120), 4),
    ROUND(5500 + (seq.n * 11) + (RAND() * 100 - 40), 4),
    FLOOR(6000000 + RAND() * 12000000),
    'SEED', NOW(6)
FROM (SELECT @k := @k + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @k := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- TLKM
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aad72-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(2800 + (seq.n * 3) + (RAND() * 60 - 30), 4),
    ROUND(2820 + (seq.n * 3) + (RAND() * 60), 4),
    ROUND(2780 + (seq.n * 3) + (RAND() * 60 - 60), 4),
    ROUND(2800 + (seq.n * 4) + (RAND() * 50 - 20), 4),
    FLOOR(10000000 + RAND() * 20000000),
    'SEED', NOW(6)
FROM (SELECT @l := @l + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @l := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- ASII
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aadcb-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(5200 + (seq.n * 6) + (RAND() * 100 - 50), 4),
    ROUND(5250 + (seq.n * 6) + (RAND() * 100), 4),
    ROUND(5150 + (seq.n * 6) + (RAND() * 100 - 100), 4),
    ROUND(5200 + (seq.n * 7) + (RAND() * 80 - 30), 4),
    FLOOR(4000000 + RAND() * 8000000),
    'SEED', NOW(6)
FROM (SELECT @m := @m + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @m := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- GOTO
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aae22-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(60 + (seq.n * 0.5) + (RAND() * 5 - 2.5), 4),
    ROUND(62 + (seq.n * 0.5) + (RAND() * 5), 4),
    ROUND(58 + (seq.n * 0.5) + (RAND() * 5 - 5), 4),
    ROUND(60 + (seq.n * 0.6) + (RAND() * 4 - 1.5), 4),
    FLOOR(50000000 + RAND() * 100000000),
    'SEED', NOW(6)
FROM (SELECT @o := @o + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @o := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- UNVR
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aae72-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(4200 + (seq.n * 5) + (RAND() * 80 - 40), 4),
    ROUND(4230 + (seq.n * 5) + (RAND() * 80), 4),
    ROUND(4170 + (seq.n * 5) + (RAND() * 80 - 80), 4),
    ROUND(4200 + (seq.n * 6) + (RAND() * 60 - 25), 4),
    FLOOR(3000000 + RAND() * 6000000),
    'SEED', NOW(6)
FROM (SELECT @p := @p + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @p := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- ICBP
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aaec0-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(10000 + (seq.n * 12) + (RAND() * 200 - 100), 4),
    ROUND(10050 + (seq.n * 12) + (RAND() * 200), 4),
    ROUND(9950 + (seq.n * 12) + (RAND() * 200 - 200), 4),
    ROUND(10000 + (seq.n * 13) + (RAND() * 150 - 50), 4),
    FLOOR(2000000 + RAND() * 4000000),
    'SEED', NOW(6)
FROM (SELECT @q := @q + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @q := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- ADRO
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aaf11-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(2600 + (seq.n * 4) + (RAND() * 80 - 40), 4),
    ROUND(2630 + (seq.n * 4) + (RAND() * 80), 4),
    ROUND(2570 + (seq.n * 4) + (RAND() * 80 - 80), 4),
    ROUND(2600 + (seq.n * 5) + (RAND() * 60 - 25), 4),
    FLOOR(7000000 + RAND() * 14000000),
    'SEED', NOW(6)
FROM (SELECT @r := @r + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @r := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- ANTM
INSERT INTO data_ingestion.ohlcv_daily (ohlcv_id, instrument_id, trade_date, open, high, low, close, volume, source, created_at)
SELECT
    UUID(), '7c0aaf5e-876f-11f1-8fa9-b42e99811673',
    DATE_SUB(CURDATE(), INTERVAL seq.n DAY),
    ROUND(1800 + (seq.n * 3) + (RAND() * 60 - 30), 4),
    ROUND(1820 + (seq.n * 3) + (RAND() * 60), 4),
    ROUND(1780 + (seq.n * 3) + (RAND() * 60 - 60), 4),
    ROUND(1800 + (seq.n * 4) + (RAND() * 50 - 20), 4),
    FLOOR(9000000 + RAND() * 18000000),
    'SEED', NOW(6)
FROM (SELECT @s := @s + 1 AS n FROM information_schema.columns a CROSS JOIN (SELECT @s := 0) AS init LIMIT 90) AS seq
WHERE seq.n > 0;

-- ──────────────────────────────────────────────────────────────────────────
-- Seed financial metrics for screening engine
-- ──────────────────────────────────────────────────────────────────────────

-- Get issuer IDs for each instrument
-- BBCA issuer
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 22.50, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s
JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 18.50, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s
JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.80, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s
JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 12.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s
JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aa99f-876f-11f1-8fa9-b42e99811673';

-- BBRI
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 18.20, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 15.30, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.50, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 8.50, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aac8f-876f-11f1-8fa9-b42e99811673';

-- BMRI
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 16.80, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad15-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 16.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad15-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.60, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad15-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 10.50, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad15-876f-11f1-8fa9-b42e99811673';

-- TLKM
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 14.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 12.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.90, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 3.20, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aad72-876f-11f1-8fa9-b42e99811673';

-- ASII
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 12.50, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aadcb-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 14.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aadcb-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 1.20, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aadcb-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 5.50, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aadcb-876f-11f1-8fa9-b42e99811673';

-- GOTO
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', -5.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae22-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 999.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae22-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.30, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae22-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 25.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae22-876f-11f1-8fa9-b42e99811673';

-- UNVR
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 19.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae72-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 20.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae72-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.40, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae72-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 6.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aae72-876f-11f1-8fa9-b42e99811673';

-- ICBP
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 20.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaec0-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 17.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaec0-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 0.70, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaec0-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 9.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaec0-876f-11f1-8fa9-b42e99811673';

-- ADRO
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 15.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf11-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 10.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf11-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 1.50, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf11-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 7.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf11-876f-11f1-8fa9-b42e99811673';

-- ANTM
INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'ROE', 8.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf5e-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'PE_RATIO', 25.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf5e-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'DEBT_TO_EQUITY', 2.00, 'RATIO', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf5e-876f-11f1-8fa9-b42e99811673';

INSERT INTO fundamental.financial_metric (metric_id, issuer_id, metric_type, value, unit, fiscal_period_type, fiscal_year, fiscal_quarter, calculation_version, available_time, calculated_at)
SELECT UUID(), s.issuer_id, 'REVENUE_GROWTH', 15.00, 'PERCENT', 'FY', 2024, NULL, 'v1', NOW(6), NOW(6)
FROM market_master.security s JOIN market_master.instrument i ON i.security_id = s.security_id
WHERE i.instrument_id = '7c0aaf5e-876f-11f1-8fa9-b42e99811673';
