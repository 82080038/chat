-- 023_kill_switch_schema.sql
-- Add locked_at column to owner_account for kill switch support

ALTER TABLE identity.owner_account
ADD COLUMN IF NOT EXISTS locked_at TIMESTAMP(6) NULL DEFAULT NULL;
