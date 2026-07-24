-- ============================================================================
-- 011_postgresql_timescaledb_schema.sql
-- Database: PostgreSQL 16+ with TimescaleDB extension
-- Schemas: ohlcv, tick, quote, valuation, economic, factor, technical, meta
-- Purpose: Time-series storage with hypertables, compression, retention
-- ============================================================================

-- Database & Extension
CREATE DATABASE market_tsdb
  WITH ENCODING 'UTF8' LC_COLLATE 'en_US.utf8' LC_CTYPE 'en_US.utf8';

\c market_tsdb

CREATE EXTENSION IF NOT EXISTS timescaledb;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Schemas
CREATE SCHEMA IF NOT EXISTS ohlcv;
CREATE SCHEMA IF NOT EXISTS tick;
CREATE SCHEMA IF NOT EXISTS quote;
CREATE SCHEMA IF NOT EXISTS valuation;
CREATE SCHEMA IF NOT EXISTS economic;
CREATE SCHEMA IF NOT EXISTS factor;
CREATE SCHEMA IF NOT EXISTS technical;
CREATE SCHEMA IF NOT EXISTS meta;

-- ============================================================================
-- meta schema: metadata tables (not hypertables)
-- ============================================================================

CREATE TABLE meta.data_source (
  source_id       UUID          PRIMARY KEY,
  source_name     VARCHAR(100)  NOT NULL UNIQUE,
  source_type     VARCHAR(50)   NOT NULL,
  endpoint_url    VARCHAR(500)  NULL,
  api_key_env     VARCHAR(100)  NULL,
  rate_limit_per_min INT        NULL,
  status          VARCHAR(20)   NOT NULL DEFAULT 'ACTIVE',
  created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE TABLE meta.ingestion_log (
  log_id          UUID          PRIMARY KEY,
  source_id       UUID          NOT NULL REFERENCES meta.data_source(source_id),
  schema_name     VARCHAR(50)   NOT NULL,
  table_name      VARCHAR(100)  NOT NULL,
  records_ingested BIGINT       NOT NULL DEFAULT 0,
  records_failed  BIGINT        NOT NULL DEFAULT 0,
  started_at      TIMESTAMPTZ   NOT NULL,
  completed_at    TIMESTAMPTZ   NULL,
  status          VARCHAR(20)   NOT NULL DEFAULT 'RUNNING',
  error_message   TEXT          NULL
);

-- ============================================================================
-- ohlcv schema: Daily and Intraday OHLCV
-- ============================================================================

-- ohlcv_daily
CREATE TABLE ohlcv.ohlcv_daily (
  instrument_id   UUID          NOT NULL,
  exchange_id     UUID          NOT NULL,
  date            DATE          NOT NULL,
  open            NUMERIC(20,8) NOT NULL,
  high            NUMERIC(20,8) NOT NULL,
  low             NUMERIC(20,8) NOT NULL,
  close           NUMERIC(20,8) NOT NULL,
  volume          NUMERIC(20,8) NOT NULL DEFAULT 0,
  vwap            NUMERIC(20,8) NULL,
  turnover        NUMERIC(20,8) NULL,
  currency        CHAR(3)       NOT NULL,
  source          VARCHAR(100)  NOT NULL,
  source_record_id VARCHAR(200) NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, exchange_id, date)
);

SELECT create_hypertable('ohlcv.ohlcv_daily', 'date',
  chunk_time_interval => INTERVAL '30 days',
  if_not_exists => TRUE
);

CREATE INDEX idx_ohlcv_daily_exchange_date ON ohlcv.ohlcv_daily (exchange_id, date DESC);
CREATE INDEX idx_ohlcv_daily_available_time ON ohlcv.ohlcv_daily (available_time);

ALTER TABLE ohlcv.ohlcv_daily SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, exchange_id',
  timescaledb.compress_orderby = 'date DESC'
);

