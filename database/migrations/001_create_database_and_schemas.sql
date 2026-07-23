-- ============================================================================
-- 001_create_database_and_schemas.sql
-- Platform: Global & Indonesia Capital Market Intelligence Platform
-- Database: MySQL 8+ (InnoDB, utf8mb4)
-- Purpose: Create database and all 10 schemas
-- ============================================================================

CREATE DATABASE IF NOT EXISTS platform
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE platform;

-- 10 schemas matching bounded contexts
CREATE SCHEMA IF NOT EXISTS identity;
CREATE SCHEMA IF NOT EXISTS market_master;
CREATE SCHEMA IF NOT EXISTS fundamental;
CREATE SCHEMA IF NOT EXISTS analytics;
CREATE SCHEMA IF NOT EXISTS portfolio;
CREATE SCHEMA IF NOT EXISTS risk;
CREATE SCHEMA IF NOT EXISTS trading;
CREATE SCHEMA IF NOT EXISTS settlement;
CREATE SCHEMA IF NOT EXISTS governance;
CREATE SCHEMA IF NOT EXISTS config;
