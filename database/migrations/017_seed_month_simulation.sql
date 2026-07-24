-- ──────────────────────────────────────────────────────────────────────────
-- 017: Seed 1-Month Simulation Data (22 trading days)
-- Each day has: signals, scores, recommendations, news, risk assessments,
-- AI analysis, and trading activity (decisions → orders → executions)
-- ──────────────────────────────────────────────────────────────────────────

USE platform;

SET @pf_growth = '7c0fa29a-876f-11f1-8fa9-b42e99811673';
SET @pf_div    = '7c0fa5b4-876f-11f1-8fa9-b42e99811673';
SET @pf_spec   = '7c0fa66f-876f-11f1-8fa9-b42e99811673';

SET @acct_growth = (SELECT account_id FROM portfolio.portfolio_account WHERE portfolio_id = @pf_growth LIMIT 1);
SET @acct_div    = (SELECT account_id FROM portfolio.portfolio_account WHERE portfolio_id = @pf_div LIMIT 1);
SET @acct_spec   = (SELECT account_id FROM portfolio.portfolio_account WHERE portfolio_id = @pf_spec LIMIT 1);

SET @bbca  = '7c0aa99f-876f-11f1-8fa9-b42e99811673';
SET @bbri  = '7c0aac8f-876f-11f1-8fa9-b42e99811673';
SET @bmri  = '7c0aad15-876f-11f1-8fa9-b42e99811673';
SET @tlkm  = '7c0aad72-876f-11f1-8fa9-b42e99811673';
SET @asii  = '7c0aadcb-876f-11f1-8fa9-b42e99811673';
SET @goto  = '7c0aae22-876f-11f1-8fa9-b42e99811673';
SET @unvr  = '7c0aae72-876f-11f1-8fa9-b42e99811673';
SET @icbp  = '7c0aaec0-876f-11f1-8fa9-b42e99811673';
SET @adro  = '7c0aaf11-876f-11f1-8fa9-b42e99811673';
SET @antm  = '7c0aaf5e-876f-11f1-8fa9-b42e99811673';

-- Clean previous sim data
DELETE FROM analytics.signal WHERE model_version = 'v2-sim';
DELETE FROM analytics.score WHERE model_version = 'v2-sim';
DELETE FROM analytics.recommendation WHERE model_version = 'v2-sim';
DELETE FROM analytics.forecast WHERE model_version = 'v2-sim';
DELETE FROM ai_engine.ai_analysis WHERE source_type = 'sim-engine';
DELETE FROM governance.audit_log WHERE actor_type = 'SYSTEM' AND action LIKE 'SIM_%';
DELETE FROM settlement.settlement WHERE execution_id IN (SELECT execution_id FROM trading.execution WHERE execution_ref LIKE 'EXEC-SIM-%');
DELETE FROM trading.execution WHERE execution_ref LIKE 'EXEC-SIM-%';
DELETE FROM trading.`order` WHERE order_ref LIKE 'ORD-SIM-%';
DELETE FROM trading.order_intent WHERE reason LIKE 'sim-%';
DELETE FROM trading.decision WHERE reason LIKE 'sim-%';

-- ──────────────────────────────────────────────────────────────────────────
-- 1. Daily Signals for 22 trading days × 10 instruments
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.signal (signal_id, instrument_id, signal_type, direction, strength, timeframe, model_version, created_at, valid_from)
SELECT
    UUID(),
    instr_id,
    signal_type,
    direction,
    strength,
    '1D',
    'v2-sim',
    DATE_SUB(NOW(), INTERVAL day_offset DAY),
    DATE_SUB(NOW(), INTERVAL day_offset DAY)
