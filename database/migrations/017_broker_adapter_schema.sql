-- ============================================================================
-- 017_broker_adapter_schema.sql
-- Purpose: Broker adapter credentials, sessions, and API call logs
-- ============================================================================

USE platform;

-- ──────────────────────────────────────────────────────────────────────────
-- broker_credential: Encrypted API credentials per broker
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trading.broker_credential (
    credential_id     CHAR(36)        NOT NULL,
    broker_id         CHAR(36)        NOT NULL,
    credential_type   VARCHAR(20)     NOT NULL DEFAULT 'API_KEY',
    api_key_enc       TEXT            NULL,
    api_secret_enc    TEXT            NULL,
    access_token      TEXT            NULL,
    refresh_token     TEXT            NULL,
    token_expires_at  DATETIME(6)     NULL,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    created_at        DATETIME(6)     NOT NULL,
    updated_at        DATETIME(6)     NULL,
    PRIMARY KEY (credential_id),
    UNIQUE KEY uq_cred_broker_type (broker_id, credential_type),
    KEY idx_cred_broker (broker_id),
    KEY idx_cred_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- broker_api_log: Log of broker API calls for audit/debugging
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trading.broker_api_log (
    log_id            CHAR(36)        NOT NULL,
    broker_id         CHAR(36)        NOT NULL,
    method            VARCHAR(10)     NOT NULL,
    endpoint          VARCHAR(500)    NOT NULL,
    request_body      TEXT            NULL,
    response_status   INT             NULL,
    response_body     TEXT            NULL,
    latency_ms        INT             NULL,
    error_message     TEXT            NULL,
    created_at        DATETIME(6)     NOT NULL,
    PRIMARY KEY (log_id),
    KEY idx_api_log_broker (broker_id),
    KEY idx_api_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
