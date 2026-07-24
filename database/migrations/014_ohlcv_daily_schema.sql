-- ============================================================================
-- 014_ohlcv_daily_schema.sql
-- Purpose: OHLCV daily data storage for Data Ingestion Service (MVP)
-- ============================================================================

USE platform;

CREATE SCHEMA IF NOT EXISTS data_ingestion;

-- ──────────────────────────────────────────────────────────────────────────
-- ohlcv_daily: Daily OHLCV (Open-High-Low-Close-Volume) market data
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS data_ingestion.ohlcv_daily (
    ohlcv_id        CHAR(36)        NOT NULL,
    instrument_id   CHAR(36)        NOT NULL,
    trade_date      DATE            NOT NULL,
    open            DECIMAL(18,4)   NOT NULL,
    high            DECIMAL(18,4)   NOT NULL,
    low             DECIMAL(18,4)   NOT NULL,
    close           DECIMAL(18,4)   NOT NULL,
    volume          BIGINT          NOT NULL DEFAULT 0,
    adjusted_close  DECIMAL(18,4)   NULL,
    source          VARCHAR(50)     NOT NULL DEFAULT 'MANUAL',
    created_at      DATETIME(6)     NOT NULL,
    PRIMARY KEY (ohlcv_id),
    UNIQUE KEY uq_ohlcv_instr_date (instrument_id, trade_date, source),
    KEY idx_ohlcv_date (trade_date),
    KEY idx_ohlcv_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- ingestion_log: Track ingestion batch runs
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS data_ingestion.ingestion_log (
    log_id          CHAR(36)        NOT NULL,
    source          VARCHAR(50)     NOT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'RUNNING',
    records_total   INT             NOT NULL DEFAULT 0,
    records_ok      INT             NOT NULL DEFAULT 0,
    records_failed  INT             NOT NULL DEFAULT 0,
    error_message   TEXT            NULL,
    started_at      DATETIME(6)     NOT NULL,
    completed_at    DATETIME(6)     NULL,
    PRIMARY KEY (log_id),
    KEY idx_ingest_status (status),
    KEY idx_ingest_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