FROM (
    SELECT @bbca AS instr_id, 'TREND_FOLLOWING' AS signal_type, 'BULLISH' AS direction, 65.0 AS strength, 22 AS day_offset
    UNION ALL SELECT @bbca, 'MACD_CROSSOVER', 'BULLISH', 70.0, 21
    UNION ALL SELECT @bbca, 'RSI_OVERSOLD', 'BULLISH', 55.0, 20
    UNION ALL SELECT @bbca, 'MOMENTUM', 'BULLISH', 62.0, 19
    UNION ALL SELECT @bbca, 'BREAKOUT', 'BULLISH', 68.0, 18
    UNION ALL SELECT @bbca, 'VOLUME_SPIKE', 'BULLISH', 60.0, 17
    UNION ALL SELECT @bbca, 'ADX_STRONG_TREND', 'BULLISH', 72.0, 16
    UNION ALL SELECT @bbca, 'BOLLINGER_SQUEEZE', 'NEUTRAL', 45.0, 15
    UNION ALL SELECT @bbca, 'MEAN_REVERSION', 'BEARISH', 40.0, 14
    UNION ALL SELECT @bbca, 'TREND_FOLLOWING', 'BULLISH', 66.0, 13
    UNION ALL SELECT @bbca, 'MACD_CROSSOVER', 'BULLISH', 68.0, 12
    UNION ALL SELECT @bbca, 'MOMENTUM', 'BULLISH', 64.0, 11
    UNION ALL SELECT @bbca, 'BREAKOUT', 'BULLISH', 71.0, 10
    UNION ALL SELECT @bbca, 'RSI_OVERBOUGHT', 'BEARISH', 50.0, 9
    UNION ALL SELECT @bbca, 'ADX_STRONG_TREND', 'BULLISH', 73.0, 8
    UNION ALL SELECT @bbca, 'TREND_FOLLOWING', 'BULLISH', 67.0, 7
    UNION ALL SELECT @bbca, 'VOLUME_SPIKE', 'BULLISH', 63.0, 6
    UNION ALL SELECT @bbca, 'MACD_CROSSOVER', 'BULLISH', 69.0, 5
    UNION ALL SELECT @bbca, 'MOMENTUM', 'BULLISH', 65.0, 4
    UNION ALL SELECT @bbca, 'BREAKOUT', 'BULLISH', 70.0, 3
    UNION ALL SELECT @bbca, 'TREND_FOLLOWING', 'BULLISH', 68.0, 2
    UNION ALL SELECT @bbca, 'ADX_STRONG_TREND', 'BULLISH', 74.0, 1

    UNION ALL SELECT @bbri, 'TREND_FOLLOWING', 'BULLISH', 60.0, 22
    UNION ALL SELECT @bbri, 'MACD_CROSSOVER', 'BULLISH', 63.0, 20
    UNION ALL SELECT @bbri, 'MOMENTUM', 'BULLISH', 58.0, 18
    UNION ALL SELECT @bbri, 'BREAKOUT', 'BULLISH', 65.0, 16
    UNION ALL SELECT @bbri, 'RSI_OVERSOLD', 'BULLISH', 52.0, 14
    UNION ALL SELECT @bbri, 'ADX_STRONG_TREND', 'BULLISH', 68.0, 12
    UNION ALL SELECT @bbri, 'TREND_FOLLOWING', 'BULLISH', 62.0, 10
    UNION ALL SELECT @bbri, 'MACD_CROSSOVER', 'BULLISH', 66.0, 8
    UNION ALL SELECT @bbri, 'MOMENTUM', 'BULLISH', 60.0, 6
    UNION ALL SELECT @bbri, 'BREAKOUT', 'BULLISH', 64.0, 4
    UNION ALL SELECT @bbri, 'TREND_FOLLOWING', 'BULLISH', 61.0, 2

    UNION ALL SELECT @icbp, 'MEAN_REVERSION', 'BULLISH', 55.0, 22
    UNION ALL SELECT @icbp, 'TREND_FOLLOWING', 'BULLISH', 58.0, 18
    UNION ALL SELECT @icbp, 'MACD_CROSSOVER', 'BULLISH', 60.0, 14
    UNION ALL SELECT @icbp, 'MOMENTUM', 'BULLISH', 56.0, 10
    UNION ALL SELECT @icbp, 'BREAKOUT', 'BULLISH', 62.0, 6
    UNION ALL SELECT @icbp, 'ADX_STRONG_TREND', 'BULLISH', 65.0, 2

    UNION ALL SELECT @antm, 'RSI_OVERBOUGHT', 'BEARISH', 48.0, 22
    UNION ALL SELECT @antm, 'MEAN_REVERSION', 'BEARISH', 42.0, 18
    UNION ALL SELECT @antm, 'MACD_CROSSOVER', 'BEARISH', 38.0, 14
    UNION ALL SELECT @antm, 'TREND_FOLLOWING', 'BEARISH', 35.0, 10
    UNION ALL SELECT @antm, 'MOMENTUM', 'BEARISH', 40.0, 6
    UNION ALL SELECT @antm, 'BREAKOUT', 'BEARISH', 37.0, 2

    UNION ALL SELECT @tlkm, 'TREND_FOLLOWING', 'NEUTRAL', 50.0, 22
    UNION ALL SELECT @tlkm, 'MEAN_REVERSION', 'BULLISH', 52.0, 15
    UNION ALL SELECT @tlkm, 'MACD_CROSSOVER', 'NEUTRAL', 48.0, 8
    UNION ALL SELECT @tlkm, 'MOMENTUM', 'BULLISH', 55.0, 1

    UNION ALL SELECT @adro, 'TREND_FOLLOWING', 'BULLISH', 63.0, 20
    UNION ALL SELECT @adro, 'BREAKOUT', 'BULLISH', 67.0, 12
    UNION ALL SELECT @adro, 'ADX_STRONG_TREND', 'BULLISH', 70.0, 4

    UNION ALL SELECT @unvr, 'MEAN_REVERSION', 'BULLISH', 54.0, 18
    UNION ALL SELECT @unvr, 'TREND_FOLLOWING', 'BULLISH', 57.0, 10
    UNION ALL SELECT @unvr, 'MOMENTUM', 'BULLISH', 56.0, 2

    UNION ALL SELECT @asii, 'MACD_CROSSOVER', 'BEARISH', 42.0, 20
    UNION ALL SELECT @asii, 'TREND_FOLLOWING', 'BEARISH', 38.0, 10
    UNION ALL SELECT @asii, 'RSI_OVERBOUGHT', 'BEARISH', 45.0, 2

    UNION ALL SELECT @goto, 'BREAKOUT', 'BULLISH', 58.0, 15
    UNION ALL SELECT @goto, 'MOMENTUM', 'BULLISH', 54.0, 5

    UNION ALL SELECT @bmri, 'TREND_FOLLOWING', 'BULLISH', 61.0, 18
    UNION ALL SELECT @bmri, 'MACD_CROSSOVER', 'BULLISH', 64.0, 9
    UNION ALL SELECT @bmri, 'ADX_STRONG_TREND', 'BULLISH', 67.0, 1
) AS signals_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 2. Daily Scores for 22 days × 5 key instruments
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.score (score_id, instrument_id, score_type, value, model_version, created_at)
SELECT
    UUID(),
    instr_id,
    'TECHNICAL',
    score_val,
    'v2-sim',
    DATE_SUB(NOW(), INTERVAL day_offset DAY)
FROM (
    SELECT @bbca AS instr_id, 62.0 + (22 - day_offset) * 0.5 AS score_val, day_offset FROM (
        SELECT 22 AS day_offset UNION ALL SELECT 20 UNION ALL SELECT 18 UNION ALL SELECT 16
        UNION ALL SELECT 14 UNION ALL SELECT 12 UNION ALL SELECT 10 UNION ALL SELECT 8
        UNION ALL SELECT 6 UNION ALL SELECT 4 UNION ALL SELECT 2 UNION ALL SELECT 1
    ) AS d1
    UNION ALL
    SELECT @bbri, 58.0 + (22 - day_offset) * 0.4, day_offset FROM (
        SELECT 22 AS day_offset UNION ALL SELECT 18 UNION ALL SELECT 14 UNION ALL SELECT 10
        UNION ALL SELECT 6 UNION ALL SELECT 2
    ) AS d2
    UNION ALL
    SELECT @icbp, 55.0 + (22 - day_offset) * 0.3, day_offset FROM (
        SELECT 22 AS day_offset UNION ALL SELECT 14 UNION ALL SELECT 6 UNION ALL SELECT 2
    ) AS d3
    UNION ALL
    SELECT @antm, 40.0 - (22 - day_offset) * 0.2, day_offset FROM (
        SELECT 22 AS day_offset UNION ALL SELECT 14 UNION ALL SELECT 6 UNION ALL SELECT 2
    ) AS d4
    UNION ALL
    SELECT @adro, 60.0 + (22 - day_offset) * 0.35, day_offset FROM (
        SELECT 20 AS day_offset UNION ALL SELECT 12 UNION ALL SELECT 4
    ) AS d5
) AS score_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 3. Daily Recommendations (action: BUY/HOLD/SELL)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.recommendation (recommendation_id, instrument_id, action, thesis, confidence, confidence_level, horizon, model_version, status, created_at)
SELECT
    UUID(),
    instr_id,
    action,
    thesis,
    confidence,
    CASE WHEN confidence >= 70 THEN 'HIGH' WHEN confidence >= 55 THEN 'MEDIUM' ELSE 'LOW' END,
    '1M',
    'v2-sim',
    'ACTIVE',
    DATE_SUB(NOW(), INTERVAL day_offset DAY)
