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
DROP SCHEMA IF EXISTS data_ingestion;
DROP SCHEMA IF EXISTS valuation;
DROP SCHEMA IF EXISTS alert;
DROP SCHEMA IF EXISTS backtesting;
DROP SCHEMA IF EXISTS paper_trading;
DROP SCHEMA IF EXISTS ai_engine;

SET FOREIGN_KEY_CHECKS = 1;