SELECT add_compression_policy('ohlcv.ohlcv_daily', INTERVAL '90 days', if_not_exists => TRUE);
SELECT add_retention_policy('ohlcv.ohlcv_daily', INTERVAL '10 years', if_not_exists => TRUE);

-- ohlcv_intraday
CREATE TABLE ohlcv.ohlcv_intraday (
  instrument_id   UUID          NOT NULL,
  exchange_id     UUID          NOT NULL,
  timestamp       TIMESTAMPTZ   NOT NULL,
  interval_seconds INT          NOT NULL,
  open            NUMERIC(20,8) NOT NULL,
  high            NUMERIC(20,8) NOT NULL,
  low             NUMERIC(20,8) NOT NULL,
  close           NUMERIC(20,8) NOT NULL,
  volume          NUMERIC(20,8) NOT NULL DEFAULT 0,
  currency        CHAR(3)       NOT NULL,
  source          VARCHAR(100)  NOT NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, exchange_id, timestamp, interval_seconds)
);

SELECT create_hypertable('ohlcv.ohlcv_intraday', 'timestamp',
  chunk_time_interval => INTERVAL '1 day',
  if_not_exists => TRUE
);

CREATE INDEX idx_ohlcv_intraday_exchange_ts ON ohlcv.ohlcv_intraday (exchange_id, timestamp DESC);
CREATE INDEX idx_ohlcv_intraday_available ON ohlcv.ohlcv_intraday (available_time);

ALTER TABLE ohlcv.ohlcv_intraday SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, exchange_id',
  timescaledb.compress_orderby = 'timestamp DESC'
);

SELECT add_compression_policy('ohlcv.ohlcv_intraday', INTERVAL '7 days', if_not_exists => TRUE);
SELECT add_retention_policy('ohlcv.ohlcv_intraday', INTERVAL '1 year', if_not_exists => TRUE);

-- ============================================================================
-- tick schema: Tick-by-tick data
-- ============================================================================

CREATE TABLE tick.tick (
  instrument_id   UUID          NOT NULL,
  exchange_id     UUID          NOT NULL,
  timestamp       TIMESTAMPTZ   NOT NULL,
  price           NUMERIC(20,8) NOT NULL,
  volume          NUMERIC(20,8) NOT NULL,
  side            CHAR(1)       NULL,
  currency        CHAR(3)       NOT NULL,
  source          VARCHAR(100)  NOT NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, exchange_id, timestamp)
);

SELECT create_hypertable('tick.tick', 'timestamp',
  chunk_time_interval => INTERVAL '1 hour',
  if_not_exists => TRUE
);

CREATE INDEX idx_tick_exchange_ts ON tick.tick (exchange_id, timestamp DESC);

ALTER TABLE tick.tick SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, exchange_id',
  timescaledb.compress_orderby = 'timestamp DESC'
);

SELECT add_compression_policy('tick.tick', INTERVAL '1 day', if_not_exists => TRUE);
SELECT add_retention_policy('tick.tick', INTERVAL '90 days', if_not_exists => TRUE);

-- ============================================================================
-- quote schema: Bid/Ask quotes
-- ============================================================================

CREATE TABLE quote.quote (
  instrument_id   UUID          NOT NULL,
  exchange_id     UUID          NOT NULL,
  timestamp       TIMESTAMPTZ   NOT NULL,
  bid_price       NUMERIC(20,8) NOT NULL,
  bid_size        NUMERIC(20,8) NOT NULL,
  ask_price       NUMERIC(20,8) NOT NULL,
  ask_size        NUMERIC(20,8) NOT NULL,
  mid_price       NUMERIC(20,8) NULL,
  spread          NUMERIC(20,8) NULL,
  currency        CHAR(3)       NOT NULL,
  source          VARCHAR(100)  NOT NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, exchange_id, timestamp)
);

SELECT create_hypertable('quote.quote', 'timestamp',
  chunk_time_interval => INTERVAL '1 hour',
  if_not_exists => TRUE
);