FROM (
    SELECT @bbca AS instr_id, 'BUY' AS action, 'Strong upward trend with bullish MACD crossover and ADX strength. Target 9500.' AS thesis, 72.0 AS confidence, 22 AS day_offset
    UNION ALL SELECT @bbca, 'BUY', 'Continued momentum and volume confirmation. Target 9600.', 74.0, 18
    UNION ALL SELECT @bbca, 'BUY', 'Breakout above resistance with strong ADX. Target 9700.', 76.0, 14
    UNION ALL SELECT @bbca, 'HOLD', 'RSI approaching overbought, moderate accumulation advised.', 70.0, 10
    UNION ALL SELECT @bbca, 'BUY', 'Fresh breakout signal with volume spike. Target 9900.', 75.0, 6
    UNION ALL SELECT @bbca, 'HOLD', 'Strong trend continues, ADX very strong. Accumulate on dips.', 71.0, 2
    UNION ALL SELECT @bbca, 'BUY', 'Trend following confirms continuation. Target 10100.', 73.0, 1

    UNION ALL SELECT @bbri, 'BUY', 'Bullish trend with MACD crossover. Target 4800.', 65.0, 20
    UNION ALL SELECT @bbri, 'HOLD', 'Steady momentum building. Accumulate.', 62.0, 14
    UNION ALL SELECT @bbri, 'BUY', 'Breakout confirmed with volume. Target 5000.', 68.0, 8
    UNION ALL SELECT @bbri, 'HOLD', 'Continued upward trend. Target 5100.', 64.0, 2

    UNION ALL SELECT @icbp, 'HOLD', 'Mean reversion opportunity, ROE strong. Target 10500.', 60.0, 18
    UNION ALL SELECT @icbp, 'BUY', 'Momentum building, fundamentals solid. Target 10800.', 63.0, 10
    UNION ALL SELECT @icbp, 'HOLD', 'Steady growth trajectory. Target 11000.', 61.0, 2

    UNION ALL SELECT @antm, 'SELL', 'Bearish trend, high D/E ratio concern. Target 1700.', 52.0, 18
    UNION ALL SELECT @antm, 'SELL', 'MACD bearish crossover, momentum weakening. Target 1650.', 55.0, 10
    UNION ALL SELECT @antm, 'SELL', 'Continued bearish signals, RSI overbought. Target 1600.', 58.0, 2

    UNION ALL SELECT @adro, 'BUY', 'Breakout with strong volume. Target 2900.', 66.0, 18
    UNION ALL SELECT @adro, 'HOLD', 'ADX very strong, trend confirmed. Accumulate.', 63.0, 10
    UNION ALL SELECT @adro, 'BUY', 'Momentum continuation, coal sector bullish. Target 3100.', 68.0, 2
) AS rec_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 4. Daily News Items spread across 22 days
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO fundamental.news_item (news_id, title, content_summary, source, source_url, published_at, available_time, sentiment_score, sentiment_label, language)
SELECT
    UUID(),
    title,
    summary,
    source,
    CONCAT('https://example.com/news/', UUID()),
    DATE_SUB(NOW(), INTERVAL day_offset DAY),
    DATE_SUB(NOW(), INTERVAL day_offset DAY),
    CASE sentiment WHEN 'POSITIVE' THEN 0.7 WHEN 'NEGATIVE' THEN -0.5 ELSE 0.0 END,
    sentiment,
    'id'
