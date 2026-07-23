-- ============================================================================
-- 006_risk_schema.sql
-- Bounded Context 6: Risk Management
-- Tables: risk_profile, risk_limit, risk_assessment, risk_event
-- NOTE: Created BEFORE portfolio because portfolio references risk_profile_id
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- risk_profile
-- ----------------------------------------------------------------------------
CREATE TABLE risk.risk_profile (
  risk_profile_id      VARCHAR(36)   NOT NULL,
  tenant_id            VARCHAR(36)   NOT NULL,
  name                 VARCHAR(200)  NOT NULL,
  risk_tolerance       ENUM('CONSERVATIVE','MODERATE','AGGRESSIVE','SPECULATIVE') NOT NULL,
  max_single_position  DECIMAL(10,6) NULL,
  max_sector_exposure  DECIMAL(10,6) NULL,
  max_portfolio_beta   DECIMAL(10,4) NULL,
  max_var_pct          DECIMAL(10,6) NULL,
  max_drawdown_pct     DECIMAL(10,6) NULL,
  min_liquidity_days   INT           NULL,
  status               ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_at           TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at           TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (risk_profile_id),
  UNIQUE KEY uq_rp_tenant_name (tenant_id, name),
  KEY idx_rp_tenant_status (tenant_id, status),
  CONSTRAINT fk_rp_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- risk_limit
-- Note: portfolio_id FK added after portfolio table is created
-- ----------------------------------------------------------------------------
CREATE TABLE risk.risk_limit (
  risk_limit_id    VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NULL,
  limit_type       VARCHAR(50)   NOT NULL,
  limit_value      DECIMAL(20,8) NOT NULL,
  limit_unit       VARCHAR(20)   NULL,
  time_horizon     VARCHAR(20)   NULL,
  confidence_level DECIMAL(5,2)  NULL,
  status           ENUM('ACTIVE','BREACHED','SUSPENDED','REMOVED') NOT NULL DEFAULT 'ACTIVE',
  effective_from   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  effective_until  TIMESTAMP(6)  NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (risk_limit_id),
  KEY idx_rl_portfolio_status (portfolio_id, status),
  KEY idx_rl_portfolio_type_effective (portfolio_id, limit_type, effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- risk_assessment
-- Note: portfolio_id FK added after portfolio table is created
-- ----------------------------------------------------------------------------
CREATE TABLE risk.risk_assessment (
  risk_assessment_id  VARCHAR(36)   NOT NULL,
  portfolio_id        VARCHAR(36)   NULL,
  assessment_type     VARCHAR(50)   NOT NULL,
  var_95              DECIMAL(20,8) NULL,
  var_99              DECIMAL(20,8) NULL,
  expected_shortfall  DECIMAL(20,8) NULL,
  portfolio_beta      DECIMAL(10,6) NULL,
  sharpe_ratio        DECIMAL(10,6) NULL,
  sortino_ratio       DECIMAL(10,6) NULL,
  max_drawdown        DECIMAL(10,6) NULL,
  volatility          DECIMAL(10,6) NULL,
  concentration_index DECIMAL(10,6) NULL,
  currency            CHAR(3)       NULL,
  as_of               TIMESTAMP(6)  NOT NULL,
  model_version       VARCHAR(20)   NULL,
  created_at          TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (risk_assessment_id),
  KEY idx_ra_portfolio_asof (portfolio_id, as_of)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- risk_event
-- Note: portfolio_id and resolved_by FKs added after portfolio/user tables exist
-- ----------------------------------------------------------------------------
CREATE TABLE risk.risk_event (
  risk_event_id    VARCHAR(36)   NOT NULL,
  portfolio_id     VARCHAR(36)   NULL,
  risk_limit_id    VARCHAR(36)   NULL,
  event_type       ENUM('LIMIT_BREACH','WARNING','RECOVERY','OVERRIDE') NOT NULL,
  severity         ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
  description      TEXT          NULL,
  current_value    DECIMAL(20,8) NULL,
  limit_value      DECIMAL(20,8) NULL,
  detected_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  resolved_at      TIMESTAMP(6)  NULL,
  resolution       TEXT          NULL,
  resolved_by      VARCHAR(36)   NULL,
  status           ENUM('OPEN','ACKNOWLEDGED','RESOLVED','ESCALATED') NOT NULL DEFAULT 'OPEN',
  PRIMARY KEY (risk_event_id),
  KEY idx_re_portfolio_status (portfolio_id, status),
  KEY idx_re_limit_status (risk_limit_id, status),
  KEY idx_re_detected_at (detected_at),
  CONSTRAINT fk_re_limit FOREIGN KEY (risk_limit_id) REFERENCES risk.risk_limit(risk_limit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
