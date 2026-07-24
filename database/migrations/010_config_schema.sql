-- ============================================================================
-- 010_config_schema.sql
-- Bounded Context 10: Configuration & System
-- Tables: configuration, feature_flag, storage_object, system_parameter,
--         api_access_log, owner_activity_log
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- configuration
-- ----------------------------------------------------------------------------
CREATE TABLE config.configuration (
  config_id        VARCHAR(36)   NOT NULL,
  config_key       VARCHAR(200)  NOT NULL,
  config_value     TEXT          NULL,
  config_type      ENUM('STRING','INTEGER','DECIMAL','BOOLEAN','JSON','ENCRYPTED') NOT NULL DEFAULT 'STRING',
  category         VARCHAR(50)   NULL,
  is_sensitive     TINYINT(1)    NOT NULL DEFAULT 0,
  description      TEXT          NULL,
  effective_from   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  effective_until  TIMESTAMP(6)  NULL,
  status           ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  version          INT           NOT NULL DEFAULT 1,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (config_id),
  UNIQUE KEY uq_cfg_key_version (config_key, version),
  KEY idx_cfg_category_status (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- feature_flag
-- ----------------------------------------------------------------------------
CREATE TABLE config.feature_flag (
  flag_id            VARCHAR(36)   NOT NULL,
  flag_key           VARCHAR(100)  NOT NULL,
  flag_name          VARCHAR(200)  NOT NULL,
  description        TEXT          NULL,
  enabled            TINYINT(1)    NOT NULL DEFAULT 0,
  effective_from     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  effective_until    TIMESTAMP(6)  NULL,
  status             ENUM('ACTIVE','DISABLED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (flag_id),
  UNIQUE KEY uq_ff_key (flag_key),
  KEY idx_ff_status_active (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- storage_object
-- ----------------------------------------------------------------------------
CREATE TABLE config.storage_object (
  object_id          VARCHAR(36)   NOT NULL,
  bucket             VARCHAR(100)  NOT NULL,
  path               VARCHAR(500)  NOT NULL,
  checksum           VARCHAR(64)   NULL,
  checksum_algorithm VARCHAR(20)   NULL DEFAULT 'SHA256',
  content_type       VARCHAR(100)  NULL,
  content_length     BIGINT        NULL,
  version            VARCHAR(50)   NOT NULL DEFAULT '1',
  entity_type        VARCHAR(50)   NULL,
  entity_id          VARCHAR(36)   NULL,
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  deleted_at         TIMESTAMP(6)  NULL,
  PRIMARY KEY (object_id),
  UNIQUE KEY uq_so_bucket_path_version (bucket, path, version),
  KEY idx_so_entity (entity_type, entity_id),
  KEY idx_so_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add deferred FKs from other schemas → storage_object
ALTER TABLE fundamental.financial_statement
  ADD CONSTRAINT fk_fs_source_doc FOREIGN KEY (source_document_id) REFERENCES config.storage_object(object_id) ON DELETE SET NULL;

ALTER TABLE fundamental.news_item
  ADD CONSTRAINT fk_news_storage_object FOREIGN KEY (storage_object_id) REFERENCES config.storage_object(object_id) ON DELETE SET NULL;

ALTER TABLE analytics.model_registry
  ADD CONSTRAINT fk_mr_storage_object FOREIGN KEY (storage_object_id) REFERENCES config.storage_object(object_id) ON DELETE SET NULL;

ALTER TABLE analytics.model_registry
  ADD CONSTRAINT fk_mr_training_dataset FOREIGN KEY (training_dataset_id) REFERENCES config.storage_object(object_id) ON DELETE SET NULL;

ALTER TABLE analytics.backtest_run
  ADD CONSTRAINT fk_bt_results_object FOREIGN KEY (results_object_id) REFERENCES config.storage_object(object_id) ON DELETE SET NULL;

-- ----------------------------------------------------------------------------
-- system_parameter
-- ----------------------------------------------------------------------------
CREATE TABLE config.system_parameter (
  param_id        VARCHAR(36)   NOT NULL,
  param_key       VARCHAR(200)  NOT NULL,
  param_value     TEXT          NULL,
  param_type      ENUM('STRING','INTEGER','DECIMAL','BOOLEAN','JSON') NOT NULL DEFAULT 'STRING',
  category        VARCHAR(50)   NULL,
  is_readonly     TINYINT(1)    NOT NULL DEFAULT 0,
  description     TEXT          NULL,
  updated_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (param_id),
  UNIQUE KEY uq_sp_param_key (param_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- api_access_log
-- ----------------------------------------------------------------------------
CREATE TABLE config.api_access_log (
  log_id            VARCHAR(36)   NOT NULL,
  endpoint          VARCHAR(500)  NOT NULL,
  method            VARCHAR(10)   NOT NULL,
  status_code       INT           NOT NULL,
  response_time_ms  INT           NULL,
  request_size      BIGINT        NULL,
  response_size     BIGINT        NULL,
  ip_address        VARCHAR(45)   NULL,
  user_agent        VARCHAR(500)  NULL,
  correlation_id    VARCHAR(36)   NULL,
  created_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  retention_until   DATE          NULL,
  PRIMARY KEY (log_id),
  KEY idx_aal_created (created_at),
  KEY idx_aal_endpoint_status (endpoint, status_code),
  KEY idx_aal_retention (retention_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- owner_activity_log
-- ----------------------------------------------------------------------------
CREATE TABLE config.owner_activity_log (
  activity_id       VARCHAR(36)   NOT NULL,
  activity_type     VARCHAR(50)   NOT NULL,
  entity_type       VARCHAR(50)   NULL,
  entity_id         VARCHAR(36)   NULL,
  description       VARCHAR(500)  NULL,
  ip_address        VARCHAR(45)   NULL,
  created_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  retention_until   DATE          NULL,
  PRIMARY KEY (activity_id),
  KEY idx_oal_created (created_at),
  KEY idx_oal_entity (entity_type, entity_id),
  KEY idx_oal_retention (retention_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