FROM (
    SELECT @bbca AS instr_id, 'BBCA Q2 profit rises 12% YoY, NIM stable at 5.2%' AS title, 'Bank Central Asia reported strong Q2 earnings with net profit growth driven by higher fee income and stable loan growth.' AS summary, 'Kontan' AS source, 'POSITIVE' AS sentiment, 22 AS day_offset
    UNION ALL SELECT @bbca, 'BBCA digital banking users surpass 20 million', 'BCA digital platform continues to grow, now serving over 20M mobile users with transaction volume up 35% YoY.', 'Bisnis Indonesia', 'POSITIVE', 19
    UNION ALL SELECT @bbca, 'BBCA announces interim dividend of Rp 150/share', 'Board approves interim dividend reflecting strong capital position and confidence in sustained earnings.', 'CNBC Indonesia', 'POSITIVE', 15
    UNION ALL SELECT @bbca, 'Analysts upgrade BBCA target to Rp 10,000', 'Multiple brokerages raise target prices citing improving asset quality and digital banking momentum.', 'Reuters', 'POSITIVE', 11
    UNION ALL SELECT @bbca, 'BBCA loan growth reaches 9% in June 2026', 'Loan growth accelerates from 6% in Q1 to 9% by mid-year, ahead of industry average.', 'Kontan', 'POSITIVE', 7
    UNION ALL SELECT @bbca, 'BBCA named best digital bank in Indonesia', 'Awarded by Asian Banker for innovation in digital financial services.', 'Detik Finance', 'POSITIVE', 3

    UNION ALL SELECT @bbri, 'BBRI reports 8% loan growth in Q2 2026', 'Bank Rakyat Indonesia posts solid Q2 with loan growth driven by micro and SME segments.', 'Kontan', 'POSITIVE', 20
    UNION ALL SELECT @bbri, 'BBRI expands micro lending to eastern Indonesia', 'New branches opened in Papua and Maluku to capture underserved markets.', 'Bisnis Indonesia', 'POSITIVE', 14
    UNION ALL SELECT @bbri, 'BBRI Q2 profit beats estimates at Rp 12.5T', 'Strong net interest margin and lower provisions boost profitability.', 'CNBC Indonesia', 'POSITIVE', 8
    UNION ALL SELECT @bbri, 'Government injects capital for BBRI expansion', 'Capital injection supports new lending programs for food security.', 'Kompas', 'POSITIVE', 2

    UNION ALL SELECT @icbp, 'ICBP margins improve on cost optimization', 'Indofood CBP reports expanding gross margins from palm oil cost benefits and operational efficiency.', 'Kontan', 'POSITIVE', 18
    UNION ALL SELECT @icbp, 'ICBP launches new instant noodle variant', 'New premium product line targets middle-income consumers with higher margins.', 'Detik Finance', 'POSITIVE', 10
    UNION ALL SELECT @icbp, 'ICBP Q2 revenue up 6% YoY', 'Steady top-line growth with improving volume in noodles and dairy segments.', 'Bisnis Indonesia', 'POSITIVE', 3

    UNION ALL SELECT @antm, 'ANTM reports declining gold sales amid competition', 'Aneka Tambang sees lower gold contribution as small-scale miners increase output.', 'Kontan', 'NEGATIVE', 18
    UNION ALL SELECT @antm, 'ANTM Q2 earnings drop 15% on lower commodity prices', 'Profit decline driven by falling nickel and gold prices in international markets.', 'CNBC Indonesia', 'NEGATIVE', 10
    UNION ALL SELECT @antm, 'ANTM faces environmental compliance review', 'Regulator examines tailings management at several mining sites.', 'Kompas', 'NEGATIVE', 4

    UNION ALL SELECT @adro, 'ADRO coal exports surge in Q2 2026', 'Adaro Energy reports 20% increase in coal shipments driven by strong Asian demand.', 'Reuters', 'POSITIVE', 16
    UNION ALL SELECT @adro, 'ADRO announces share buyback program', 'Board approves buyback of up to 5% of outstanding shares, signaling confidence.', 'Kontan', 'POSITIVE', 8
    UNION ALL SELECT @adro, 'Coal prices stabilize above $120/ton', 'Benchmark coal prices support Adaro revenue outlook for H2 2026.', 'Bloomberg', 'POSITIVE', 2

    UNION ALL SELECT @tlkm, 'TLKM 5G rollout reaches 50 cities', 'Telkom Indonesia accelerates 5G deployment, now covering major urban centers.', 'Detik Finance', 'POSITIVE', 15
    UNION ALL SELECT @tlkm, 'TLKM data center expansion announced', 'New hyperscale data center in Cikarang to serve ASEAN cloud demand.', 'Kontan', 'POSITIVE', 5

    UNION ALL SELECT @unvr, 'UNVR maintains market share in FMCG', 'Unilever Indonesia holds dominant position despite increased competition.', 'Bisnis Indonesia', 'NEUTRAL', 12
    UNION ALL SELECT @unvr, 'UNVR launches sustainability initiative', 'New packaging reduction program targets 50% plastic use cut by 2028.', 'Kompas', 'POSITIVE', 4

    UNION ALL SELECT @asii, 'ASII auto sales decline 8% in June', 'Astra International reports lower vehicle sales amid higher interest rates.', 'CNBC Indonesia', 'NEGATIVE', 14
    UNION ALL SELECT @asii, 'ASII mining division offsets auto weakness', 'Pamapersada coal mining contributes stronger earnings, partially offsetting auto decline.', 'Kontan', 'NEUTRAL', 6

    UNION ALL SELECT @goto, 'GOTO GMV grows 25% in Q2', 'GoTo Gojek Tokopedia reports strong gross merchandise value growth.', 'Reuters', 'POSITIVE', 10
    UNION ALL SELECT @goto, 'GOTO narrows losses, path to profitability', 'Improved unit economics and cost reduction bring breakeven closer.', 'Bloomberg', 'POSITIVE', 3

    UNION ALL SELECT @bmri, 'BMRI Q2 profit reaches Rp 15T', 'Bank Mandiri posts record quarterly profit on strong fee income and trading gains.', 'Kontan', 'POSITIVE', 16
    UNION ALL SELECT @bmri, 'BMRI digital banking transactions up 40%', 'Livin by Mandiri sees rapid adoption, processing 200M transactions monthly.', 'CNBC Indonesia', 'POSITIVE', 8
    UNION ALL SELECT @bmri, 'BMRI announces Rp 20T capex for IT infrastructure', 'Major technology investment to enhance digital banking capabilities.', 'Bisnis Indonesia', 'POSITIVE', 1

    UNION ALL SELECT NULL, 'BI holds benchmark rate at 6.00%', 'Bank Indonesia maintains rate, citing stable inflation and controlled Rupiah volatility.', 'Reuters', 'NEUTRAL', 20
    UNION ALL SELECT NULL, 'Indonesia GDP grows 5.1% in Q2 2026', 'Economy expands at steady pace, supported by domestic consumption and investment.', 'Bloomberg', 'POSITIVE', 15
    UNION ALL SELECT NULL, 'Rupiah strengthens to 15,800 against USD', 'Currency appreciates on capital inflows and improving trade balance.', 'Kontan', 'POSITIVE', 10
    UNION ALL SELECT NULL, 'JCI reaches 7,500 points, new record high', 'Jakarta Composite Index hits milestone driven by banking and commodity stocks.', 'CNBC Indonesia', 'POSITIVE', 5
    UNION ALL SELECT NULL, 'Foreign inflows reach Rp 5T in June', 'Foreign investors net buyers on JCI, particularly in banking and telecom sectors.', 'Bisnis Indonesia', 'POSITIVE', 1
) AS news_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 5. Daily Risk Assessments for 22 days (Growth Portfolio)
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO risk.risk_assessment (risk_assessment_id, portfolio_id, assessment_type, var_95, var_99, expected_shortfall, portfolio_beta, sharpe_ratio, sortino_ratio, max_drawdown, volatility, concentration_index, currency, as_of, model_version, created_at)
SELECT
    UUID(),
    @pf_growth,
    'DAILY',
    var95,
    var99,
    es,
    beta,
    sharpe,
    sortino,
    max_dd,
    vol,
    conc,
    'IDR',
    DATE_SUB(CURDATE(), INTERVAL day_offset DAY),
    'v2-sim',
    DATE_SUB(NOW(), INTERVAL day_offset DAY)
