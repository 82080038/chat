-- ============================================================================
-- 020_ai_engine_schema.sql
-- Purpose: AI engine — sentiment analysis, pattern recognition, anomaly detection
-- ============================================================================

USE platform;

CREATE SCHEMA IF NOT EXISTS ai_engine;

-- ──────────────────────────────────────────────────────────────────────────
-- ai_analysis: Results of AI analysis (sentiment, pattern, anomaly)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_engine.ai_analysis (
    analysis_id     CHAR(36)        NOT NULL,
    analysis_type   VARCHAR(20)     NOT NULL,
    instrument_id   CHAR(36)        NULL,
    source_id       CHAR(36)        NULL,
    source_type     VARCHAR(20)     NULL,
    sentiment_score DECIMAL(5,2)    NULL,
    sentiment_label VARCHAR(10)     NULL,
    entities        JSON            NULL,
    events          JSON            NULL,
    pattern_type    VARCHAR(50)     NULL,
    pattern_confidence DECIMAL(5,2) NULL,
    anomaly_score   DECIMAL(5,2)    NULL,
    anomaly_type    VARCHAR(50)     NULL,
    summary         TEXT            NULL,
    metadata        JSON            NULL,
    created_at      DATETIME(6)     NOT NULL,
    PRIMARY KEY (analysis_id),
    KEY idx_ai_type (analysis_type),
    KEY idx_ai_instrument (instrument_id),
    KEY idx_ai_source (source_id, source_type),
    KEY idx_ai_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- ai_model_run: Track model execution metadata
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_engine.ai_model_run (
    run_id          CHAR(36)        NOT NULL,
    model_name      VARCHAR(100)    NOT NULL,
    model_version   VARCHAR(50)     NOT NULL,
    input_count     INT             NOT NULL DEFAULT 0,
    output_count    INT             NOT NULL DEFAULT 0,
    status          VARCHAR(20)     NOT NULL DEFAULT 'PENDING',
    error_message   TEXT            NULL,
    started_at      DATETIME(6)     NOT NULL,
    completed_at    DATETIME(6)     NULL,
    PRIMARY KEY (run_id),
    KEY idx_ai_run_model (model_name),
    KEY idx_ai_run_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
