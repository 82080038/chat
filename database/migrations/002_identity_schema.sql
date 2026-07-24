-- ============================================================================
-- 002_identity_schema.sql
-- Bounded Context 1: Single-Owner Identity & Access
-- Tables: owner_account, owner_preference, owner_session
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- owner_account
-- ----------------------------------------------------------------------------
CREATE TABLE identity.owner_account (
  owner_id        VARCHAR(36)   NOT NULL,
  singleton_key   TINYINT       NOT NULL DEFAULT 1,
  email           VARCHAR(255)  NOT NULL,
  password_hash   VARCHAR(255)  NOT NULL,
  legal_name      VARCHAR(500)  NULL,
  display_name    VARCHAR(200)  NULL,
  phone           VARCHAR(50)   NULL,
  status          ENUM('ACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  failed_login_attempts INT      NOT NULL DEFAULT 0,
  locked_until    TIMESTAMP(6)  NULL,
  last_login_at   TIMESTAMP(6)  NULL,
  password_changed_at TIMESTAMP(6) NULL,
  created_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (owner_id),
  UNIQUE KEY uq_owner_singleton (singleton_key),
  UNIQUE KEY uq_owner_email (email),
  CONSTRAINT chk_owner_singleton CHECK (singleton_key = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- owner_preference
-- ----------------------------------------------------------------------------
CREATE TABLE identity.owner_preference (
  owner_id         VARCHAR(36)   NOT NULL,
  timezone         VARCHAR(50)   NOT NULL DEFAULT 'Asia/Jakarta',
  language         VARCHAR(10)   NOT NULL DEFAULT 'id',
  base_currency    CHAR(3)       NOT NULL DEFAULT 'IDR',
  default_exchange VARCHAR(36)   NULL,
  theme            VARCHAR(20)   NOT NULL DEFAULT 'light',
  updated_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (owner_id),
  CONSTRAINT fk_op_owner FOREIGN KEY (owner_id) REFERENCES identity.owner_account(owner_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE identity.owner_session (
  session_id         VARCHAR(36)   NOT NULL,
  owner_id           VARCHAR(36)   NOT NULL,
  refresh_token_hash CHAR(64)      NOT NULL,
  access_jti         VARCHAR(36)   NOT NULL,
  ip_address         VARCHAR(45)   NULL,
  user_agent         VARCHAR(500)  NULL,
  expires_at         TIMESTAMP(6)  NOT NULL,
  revoked_at         TIMESTAMP(6)  NULL,
  created_at         TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  last_used_at       TIMESTAMP(6)  NULL,
  PRIMARY KEY (session_id),
  UNIQUE KEY uq_owner_session_refresh_hash (refresh_token_hash),
  UNIQUE KEY uq_owner_session_access_jti (access_jti),
  KEY idx_owner_session_owner_active (owner_id, revoked_at, expires_at),
  CONSTRAINT fk_owner_session_owner FOREIGN KEY (owner_id) REFERENCES identity.owner_account(owner_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
