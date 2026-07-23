-- ============================================================================
-- 012_seed_data.sql
-- Seed data: exchanges, default tenant, admin user, default config
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- Seed: Exchanges
-- ----------------------------------------------------------------------------
INSERT INTO market_master.exchange (exchange_id, name, mic_code, country, timezone, currency, status) VALUES
  (UUID(), 'Indonesia Stock Exchange', 'XIDX', 'ID', 'Asia/Jakarta', 'IDR', 'ACTIVE'),
  (UUID(), 'New York Stock Exchange', 'XNYS', 'US', 'America/New_York', 'USD', 'ACTIVE'),
  (UUID(), 'Nasdaq', 'XNAS', 'US', 'America/New_York', 'USD', 'ACTIVE'),
  (UUID(), 'London Stock Exchange', 'XLON', 'GB', 'Europe/London', 'GBP', 'ACTIVE'),
  (UUID(), 'Tokyo Stock Exchange', 'XTKS', 'JP', 'Asia/Tokyo', 'JPY', 'ACTIVE'),
  (UUID(), 'Singapore Exchange', 'XSES', 'SG', 'Asia/Singapore', 'SGD', 'ACTIVE'),
  (UUID(), 'Hong Kong Exchange', 'XHKG', 'HK', 'Asia/Hong_Kong', 'HKD', 'ACTIVE');

-- ----------------------------------------------------------------------------
-- Seed: Default tenant
-- ----------------------------------------------------------------------------
INSERT INTO identity.tenant (tenant_id, name, slug, plan, status) VALUES
  (UUID(), 'Default Tenant', 'default', 'ENTERPRISE', 'ACTIVE');

-- ----------------------------------------------------------------------------
-- Seed: Default permissions
-- ----------------------------------------------------------------------------
INSERT INTO identity.permission (permission_id, name, description, category) VALUES
  (UUID(), 'market_data.read', 'Read market data', 'MARKET_DATA'),
  (UUID(), 'market_data.write', 'Write market data', 'MARKET_DATA'),
  (UUID(), 'fundamental.read', 'Read fundamental data', 'FUNDAMENTAL'),
  (UUID(), 'fundamental.write', 'Write fundamental data', 'FUNDAMENTAL'),
  (UUID(), 'analytics.read', 'Read analytics', 'ANALYTICS'),
  (UUID(), 'analytics.write', 'Write analytics', 'ANALYTICS'),
  (UUID(), 'portfolio.read', 'Read portfolio', 'PORTFOLIO'),
  (UUID(), 'portfolio.write', 'Write portfolio', 'PORTFOLIO'),
  (UUID(), 'risk.read', 'Read risk data', 'RISK'),
  (UUID(), 'risk.write', 'Write risk data', 'RISK'),
  (UUID(), 'trading.read', 'Read trading data', 'TRADING'),
  (UUID(), 'trading.write', 'Execute trades', 'TRADING'),
  (UUID(), 'trading.approve', 'Approve orders', 'TRADING'),
  (UUID(), 'governance.read', 'Read audit logs', 'GOVERNANCE'),
  (UUID(), 'governance.write', 'Write governance', 'GOVERNANCE'),
  (UUID(), 'config.read', 'Read configuration', 'CONFIG'),
  (UUID(), 'config.write', 'Write configuration', 'CONFIG'),
  (UUID(), 'admin.full', 'Full admin access', 'ADMIN');

-- ----------------------------------------------------------------------------
-- Seed: Default system parameters
-- ----------------------------------------------------------------------------
INSERT INTO config.system_parameter (param_id, param_key, param_value, param_type, category, is_readonly, description) VALUES
  (UUID(), 'platform.version', '0.1.0', 'STRING', 'SYSTEM', 1, 'Platform version'),
  (UUID(), 'platform.name', 'Global & Indonesia Capital Market Intelligence Platform', 'STRING', 'SYSTEM', 1, 'Platform name'),
  (UUID(), 'platform.environment', 'development', 'STRING', 'SYSTEM', 0, 'Environment: development/staging/production'),
  (UUID(), 'trading.default_settlement', 'T_PLUS_2', 'STRING', 'TRADING', 0, 'Default settlement cycle'),
  (UUID(), 'trading.auto_approve_threshold', '0', 'DECIMAL', 'TRADING', 0, 'Auto-approve threshold (0 = manual only)'),
  (UUID(), 'risk.default_var_confidence', '0.95', 'DECIMAL', 'RISK', 0, 'Default VaR confidence level'),
  (UUID(), 'risk.default_var_horizon', '1', 'INTEGER', 'RISK', 0, 'Default VaR horizon in days'),
  (UUID(), 'cache.default_ttl', '300', 'INTEGER', 'CACHE', 0, 'Default cache TTL in seconds'),
  (UUID(), 'api.rate_limit_per_minute', '60', 'INTEGER', 'API', 0, 'API rate limit per minute'),
  (UUID(), 'api.pagination_default_size', '50', 'INTEGER', 'API', 0, 'Default page size'),
  (UUID(), 'api.pagination_max_size', '200', 'INTEGER', 'API', 0, 'Maximum page size');
