-- Migration 030: Extended Market Types
-- Adds support for ETF, Crypto, Sukuk, Mutual Fund, Options, Repo, Swap
-- Extends exchanges and instruments for broader capital market coverage

-- Add new exchanges for crypto and OTC derivatives
INSERT IGNORE INTO market_master.exchange (exchange_id, name, country, mic_code, timezone, currency, status) VALUES
('glb-exch-crypto', 'Crypto Market', 'XX', 'CRYPTO', 'UTC', 'USD', 'ACTIVE'),
('glb-exch-idx-etf', 'IDX ETF Market', 'ID', 'XETF', 'Asia/Jakarta', 'IDR', 'ACTIVE'),
('glb-exch-otc-deriv', 'OTC Derivatives Market', 'XX', 'OTCD', 'UTC', 'USD', 'ACTIVE');

-- ============================================================
-- ETF Instruments (Yahoo Finance: EIDO, AAXJ, EWZ, VWO)
-- ============================================================
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
('glb-issuer-etf', 'iShares ETF Group', 'ISHARES', 'US', 'FINANCIAL', 'ACTIVE'),
('glb-issuer-vanguard', 'Vanguard Group', 'VANGUARD', 'US', 'FINANCIAL', 'ACTIVE');

INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
('glb-sec-eido', 'glb-issuer-etf', 'ETF', 'USD', 'ACTIVE'),
('glb-sec-aaxj', 'glb-issuer-etf', 'ETF', 'USD', 'ACTIVE'),
('glb-sec-vwo', 'glb-issuer-vanguard', 'ETF', 'USD', 'ACTIVE');

INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
('glb-inst-eido', 'glb-sec-eido', 'EQUITY', 'ETF', 'USD', 'ACTIVE'),
('glb-inst-aaxj', 'glb-sec-aaxj', 'EQUITY', 'ETF', 'USD', 'ACTIVE'),
('glb-inst-vwo', 'glb-sec-vwo', 'EQUITY', 'ETF', 'USD', 'ACTIVE');

INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
(UUID(), 'glb-inst-eido', 'glb-exch-global', 'EIDO', 'USD', 'ACTIVE'),
(UUID(), 'glb-inst-aaxj', 'glb-exch-global', 'AAXJ', 'USD', 'ACTIVE'),
(UUID(), 'glb-inst-vwo', 'glb-exch-global', 'VWO', 'USD', 'ACTIVE');

-- ============================================================
-- Crypto Instruments (Yahoo Finance: BTC-USD, ETH-USD)
-- ============================================================
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
('glb-issuer-crypto', 'Crypto Market Global', 'CRYPTO', 'XX', 'DIGITAL', 'ACTIVE');

INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
('glb-sec-btc', 'glb-issuer-crypto', 'CRYPTO', 'USD', 'ACTIVE'),
('glb-sec-eth', 'glb-issuer-crypto', 'CRYPTO', 'USD', 'ACTIVE');

INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
('glb-inst-btc', 'glb-sec-btc', 'CRYPTO', 'SPOT', 'USD', 'ACTIVE'),
('glb-inst-eth', 'glb-sec-eth', 'CRYPTO', 'SPOT', 'USD', 'ACTIVE');

INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
(UUID(), 'glb-inst-btc', 'glb-exch-crypto', 'BTC-USD', 'USD', 'ACTIVE'),
(UUID(), 'glb-inst-eth', 'glb-exch-crypto', 'ETH-USD', 'USD', 'ACTIVE');

-- ============================================================
-- Sukuk (Indonesia Government Sukuk - structural support)
-- ============================================================
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
('glb-issuer-sukuk-id', 'Pemerintah Republik Indonesia', 'SUKUK_ID', 'ID', 'SOVEREIGN', 'ACTIVE');

INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, par_value, issue_date, maturity_date, status) VALUES
('glb-sec-sukuk-10y', 'glb-issuer-sukuk-id', 'SUKUK', 'IDR', 1000000.0000, '2023-01-15', '2033-01-15', 'ACTIVE'),
('glb-sec-sukuk-5y', 'glb-issuer-sukuk-id', 'SUKUK', 'IDR', 1000000.0000, '2024-01-15', '2029-01-15', 'ACTIVE');

INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
('glb-inst-sukuk-10y', 'glb-sec-sukuk-10y', 'FIXED_INCOME', 'SUKUK', 'IDR', 'ACTIVE'),
('glb-inst-sukuk-5y', 'glb-sec-sukuk-5y', 'FIXED_INCOME', 'SUKUK', 'IDR', 'ACTIVE');

INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
(UUID(), 'glb-inst-sukuk-10y', 'glb-exch-ice', 'SUKUK10Y', 'IDR', 'ACTIVE'),
(UUID(), 'glb-inst-sukuk-5y', 'glb-exch-ice', 'SUKUK5Y', 'IDR', 'ACTIVE');

-- ============================================================
-- Mutual Fund (Reksa Dana - structural support)
-- ============================================================
INSERT IGNORE INTO market_master.issuer (issuer_id, legal_name, short_name, country, sector_code, status) VALUES
('glb-issuer-reksadana', 'Manajer Investasi Indonesia', 'MI_ID', 'ID', 'ASSET_MGMT', 'ACTIVE');

INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
('glb-sec-rd-equity', 'glb-issuer-reksadana', 'MUTUAL_FUND', 'IDR', 'ACTIVE'),
('glb-sec-rd-fixed', 'glb-issuer-reksadana', 'MUTUAL_FUND', 'IDR', 'ACTIVE'),
('glb-sec-rd-mixed', 'glb-issuer-reksadana', 'MUTUAL_FUND', 'IDR', 'ACTIVE');

INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
('glb-inst-rd-equity', 'glb-sec-rd-equity', 'EQUITY', 'MUTUAL_FUND', 'IDR', 'ACTIVE'),
('glb-inst-rd-fixed', 'glb-sec-rd-fixed', 'FIXED_INCOME', 'MUTUAL_FUND', 'IDR', 'ACTIVE'),
('glb-inst-rd-mixed', 'glb-sec-rd-mixed', 'MIXED', 'MUTUAL_FUND', 'IDR', 'ACTIVE');

INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
(UUID(), 'glb-inst-rd-equity', 'glb-exch-idx-etf', 'RD-EQUITY', 'IDR', 'ACTIVE'),
(UUID(), 'glb-inst-rd-fixed', 'glb-exch-idx-etf', 'RD-FIXED', 'IDR', 'ACTIVE'),
(UUID(), 'glb-inst-rd-mixed', 'glb-exch-idx-etf', 'RD-MIXED', 'IDR', 'ACTIVE');

-- ============================================================
-- Options (Structural support - equity options)
-- ============================================================
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
('glb-sec-option-bbca', 'glb-issuer-etf', 'OPTION', 'IDR', 'ACTIVE');

INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
('glb-inst-option-bbca', 'glb-sec-option-bbca', 'DERIVATIVE', 'OPTION', 'IDR', 'ACTIVE');

INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
(UUID(), 'glb-inst-option-bbca', 'glb-exch-otc-deriv', 'OPT-BBCA', 'IDR', 'ACTIVE');

-- ============================================================
-- Repo & Swap (Structural support - OTC)
-- ============================================================
INSERT IGNORE INTO market_master.security (security_id, issuer_id, security_type, currency, status) VALUES
('glb-sec-repo-idr', 'glb-issuer-sukuk-id', 'REPO', 'IDR', 'ACTIVE'),
('glb-sec-swap-idr-usd', 'glb-issuer-crypto', 'SWAP', 'USD', 'ACTIVE');

INSERT IGNORE INTO market_master.instrument (instrument_id, security_id, asset_class, instrument_type, currency, status) VALUES
('glb-inst-repo-idr', 'glb-sec-repo-idr', 'FIXED_INCOME', 'REPO', 'IDR', 'ACTIVE'),
('glb-inst-swap-idr-usd', 'glb-sec-swap-idr-usd', 'DERIVATIVE', 'SWAP', 'USD', 'ACTIVE');

INSERT IGNORE INTO market_master.listing (listing_id, instrument_id, exchange_id, ticker, currency, status) VALUES
(UUID(), 'glb-inst-repo-idr', 'glb-exch-otc-deriv', 'REPO-IDR', 'IDR', 'ACTIVE'),
(UUID(), 'glb-inst-swap-idr-usd', 'glb-exch-otc-deriv', 'SWAP-IDR-USD', 'USD', 'ACTIVE');
