-- ============================================================================
-- 009_governance_schema.sql
-- Bounded Context 9: Governance & Audit
-- Tables: audit_log, approval, workflow, workflow_step, policy, policy_evaluation
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- audit_log (APPEND-ONLY)
-- ----------------------------------------------------------------------------
CREATE TABLE governance.audit_log (
  audit_log_id    VARCHAR(36)   NOT NULL,
  tenant_id       VARCHAR(36)   NOT NULL,
  actor_type      ENUM('USER','SYSTEM','API_CLIENT','BROKER') NOT NULL,
  actor_id        VARCHAR(36)   NULL,
  action          VARCHAR(100)  NOT NULL,
  entity_type     VARCHAR(50)   NULL,
  entity_id       VARCHAR(36)   NULL,
  old_values      JSON          NULL,
  new_values      JSON          NULL,
  ip_address      VARCHAR(45)   NULL,
  user_agent      VARCHAR(500)  NULL,
  correlation_id  VARCHAR(36)   NULL,
  event_id        VARCHAR(36)   NULL,
  created_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  retention_until DATE          NULL,
  PRIMARY KEY (audit_log_id),
  KEY idx_audit_tenant_created (tenant_id, created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_actor (actor_type, actor_id, created_at),
  KEY idx_audit_correlation (correlation_id),
  KEY idx_audit_retention (retention_until),
  CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- approval
-- ----------------------------------------------------------------------------
CREATE TABLE governance.approval (
  approval_id       VARCHAR(36)   NOT NULL,
  tenant_id         VARCHAR(36)   NOT NULL,
  entity_type       VARCHAR(50)   NOT NULL,
  entity_id         VARCHAR(36)   NOT NULL,
  approval_type     ENUM('ORDER','DECISION','REBALANCE','RISK_OVERRIDE','MODEL_DEPLOY') NOT NULL,
  requested_by      VARCHAR(36)   NOT NULL,
  requested_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  approved_by       VARCHAR(36)   NULL,
  approved_at       TIMESTAMP(6)  NULL,
  rejected_by       VARCHAR(36)   NULL,
  rejected_at       TIMESTAMP(6)  NULL,
  rejection_reason  TEXT          NULL,
  status            ENUM('PENDING','APPROVED','REJECTED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  expires_at        TIMESTAMP(6)  NULL,
  created_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (approval_id),
  KEY idx_approval_tenant_status_expires (tenant_id, status, expires_at),
  KEY idx_approval_entity (entity_type, entity_id),
  KEY idx_approval_requested_by_status (requested_by, status),
  CONSTRAINT fk_approval_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_approval_requested_by FOREIGN KEY (requested_by) REFERENCES identity.user(user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_approval_approved_by FOREIGN KEY (approved_by) REFERENCES identity.user(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_approval_rejected_by FOREIGN KEY (rejected_by) REFERENCES identity.user(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- workflow
-- ----------------------------------------------------------------------------
CREATE TABLE governance.workflow (
  workflow_id      VARCHAR(36)   NOT NULL,
  tenant_id        VARCHAR(36)   NOT NULL,
  workflow_type    VARCHAR(50)   NOT NULL,
  entity_type      VARCHAR(50)   NULL,
  entity_id        VARCHAR(36)   NULL,
  current_step     INT           NOT NULL DEFAULT 0,
  total_steps      INT           NOT NULL,
  status           ENUM('PENDING','IN_PROGRESS','COMPLETED','CANCELLED','FAILED') NOT NULL DEFAULT 'PENDING',
  initiated_by     VARCHAR(36)   NOT NULL,
  initiated_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  completed_at     TIMESTAMP(6)  NULL,
  metadata         JSON          NULL,
  PRIMARY KEY (workflow_id),
  KEY idx_wf_tenant_status (tenant_id, status),
  KEY idx_wf_entity (entity_type, entity_id),
  CONSTRAINT fk_wf_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_wf_initiated_by FOREIGN KEY (initiated_by) REFERENCES identity.user(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- workflow_step
-- ----------------------------------------------------------------------------
CREATE TABLE governance.workflow_step (
  step_id          VARCHAR(36)   NOT NULL,
  workflow_id      VARCHAR(36)   NOT NULL,
  step_number      INT           NOT NULL,
  step_name        VARCHAR(100)  NOT NULL,
  step_type        ENUM('APPROVAL','NOTIFICATION','VALIDATION','EXECUTION','WAIT') NOT NULL,
  assigned_to      VARCHAR(36)   NULL,
  assigned_role    VARCHAR(100)  NULL,
  status           ENUM('PENDING','IN_PROGRESS','COMPLETED','SKIPPED','FAILED') NOT NULL DEFAULT 'PENDING',
  started_at       TIMESTAMP(6)  NULL,
  completed_at     TIMESTAMP(6)  NULL,
  result           JSON          NULL,
  notes            TEXT          NULL,
  PRIMARY KEY (step_id),
  UNIQUE KEY uq_ws_workflow_stepnum (workflow_id, step_number),
  KEY idx_ws_assigned_to_status (assigned_to, status),
  CONSTRAINT fk_ws_workflow FOREIGN KEY (workflow_id) REFERENCES governance.workflow(workflow_id) ON DELETE CASCADE,
  CONSTRAINT fk_ws_assigned_to FOREIGN KEY (assigned_to) REFERENCES identity.user(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- policy
-- ----------------------------------------------------------------------------
CREATE TABLE governance.policy (
  policy_id        VARCHAR(36)   NOT NULL,
  tenant_id        VARCHAR(36)   NOT NULL,
  policy_type      ENUM('TRADING','RISK','COMPLIANCE','DATA_GOVERNANCE') NOT NULL,
  name             VARCHAR(200)  NOT NULL,
  description      TEXT          NULL,
  rules            JSON          NULL,
  priority         INT           NOT NULL DEFAULT 0,
  effective_from   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  effective_until  TIMESTAMP(6)  NULL,
  status           ENUM('DRAFT','ACTIVE','SUPERSEDED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  version          INT           NOT NULL DEFAULT 1,
  created_by       VARCHAR(36)   NOT NULL,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (policy_id),
  UNIQUE KEY uq_pol_tenant_type_version (tenant_id, policy_type, version),
  KEY idx_pol_tenant_type_status (tenant_id, policy_type, status),
  KEY idx_pol_tenant_effective (tenant_id, effective_from, effective_until),
  CONSTRAINT fk_pol_tenant FOREIGN KEY (tenant_id) REFERENCES identity.tenant(tenant_id) ON DELETE RESTRICT,
  CONSTRAINT fk_pol_created_by FOREIGN KEY (created_by) REFERENCES identity.user(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- policy_evaluation
-- ----------------------------------------------------------------------------
CREATE TABLE governance.policy_evaluation (
  evaluation_id      VARCHAR(36)   NOT NULL,
  policy_id          VARCHAR(36)   NOT NULL,
  entity_type        VARCHAR(50)   NOT NULL,
  entity_id          VARCHAR(36)   NOT NULL,
  evaluation_result  ENUM('PASS','FAIL','WARN','SKIP') NOT NULL,
  rule_results       JSON          NULL,
  evaluated_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  evaluated_by       VARCHAR(36)   NULL,
  PRIMARY KEY (evaluation_id),
  UNIQUE KEY uq_pe_policy_entity_time (policy_id, entity_type, entity_id, evaluated_at),
  KEY idx_pe_entity (entity_type, entity_id),
  KEY idx_pe_evaluated_at (evaluated_at),
  CONSTRAINT fk_pe_policy FOREIGN KEY (policy_id) REFERENCES governance.policy(policy_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