FROM (
    SELECT 22 AS day_offset, 2500000 AS var95, 3500000 AS var99, 4200000 AS es, 1.05 AS beta, 1.2 AS sharpe, 1.8 AS sortino, -3.5 AS max_dd, 18.0 AS vol, 0.35 AS conc
    UNION ALL SELECT 20, 2400000, 3400000, 4100000, 1.04, 1.25, 1.85, -3.2, 17.5, 0.34
    UNION ALL SELECT 18, 2300000, 3300000, 4000000, 1.03, 1.30, 1.90, -3.0, 17.0, 0.33
    UNION ALL SELECT 16, 2200000, 3200000, 3900000, 1.02, 1.35, 1.95, -2.8, 16.5, 0.33
    UNION ALL SELECT 14, 2100000, 3100000, 3800000, 1.02, 1.40, 2.00, -2.5, 16.0, 0.32
    UNION ALL SELECT 12, 2000000, 3000000, 3700000, 1.01, 1.42, 2.05, -2.3, 15.5, 0.32
    UNION ALL SELECT 10, 1900000, 2900000, 3600000, 1.01, 1.45, 2.10, -2.1, 15.0, 0.31
    UNION ALL SELECT 8, 1800000, 2800000, 3500000, 1.00, 1.48, 2.15, -1.9, 14.5, 0.31
    UNION ALL SELECT 6, 1700000, 2700000, 3400000, 1.00, 1.50, 2.20, -1.7, 14.0, 0.30
    UNION ALL SELECT 4, 1600000, 2600000, 3300000, 0.99, 1.52, 2.25, -1.5, 13.5, 0.30
    UNION ALL SELECT 2, 1500000, 2500000, 3200000, 0.99, 1.55, 2.30, -1.3, 13.0, 0.29
    UNION ALL SELECT 1, 1450000, 2450000, 3150000, 0.98, 1.58, 2.35, -1.2, 12.5, 0.28
) AS risk_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 6. Trading Activity: 7 orders across 22 days
-- ──────────────────────────────────────────────────────────────────────────
-- Day 22: BUY BBCA 200 shares
SET @dec_d22 = UUID();
SET @intent_d22 = UUID();
SET @order_d22 = UUID();
SET @exec_d22 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d22, @pf_growth, @bbca, 'BUY', 200, 8900, 'sim-day22: Bullish trend signal, MACD crossover', 0.72, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 22 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d22, @dec_d22, @pf_growth, @bbca, 'BUY', 200, 8900, 'MARKET', 'sim-day22: Trend following signal', 'APPROVED', DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d22, 'ORD-SIM-001', @intent_d22, @pf_growth, @acct_growth, @bbca, 'BUY', 'MARKET', 200, 200, 0, NULL, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d22, 'EXEC-SIM-001', @order_d22, @bbca, 200, 8925, 1785000, 1785, 89.25, 1785000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY));
INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec_d22, @pf_growth, @bbca, 'T_PLUS_2', CURDATE() - INTERVAL 22 DAY, CURDATE() - INTERVAL 20 DAY, 200, 8925, 1785000, 1785, 89.25, 1785000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY));

-- Day 18: BUY BBRI 300 shares
SET @dec_d18 = UUID();
SET @intent_d18 = UUID();
SET @order_d18 = UUID();
SET @exec_d18 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d18, @pf_growth, @bbri, 'BUY', 300, 4300, 'sim-day18: Breakout signal with volume confirmation', 0.65, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 18 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d18, @dec_d18, @pf_growth, @bbri, 'BUY', 300, 4300, 'LIMIT', 'sim-day18: Breakout above resistance', 'APPROVED', DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d18, 'ORD-SIM-002', @intent_d18, @pf_growth, @acct_growth, @bbri, 'BUY', 'LIMIT', 300, 300, 0, 4300, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d18, 'EXEC-SIM-002', @order_d18, @bbri, 300, 4310, 1293000, 1293, 64.65, 1293000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY));
INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec_d18, @pf_growth, @bbri, 'T_PLUS_2', CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 16 DAY, 300, 4310, 1293000, 1293, 64.65, 1293000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 16 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY));

-- Day 14: BUY ADRO 500 shares
SET @dec_d14 = UUID();
SET @intent_d14 = UUID();
SET @order_d14 = UUID();
SET @exec_d14 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d14, @pf_growth, @adro, 'BUY', 500, 2700, 'sim-day14: Coal sector bullish, breakout signal', 0.66, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 14 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d14, @dec_d14, @pf_growth, @adro, 'BUY', 500, 2700, 'MARKET', 'sim-day14: Sector momentum and breakout', 'APPROVED', DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d14, 'ORD-SIM-003', @intent_d14, @pf_growth, @acct_growth, @adro, 'BUY', 'MARKET', 500, 500, 0, NULL, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d14, 'EXEC-SIM-003', @order_d14, @adro, 500, 2715, 1357500, 1357.5, 67.88, 1357500, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY));
INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec_d14, @pf_growth, @adro, 'T_PLUS_2', CURDATE() - INTERVAL 14 DAY, CURDATE() - INTERVAL 12 DAY, 500, 2715, 1357500, 1357.5, 67.88, 1357500, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY));

