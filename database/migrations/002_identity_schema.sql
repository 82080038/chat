-- ============================================================================
-- 002_identity_schema.sql
-- Bounded Context 1: Single-Owner Identity & Access
-- Tables: owner_account, owner_preference
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
  last_login_at   TIMESTAMP(6)  NULL,
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
