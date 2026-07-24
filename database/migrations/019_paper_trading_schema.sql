-- ============================================================================
-- 019_paper_trading_schema.sql
-- Purpose: Paper trading — simulated execution, virtual portfolio
-- ============================================================================

USE platform;

CREATE SCHEMA IF NOT EXISTS paper_trading;

-- ──────────────────────────────────────────────────────────────────────────
-- paper_account: Virtual trading account with fake cash
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paper_trading.paper_account (
    account_id      CHAR(36)        NOT NULL,
    name            VARCHAR(100)    NOT NULL,
    initial_cash    DECIMAL(18,4)   NOT NULL,
    cash_balance    DECIMAL(18,4)   NOT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'ACTIVE',
    created_at      DATETIME(6)     NOT NULL,
    PRIMARY KEY (account_id),
    KEY idx_paper_acct_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- paper_order: Simulated orders
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paper_trading.paper_order (
    order_id        CHAR(36)        NOT NULL,
    account_id      CHAR(36)        NOT NULL,
    instrument_id   CHAR(36)        NOT NULL,
    symbol          VARCHAR(50)     NOT NULL,
    side            VARCHAR(10)     NOT NULL,
    order_type      VARCHAR(10)     NOT NULL DEFAULT 'MARKET',
    quantity        DECIMAL(20,8)   NOT NULL,
    price           DECIMAL(18,4)   NULL,
    filled_price    DECIMAL(18,4)   NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'PENDING',
    signal_id       CHAR(36)        NULL,
    created_at      DATETIME(6)     NOT NULL,
    filled_at       DATETIME(6)     NULL,
    PRIMARY KEY (order_id),
    KEY idx_paper_order_acct (account_id),
    KEY idx_paper_order_status (status),
    KEY idx_paper_order_signal (signal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- paper_position: Current holdings in virtual portfolio
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paper_trading.paper_position (
    position_id     CHAR(36)        NOT NULL,
    account_id      CHAR(36)        NOT NULL,
    instrument_id   CHAR(36)        NOT NULL,
    symbol          VARCHAR(50)     NOT NULL,
    quantity        DECIMAL(20,8)   NOT NULL DEFAULT 0,
    avg_price       DECIMAL(18,4)   NOT NULL DEFAULT 0,
    realized_pnl    DECIMAL(18,4)   NOT NULL DEFAULT 0,
    created_at      DATETIME(6)     NOT NULL,
    updated_at      DATETIME(6)     NULL,
    PRIMARY KEY (position_id),
    UNIQUE KEY uq_paper_pos_acct_inst (account_id, instrument_id),
    KEY idx_paper_pos_acct (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
