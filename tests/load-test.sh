#!/bin/bash
# Load testing script — uses Apache Bench (ab) for basic load testing
# Usage: ./tests/load-test.sh [base_url] [concurrent] [total]
# Example: ./tests/load-test.sh http://localhost:8080 50 1000

set -e

BASE_URL="${1:-http://localhost:8080}"
CONCURRENT="${2:-50}"
TOTAL="${3:-1000}"

echo "=== Load Test: Capital Market Platform ==="
echo "Target: ${BASE_URL}"
echo "Concurrent: ${CONCURRENT}"
echo "Total requests: ${TOTAL}"
echo ""

echo "--- Health Check ---"
ab -n 100 -c 10 "${BASE_URL}/health" 2>/dev/null | grep -E "Requests per second|Time per request|Failed requests|Complete requests"

echo ""
echo "--- Metrics Endpoint ---"
ab -n 100 -c 10 "${BASE_URL}/metrics" 2>/dev/null | grep -E "Requests per second|Time per request|Failed requests|Complete requests"

echo ""
echo "--- Full Load Test (Health) ---"
ab -n "${TOTAL}" -c "${CONCURRENT}" "${BASE_URL}/health" 2>/dev/null | grep -E "Requests per second|Time per request|Failed requests|Complete requests|Transfer rate|Percentage"

echo ""
echo "=== Load Test Complete ==="
