-- ============================================================================
-- 003_market_master_schema.sql
-- Bounded Context 2: Market Master (Security Master)
-- Tables: exchange, issuer, security, instrument, listing, corporate_action,
--         index_master, index_membership, market_calendar
-- ============================================================================

USE platform;

-- ----------------------------------------------------------------------------
-- exchange
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.exchange (
  exchange_id    VARCHAR(36)   NOT NULL,
  name           VARCHAR(100)  NOT NULL,
  mic_code       VARCHAR(10)   NOT NULL,
  country        CHAR(2)       NOT NULL,
  timezone       VARCHAR(50)   NOT NULL,
  currency       CHAR(3)       NOT NULL,
  status         ENUM('ACTIVE','CLOSED','MERGED') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (exchange_id),
  UNIQUE KEY uq_exchange_mic (mic_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- issuer
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.issuer (
  issuer_id      VARCHAR(36)   NOT NULL,
  legal_name     VARCHAR(500)  NOT NULL,
  short_name     VARCHAR(100)  NULL,
  country        CHAR(2)       NOT NULL,
  jurisdiction   VARCHAR(100)  NULL,
  legal_entity_identifier VARCHAR(20) NULL,
  status         ENUM('ACTIVE','INACTIVE','DISSOLVED','MERGED') NOT NULL DEFAULT 'ACTIVE',
  incorporation_date DATE     NULL,
  sector_code    VARCHAR(50)   NULL,
  industry_code  VARCHAR(50)   NULL,
  created_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at     TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (issuer_id),
  UNIQUE KEY uq_issuer_lei (legal_entity_identifier),
  KEY idx_issuer_country_status (country, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- security
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.security (
  security_id    VARCHAR(36)   NOT NULL,
  issuer_id      VARCHAR(36)   NOT NULL,
  security_type  VARCHAR(50)   NOT NULL,
  currency       CHAR(3)       NOT NULL,
  issue_date     DATE          NULL,
  maturity_date  DATE          NULL,
  par_value      DECIMAL(20,4) NULL,
  status         VARCHAR(20)   NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (security_id),
  KEY idx_security_issuer_type (issuer_id, security_type),
  CONSTRAINT fk_sec_issuer FOREIGN KEY (issuer_id) REFERENCES market_master.issuer(issuer_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- instrument
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.instrument (
  instrument_id    VARCHAR(36)   NOT NULL,
  security_id      VARCHAR(36)   NOT NULL,
  asset_class      VARCHAR(30)   NOT NULL,
  instrument_type  VARCHAR(50)   NOT NULL,
  currency         CHAR(3)       NOT NULL,
  status           VARCHAR(20)   NOT NULL DEFAULT 'ACTIVE',
  status_changed_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (instrument_id),
  KEY idx_inst_security_status (security_id, status),
  KEY idx_inst_asset_class_status (asset_class, status),
  CONSTRAINT fk_inst_security FOREIGN KEY (security_id) REFERENCES market_master.security(security_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- listing
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.listing (
  listing_id     VARCHAR(36)   NOT NULL,
  instrument_id  VARCHAR(36)   NOT NULL,
  exchange_id    VARCHAR(36)   NOT NULL,
  ticker         VARCHAR(50)   NOT NULL,
  isin           VARCHAR(12)   NULL,
  currency       CHAR(3)       NOT NULL,
  listing_date   DATE          NULL,
  delisting_date DATE          NULL,
  status         ENUM('ACTIVE','SUSPENDED','DELISTED') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (listing_id),
  UNIQUE KEY uq_listing_exchange_ticker_active (exchange_id, ticker),
  UNIQUE KEY uq_listing_isin (isin),
  KEY idx_listing_instrument (instrument_id),
  KEY idx_listing_exchange_status (exchange_id, status),
  CONSTRAINT fk_listing_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT,
  CONSTRAINT fk_listing_exchange FOREIGN KEY (exchange_id) REFERENCES market_master.exchange(exchange_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- corporate_action
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.corporate_action (
  corporate_action_id VARCHAR(36)   NOT NULL,
  instrument_id       VARCHAR(36)   NOT NULL,
  action_type         VARCHAR(50)   NOT NULL,
  announcement_date   DATE          NULL,
  ex_date             DATE          NULL,
  record_date         DATE          NULL,
  payment_date        DATE          NULL,
  effective_date      DATE          NOT NULL,
  ratio               DECIMAL(20,8) NULL,
  amount              DECIMAL(20,4) NULL,
  currency            CHAR(3)       NULL,
  source              VARCHAR(100)  NOT NULL,
  source_record_id    VARCHAR(200)  NULL,
  PRIMARY KEY (corporate_action_id),
  KEY idx_ca_instrument_effective (instrument_id, effective_date),
  KEY idx_ca_type_effective (action_type, effective_date),
  CONSTRAINT fk_ca_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- index_master
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.index_master (
  index_id       VARCHAR(36)   NOT NULL,
  name           VARCHAR(200)  NOT NULL,
  exchange_id    VARCHAR(36)   NOT NULL,
  currency       CHAR(3)       NOT NULL,
  methodology    VARCHAR(100)  NULL,
  status         ENUM('ACTIVE','DISCONTINUED') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (index_id),
  KEY idx_index_exchange_status (exchange_id, status),
  CONSTRAINT fk_idx_exchange FOREIGN KEY (exchange_id) REFERENCES market_master.exchange(exchange_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- index_membership
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.index_membership (
  index_id       VARCHAR(36)   NOT NULL,
  instrument_id  VARCHAR(36)   NOT NULL,
  effective_date DATE          NOT NULL,
  end_date       DATE          NULL,
  weight         DECIMAL(10,6) NULL,
  shares         DECIMAL(20,4) NULL,
  PRIMARY KEY (index_id, instrument_id, effective_date),
  KEY idx_im_instrument_effective (instrument_id, effective_date),
  KEY idx_im_index_effective_end (index_id, effective_date, end_date),
  CONSTRAINT fk_im_index FOREIGN KEY (index_id) REFERENCES market_master.index_master(index_id) ON DELETE CASCADE,
  CONSTRAINT fk_im_instrument FOREIGN KEY (instrument_id) REFERENCES market_master.instrument(instrument_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- market_calendar
-- ----------------------------------------------------------------------------
CREATE TABLE market_master.market_calendar (
  calendar_id    VARCHAR(36)   NOT NULL,
  exchange_id    VARCHAR(36)   NOT NULL,
  date           DATE          NOT NULL,
  day_type       ENUM('TRADING','HALF_DAY','HOLIDAY','WEEKEND') NOT NULL,
  open_time      TIME          NULL,
  close_time     TIME          NULL,
  description    VARCHAR(200)  NULL,
  PRIMARY KEY (calendar_id),
  UNIQUE KEY uq_calendar_exchange_date (exchange_id, date),
  CONSTRAINT fk_mc_exchange FOREIGN KEY (exchange_id) REFERENCES market_master.exchange(exchange_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
