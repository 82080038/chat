-- ============================================================================
-- 002_identity_schema.sql
-- Bounded Context 1: Identity & Access Management
-- Tables: tenant, user, role, permission, user_role, role_permission,
--         api_client, user_preference
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- tenant
-- ----------------------------------------------------------------------------
CREATE TABLE identity.tenant (
  tenant_id      VARCHAR(36)   NOT NULL,
  name           VARCHAR(200)  NOT NULL,
  slug           VARCHAR(100)  NOT NULL,
  plan           ENUM('FREE','PRO','ENTERPRISE') NOT NULL DEFAULT 'FREE',
  status         ENUM('ACTIVE','SUSPENDED','TERMINATED') NOT NULL DEFAULT 'ACTIVE',
  created_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (tenant_id),
  UNIQUE KEY uq_tenant_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- user
-- ----------------------------------------------------------------------------
CREATE TABLE identity.user (
  user_id        VARCHAR(36)   NOT NULL,
  tenant_id      VARCHAR(36)   NOT NULL,
  email          VARCHAR(255)  NOT NULL,
  password_hash  VARCHAR(255)  NULL,
  legal_name     VARCHAR(500)  NULL,
  display_name   VARCHAR(200)  NULL,
  phone          VARCHAR(50)   NULL,
  status         ENUM('ACTIVE','SUSPENDED','ERASED') NOT NULL DEFAULT 'ACTIVE',
  email_verified TINYINT(1)    NOT NULL DEFAULT 0,
  last_login_at  TIMESTAMP(6)  NULL,
  created_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_user_tenant_email (tenant_id, email),
  KEY idx_user_tenant_status (tenant_id, status),
  CONSTRAINT fk_user_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- role
-- ----------------------------------------------------------------------------
CREATE TABLE identity.role (
  role_id        VARCHAR(36)   NOT NULL,
  tenant_id      VARCHAR(36)   NOT NULL,
  name           VARCHAR(100)  NOT NULL,
  description    VARCHAR(500)  NULL,
  is_system      TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_role_tenant_name (tenant_id, name),
  CONSTRAINT fk_role_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- permission
-- ----------------------------------------------------------------------------
CREATE TABLE identity.permission (
  permission_id  VARCHAR(36)   NOT NULL,
  name           VARCHAR(100)  NOT NULL,
  description    VARCHAR(500)  NULL,
  category       VARCHAR(50)   NULL,
  PRIMARY KEY (permission_id),
  UNIQUE KEY uq_permission_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- user_role (junction)
-- ----------------------------------------------------------------------------
CREATE TABLE identity.user_role (
  user_id        VARCHAR(36)   NOT NULL,
  role_id        VARCHAR(36)   NOT NULL,
  assigned_at    TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id, role_id),
  KEY idx_user_role_user (user_id),
  KEY idx_user_role_role (role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES identity.user(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES identity.role(role_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- role_permission (junction)
-- ----------------------------------------------------------------------------
CREATE TABLE identity.role_permission (
  role_id        VARCHAR(36)   NOT NULL,
  permission_id  VARCHAR(36)   NOT NULL,
  granted_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (role_id, permission_id),
  KEY idx_role_perm_role (role_id),
  KEY idx_role_perm_perm (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES identity.role(role_id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES identity.permission(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- api_client
-- ----------------------------------------------------------------------------
CREATE TABLE identity.api_client (
  api_client_id  VARCHAR(36)   NOT NULL,
  tenant_id      VARCHAR(36)   NOT NULL,
  user_id        VARCHAR(36)   NULL,
  name           VARCHAR(200)  NOT NULL,
  api_key_hash   VARCHAR(255)  NOT NULL,
  scopes         JSON          NULL,
  status         ENUM('ACTIVE','REVOKED') NOT NULL DEFAULT 'ACTIVE',
  expires_at     TIMESTAMP(6)  NULL,
  created_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (api_client_id),
  UNIQUE KEY uq_api_client_key_hash (api_key_hash),
  KEY idx_api_client_tenant_status (tenant_id, status),
  CONSTRAINT fk_ac_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_ac_user FOREIGN KEY (user_id) REFERENCES identity.user(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- user_preference
-- ----------------------------------------------------------------------------
CREATE TABLE identity.user_preference (
  user_id          VARCHAR(36)   NOT NULL,
  timezone         VARCHAR(50)   NULL DEFAULT 'Asia/Jakarta',
  language         VARCHAR(10)   NULL DEFAULT 'id',
  base_currency    CHAR(3)       NULL DEFAULT 'IDR',
  default_exchange VARCHAR(36)   NULL,
  theme            VARCHAR(20)   NULL DEFAULT 'light',
  updated_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES identity.user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
