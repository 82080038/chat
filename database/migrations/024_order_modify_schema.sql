-- 024_order_modify_schema.sql
-- Add updated_at column to trading.order for modify tracking

ALTER TABLE trading.order
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP(6) NULL DEFAULT NULL;
