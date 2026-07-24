#!/bin/bash
# ============================================================================
# E2E Integration Test: Full platform workflow
# Login → Instruments → Screening → Detail → Indicators → Regime → Composite
# → Market Impact → Liquidity → Factors → Order Intent → Risk Check
# ============================================================================

set -e

BASE_URL="http://localhost:8080"
PASS=0
FAIL=0
TOTAL=0

check() {
    TOTAL=$((TOTAL + 1))
    if [ "$1" = "OK" ]; then
        PASS=$((PASS + 1))
        echo "  ✅ $2"
    else
        FAIL=$((FAIL + 1))
        echo "  ❌ $2"
    fi
}

echo "=========================================="
echo "  E2E Integration Test"
echo "=========================================="

# ─── 1. Login ──────────────────────────────────────────────────────────
echo ""
echo "1. Authentication"
TOKEN=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"owner@platform.local","password":"Test@1234567"}' \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('token',''))" 2>/dev/null)

if [ -n "$TOKEN" ]; then
    check "OK" "Login → token received"
else
    check "FAIL" "Login → no token"
    echo "Cannot continue without token. Aborting."
    exit 1
fi

# Verify token
ME=$(curl -s "$BASE_URL/auth/me" -H "Authorization: Bearer $TOKEN" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('email',''))" 2>/dev/null)
if [ "$ME" = "owner@platform.local" ]; then
    check "OK" "Auth/me → correct user"
else
    check "FAIL" "Auth/me → wrong user: $ME"
fi

# ─── 2. Instruments ────────────────────────────────────────────────────
echo ""
echo "2. Instruments"
INST_COUNT=$(curl -s "$BASE_URL/instruments?per_page=100" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d.get('data',[])))" 2>/dev/null)
if [ "$INST_COUNT" -gt 0 ] 2>/dev/null; then
    check "OK" "List instruments → $INST_COUNT found"
else
    check "FAIL" "List instruments → empty"
fi

INST_ID=$(curl -s "$BASE_URL/instruments?per_page=1" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['data'][0]['instrument_id'])" 2>/dev/null)
if [ -n "$INST_ID" ]; then
    check "OK" "Get instrument ID → $INST_ID"
else
    check "FAIL" "Get instrument ID → empty"
fi

# ─── 3. Technical Indicators ───────────────────────────────────────────
echo ""
echo "3. Technical Indicators"
SMA=$(curl -s "$BASE_URL/instruments/$INST_ID/indicators/sma?period=20" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('latest','NULL'))" 2>/dev/null)
if [ "$SMA" != "None" ] && [ "$SMA" != "NULL" ]; then
    check "OK" "SMA 20 → $SMA"
else
    check "FAIL" "SMA 20 → null"
fi

RSI=$(curl -s "$BASE_URL/instruments/$INST_ID/indicators/rsi?period=14" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('latest','NULL'))" 2>/dev/null)
if [ "$RSI" != "None" ] && [ "$RSI" != "NULL" ]; then
    check "OK" "RSI 14 → $RSI"
else
    check "FAIL" "RSI 14 → null"
fi

MACD=$(curl -s "$BASE_URL/instruments/$INST_ID/indicators/macd" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('trend','NULL'))" 2>/dev/null)
if [ "$MACD" != "None" ] && [ "$MACD" != "NULL" ]; then
    check "OK" "MACD trend → $MACD"
else
    check "FAIL" "MACD trend → null"
fi

ADX=$(curl -s "$BASE_URL/instruments/$INST_ID/indicators/adx" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('trend_strength','NULL'))" 2>/dev/null)
if [ "$ADX" != "None" ] && [ "$ADX" != "NULL" ]; then
    check "OK" "ADX strength → $ADX"
else
    check "FAIL" "ADX strength → null"
fi

ALL_IND=$(curl -s "$BASE_URL/instruments/$INST_ID/indicators" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d.get('data',{})))" 2>/dev/null)
if [ "$ALL_IND" -gt 5 ] 2>/dev/null; then
    check "OK" "All indicators → $ALL_IND keys"
else
    check "FAIL" "All indicators → incomplete"
fi

# ─── 4. Market Regime ──────────────────────────────────────────────────
echo ""
echo "4. Market Regime"
REGIME=$(curl -s "$BASE_URL/instruments/$INST_ID/regime" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('regime','NULL'))" 2>/dev/null)
if [ -n "$REGIME" ] && [ "$REGIME" != "None" ] && [ "$REGIME" != "NULL" ]; then
    check "OK" "Regime → $REGIME"
else
    check "FAIL" "Regime → null"
fi

# ─── 5. Composite Score ────────────────────────────────────────────────
echo ""
echo "5. Composite Score"
CS=$(curl -s "$BASE_URL/instruments/$INST_ID/composite-score" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); dd=d.get('data',{}); print(f\"{dd.get('composite_score',0)}:{dd.get('recommendation','NULL')}\")" 2>/dev/null)
CS_SCORE=$(echo "$CS" | cut -d: -f1)
CS_REC=$(echo "$CS" | cut -d: -f2)
if [ -n "$CS_SCORE" ] && [ "$CS_SCORE" != "0" ]; then
    check "OK" "Composite score → $CS_SCORE, rec: $CS_REC"
else
    check "FAIL" "Composite score → zero/null"
fi

# ─── 6. Screening ──────────────────────────────────────────────────────
echo ""
echo "6. Screening Engine"
SCR_TOTAL=$(curl -s -X POST "$BASE_URL/screening" -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"asset_class":"EQUITY","min_roe":15,"limit":10}' \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('total',0))" 2>/dev/null)
if [ "$SCR_TOTAL" -gt 0 ] 2>/dev/null; then
    check "OK" "Screening ROE>=15 → $SCR_TOTAL results"
