-- 025_market_microstructure_schema.sql
-- Market Microstructure analysis: order book snapshots, spread analysis, market impact

CREATE SCHEMA IF NOT EXISTS microstructure;

-- Order book snapshot (point-in-time capture of bid/ask depth)
CREATE TABLE IF NOT EXISTS microstructure.order_book_snapshot (
    snapshot_id      VARCHAR(36)    NOT NULL PRIMARY KEY,
    instrument_id    VARCHAR(36)    NOT NULL,
    exchange_id      VARCHAR(36)    NOT NULL,
    timestamp        TIMESTAMP(6)   NOT NULL,
    bid_price_1      DECIMAL(20,8)  NULL,
    bid_volume_1     BIGINT         NULL,
    bid_price_2      DECIMAL(20,8)  NULL,
    bid_volume_2     BIGINT         NULL,
    bid_price_3      DECIMAL(20,8)  NULL,
    bid_volume_3     BIGINT         NULL,
    bid_price_4      DECIMAL(20,8)  NULL,
    bid_volume_4     BIGINT         NULL,
    bid_price_5      DECIMAL(20,8)  NULL,
    bid_volume_5     BIGINT         NULL,
    ask_price_1      DECIMAL(20,8)  NULL,
    ask_volume_1     BIGINT         NULL,
    ask_price_2      DECIMAL(20,8)  NULL,
    ask_volume_2     BIGINT         NULL,
    ask_price_3      DECIMAL(20,8)  NULL,
    ask_volume_3     BIGINT         NULL,
    ask_price_4      DECIMAL(20,8)  NULL,
    ask_volume_4     BIGINT         NULL,
    ask_price_5      DECIMAL(20,8)  NULL,
    ask_volume_5     BIGINT         NULL,
    mid_price        DECIMAL(20,8)  NULL,
    spread           DECIMAL(20,8)  NULL,
    spread_bps       DECIMAL(10,4)  NULL,
    total_bid_volume BIGINT         NULL,
    total_ask_volume BIGINT         NULL,
    imbalance        DECIMAL(10,6)  NULL,
    source           VARCHAR(50)    NOT NULL DEFAULT 'MANUAL',
    created_at       TIMESTAMP(6)   NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

    INDEX idx_obs_instrument (instrument_id, timestamp),
    INDEX idx_obs_exchange (exchange_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Microstructure metrics (computed analysis per instrument per day)
CREATE TABLE IF NOT EXISTS microstructure.metrics (
    metric_id            VARCHAR(36)    NOT NULL PRIMARY KEY,
    instrument_id        VARCHAR(36)    NOT NULL,
    metric_date          DATE           NOT NULL,
    avg_spread_bps       DECIMAL(10,4)  NULL,
    max_spread_bps       DECIMAL(10,4)  NULL,
    min_spread_bps       DECIMAL(10,4)  NULL,
    avg_depth            DECIMAL(20,4)  NULL,
    avg_imbalance        DECIMAL(10,6)  NULL,
    liquidity_score      DECIMAL(5,2)   NULL,
    liquidity_regime     ENUM('NORMAL','THIN','STRESS') NULL,
    estimated_liquidation_days DECIMAL(10,2) NULL,
    market_impact_bps    DECIMAL(10,4)  NULL,
    slippage_estimate    DECIMAL(20,8)  NULL,
    effective_spread     DECIMAL(20,8)  NULL,
    realized_spread      DECIMAL(20,8)  NULL,
    trade_to_order_ratio DECIMAL(10,4)  NULL,
    created_at           TIMESTAMP(6)   NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

    UNIQUE KEY uq_mm_instrument_date (instrument_id, metric_date),
    INDEX idx_mm_date (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
