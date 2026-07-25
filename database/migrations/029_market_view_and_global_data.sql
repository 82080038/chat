-- ============================================================================
-- 029_market_view_and_global_data.sql
-- Purpose: Create market.instrument view for cross-schema queries,
--          and seed global market instruments (indices, commodities, FX)
-- ============================================================================

USE platform;

-- ──────────────────────────────────────────────────────────────────────────
-- 1. Create market schema with instrument view
--    This view joins market_master tables to expose a flattened instrument
--    with ticker, sector, and other fields needed by RiskService and
--    DataIngestionService.
-- ──────────────────────────────────────────────────────────────────────────
CREATE SCHEMA IF NOT EXISTS market;

CREATE OR REPLACE VIEW market.instrument AS
SELECT
    i.instrument_id,
    i.security_id,
    i.asset_class,
    i.instrument_type,
    i.currency,
    i.status,
    i.status_changed_at,
    l.ticker,
    l.ticker AS symbol,
    l.isin,
    l.exchange_id,
    e.mic_code AS exchange_mic,
    e.name AS exchange_name,
    s.security_type,
    s.issuer_id,
    iss.legal_name AS issuer_name,
    iss.short_name AS issuer_short,
    iss.country AS issuer_country,
    iss.sector_code AS sector,
    iss.industry_code AS industry
FROM market_master.instrument i
LEFT JOIN market_master.listing l ON l.instrument_id = i.instrument_id AND l.status = 'ACTIVE'
LEFT JOIN market_master.exchange e ON e.exchange_id = l.exchange_id
LEFT JOIN market_master.security s ON s.security_id = i.security_id
LEFT JOIN market_master.issuer iss ON iss.issuer_id = s.issuer_id;

-- ──────────────────────────────────────────────────────────────────────────
-- 2. Seed global exchanges (if not exists)
-- ──────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO market_master.exchange (exchange_id, name, mic_code, country, timezone, currency, status) VALUES
  ('glb-exch-cme', 'Chicago Mercantile Exchange', 'XCME', 'US', 'America/Chicago', 'USD', 'ACTIVE'),
  ('glb-exch-ice', 'Intercontinental Exchange', 'XICE', 'US', 'America/New_York', 'USD', 'ACTIVE'),
  ('glb-exch-global', 'Global OTC', 'GLOBAL', 'XX', 'UTC', 'USD', 'ACTIVE');

-- ──────────────────────────────────────────────────────────────────────────
-- 3. Seed global market issuers, securities, instruments, listings
-- ──────────────────────────────────────────────────────────────────────────

-- Helper: we use deterministic UUIDs for global instruments so the seeder
-- can reference them reliably.

-- IHSG (Jakarta Composite Index)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-jkse', 'Indonesia Stock Exchange', 'IDX', 'ID', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-jkse', 'glb-iss-jkse', 'INDEX', 'IDR', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-jkse', 'glb-sec-jkse', 'INDEX', 'EQUITY_INDEX', 'IDR', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-jkse', 'glb-inst-jkse', (SELECT exchange_id FROM market_master.exchange WHERE mic_code='XIDX'), '^JKSE', 'IDR', 'ACTIVE');

-- S&P 500
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-gspc', 'S&P Dow Jones Indices', 'S&P', 'US', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-gspc', 'glb-iss-gspc', 'INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-gspc', 'glb-sec-gspc', 'INDEX', 'EQUITY_INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-gspc', 'glb-inst-gspc', 'glb-exch-global', '^GSPC', 'USD', 'ACTIVE');

-- Dow Jones Industrial Average
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-dji', 'S&P Dow Jones Indices', 'DJI', 'US', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-dji', 'glb-iss-dji', 'INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-dji', 'glb-sec-dji', 'INDEX', 'EQUITY_INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-dji', 'glb-inst-dji', 'glb-exch-global', '^DJI', 'USD', 'ACTIVE');

-- Nasdaq Composite
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-ixic', 'Nasdaq Inc', 'NASDAQ', 'US', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-ixic', 'glb-iss-ixic', 'INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-ixic', 'glb-sec-ixic', 'INDEX', 'EQUITY_INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-ixic', 'glb-inst-ixic', 'glb-exch-global', '^IXIC', 'USD', 'ACTIVE');

