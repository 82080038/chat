#!/bin/bash
# ============================================================================
# migrate.sh
# Migration runner for MySQL (and optionally PostgreSQL)
# Usage: ./migrate.sh [up|down|seed|seed-sim|reset]
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
  local label="$(basename "$file")"
  echo "Running: $label"
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" < "$file" 2>&1 | grep -v "^Warning" || true
  echo "  ✓ Done"
}

run_mysql_raw() {
  local file="$1"
  mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} < "$file" 2>&1 | grep -v "^Warning" || true
}

case "${1:-up}" in
  up)
    echo "=== Running MySQL Migrations (UP) ==="
    for f in $(ls "$MIGRATION_DIR"/0[0-9][0-9]_*.sql | sort); do
      # Skip PostgreSQL-only migration (011)
      if [[ "$(basename "$f")" == "011_postgresql_timescaledb_schema.sql" ]]; then
        echo "Skipping: $(basename "$f") (PostgreSQL-only)"
        continue
      fi
      # Skip drop script
      if [[ "$(basename "$f")" == "013_drop_all.sql" ]]; then
        continue
      fi
      # Skip seed files (handled by seed/seed-sim commands)
      if [[ "$(basename "$f")" == "012_seed_data.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "026_seed_sample_data.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "027_seed_full_simulation_data.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "028_seed_month_simulation.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "021_seed_simulation_data.sql" ]]; then
        continue
      fi
      # First migration creates the database, run without specifying DB name
      if [[ "$(basename "$f")" == "001_create_database_and_schemas.sql" ]]; then
        run_mysql_raw "$f"
      else
        run_mysql "$f"
      fi
    done
    echo "=== MySQL Migrations Complete ==="
    echo ""
    echo "Next steps:"
    echo "  ./database/migrate.sh seed         # Seed base data (exchanges, system params)"
    echo "  ./database/migrate.sh seed-sim     # Seed simulation data (instruments, brokers, etc.)"
    ;;

  seed)
    echo "=== Running Base Seed Data ==="
    run_mysql "$MIGRATION_DIR/012_seed_data.sql"
    echo "=== Base Seed Complete ==="
    ;;

  seed-sim)
    echo "=== Running Simulation Seed Data ==="
    run_mysql "$MIGRATION_DIR/021_seed_simulation_data.sql"
    echo "=== Simulation Seed Complete ==="
    echo ""
    echo "Seeded: 20 IDX instruments, 2 brokers, 3 alerts, 5 signals, 2 policies, 1 backtest, 1 risk profile"
    ;;

  down)
    echo "=== Running Rollback (DOWN) ==="
    run_mysql "$MIGRATION_DIR/013_drop_all.sql"
    echo "=== Rollback Complete ==="
    ;;

  reset)
    echo "=== Full Reset (down + up + seed + seed-sim) ==="
    run_mysql "$MIGRATION_DIR/013_drop_all.sql"
    echo "--- Recreating schemas ---"
    for f in $(ls "$MIGRATION_DIR"/0[0-9][0-9]_*.sql | sort); do
      if [[ "$(basename "$f")" == "011_postgresql_timescaledb_schema.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "013_drop_all.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "012_seed_data.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "026_seed_sample_data.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "027_seed_full_simulation_data.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "028_seed_month_simulation.sql" ]]; then
        continue
      fi
      if [[ "$(basename "$f")" == "021_seed_simulation_data.sql" ]]; then
        continue
      fi
      run_mysql "$f"
    done
    run_mysql "$MIGRATION_DIR/012_seed_data.sql"
    run_mysql "$MIGRATION_DIR/021_seed_simulation_data.sql"
    echo "=== Full Reset Complete ==="
    ;;

  *)
    echo "Usage: $0 [up|down|seed|seed-sim|reset]"
    echo ""
    echo "Commands:"
    echo "  up        Run all schema migrations (creates tables)"
    echo "  seed      Run base seed data (exchanges, system parameters)"
    echo "  seed-sim  Run simulation seed data (instruments, brokers, signals, etc.)"
    echo "  down      Drop all schemas (destroys all data)"
    echo "  reset     Full reset: down + up + seed + seed-sim"
    exit 1
    ;;
esac
