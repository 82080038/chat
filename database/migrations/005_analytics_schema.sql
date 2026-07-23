-- ============================================================================
-- 005_analytics_schema.sql
-- Bounded Context 4: Analytics & Intelligence
-- Tables: feature_definition, feature_value, signal, forecast, recommendation,
--         score, model_registry, backtest_run
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- feature_definition
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.feature_definition (
  feature_id          VARCHAR(36)   NOT NULL,
  feature_name        VARCHAR(100)  NOT NULL,
  feature_version     VARCHAR(20)   NOT NULL,
  description         TEXT          NULL,
  calculation_method  TEXT          NULL,
  input_dependencies  JSON          NULL,
  output_type         VARCHAR(50)   NULL,
  status              ENUM('EXPERIMENTAL','ACTIVE','DEPRECATED') NOT NULL DEFAULT 'EXPERIMENTAL',
  created_at          TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (feature_id),
  UNIQUE KEY uq_fd_feature_name (feature_name),
  KEY idx_fd_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- feature_value
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.feature_value (
  feature_value_id  VARCHAR(36)   NOT NULL,
  feature_id        VARCHAR(36)   NOT NULL,
  instrument_id     VARCHAR(36)   NOT NULL,
  timestamp         TIMESTAMP(6)  NOT NULL,
  value             DECIMAL(20,8) NULL,
  quality_score     SMALLINT      NULL,
  model_version     VARCHAR(20)   NULL,
  calculated_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (feature_value_id),
  UNIQUE KEY uq_fv_feature_inst_time (feature_id, instrument_id, timestamp),
  KEY idx_fv_instrument_time (instrument_id, timestamp),
  CONSTRAINT fk_fv_feature FOREIGN KEY (feature_id) REFERENCES analytics.feature_definition(feature_id) ON DELETE RESTRICT,
  CONSTRAINT fk_fv_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- signal
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.signal (
  signal_id          VARCHAR(36)   NOT NULL,
  instrument_id      VARCHAR(36)   NOT NULL,
  signal_type        VARCHAR(50)   NOT NULL,
  direction          ENUM('BULLISH','BEARISH','NEUTRAL') NOT NULL,
  strength           DECIMAL(5,2)  NULL,
  timeframe          VARCHAR(10)   NOT NULL,
  model_version      VARCHAR(20)   NULL,
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  valid_from         TIMESTAMP(6)  NOT NULL,
  valid_until        TIMESTAMP(6)  NULL,
  invalidated_at     TIMESTAMP(6)  NULL,
  invalidated_reason VARCHAR(200)  NULL,
  PRIMARY KEY (signal_id),
  UNIQUE KEY uq_sig_inst_type_tf_from (instrument_id, signal_type, timeframe, valid_from),
  KEY idx_sig_instrument_valid (instrument_id, valid_from, valid_until),
  KEY idx_sig_instrument_type_created (instrument_id, signal_type, created_at),
  CONSTRAINT fk_sig_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- forecast
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.forecast (
  forecast_id              VARCHAR(36)   NOT NULL,
  instrument_id            VARCHAR(36)   NOT NULL,
  target_variable          VARCHAR(50)   NOT NULL,
  horizon                  VARCHAR(20)   NOT NULL,
  prediction_value         DECIMAL(20,8) NULL,
  confidence_interval_low  DECIMAL(20,8) NULL,
  confidence_interval_high DECIMAL(20,8) NULL,
  confidence               DECIMAL(5,2)  NULL,
  model_version            VARCHAR(20)   NULL,
  feature_snapshot_id      VARCHAR(36)   NULL,
  created_at               TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  valid_until              TIMESTAMP(6)  NULL,
  PRIMARY KEY (forecast_id),
  KEY idx_fc_instrument_var_created (instrument_id, target_variable, created_at),
  KEY idx_fc_model_version_created (model_version, created_at),
  CONSTRAINT fk_fc_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE,
  CONSTRAINT fk_fc_feature_snapshot FOREIGN KEY (feature_snapshot_id) REFERENCES analytics.feature_value(feature_value_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- recommendation
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.recommendation (
  recommendation_id  VARCHAR(36)   NOT NULL,
  instrument_id      VARCHAR(36)   NOT NULL,
  action             ENUM('BUY','HOLD','SELL','ABSTAIN','NO_ACTION') NOT NULL,
  thesis             TEXT          NULL,
  confidence         DECIMAL(5,2)  NULL,
  confidence_level   ENUM('LOW','MEDIUM','HIGH') NULL,
  horizon            VARCHAR(20)   NULL,
  model_version      VARCHAR(20)   NULL,
  signal_ids         JSON          NULL,
  forecast_ids       JSON          NULL,
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  valid_until        TIMESTAMP(6)  NULL,
  status             ENUM('ACTIVE','EXPIRED','INVALIDATED','EXECUTED') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (recommendation_id),
  KEY idx_rec_instrument_status_created (instrument_id, status, created_at),
  KEY idx_rec_status_active (status),
  CONSTRAINT fk_rec_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- score
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.score (
  score_id          VARCHAR(36)   NOT NULL,
  instrument_id     VARCHAR(36)   NOT NULL,
  score_type        VARCHAR(50)   NOT NULL,
  value             DECIMAL(5,2)  NULL,
  component_scores  JSON          NULL,
  model_version     VARCHAR(20)   NULL,
  created_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  valid_until       TIMESTAMP(6)  NULL,
  PRIMARY KEY (score_id),
  KEY idx_score_instrument_type_created (instrument_id, score_type, created_at),
  CONSTRAINT fk_score_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- model_registry
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.model_registry (
  model_id           VARCHAR(36)   NOT NULL,
  model_name         VARCHAR(100)  NOT NULL,
  model_version      VARCHAR(20)   NOT NULL,
  model_type         VARCHAR(50)   NULL,
  description        TEXT          NULL,
  storage_object_id  VARCHAR(36)   NULL,
  training_dataset_id VARCHAR(36)  NULL,
  metrics            JSON          NULL,
  status             ENUM('DRAFT','VALIDATED','DEPLOYED','RETIRED') NOT NULL DEFAULT 'DRAFT',
  deployed_at        TIMESTAMP(6)  NULL,
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (model_id),
  UNIQUE KEY uq_mr_name_version (model_name, model_version),
  KEY idx_mr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- backtest_run
-- Note: portfolio_id FK added after portfolio table is created (forward reference)
-- ----------------------------------------------------------------------------
CREATE TABLE analytics.backtest_run (
  backtest_id        VARCHAR(36)   NOT NULL,
  strategy_name      VARCHAR(100)  NOT NULL,
  strategy_version   VARCHAR(20)   NOT NULL,
  model_id           VARCHAR(36)   NULL,
  portfolio_id       VARCHAR(36)   NULL,
  start_date         DATE          NOT NULL,
  end_date           DATE          NOT NULL,
  initial_capital    DECIMAL(20,4) NOT NULL,
  final_capital      DECIMAL(20,4) NULL,
  returns            DECIMAL(10,6) NULL,
  sharpe_ratio       DECIMAL(10,6) NULL,
  max_drawdown       DECIMAL(10,6) NULL,
  win_rate           DECIMAL(5,2)  NULL,
  parameters         JSON          NULL,
  results_object_id  VARCHAR(36)   NULL,
  status             ENUM('PENDING','RUNNING','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (backtest_id),
  UNIQUE KEY uq_bt_portfolio_strategy_dates (portfolio_id, strategy_name, strategy_version, start_date, end_date),
  KEY idx_bt_portfolio_created (portfolio_id, created_at),
  KEY idx_bt_strategy_status (strategy_name, status),
  CONSTRAINT fk_bt_model FOREIGN KEY (model_id) REFERENCES analytics.model_registry(model_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
