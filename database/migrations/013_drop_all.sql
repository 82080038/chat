-- ============================================================================
-- 013_drop_all.sql
-- Rollback: Drop all schemas (for development reset)
-- WARNING: This will destroy all data. Use with caution.
-- ============================================================================

USE platform;

SET FOREIGN_KEY_CHECKS = 0;

DROP SCHEMA IF EXISTS config;
DROP SCHEMA IF EXISTS governance;
DROP SCHEMA IF EXISTS settlement;
DROP SCHEMA IF EXISTS trading;
DROP SCHEMA IF EXISTS risk;
DROP SCHEMA IF EXISTS portfolio;
DROP SCHEMA IF EXISTS analytics;
DROP SCHEMA IF EXISTS fundamental;
DROP SCHEMA IF EXISTS market_master;
DROP SCHEMA IF EXISTS identity;

SET FOREIGN_KEY_CHECKS = 1;