CREATE INDEX idx_quote_exchange_ts ON quote.quote (exchange_id, timestamp DESC);

ALTER TABLE quote.quote SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, exchange_id',
  timescaledb.compress_orderby = 'timestamp DESC'
);

SELECT add_compression_policy('quote.quote', INTERVAL '1 day', if_not_exists => TRUE);
SELECT add_retention_policy('quote.quote', INTERVAL '90 days', if_not_exists => TRUE);

-- ============================================================================
-- valuation schema: Valuation metrics time series
-- ============================================================================

CREATE TABLE valuation.valuation_metric (
  instrument_id   UUID          NOT NULL,
  metric_type     VARCHAR(50)   NOT NULL,
  date            DATE          NOT NULL,
  value           NUMERIC(20,8) NOT NULL,
  currency        CHAR(3)       NOT NULL,
  source          VARCHAR(100)  NOT NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, metric_type, date)
);

SELECT create_hypertable('valuation.valuation_metric', 'date',
  chunk_time_interval => INTERVAL '90 days',
  if_not_exists => TRUE
);

CREATE INDEX idx_val_metric_type_date ON valuation.valuation_metric (metric_type, date DESC);
CREATE INDEX idx_val_available_time ON valuation.valuation_metric (available_time);

ALTER TABLE valuation.valuation_metric SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, metric_type',
  timescaledb.compress_orderby = 'date DESC'
);

SELECT add_compression_policy('valuation.valuation_metric', INTERVAL '180 days', if_not_exists => TRUE);
SELECT add_retention_policy('valuation.valuation_metric', INTERVAL '10 years', if_not_exists => TRUE);

-- ============================================================================
-- economic schema: Economic indicators time series
-- ============================================================================

CREATE TABLE economic.economic_indicator_ts (
  country         CHAR(2)       NOT NULL,
  indicator_type  VARCHAR(50)   NOT NULL,
  period          DATE          NOT NULL,
  value           NUMERIC(20,6) NOT NULL,
  unit            VARCHAR(20)   NULL,
  frequency       VARCHAR(10)   NULL,
  revision_number INT           NOT NULL DEFAULT 1,
  source          VARCHAR(100)  NOT NULL,
  source_record_id VARCHAR(200) NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (country, indicator_type, period, revision_number)
);

SELECT create_hypertable('economic.economic_indicator_ts', 'period',
  chunk_time_interval => INTERVAL '365 days',
  if_not_exists => TRUE
);

CREATE INDEX idx_econ_country_type_period ON economic.economic_indicator_ts (country, indicator_type, period DESC);
CREATE INDEX idx_econ_available_time ON economic.economic_indicator_ts (available_time);

ALTER TABLE economic.economic_indicator_ts SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'country, indicator_type',
  timescaledb.compress_orderby = 'period DESC'
);

SELECT add_compression_policy('economic.economic_indicator_ts', INTERVAL '365 days', if_not_exists => TRUE);
SELECT add_retention_policy('economic.economic_indicator_ts', INTERVAL '20 years', if_not_exists => TRUE);

-- ============================================================================
-- factor schema: Factor model time series
-- ============================================================================

CREATE TABLE factor.factor_time_series (
  instrument_id   UUID          NOT NULL,
  factor_type     VARCHAR(50)   NOT NULL,
  date            DATE          NOT NULL,
  value           NUMERIC(20,8) NOT NULL,
  model_version   VARCHAR(20)   NOT NULL,
  source          VARCHAR(100)  NOT NULL,
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, factor_type, date, model_version)
);

SELECT create_hypertable('factor.factor_time_series', 'date',
  chunk_time_interval => INTERVAL '90 days',
  if_not_exists => TRUE
);

CREATE INDEX idx_factor_type_date ON factor.factor_time_series (factor_type, date DESC);
CREATE INDEX idx_factor_available_time ON factor.factor_time_series (available_time);

ALTER TABLE factor.factor_time_series SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, factor_type',
  timescaledb.compress_orderby = 'date DESC'
);

