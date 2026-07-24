-- ============================================================================
-- 007_portfolio_schema.sql
-- Bounded Context 5: Portfolio Management
-- Tables: portfolio, portfolio_account, position, position_snapshot,
--         cash_balance, cash_transaction, portfolio_target
-- Note: broker table from trading schema must exist first for portfolio_account.broker_id
--       broker is created in 008_trading_schema.sql, so FK is added there
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- portfolio
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.portfolio (
  portfolio_id     VARCHAR(36)   NOT NULL,
  name             VARCHAR(200)  NOT NULL,
  description      TEXT          NULL,
  base_currency    CHAR(3)       NOT NULL DEFAULT 'IDR',
  portfolio_type   ENUM('LIVE','PAPER','BACKTEST','SHADOW') NOT NULL DEFAULT 'PAPER',
  status           ENUM('ACTIVE','FROZEN','CLOSED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  inception_date   DATE          NULL,
  benchmark_id     VARCHAR(36)   NULL,
  risk_profile_id  VARCHAR(36)   NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (portfolio_id),
  UNIQUE KEY uq_port_name (name),
  KEY idx_port_type_status (portfolio_type, status),
  CONSTRAINT fk_port_benchmark FOREIGN KEY (benchmark_id) REFERENCES market_master.index_master(index_id) ON DELETE SET NULL,
  CONSTRAINT fk_port_risk_profile FOREIGN KEY (risk_profile_id) REFERENCES risk.risk_profile(risk_profile_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- portfolio_account
-- broker_id FK added in 008_trading_schema.sql after broker table is created
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.portfolio_account (
  account_id          VARCHAR(36)   NOT NULL,
  portfolio_id        VARCHAR(36)   NOT NULL,
  broker_id           VARCHAR(36)   NULL,
  broker_account_code VARCHAR(100)  NULL,
  account_type        ENUM('CASH','MARGIN','SHORT') NOT NULL DEFAULT 'CASH',
  currency            CHAR(3)       NOT NULL,
  status              ENUM('ACTIVE','CLOSED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  opened_at           TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (account_id),
  KEY idx_pa_portfolio_status (portfolio_id, status),
  KEY idx_pa_broker_code (broker_id, broker_account_code),
  CONSTRAINT fk_pa_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- position
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.position (
  position_id      VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NOT NULL,
  instrument_id    VARCHAR(36)   NOT NULL,
  quantity         DECIMAL(20,8) NOT NULL DEFAULT 0,
  average_cost     DECIMAL(20,8) NULL,
  realized_pnl     DECIMAL(20,8) NULL DEFAULT 0,
  unrealized_pnl   DECIMAL(20,8) NULL DEFAULT 0,
  position_type    ENUM('LONG','SHORT') NOT NULL DEFAULT 'LONG',
  status           ENUM('OPEN','CLOSED','PARTIALLY_CLOSED') NOT NULL DEFAULT 'OPEN',
  opened_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  closed_at        TIMESTAMP(6)  NULL,
  as_of            TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (position_id),
  KEY idx_pos_portfolio_instrument_status (portfolio_id, instrument_id, status),
  KEY idx_pos_portfolio_asof (portfolio_id, as_of),
  CONSTRAINT fk_pos_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT,
  CONSTRAINT fk_pos_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- position_snapshot
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.position_snapshot (
  snapshot_id      VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NOT NULL,
  instrument_id    VARCHAR(36)   NOT NULL,
  quantity         DECIMAL(20,8) NOT NULL,
  average_cost     DECIMAL(20,8) NULL,
  market_price     DECIMAL(20,8) NULL,
  market_value     DECIMAL(20,8) NULL,
  unrealized_pnl   DECIMAL(20,8) NULL,
  realized_pnl     DECIMAL(20,8) NULL,
  weight           DECIMAL(10,6) NULL,
  snapshot_date    DATE          NOT NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (snapshot_id),
  UNIQUE KEY uq_ps_portfolio_instrument_date (portfolio_id, instrument_id, snapshot_date),
  KEY idx_ps_portfolio_date (portfolio_id, snapshot_date),
  CONSTRAINT fk_ps_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- cash_balance
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.cash_balance (
  cash_balance_id    VARCHAR(36)   NOT NULL,
  portfolio_id       VARCHAR(36)   NOT NULL,
  currency           CHAR(3)       NOT NULL,
  ledger_balance     DECIMAL(20,8) NOT NULL DEFAULT 0,
  settled_balance    DECIMAL(20,8) NOT NULL DEFAULT 0,
  available_balance  DECIMAL(20,8) NOT NULL DEFAULT 0,
  reserved_balance   DECIMAL(20,8) NOT NULL DEFAULT 0,
  as_of              TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (cash_balance_id),
  UNIQUE KEY uq_cb_portfolio_currency (portfolio_id, currency),
  CONSTRAINT fk_cb_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- cash_transaction
-- execution_id FK added in 008_trading_schema.sql after execution table is created
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.cash_transaction (
  cash_txn_id      VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NOT NULL,
  currency         CHAR(3)       NOT NULL,
  transaction_type ENUM('DEPOSIT','WITHDRAWAL','DIVIDEND','INTEREST','FEE','TAX','SETTLEMENT','COMMISSION') NOT NULL,
  amount           DECIMAL(20,8) NOT NULL,
  direction        ENUM('CREDIT','DEBIT') NOT NULL,
  execution_id     VARCHAR(36)   NULL,
  description      VARCHAR(500)  NULL,
  value_date       DATE          NOT NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  status           ENUM('PENDING','SETTLED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  PRIMARY KEY (cash_txn_id),
  KEY idx_ct_portfolio_value_date (portfolio_id, value_date),
  KEY idx_ct_execution (execution_id),
  CONSTRAINT fk_ct_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- portfolio_target
-- ----------------------------------------------------------------------------
CREATE TABLE portfolio.portfolio_target (
  target_id        VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NOT NULL,
  instrument_id    VARCHAR(36)   NOT NULL,
  target_weight    DECIMAL(10,6) NULL,
  target_quantity  DECIMAL(20,8) NULL,
  target_type      ENUM('WEIGHT','QUANTITY','RANGE') NOT NULL DEFAULT 'WEIGHT',
  min_weight       DECIMAL(10,6) NULL,
  max_weight       DECIMAL(10,6) NULL,
  effective_from   DATE          NOT NULL,
  effective_until  DATE          NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (target_id),
  UNIQUE KEY uq_pt_portfolio_instrument_from (portfolio_id, instrument_id, effective_from),
  KEY idx_pt_portfolio_effective (portfolio_id, effective_from, effective_until),
  CONSTRAINT fk_pt_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ADD FORWARD FKs: risk tables → portfolio
-- ============================================================================
ALTER TABLE risk.risk_limit
  ADD CONSTRAINT fk_rl_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE CASCADE;

ALTER TABLE risk.risk_assessment
  ADD CONSTRAINT fk_ra_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE CASCADE;

ALTER TABLE risk.risk_event
  ADD CONSTRAINT fk_re_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE CASCADE;

-- ============================================================================
-- ADD FORWARD FK: backtest_run → portfolio
-- ============================================================================
ALTER TABLE analytics.backtest_run
  ADD CONSTRAINT fk_bt_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE SET NULL;
