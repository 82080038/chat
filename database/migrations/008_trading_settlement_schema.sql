-- ============================================================================
-- 008_trading_schema.sql
-- Bounded Context 7: Trading & Execution
-- Tables: broker, decision, order_intent, order, execution
-- Also adds deferred FKs: portfolio_account.broker_id, cash_transaction.execution_id
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- broker
-- ----------------------------------------------------------------------------
CREATE TABLE trading.broker (
  broker_id       VARCHAR(36)   NOT NULL,
  name            VARCHAR(200)  NOT NULL,
  legal_name      VARCHAR(500)  NULL,
  country         CHAR(2)       NOT NULL,
  regulatory_id   VARCHAR(100)  NULL,
  api_type        ENUM('REST','WEBSOCKET','FIX','NONE') NOT NULL DEFAULT 'NONE',
  api_endpoint    VARCHAR(500)  NULL,
  status          ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  created_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (broker_id),
  KEY idx_broker_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add deferred FK: portfolio_account.broker_id → broker
ALTER TABLE portfolio.portfolio_account
  ADD CONSTRAINT fk_pa_broker FOREIGN KEY (broker_id) REFERENCES trading.broker(broker_id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- decision
-- ----------------------------------------------------------------------------
CREATE TABLE trading.decision (
  decision_id         VARCHAR(36)   NOT NULL,
  portfolio_id        VARCHAR(36)   NOT NULL,
  instrument_id       VARCHAR(36)   NOT NULL,
  recommendation_id   VARCHAR(36)   NULL,
  risk_assessment_id  VARCHAR(36)   NULL,
  action              ENUM('BUY','SELL','HOLD','ABSTAIN','REBALANCE') NOT NULL,
  intended_quantity   DECIMAL(20,8) NULL,
  intended_price      DECIMAL(20,8) NULL,
  reason              TEXT          NULL,
  confidence          DECIMAL(5,2)  NULL,
  policy_result       ENUM('APPROVED','REJECTED','MODIFIED','MANUAL_OVERRIDE') NOT NULL DEFAULT 'APPROVED',
  policy_checks       JSON          NULL,
  human_override      TINYINT(1)    NOT NULL DEFAULT 0,
  override_reason     TEXT          NULL,
  created_at          TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  status              ENUM('PENDING','APPROVED','REJECTED','EXECUTED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  PRIMARY KEY (decision_id),
  KEY idx_dec_portfolio_created (portfolio_id, created_at),
  KEY idx_dec_instrument_status (instrument_id, status),
  KEY idx_dec_recommendation (recommendation_id),
  CONSTRAINT fk_dec_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT,
  CONSTRAINT fk_dec_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT,
  CONSTRAINT fk_dec_recommendation FOREIGN KEY (recommendation_id) REFERENCES analytics.recommendation(recommendation_id) ON DELETE SET NULL,
  CONSTRAINT fk_dec_risk_assessment FOREIGN KEY (risk_assessment_id) REFERENCES risk.risk_assessment(risk_assessment_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- order_intent
-- ----------------------------------------------------------------------------
CREATE TABLE trading.order_intent (
  order_intent_id  VARCHAR(36)   NOT NULL,
  decision_id      VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NOT NULL,
  instrument_id    VARCHAR(36)   NOT NULL,
  side             ENUM('BUY','SELL') NOT NULL,
  target_quantity  DECIMAL(20,8) NOT NULL,
  target_price     DECIMAL(20,8) NULL,
  strategy         VARCHAR(50)   NULL,
  reason           VARCHAR(500)  NULL,
  status           ENUM('DRAFT','APPROVED','REJECTED','EXPIRED','CONVERTED') NOT NULL DEFAULT 'DRAFT',
  approved_at      TIMESTAMP(6)  NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at       TIMESTAMP(6)  NULL,
  PRIMARY KEY (order_intent_id),
  KEY idx_oi_decision (decision_id),
  KEY idx_oi_portfolio_status (portfolio_id, status),
  CONSTRAINT fk_oi_decision FOREIGN KEY (decision_id) REFERENCES trading.decision(decision_id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT,
  CONSTRAINT fk_oi_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- order
-- ----------------------------------------------------------------------------
CREATE TABLE trading.order (
  order_id           VARCHAR(36)   NOT NULL,
  order_ref          VARCHAR(30)   NOT NULL,
  order_intent_id    VARCHAR(36)   NOT NULL,
  portfolio_id       VARCHAR(36)   NOT NULL,
  account_id         VARCHAR(36)   NOT NULL,
  instrument_id      VARCHAR(36)   NOT NULL,
  side               ENUM('BUY','SELL') NOT NULL,
  order_type         ENUM('MARKET','LIMIT','STOP','STOP_LIMIT','ICEBERG') NOT NULL DEFAULT 'MARKET',
  quantity           DECIMAL(20,8) NOT NULL,
  filled_quantity    DECIMAL(20,8) NOT NULL DEFAULT 0,
  remaining_quantity DECIMAL(20,8) NOT NULL,
  limit_price        DECIMAL(20,8) NULL,
  stop_price         DECIMAL(20,8) NULL,
  time_in_force      ENUM('DAY','GTC','IOC','FOK','GTD') NOT NULL DEFAULT 'DAY',
  expire_at          TIMESTAMP(6)  NULL,
  broker_order_id    VARCHAR(100)  NULL,
  status             ENUM('PENDING','SUBMITTED','PARTIALLY_FILLED','FILLED','CANCELLED','REJECTED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  rejection_reason   VARCHAR(500)  NULL,
  submitted_at       TIMESTAMP(6)  NULL,
  filled_at          TIMESTAMP(6)  NULL,
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (order_id),
  UNIQUE KEY uq_order_ref (order_ref),
  KEY idx_order_portfolio_status_created (portfolio_id, status, created_at),
  KEY idx_order_instrument_status (instrument_id, status),
  KEY idx_order_broker_order_id (broker_order_id),
  CONSTRAINT fk_ord_intent FOREIGN KEY (order_intent_id) REFERENCES trading.order_intent(order_intent_id) ON DELETE RESTRICT,
  CONSTRAINT fk_ord_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT,
  CONSTRAINT fk_ord_account FOREIGN KEY (account_id) REFERENCES portfolio.portfolio_account(account_id) ON DELETE RESTRICT,
  CONSTRAINT fk_ord_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- execution
-- ----------------------------------------------------------------------------
CREATE TABLE trading.execution (
  execution_id        VARCHAR(36)   NOT NULL,
  execution_ref       VARCHAR(30)   NOT NULL,
  order_id            VARCHAR(36)   NOT NULL,
  instrument_id       VARCHAR(36)   NOT NULL,
  fill_quantity       DECIMAL(20,8) NOT NULL,
  fill_price          DECIMAL(20,8) NOT NULL,
  fill_value          DECIMAL(20,8) NOT NULL,
  commission          DECIMAL(20,8) NULL DEFAULT 0,
  fees                DECIMAL(20,8) NULL DEFAULT 0,
  taxes               DECIMAL(20,8) NULL DEFAULT 0,
  net_value           DECIMAL(20,8) NULL,
  currency            CHAR(3)       NOT NULL,
  broker_execution_id VARCHAR(100)  NULL,
  executed_at         TIMESTAMP(6)  NOT NULL,
  created_at          TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  status              ENUM('PENDING_SETTLEMENT','SETTLED','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING_SETTLEMENT',
  PRIMARY KEY (execution_id),
  UNIQUE KEY uq_exec_ref (execution_ref),
  KEY idx_exec_order (order_id),
  KEY idx_exec_instrument_executed (instrument_id, executed_at),
  KEY idx_exec_status_pending (status),
  CONSTRAINT fk_exec_order FOREIGN KEY (order_id) REFERENCES trading.order(order_id) ON DELETE RESTRICT,
  CONSTRAINT fk_exec_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add deferred FK: cash_transaction.execution_id → execution
ALTER TABLE portfolio.cash_transaction
  ADD CONSTRAINT fk_ct_execution FOREIGN KEY (execution_id) REFERENCES trading.execution(execution_id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- settlement
-- ----------------------------------------------------------------------------
CREATE TABLE settlement.settlement (
  settlement_id     VARCHAR(36)   NOT NULL,
  execution_id      VARCHAR(36)   NOT NULL,
  portfolio_id      VARCHAR(36)   NOT NULL,
  instrument_id     VARCHAR(36)   NOT NULL,
  settlement_type   ENUM('T_PLUS_1','T_PLUS_2','T_PLUS_0','SAME_DAY') NOT NULL DEFAULT 'T_PLUS_2',
  trade_date        DATE          NOT NULL,
  settlement_date   DATE          NOT NULL,
  quantity          DECIMAL(20,8) NOT NULL,
  price             DECIMAL(20,8) NOT NULL,
  gross_amount      DECIMAL(20,8) NOT NULL,
  commission        DECIMAL(20,8) NULL DEFAULT 0,
  fees              DECIMAL(20,8) NULL DEFAULT 0,
  taxes             DECIMAL(20,8) NULL DEFAULT 0,
  net_amount        DECIMAL(20,8) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  status            ENUM('PENDING','SETTLED','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  settled_at        TIMESTAMP(6)  NULL,
  created_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (settlement_id),
  UNIQUE KEY uq_set_execution (execution_id),
  KEY idx_set_portfolio_date (portfolio_id, settlement_date),
  KEY idx_set_status_date (status, settlement_date),
  CONSTRAINT fk_set_execution FOREIGN KEY (execution_id) REFERENCES trading.execution(execution_id) ON DELETE RESTRICT,
  CONSTRAINT fk_set_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE RESTRICT,
  CONSTRAINT fk_set_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- reconciliation
-- ----------------------------------------------------------------------------
CREATE TABLE settlement.reconciliation (
  reconciliation_id    VARCHAR(36)   NOT NULL,
  portfolio_id         VARCHAR(36)   NOT NULL,
  reconciliation_type  ENUM('POSITION','CASH','EXECUTION','CORPORATE_ACTION') NOT NULL,
  reconciliation_date  DATE          NOT NULL,
  internal_record_id   VARCHAR(36)   NULL,
  broker_record_id     VARCHAR(100)  NULL,
  internal_value       DECIMAL(20,8) NULL,
  broker_value         DECIMAL(20,8) NULL,
  discrepancy          DECIMAL(20,8) NULL,
  status               ENUM('MATCHED','MISMATCH','PENDING','RESOLVED','ESCALATED') NOT NULL DEFAULT 'PENDING',
  detected_at          TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  resolved_at          TIMESTAMP(6)  NULL,
  resolution           TEXT          NULL,
  created_at           TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (reconciliation_id),
  UNIQUE KEY uq_recon_portfolio_type_date_record (portfolio_id, reconciliation_type, reconciliation_date, internal_record_id),
  KEY idx_recon_portfolio_date (portfolio_id, reconciliation_date),
  KEY idx_recon_status_open (status),
  KEY idx_recon_type_date (reconciliation_type, reconciliation_date),
  CONSTRAINT fk_recon_portfolio FOREIGN KEY (portfolio_id) REFERENCES portfolio.portfolio(portfolio_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
