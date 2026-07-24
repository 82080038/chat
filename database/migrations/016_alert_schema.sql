-- ============================================================================
-- 016_alert_schema.sql
-- Purpose: Alert system — price, signal, risk alerts with notifications
-- ============================================================================

USE platform;

CREATE SCHEMA IF NOT EXISTS alert;

-- ──────────────────────────────────────────────────────────────────────────
-- alert: Alert definitions (price, signal, risk)
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alert.alert (
    alert_id        CHAR(36)        NOT NULL,
    alert_type      VARCHAR(20)     NOT NULL,
    instrument_id   CHAR(36)        NULL,
    portfolio_id    CHAR(36)        NULL,
    condition_op    VARCHAR(10)     NOT NULL,
    threshold       DECIMAL(18,4)   NOT NULL,
    description     VARCHAR(500)    NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    triggered_count INT             NOT NULL DEFAULT 0,
    last_triggered  DATETIME(6)     NULL,
    created_at      DATETIME(6)     NOT NULL,
    updated_at      DATETIME(6)     NULL,
    PRIMARY KEY (alert_id),
    KEY idx_alert_type (alert_type),
    KEY idx_alert_active (is_active),
    KEY idx_alert_instrument (instrument_id),
    KEY idx_alert_portfolio (portfolio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- alert_rule: Additional rules/parameters for an alert
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alert.alert_rule (
    rule_id         CHAR(36)        NOT NULL,
    alert_id        CHAR(36)        NOT NULL,
    rule_key        VARCHAR(50)     NOT NULL,
    rule_value      VARCHAR(200)    NOT NULL,
    PRIMARY KEY (rule_id),
    UNIQUE KEY uq_rule_alert_key (alert_id, rule_key),
    KEY idx_rule_alert (alert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────────────
-- alert_notification: Fired alert notifications
-- ──────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS alert.alert_notification (
    notification_id CHAR(36)        NOT NULL,
    alert_id        CHAR(36)        NOT NULL,
    trigger_value   DECIMAL(18,4)   NULL,
    message         VARCHAR(500)    NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'PENDING',
    acknowledged_at DATETIME(6)     NULL,
    created_at      DATETIME(6)     NOT NULL,
    PRIMARY KEY (notification_id),
    KEY idx_notif_alert (alert_id),
    KEY idx_notif_status (status),
    KEY idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