SELECT add_compression_policy('factor.factor_time_series', INTERVAL '180 days', if_not_exists => TRUE);
SELECT add_retention_policy('factor.factor_time_series', INTERVAL '10 years', if_not_exists => TRUE);

-- ============================================================================
-- technical schema: Technical indicators time series
-- ============================================================================

CREATE TABLE technical.technical_indicator (
  instrument_id   UUID          NOT NULL,
  indicator_type  VARCHAR(50)   NOT NULL,
  timeframe       VARCHAR(10)   NOT NULL,
  timestamp       TIMESTAMPTZ   NOT NULL,
  value           NUMERIC(20,8) NOT NULL,
  parameters      JSONB         NULL,
  model_version   VARCHAR(20)   NULL,
  source          VARCHAR(100)  NOT NULL DEFAULT 'INTERNAL',
  available_time  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
  PRIMARY KEY (instrument_id, indicator_type, timeframe, timestamp)
);

SELECT create_hypertable('technical.technical_indicator', 'timestamp',
  chunk_time_interval => INTERVAL '7 days',
  if_not_exists => TRUE
);

CREATE INDEX idx_ti_type_timeframe_ts ON technical.technical_indicator (indicator_type, timeframe, timestamp DESC);
CREATE INDEX idx_ti_available_time ON technical.technical_indicator (available_time);

ALTER TABLE technical.technical_indicator SET (
  timescaledb.compress,
  timescaledb.compress_segmentby = 'instrument_id, indicator_type, timeframe',
  timescaledb.compress_orderby = 'timestamp DESC'
);

SELECT add_compression_policy('technical.technical_indicator', INTERVAL '30 days', if_not_exists => TRUE);
SELECT add_retention_policy('technical.technical_indicator', INTERVAL '5 years', if_not_exists => TRUE);

-- ============================================================================
-- Continuous Aggregates
-- ============================================================================

-- Daily OHLCV from intraday (if intraday data is available)
CREATE MATERIALIZED VIEW ohlcv.ohlcv_daily_cagg
WITH (timescaledb.continuous) AS
SELECT
  instrument_id,
  exchange_id,
  date_trunc('day', timestamp) AS date,
  first(open, timestamp) AS open,
  max(high) AS high,
  min(low) AS low,
  last(close, timestamp) AS close,
  sum(volume) AS volume,
  currency,
  max(source) AS source,
  max(available_time) AS available_time
FROM ohlcv.ohlcv_intraday
GROUP BY instrument_id, exchange_id, date_trunc('day', timestamp), currency
WITH NO DATA;

SELECT add_continuous_aggregate_policy('ohlcv.ohlcv_daily_cagg',
  start_offset => INTERVAL '7 days',
  end_offset => INTERVAL '1 hour',
  schedule_interval => INTERVAL '1 hour',
  if_not_exists => TRUE
);

-- ============================================================================
-- Summary
-- ============================================================================
-- Hypertables created:
--   1. ohlcv.ohlcv_daily         (30-day chunks, 90d compress, 10yr retain)
--   2. ohlcv.ohlcv_intraday      (1-day chunks, 7d compress, 1yr retain)
--   3. tick.tick                 (1-hour chunks, 1d compress, 90d retain)
--   4. quote.quote               (1-hour chunks, 1d compress, 90d retain)
--   5. valuation.valuation_metric (90-day chunks, 180d compress, 10yr retain)
--   6. economic.economic_indicator_ts (365-day chunks, 365d compress, 20yr retain)
--   7. factor.factor_time_series (90-day chunks, 180d compress, 10yr retain)
--   8. technical.technical_indicator (7-day chunks, 30d compress, 5yr retain)
--
-- Continuous aggregates:
--   1. ohlcv.ohlcv_daily_cagg (from intraday → daily)
--
-- Meta tables (non-hypertable):
--   1. meta.data_source
--   2. meta.ingestion_log