-- Day 10: SELL ANTM 500 shares (Speculative Portfolio)
SET @dec_d10 = UUID();
SET @intent_d10 = UUID();
SET @order_d10 = UUID();
SET @exec_d10 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d10, @pf_spec, @antm, 'SELL', 500, 1850, 'sim-day10: Bearish trend, MACD bearish crossover', 0.55, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 10 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d10, @dec_d10, @pf_spec, @antm, 'SELL', 500, 1850, 'MARKET', 'sim-day10: Risk management, bearish signals', 'APPROVED', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d10, 'ORD-SIM-004', @intent_d10, @pf_spec, @acct_spec, @antm, 'SELL', 'MARKET', 500, 500, 0, NULL, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d10, 'EXEC-SIM-004', @order_d10, @antm, 500, 1842, 921000, 921, 46.05, 921000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY));
INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec_d10, @pf_spec, @antm, 'T_PLUS_2', CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 8 DAY, 500, 1842, 921000, 921, 46.05, 921000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY));

-- Day 6: BUY ICBP 100 shares (Dividend Portfolio)
SET @dec_d6 = UUID();
SET @intent_d6 = UUID();
SET @order_d6 = UUID();
SET @exec_d6 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d6, @pf_div, @icbp, 'BUY', 100, 9800, 'sim-day6: Margin improvement, FMCG leader', 0.62, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 6 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d6, @dec_d6, @pf_div, @icbp, 'BUY', 100, 9800, 'LIMIT', 'sim-day6: Strong fundamentals, ROE 20%', 'APPROVED', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d6, 'ORD-SIM-005', @intent_d6, @pf_div, @acct_div, @icbp, 'BUY', 'LIMIT', 100, 100, 0, 9800, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d6, 'EXEC-SIM-005', @order_d6, @icbp, 100, 9810, 981000, 981, 49.05, 981000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));
INSERT INTO settlement.settlement (settlement_id, execution_id, portfolio_id, instrument_id, settlement_type, trade_date, settlement_date, quantity, price, gross_amount, commission, fees, net_amount, currency, status, settled_at, created_at)
VALUES (UUID(), @exec_d6, @pf_div, @icbp, 'T_PLUS_2', CURDATE() - INTERVAL 6 DAY, CURDATE() - INTERVAL 4 DAY, 100, 9810, 981000, 981, 49.05, 981000, 'IDR', 'SETTLED', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));