-- Nikkei 225
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-n225', 'Tokyo Stock Exchange', 'TSE', 'JP', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-n225', 'glb-iss-n225', 'INDEX', 'JPY', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-n225', 'glb-sec-n225', 'INDEX', 'EQUITY_INDEX', 'JPY', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-n225', 'glb-inst-n225', (SELECT exchange_id FROM market_master.exchange WHERE mic_code='XTKS'), '^N225', 'JPY', 'ACTIVE');

-- Hang Seng Index
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-hsi', 'Hong Kong Exchange', 'HKEX', 'HK', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-hsi', 'glb-iss-hsi', 'INDEX', 'HKD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-hsi', 'glb-sec-hsi', 'INDEX', 'EQUITY_INDEX', 'HKD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-hsi', 'glb-inst-hsi', (SELECT exchange_id FROM market_master.exchange WHERE mic_code='XHKG'), '^HSI', 'HKD', 'ACTIVE');

-- Gold Futures (GC=F)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-gold', 'CME Group', 'CME', 'US', 'COMMODITY', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-gold', 'glb-iss-gold', 'COMMODITY', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-gold', 'glb-sec-gold', 'COMMODITY', 'FUTURE', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-gold', 'glb-inst-gold', 'glb-exch-cme', 'GC=F', 'USD', 'ACTIVE');

-- Crude Oil Futures (CL=F)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-oil', 'CME Group / NYMEX', 'NYMEX', 'US', 'COMMODITY', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-oil', 'glb-iss-oil', 'COMMODITY', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-oil', 'glb-sec-oil', 'COMMODITY', 'FUTURE', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-oil', 'glb-inst-oil', 'glb-exch-cme', 'CL=F', 'USD', 'ACTIVE');

-- Silver Futures (SI=F)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-silver', 'CME Group / COMEX', 'COMEX', 'US', 'COMMODITY', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-silver', 'glb-iss-silver', 'COMMODITY', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-silver', 'glb-sec-silver', 'COMMODITY', 'FUTURE', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-silver', 'glb-inst-silver', 'glb-exch-cme', 'SI=F', 'USD', 'ACTIVE');

-- USD/IDR (IDR=X)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-usdidr', 'Global FX Market', 'FX', 'XX', 'CURRENCY', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-usdidr', 'glb-iss-usdidr', 'CURRENCY', 'IDR', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-usdidr', 'glb-sec-usdidr', 'CURRENCY', 'FX_PAIR', 'IDR', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-usdidr', 'glb-inst-usdidr', 'glb-exch-global', 'IDR=X', 'IDR', 'ACTIVE');

-- EUR/USD (EURUSD=X)
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-eurusd', 'glb-iss-usdidr', 'CURRENCY', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-eurusd', 'glb-sec-eurusd', 'CURRENCY', 'FX_PAIR', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-eurusd', 'glb-inst-eurusd', 'glb-exch-global', 'EURUSD=X', 'USD', 'ACTIVE');

-- USD/JPY (JPY=X)
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-usdjpy', 'glb-iss-usdidr', 'CURRENCY', 'JPY', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-usdjpy', 'glb-sec-usdjpy', 'CURRENCY', 'FX_PAIR', 'JPY', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-usdjpy', 'glb-inst-usdjpy', 'glb-exch-global', 'JPY=X', 'JPY', 'ACTIVE');

-- US 10-Year Treasury Yield (^TNX)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-tnx', 'US Treasury', 'UST', 'US', 'BOND', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-tnx', 'glb-iss-tnx', 'BOND', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-tnx', 'glb-sec-tnx', 'FIXED_INCOME', 'BOND_YIELD', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-tnx', 'glb-inst-tnx', 'glb-exch-global', '^TNX', 'USD', 'ACTIVE');

-- VIX Volatility Index (^VIX)
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
  ('glb-iss-vix', 'CBOE Global Markets', 'CBOE', 'US', 'INDEX', 'ACTIVE');
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
  ('glb-sec-vix', 'glb-iss-vix', 'INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
  ('glb-inst-vix', 'glb-sec-vix', 'INDEX', 'VOLATILITY_INDEX', 'USD', 'ACTIVE');
INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
  ('glb-list-vix', 'glb-inst-vix', 'glb-exch-global', '^VIX', 'USD', 'ACTIVE');
