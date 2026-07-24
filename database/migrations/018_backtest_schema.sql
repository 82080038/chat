-- ============================================================================
-- 018_backtest_schema.sql
-- Purpose: Backtesting framework — runs, trades, and performance metrics
-- ============================================================================

USE platform;

CREATE SCHEMA IF NOT EXISTS backtesting;

-- ──────────────────────────────────────────────────────────────────────────
-- backtest_run: A single backtest execution
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS backtesting.backtest_run (
    run_id          CHAR(36)        NOT NULL,
    strategy_name   VARCHAR(100)    NOT NULL,
    instrument_id   CHAR(36)        NULL,
    portfolio_id    CHAR(36)        NULL,
    start_date      DATE            NOT NULL,
    end_date        DATE            NOT NULL,
    initial_capital DECIMAL(18,4)   NOT NULL,
    final_capital   DECIMAL(18,4)   NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'PENDING',
    parameters      JSON            NULL,
    created_at      DATETIME(6)     NOT NULL,
    completed_at    DATETIME(6)     NULL,
    PRIMARY KEY (run_id),
    KEY idx_bt_run_status (status),
    KEY idx_bt_run_strategy (strategy_name),
    KEY idx_bt_run_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- backtest_trade: Individual trades executed during a backtest
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS backtesting.backtest_trade (
    trade_id        CHAR(36)        NOT NULL,
    run_id          CHAR(36)        NOT NULL,
    instrument_id   CHAR(36)        NOT NULL,
    side            VARCHAR(10)     NOT NULL,
    quantity        DECIMAL(20,8)   NOT NULL,
    entry_price     DECIMAL(18,4)   NOT NULL,
    exit_price      DECIMAL(18,4)   NULL,
    entry_date      DATETIME(6)     NOT NULL,
    exit_date       DATETIME(6)     NULL,
    pnl             DECIMAL(18,4)   NULL,
    pnl_pct         DECIMAL(10,4)   NULL,
    PRIMARY KEY (trade_id),
    KEY idx_bt_trade_run (run_id),
    KEY idx_bt_trade_instrument (instrument_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- backtest_metrics: Computed performance metrics per run
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS backtesting.backtest_metrics (
    metrics_id      CHAR(36)        NOT NULL,
    run_id          CHAR(36)        NOT NULL,
    total_return    DECIMAL(10,4)   NULL,
    annualized_return DECIMAL(10,4) NULL,
    sharpe_ratio    DECIMAL(10,4)   NULL,
    sortino_ratio   DECIMAL(10,4)   NULL,
    max_drawdown    DECIMAL(10,4)   NULL,
    win_rate        DECIMAL(10,4)   NULL,
    profit_factor   DECIMAL(10,4)   NULL,
    total_trades    INT             NULL,
    winning_trades  INT             NULL,
    losing_trades   INT             NULL,
    avg_win         DECIMAL(18,4)   NULL,
    avg_loss        DECIMAL(18,4)   NULL,
    created_at      DATETIME(6)     NOT NULL,
    PRIMARY KEY (metrics_id),
    UNIQUE KEY uq_metrics_run (run_id),
    KEY idx_bt_metrics_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
