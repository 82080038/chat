-- ============================================================================
-- 013_drop_all.sql
-- Rollback: Drop all schemas (for development reset)
-- WARNING: This will destroy all data. Use with caution.
-- ============================================================================

USE platform;

DROP SCHEMA IF EXISTS config CASCADE;
DROP SCHEMA IF EXISTS governance CASCADE;
DROP SCHEMA IF EXISTS settlement CASCADE;
DROP SCHEMA IF EXISTS trading CASCADE;
DROP SCHEMA IF EXISTS risk CASCADE;
DROP SCHEMA IF EXISTS portfolio CASCADE;
DROP SCHEMA IF EXISTS analytics CASCADE;
DROP SCHEMA IF EXISTS fundamental CASCADE;
DROP SCHEMA IF EXISTS market_master CASCADE;
DROP SCHEMA IF EXISTS identity CASCADE;