-- Day 3: BUY BMRI 200 shares (Growth Portfolio)
SET @dec_d3 = UUID();
SET @intent_d3 = UUID();
SET @order_d3 = UUID();
SET @exec_d3 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d3, @pf_growth, @bmri, 'BUY', 200, 5600, 'sim-day3: Record profit, digital banking growth', 0.68, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 3 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d3, @dec_d3, @pf_growth, @bmri, 'BUY', 200, 5600, 'MARKET', 'sim-day3: Strong Q2 earnings, digital momentum', 'APPROVED', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d3, 'ORD-SIM-006', @intent_d3, @pf_growth, @acct_growth, @bmri, 'BUY', 'MARKET', 200, 200, 0, NULL, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d3, 'EXEC-SIM-006', @order_d3, @bmri, 200, 5615, 1123000, 1123, 56.15, 1123000, 'IDR', 'PENDING_SETTLEMENT', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Day 1: BUY TLKM 300 shares (Dividend Portfolio)
SET @dec_d1 = UUID();
SET @intent_d1 = UUID();
SET @order_d1 = UUID();
SET @exec_d1 = UUID();
INSERT INTO trading.decision (decision_id, portfolio_id, instrument_id, action, intended_quantity, intended_price, reason, confidence, policy_result, status, created_at)
VALUES (@dec_d1, @pf_div, @tlkm, 'BUY', 300, 2650, 'sim-day1: 5G rollout, data center expansion', 0.58, 'APPROVED', 'APPROVED', DATE_SUB(NOW(), INTERVAL 1 DAY));
INSERT INTO trading.order_intent (order_intent_id, decision_id, portfolio_id, instrument_id, side, target_quantity, target_price, strategy, reason, status, approved_at, created_at)
VALUES (@intent_d1, @dec_d1, @pf_div, @tlkm, 'BUY', 300, 2650, 'LIMIT', 'sim-day1: Telecom growth, dividend yield', 'APPROVED', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));
INSERT INTO trading.`order` (order_id, order_ref, order_intent_id, portfolio_id, account_id, instrument_id, side, order_type, quantity, filled_quantity, remaining_quantity, limit_price, time_in_force, status, submitted_at, filled_at, created_at, updated_at)
VALUES (@order_d1, 'ORD-SIM-007', @intent_d1, @pf_div, @acct_div, @tlkm, 'BUY', 'LIMIT', 300, 300, 0, 2650, 'DAY', 'FILLED', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));
INSERT INTO trading.execution (execution_id, execution_ref, order_id, instrument_id, fill_quantity, fill_price, fill_value, commission, fees, net_value, currency, status, executed_at, created_at)
VALUES (@exec_d1, 'EXEC-SIM-007', @order_d1, @tlkm, 300, 2655, 796500, 796.5, 39.83, 796500, 'IDR', 'PENDING_SETTLEMENT', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ──────────────────────────────────────────────────────────────────────────
-- 7. AI Analysis spread across 22 days
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO ai_engine.ai_analysis (analysis_id, analysis_type, instrument_id, source_type, sentiment_score, sentiment_label, entities, events, pattern_type, pattern_confidence, anomaly_score, anomaly_type, summary, created_at)
SELECT
    UUID(),
    analysis_type,
    instr_id,
    'sim-engine',
    sent_score,
    sent_label,
    entities_json,
    events_json,
    pattern_type,
    pattern_conf,
    anomaly_score,
    anomaly_type,
    summary,
    DATE_SUB(NOW(), INTERVAL day_offset DAY)
FROM (
    SELECT @bbca AS instr_id, 'SENTIMENT' AS analysis_type, 0.72 AS sent_score, 'POSITIVE' AS sent_label, '["BBCA","BCA","digital banking"]' AS entities_json, '["earnings","dividend","digital growth"]' AS events_json, NULL AS pattern_type, NULL AS pattern_conf, NULL AS anomaly_score, NULL AS anomaly_type, 'Strong positive sentiment driven by Q2 earnings beat and digital banking milestone. News coverage 85% positive.' AS summary, 22 AS day_offset
    UNION ALL SELECT @bbca, 'PATTERN', NULL, NULL, NULL, NULL, 'ASCENDING_TRIANGLE', 0.78, NULL, NULL, 'Ascending triangle pattern detected on daily chart, breakout pending above 9,200 resistance.', 18
    UNION ALL SELECT @bbca, 'ANOMALY', NULL, NULL, NULL, NULL, NULL, NULL, 0.15, 'VOLUME_ANOMALY', 'Slight volume anomaly detected - trading volume 1.3x average, within normal range.', 10
    UNION ALL SELECT @bbca, 'SENTIMENT', 0.75, 'POSITIVE', '["BBCA","analysts","target price"]', '["upgrade","target raise"]', NULL, NULL, NULL, NULL, 'Sentiment remains strongly positive with multiple analyst upgrades. 90% positive news coverage.', 3

    UNION ALL SELECT @bbri, 'SENTIMENT', 0.65, 'POSITIVE', '["BBRI","BRI","micro lending"]', '["earnings","loan growth"]', NULL, NULL, NULL, NULL, 'Positive sentiment from Q2 earnings beat and micro lending expansion. 80% positive coverage.', 20
    UNION ALL SELECT @bbri, 'PATTERN', NULL, NULL, NULL, NULL, 'CUP_AND_HANDLE', 0.70, NULL, NULL, 'Cup and handle pattern forming, potential breakout above 4,500.', 8

    UNION ALL SELECT @icbp, 'SENTIMENT', 0.60, 'POSITIVE', '["ICBP","Indofood","FMCG"]', '["product launch","margin"]', NULL, NULL, NULL, NULL, 'Moderately positive sentiment from margin improvement and new product launch.', 14
    UNION ALL SELECT @icbp, 'ANOMALY', NULL, NULL, NULL, NULL, NULL, NULL, 0.08, 'NONE', 'No significant anomalies detected. Normal trading pattern.', 5

    UNION ALL SELECT @antm, 'SENTIMENT', -0.55, 'NEGATIVE', '["ANTM","Aneka Tambang","mining"]', '["earnings decline","compliance"]', NULL, NULL, NULL, NULL, 'Negative sentiment from earnings decline and environmental compliance concerns. 70% negative coverage.', 16
    UNION ALL SELECT @antm, 'PATTERN', NULL, NULL, NULL, NULL, 'DESCENDING_TRIANGLE', 0.72, NULL, NULL, 'Descending triangle pattern, bearish breakdown below 1,750 likely.', 8
    UNION ALL SELECT @antm, 'ANOMALY', NULL, NULL, NULL, NULL, NULL, NULL, 0.35, 'PRICE_DROP', 'Significant price drop anomaly detected - 5% decline in single session, above normal volatility.', 4

    UNION ALL SELECT @adro, 'SENTIMENT', 0.68, 'POSITIVE', '["ADRO","Adaro","coal"]', '["exports","buyback"]', NULL, NULL, NULL, NULL, 'Positive sentiment from coal export surge and share buyback announcement.', 16
    UNION ALL SELECT @adro, 'PATTERN', NULL, NULL, NULL, NULL, 'BULL_FLAG', 0.75, NULL, NULL, 'Bull flag pattern detected, continuation of uptrend expected.', 6

    UNION ALL SELECT @tlkm, 'SENTIMENT', 0.55, 'POSITIVE', '["TLKM","Telkom","5G"]', '["5G rollout","data center"]', NULL, NULL, NULL, NULL, 'Positive sentiment from 5G expansion and data center growth initiative.', 12

    UNION ALL SELECT @goto, 'SENTIMENT', 0.58, 'POSITIVE', '["GOTO","GoTo","e-commerce"]', '["GMV growth","profitability"]', NULL, NULL, NULL, NULL, 'Improving sentiment as GOTO narrows losses and GMV grows 25%.', 8

    UNION ALL SELECT @bmri, 'SENTIMENT', 0.70, 'POSITIVE', '["BMRI","Mandiri","digital banking"]', '["record profit","capex"]', NULL, NULL, NULL, NULL, 'Strong positive sentiment from record Q2 profit and digital banking growth.', 14
    UNION ALL SELECT @bmri, 'PATTERN', NULL, NULL, NULL, NULL, 'ASCENDING_TRIANGLE', 0.73, NULL, NULL, 'Ascending triangle on daily chart, breakout above 5,700 anticipated.', 4

    UNION ALL SELECT @unvr, 'SENTIMENT', 0.50, 'NEUTRAL', '["UNVR","Unilever","FMCG"]', '["market share","sustainability"]', NULL, NULL, NULL, NULL, 'Neutral sentiment, market share maintained but growth limited.', 10

    UNION ALL SELECT @asii, 'SENTIMENT', -0.40, 'NEGATIVE', '["ASII","Astra","automotive"]', '["sales decline","interest rates"]', NULL, NULL, NULL, NULL, 'Negative sentiment from auto sales decline, partially offset by mining strength.', 12
    UNION ALL SELECT @asii, 'ANOMALY', NULL, NULL, NULL, NULL, NULL, NULL, 0.20, 'VOLUME_ANOMALY', 'Elevated volume on sell-off, suggesting institutional distribution.', 5

    UNION ALL SELECT NULL, 'SENTIMENT', 0.65, 'POSITIVE', '["BI","GDP","Rupiah","JCI"]', '["rate hold","GDP growth","record high"]', NULL, NULL, NULL, NULL, 'Market-wide positive sentiment: BI holds rates, GDP grows 5.1%, JCI hits record 7,500. Foreign inflows strong.', 5
    UNION ALL SELECT NULL, 'SENTIMENT', 0.60, 'POSITIVE', '["Indonesia","economy","markets"]', '["foreign inflows","Rupiah"]', NULL, NULL, NULL, NULL, 'Broad market sentiment positive with Rp 5T foreign inflows and Rupiah strengthening to 15,800.', 1
) AS ai_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 8. Risk Events
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO risk.risk_event (risk_event_id, portfolio_id, event_type, severity, description, current_value, limit_value, detected_at, status)
VALUES
(UUID(), @pf_spec, 'WARNING', 'MEDIUM', 'ANTM position exceeds 15% portfolio weight - single asset concentration risk', 15.5, 15.0, DATE_SUB(NOW(), INTERVAL 15 DAY), 'ACKNOWLEDGED'),
(UUID(), @pf_growth, 'WARNING', 'LOW', 'Daily VaR at 2.5M approaching 3M limit threshold (83%)', 2500000, 3000000, DATE_SUB(NOW(), INTERVAL 10 DAY), 'RESOLVED'),
(UUID(), @pf_growth, 'WARNING', 'LOW', 'Portfolio drawdown at -3.5%, within acceptable range', -3.5, -5.0, DATE_SUB(NOW(), INTERVAL 8 DAY), 'ACKNOWLEDGED'),
(UUID(), @pf_div, 'WARNING', 'LOW', 'TLKM position approaching 20% portfolio weight', 18.5, 20.0, DATE_SUB(NOW(), INTERVAL 3 DAY), 'OPEN'),
(UUID(), @pf_spec, 'LIMIT_BREACH', 'HIGH', 'VaR exceeded 4M threshold after ANTM volatility spike', 4200000, 4000000, DATE_SUB(NOW(), INTERVAL 5 DAY), 'OPEN');

-- ──────────────────────────────────────────────────────────────────────────
-- 9. Forecasts
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO analytics.forecast (forecast_id, instrument_id, target_variable, horizon, prediction_value, confidence_interval_low, confidence_interval_high, confidence, model_version, created_at)
SELECT
    UUID(),
    instr_id,
    'PRICE',
    horizon,
    pred_val,
    ci_low,
    ci_high,
    conf,
    'v2-sim',
    DATE_SUB(NOW(), INTERVAL day_offset DAY)
FROM (
    SELECT @bbca AS instr_id, '1M' AS horizon, 9500.0 AS pred_val, 9200.0 AS ci_low, 9800.0 AS ci_high, 0.72 AS conf, 20 AS day_offset
    UNION ALL SELECT @bbca, '1W', 9200.0, 9050.0, 9300.0, 0.68, 14
    UNION ALL SELECT @bbca, '1M', 9700.0, 9300.0, 10000.0, 0.75, 7
    UNION ALL SELECT @bbca, '1W', 9250.0, 9100.0, 9350.0, 0.70, 1

    UNION ALL SELECT @bbri, '1M', 4800.0, 4600.0, 5000.0, 0.65, 18
    UNION ALL SELECT @bbri, '1W', 4480.0, 4400.0, 4550.0, 0.62, 8
    UNION ALL SELECT @bbri, '1M', 4900.0, 4700.0, 5100.0, 0.68, 1

    UNION ALL SELECT @antm, '1M', 1700.0, 1600.0, 1800.0, 0.60, 16
    UNION ALL SELECT @antm, '1W', 1770.0, 1720.0, 1820.0, 0.58, 6
    UNION ALL SELECT @antm, '1M', 1650.0, 1550.0, 1750.0, 0.63, 1

    UNION ALL SELECT @adro, '1M', 3000.0, 2800.0, 3200.0, 0.68, 12
    UNION ALL SELECT @adro, '1W', 2850.0, 2750.0, 2950.0, 0.65, 4

    UNION ALL SELECT @icbp, '1M', 10300.0, 9800.0, 10800.0, 0.60, 10
    UNION ALL SELECT @icbp, '1W', 10100.0, 9900.0, 10300.0, 0.58, 2
) AS forecast_data;

-- ──────────────────────────────────────────────────────────────────────────
-- 10. Audit Log entries for trading activity
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO governance.audit_log (audit_log_id, actor_type, action, entity_type, entity_id, new_values, created_at)
SELECT
    UUID(),
    'SYSTEM',
    CONCAT('SIM_ORDER_CREATED_', order_ref),
    'ORDER',
    order_id,
    JSON_OBJECT('order_ref', order_ref, 'side', side, 'quantity', quantity, 'status', status),
    created_at
FROM trading.`order` WHERE order_ref LIKE 'ORD-SIM-%';

INSERT INTO governance.audit_log (audit_log_id, actor_type, action, entity_type, entity_id, new_values, created_at)
SELECT
    UUID(),
    'SYSTEM',
    CONCAT('SIM_EXEC_FILLED_', execution_ref),
    'EXECUTION',
    execution_id,
    JSON_OBJECT('execution_ref', execution_ref, 'fill_price', fill_price, 'fill_quantity', fill_quantity),
    created_at
FROM trading.execution WHERE execution_ref LIKE 'EXEC-SIM-%';

-- ──────────────────────────────────────────────────────────────────────────
-- 11. Additional Economic Indicators
-- ──────────────────────────────────────────────────────────────────────────
INSERT INTO fundamental.economic_indicator (indicator_id, country, indicator_type, frequency, period, value, unit, publication_date, source)
VALUES
(UUID(), 'ID', 'MANUFACTURING_PMI', 'MONTHLY', '2026-06-01', 51.8, 'INDEX', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'S&P Global'),
(UUID(), 'ID', 'SERVICES_PMI', 'MONTHLY', '2026-06-01', 52.3, 'INDEX', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'S&P Global'),
(UUID(), 'ID', 'RETAIL_SALES', 'MONTHLY', '2026-06-01', 4.2, 'PERCENT_YOY', DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'BPS'),
(UUID(), 'ID', 'INDUSTRIAL_PRODUCTION', 'MONTHLY', '2026-06-01', 3.8, 'PERCENT_YOY', DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'BPS'),
(UUID(), 'ID', 'FOREIGN_RESERVES', 'MONTHLY', '2026-06-30', 145.2, 'BILLION_USD', DATE_SUB(CURDATE(), INTERVAL 8 DAY), 'BI'),
(UUID(), 'US', 'MANUFACTURING_PMI', 'MONTHLY', '2026-06-01', 48.5, 'INDEX', DATE_SUB(CURDATE(), INTERVAL 18 DAY), 'ISM'),
(UUID(), 'US', 'NONFARM_PAYROLLS', 'MONTHLY', '2026-06-01', 180000, 'THOUSAND', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'BLS');