else
    check "FAIL" "Screening → no results"
fi

# ─── 7. Support/Resistance & Trend ─────────────────────────────────────
echo ""
echo "7. Support/Resistance & Trend"
SR=$(curl -s "$BASE_URL/instruments/$INST_ID/support-resistance" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); dd=d.get('data',{}); print(dd.get('current_price','NULL'))" 2>/dev/null)
if [ "$SR" != "None" ] && [ "$SR" != "NULL" ]; then
    check "OK" "Support/Resistance → price: $SR"
else
    check "FAIL" "Support/Resistance → null"
fi

TREND=$(curl -s "$BASE_URL/instruments/$INST_ID/trend" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('trend','NULL'))" 2>/dev/null)
if [ -n "$TREND" ] && [ "$TREND" != "None" ] && [ "$TREND" != "NULL" ]; then
    check "OK" "Trend → $TREND"
else
    check "FAIL" "Trend → null"
fi

# ─── 8. Market Microstructure ──────────────────────────────────────────
echo ""
echo "8. Market Microstructure"
SPREAD=$(curl -s "$BASE_URL/instruments/$INST_ID/bid-ask-spread" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('classification','NULL'))" 2>/dev/null)
if [ -n "$SPREAD" ] && [ "$SPREAD" != "None" ] && [ "$SPREAD" != "NULL" ]; then
    check "OK" "Bid/Ask Spread → $SPREAD"
else
    check "FAIL" "Bid/Ask Spread → null"
fi

OB=$(curl -s "$BASE_URL/instruments/$INST_ID/order-book?levels=5" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d.get('data',{}).get('levels',[])))" 2>/dev/null)
if [ "$OB" -gt 0 ] 2>/dev/null; then
    check "OK" "Order Book → $OB levels"
else
    check "FAIL" "Order Book → empty"
fi

MI=$(curl -s -X POST "$BASE_URL/instruments/$INST_ID/market-impact" -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" -d '{"order_value":500000000,"side":"BUY"}' \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('classification','NULL'))" 2>/dev/null)
if [ -n "$MI" ] && [ "$MI" != "None" ] && [ "$MI" != "NULL" ]; then
    check "OK" "Market Impact → $MI"
else
    check "FAIL" "Market Impact → null"
fi

LIQ=$(curl -s "$BASE_URL/instruments/$INST_ID/liquidity-score" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('grade','NULL'))" 2>/dev/null)
if [ -n "$LIQ" ] && [ "$LIQ" != "None" ] && [ "$LIQ" != "NULL" ]; then
    check "OK" "Liquidity Score → grade: $LIQ"
else
    check "FAIL" "Liquidity Score → null"
fi

# ─── 9. Market Factor Matrix ───────────────────────────────────────────
echo ""
echo "9. Market Factor Matrix"
GF=$(curl -s "$BASE_URL/factors/global-indonesia" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('summary','NULL'))" 2>/dev/null)
if [ -n "$GF" ] && [ "$GF" != "None" ] && [ "$GF" != "NULL" ]; then
    check "OK" "Global Factors → $GF"
else
    check "FAIL" "Global Factors → null"
fi

RP=$(curl -s "$BASE_URL/factors/rupiah-pressure" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('grade','NULL'))" 2>/dev/null)
if [ -n "$RP" ] && [ "$RP" != "None" ] && [ "$RP" != "NULL" ]; then
    check "OK" "Rupiah Pressure → $RP"
else
    check "FAIL" "Rupiah Pressure → null"
fi

FC=$(curl -s "$BASE_URL/factors/flow-confirmation" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('grade','NULL'))" 2>/dev/null)
if [ -n "$FC" ] && [ "$FC" != "None" ] && [ "$FC" != "NULL" ]; then
    check "OK" "Flow Confirmation → $FC"
else
    check "FAIL" "Flow Confirmation → null"
fi

# ─── 10. Stop Loss ─────────────────────────────────────────────────────
echo ""
echo "10. Risk - Stop Loss"
SL=$(curl -s -X POST "$BASE_URL/instruments/$INST_ID/stop-loss" -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" -d '{"entry_price":100,"side":"BUY"}' \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('method','NULL'))" 2>/dev/null)
if [ -n "$SL" ] && [ "$SL" != "None" ] && [ "$SL" != "NULL" ]; then
    check "OK" "Stop Loss → method: $SL"
else
    check "FAIL" "Stop Loss → null"
fi

# ─── 11. Data Quality ──────────────────────────────────────────────────
echo ""
echo "11. Data Quality"
DQ=$(curl -s "$BASE_URL/ingestion/quality/$INST_ID" -H "Authorization: Bearer $TOKEN" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('passed','NULL'))" 2>/dev/null)
if [ -n "$DQ" ] && [ "$DQ" != "None" ] && [ "$DQ" != "NULL" ]; then
    check "OK" "Data Quality → passed: $DQ"
else
    check "FAIL" "Data Quality → null"
fi

# ─── 12. Health ────────────────────────────────────────────────────────
echo ""
echo "12. Health Check"
HEALTH=$(curl -s "$BASE_URL/health" \
    | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('data',{}).get('status','NULL'))" 2>/dev/null)
if [ "$HEALTH" = "healthy" ]; then
    check "OK" "Health → $HEALTH"
else
    check "FAIL" "Health → $HEALTH"
fi

# ─── Summary ───────────────────────────────────────────────────────────
echo ""
echo "=========================================="
echo "  Results: $PASS/$TOTAL passed, $FAIL failed"
echo "=========================================="

if [ "$FAIL" -eq 0 ]; then
    echo "  ✅ ALL TESTS PASSED"
    exit 0
else
    echo "  ❌ SOME TESTS FAILED"
    exit 1
fi
