#!/bin/bash
# ============================================================================
# migrate.sh
# Migration runner for MySQL (and optionally PostgreSQL)
# Usage: ./migrate.sh [up|down|seed]
# ============================================================================

set -e

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-platform}"

MIGRATION_DIR="$(dirname "$0")/migrations"

run_mysql() {
  local file="$1"
  echo "Running: $file"
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} < "$file"
  echo "  ✓ Done"
}

case "${1:-up}" in
  up)
    echo "=== Running MySQL Migrations (UP) ==="
    for f in "$MIGRATION_DIR"/00[1-9]_*.sql "$MIGRATION_DIR"/01[0-9]_*.sql "$MIGRATION_DIR"/020_*.sql; do
      run_mysql "$f"
    done
    echo "=== MySQL Migrations Complete ==="
    ;;
  seed)
    echo "=== Running Seed Data ==="
    run_mysql "$MIGRATION_DIR/012_seed_data.sql"
    echo "=== Seed Complete ==="
    ;;
  down)
    echo "=== Running Rollback (DOWN) ==="
    run_mysql "$MIGRATION_DIR/013_drop_all.sql"
    echo "=== Rollback Complete ==="
    ;;
  *)
    echo "Usage: $0 [up|down|seed]"
    exit 1
    ;;
esac
