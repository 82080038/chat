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
  actor_type      ENUM('OWNER','SYSTEM','BROKER') NOT NULL,
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
  KEY idx_audit_created (created_at),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_actor_created (actor_type, created_at),
  KEY idx_audit_correlation (correlation_id),
  KEY idx_audit_retention (retention_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- approval
-- ----------------------------------------------------------------------------
CREATE TABLE governance.approval (
  approval_id       VARCHAR(36)   NOT NULL,
  entity_type       VARCHAR(50)   NOT NULL,
  entity_id         VARCHAR(36)   NOT NULL,
  approval_type     ENUM('ORDER','DECISION','REBALANCE','RISK_OVERRIDE','MODEL_DEPLOY') NOT NULL,
  requested_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  approved_at       TIMESTAMP(6)  NULL,
  rejected_at       TIMESTAMP(6)  NULL,
  rejection_reason  TEXT          NULL,
  status            ENUM('PENDING','APPROVED','REJECTED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  expires_at        TIMESTAMP(6)  NULL,
  created_at        TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (approval_id),
  KEY idx_approval_status_expires (status, expires_at),
  KEY idx_approval_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- workflow
-- ----------------------------------------------------------------------------
CREATE TABLE governance.workflow (
  workflow_id      VARCHAR(36)   NOT NULL,
  workflow_type    VARCHAR(50)   NOT NULL,
  entity_type      VARCHAR(50)   NULL,
  entity_id        VARCHAR(36)   NULL,
  current_step     INT           NOT NULL DEFAULT 0,
  total_steps      INT           NOT NULL,
  status           ENUM('PENDING','IN_PROGRESS','COMPLETED','CANCELLED','FAILED') NOT NULL DEFAULT 'PENDING',
  initiated_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  completed_at     TIMESTAMP(6)  NULL,
  metadata         JSON          NULL,
  PRIMARY KEY (workflow_id),
  KEY idx_wf_status (status),
  KEY idx_wf_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- workflow_step
-- ----------------------------------------------------------------------------
CREATE TABLE governance.workflow_step (
  step_id          VARCHAR(36)   NOT NULL,
  workflow_id      VARCHAR(36)   NOT NULL,
  step_number      INT           NOT NULL,
  step_name        VARCHAR(100)  NOT NULL,
  step_type        ENUM('CONFIRMATION','NOTIFICATION','VALIDATION','EXECUTION','WAIT') NOT NULL,
  status           ENUM('PENDING','IN_PROGRESS','COMPLETED','SKIPPED','FAILED') NOT NULL DEFAULT 'PENDING',
  started_at       TIMESTAMP(6)  NULL,
  completed_at     TIMESTAMP(6)  NULL,
  result           JSON          NULL,
  notes            TEXT          NULL,
  PRIMARY KEY (step_id),
  UNIQUE KEY uq_ws_workflow_stepnum (workflow_id, step_number),
  KEY idx_ws_status (status),
  CONSTRAINT fk_ws_workflow FOREIGN KEY (workflow_id) REFERENCES governance.workflow(workflow_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- policy
-- ----------------------------------------------------------------------------
CREATE TABLE governance.policy (
  policy_id        VARCHAR(36)   NOT NULL,
  policy_type      ENUM('TRADING','RISK','COMPLIANCE','DATA_GOVERNANCE') NOT NULL,
  name             VARCHAR(200)  NOT NULL,
  description      TEXT          NULL,
  rules            JSON          NULL,
  priority         INT           NOT NULL DEFAULT 0,
  effective_from   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  effective_until  TIMESTAMP(6)  NULL,
  status           ENUM('DRAFT','ACTIVE','SUPERSEDED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  version          INT           NOT NULL DEFAULT 1,
  created_at       TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (policy_id),
  UNIQUE KEY uq_pol_type_version (policy_type, version),
  KEY idx_pol_type_status (policy_type, status),
  KEY idx_pol_effective (effective_from, effective_until)
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
  PRIMARY KEY (evaluation_id),
  UNIQUE KEY uq_pe_policy_entity_time (policy_id, entity_type, entity_id, evaluated_at),
  KEY idx_pe_entity (entity_type, entity_id),
  KEY idx_pe_evaluated_at (evaluated_at),
  CONSTRAINT fk_pe_policy FOREIGN KEY (policy_id) REFERENCES governance.policy(policy_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
