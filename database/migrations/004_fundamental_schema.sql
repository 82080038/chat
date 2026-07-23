-- ============================================================================
-- 004_fundamental_schema.sql
-- Bounded Context 3: Fundamental Data
-- Tables: financial_statement, financial_statement_line, financial_metric,
--         economic_indicator, news_item, news_instrument
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- financial_statement
-- ----------------------------------------------------------------------------
CREATE TABLE fundamental.financial_statement (
  financial_statement_id  VARCHAR(36)   NOT NULL,
  issuer_id               VARCHAR(36)   NOT NULL,
  statement_type          ENUM('INCOME','BALANCE','CASHFLOW','COMPREHENSIVE') NOT NULL,
  fiscal_period_type      ENUM('Q1','Q2','Q3','Q4','FY','H1','H2','YTD') NOT NULL,
  fiscal_year             SMALLINT      NOT NULL,
  fiscal_quarter          TINYINT       NULL,
  period_start            DATE          NOT NULL,
  period_end              DATE          NOT NULL,
  publication_date        DATE          NULL,
  available_time          TIMESTAMP(6)  NOT NULL,
  currency                CHAR(3)       NOT NULL,
  unit                    VARCHAR(20)   NULL,
  source                  VARCHAR(100)  NOT NULL,
  source_document_id      VARCHAR(36)   NULL,
  version                 INT           NOT NULL DEFAULT 1,
  revision_of             VARCHAR(36)   NULL,
  status                  ENUM('DRAFT','PUBLISHED','REVISED','SUPERSEDED') NOT NULL DEFAULT 'DRAFT',
  created_at              TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (financial_statement_id),
  UNIQUE KEY uq_fs_issuer_type_period_ver (issuer_id, statement_type, fiscal_year, fiscal_quarter, version),
  KEY idx_fs_issuer_period (issuer_id, fiscal_year, fiscal_quarter),
  KEY idx_fs_issuer_type_status (issuer_id, statement_type, status),
  KEY idx_fs_available_time (available_time),
  KEY idx_fs_revision_of (revision_of),
  CONSTRAINT fk_fs_issuer FOREIGN KEY (issuer_id) REFERENCES market_master.issuer(issuer_id) ON DELETE RESTRICT,
  CONSTRAINT fk_fs_revision FOREIGN KEY (revision_of) REFERENCES fundamental.financial_statement(financial_statement_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- financial_statement_line
-- ----------------------------------------------------------------------------
CREATE TABLE fundamental.financial_statement_line (
  line_id                VARCHAR(36)   NOT NULL,
  financial_statement_id VARCHAR(36)   NOT NULL,
  line_item_code         VARCHAR(50)   NOT NULL,
  line_item_name         VARCHAR(200)  NOT NULL,
  value                  DECIMAL(20,4) NULL,
  unit                   VARCHAR(20)   NULL,
  currency               CHAR(3)       NULL,
  order_position         INT           NOT NULL DEFAULT 0,
  is_subtotal            TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (line_id),
  UNIQUE KEY uq_fsl_stmt_code (financial_statement_id, line_item_code),
  KEY idx_fsl_stmt_position (financial_statement_id, order_position),
  CONSTRAINT fk_fsl_stmt FOREIGN KEY (financial_statement_id) REFERENCES fundamental.financial_statement(financial_statement_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- financial_metric
-- ----------------------------------------------------------------------------
CREATE TABLE fundamental.financial_metric (
  metric_id          VARCHAR(36)   NOT NULL,
  issuer_id          VARCHAR(36)   NOT NULL,
  metric_type        VARCHAR(50)   NOT NULL,
  value              DECIMAL(20,6) NULL,
  unit               VARCHAR(20)   NULL,
  fiscal_period_type VARCHAR(10)   NOT NULL,
  fiscal_year        SMALLINT      NOT NULL,
  fiscal_quarter     TINYINT       NULL,
  calculation_version VARCHAR(20)  NOT NULL,
  available_time     TIMESTAMP(6)  NOT NULL,
  calculated_at      TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (metric_id),
  UNIQUE KEY uq_fm_issuer_type_period_calcver (issuer_id, metric_type, fiscal_year, fiscal_quarter, calculation_version),
  KEY idx_fm_issuer_type_period (issuer_id, metric_type, fiscal_year, fiscal_quarter),
  KEY idx_fm_issuer_available (issuer_id, available_time),
  CONSTRAINT fk_fm_issuer FOREIGN KEY (issuer_id) REFERENCES market_master.issuer(issuer_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- economic_indicator
-- ----------------------------------------------------------------------------
CREATE TABLE fundamental.economic_indicator (
  indicator_id     VARCHAR(36)   NOT NULL,
  country          CHAR(2)       NOT NULL,
  indicator_type   VARCHAR(50)   NOT NULL,
  frequency        VARCHAR(10)   NULL,
  period           DATE          NOT NULL,
  value            DECIMAL(20,6) NULL,
  unit             VARCHAR(20)   NULL,
  publication_date DATE          NULL,
  available_time   TIMESTAMP(6)  NOT NULL,
  revision_number  INT           NOT NULL DEFAULT 1,
  revision_of      VARCHAR(36)   NULL,
  source           VARCHAR(100)  NOT NULL,
  source_record_id VARCHAR(200)  NULL,
  PRIMARY KEY (indicator_id),
  UNIQUE KEY uq_ei_country_type_period_rev (country, indicator_type, period, revision_number),
  KEY idx_ei_country_type_period (country, indicator_type, period),
  KEY idx_ei_country_available (country, available_time),
  KEY idx_ei_revision_of (revision_of),
  CONSTRAINT fk_ei_revision FOREIGN KEY (revision_of) REFERENCES fundamental.economic_indicator(indicator_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- news_item
-- ----------------------------------------------------------------------------
CREATE TABLE fundamental.news_item (
  news_id          VARCHAR(36)   NOT NULL,
  title            VARCHAR(500)  NOT NULL,
  content_summary  TEXT          NULL,
  source           VARCHAR(100)  NOT NULL,
  source_url       VARCHAR(1000) NULL,
  published_at     TIMESTAMP(6)  NULL,
  available_time   TIMESTAMP(6)  NOT NULL,
  sentiment_score  DECIMAL(5,2)  NULL,
  sentiment_label  ENUM('POSITIVE','NEGATIVE','NEUTRAL') NULL,
  language         VARCHAR(10)   NULL,
  storage_object_id VARCHAR(36)  NULL,
  PRIMARY KEY (news_id),
  KEY idx_news_published (published_at),
  KEY idx_news_available (available_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- news_instrument (junction)
-- ----------------------------------------------------------------------------
CREATE TABLE fundamental.news_instrument (
  news_id          VARCHAR(36)   NOT NULL,
  instrument_id    VARCHAR(36)   NOT NULL,
  relevance_score  DECIMAL(5,2)  NULL,
  PRIMARY KEY (news_id, instrument_id),
  KEY idx_ni_instrument (instrument_id),
  CONSTRAINT fk_ni_news FOREIGN KEY (news_id) REFERENCES fundamental.news_item(news_id) ON DELETE CASCADE,
  CONSTRAINT fk_ni_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
