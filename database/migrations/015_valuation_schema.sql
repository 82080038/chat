-- ============================================================================
-- 015_valuation_schema.sql
-- Purpose: Valuation engine — DCF, relative valuation, fair value results
-- ============================================================================

USE platform;

CREATE SCHEMA IF NOT EXISTS valuation;

-- ──────────────────────────────────────────────────────────────────────────
-- valuation_result: Stores valuation outputs (DCF, relative, blended fair value)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS valuation.valuation_result (
    valuation_id         CHAR(36)        NOT NULL,
    instrument_id        CHAR(36)        NOT NULL,
    valuation_type       VARCHAR(20)     NOT NULL,
    -- DCF inputs
    discount_rate        DECIMAL(8,4)    NULL,
    terminal_growth      DECIMAL(8,4)    NULL,
    projected_fcf        TEXT            NULL,
    -- Relative valuation inputs
    peer_group           VARCHAR(200)    NULL,
    peer_metric          VARCHAR(20)     NULL,
    peer_value           DECIMAL(18,4)   NULL,
    -- Results
    fair_value           DECIMAL(18,4)   NOT NULL,
    margin_of_safety     DECIMAL(8,4)    NULL,
    confidence_score     DECIMAL(5,2)    NULL,
    assumptions          TEXT            NULL,
    -- Meta
    as_of_date           DATE            NOT NULL,
    currency             CHAR(3)         NOT NULL DEFAULT 'IDR',
    created_at           DATETIME(6)     NOT NULL,
    PRIMARY KEY (valuation_id),
    KEY idx_val_instrument (instrument_id),
    KEY idx_val_type (valuation_type),
    KEY idx_val_date (as_of_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
