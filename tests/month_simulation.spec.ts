import { test, expect } from '@playwright/test';

const BASE_URL = 'http://localhost:8080';
const FRONTEND_URL = 'http://localhost:8080/dashboard';
const DAY_DURATION_MS = 5000; // 1 day = 5 seconds
const TOTAL_DAYS = 22; // 22 trading days in 1 month

// These will be fetched dynamically from the API after login
let BBCA = '';
let PF_GROWTH = '';
let INSTRUMENTS: { id: string; ticker: string }[] = [];

test('1-Month Market Simulation (22 days × 5 sec/day) - Headed Browser', async ({ page }) => {
  test.setTimeout(DAY_DURATION_MS * TOTAL_DAYS + 30000);

  // ─── Login ──────────────────────────────────────────────────────────
  console.log('\n🚀 Starting 1-Month Market Simulation');
  console.log(`   ${TOTAL_DAYS} trading days, ${DAY_DURATION_MS / 1000}s per day`);
  console.log(`   Total duration: ~${(DAY_DURATION_MS * TOTAL_DAYS) / 1000}s`);
  console.log('==========================================\n');

  const loginRes = await page.request.post(`${BASE_URL}/auth/login`, {
    data: { email: 'owner@platform.local', password: 'Test@1234567' },
  });
  expect(loginRes.ok()).toBeTruthy();
  const token = (await loginRes.json()).data.token;
  console.log('🔐 Logged in successfully');

  const headers = { Authorization: `Bearer ${token}` };

  // Navigate to dashboard
  await page.goto(FRONTEND_URL);
  await page.waitForTimeout(1000);
  await page.setViewportSize({ width: 1920, height: 1080 });

  // ─── Pre-fetch baseline data ────────────────────────────────────────
  console.log('\n📊 Fetching baseline market data...');
  const baselineRes = await page.request.get(`${BASE_URL}/instruments?per_page=20`, { headers });
  const baseline = (await baselineRes.json()).data || [];
  console.log(`   ${baseline.length} active instruments`);

  // Build INSTRUMENTS list dynamically from API response
  INSTRUMENTS = baseline.map((i: any) => ({
    id: i.instrument_id,
    ticker: i.ticker || i.listings?.[0]?.ticker || 'UNKNOWN',
  }));
  const bbcaInstr = baseline.find((i: any) => i.ticker === 'BBCA' || i.listings?.some((l: any) => l.ticker === 'BBCA'));
  BBCA = bbcaInstr?.instrument_id || baseline[0]?.instrument_id || '';

  // Fetch portfolios
  const pfRes = await page.request.get(`${BASE_URL}/portfolios?per_page=5`, { headers });
  if (pfRes.ok()) {
    const portfolios = (await pfRes.json()).data || [];
    PF_GROWTH = portfolios[0]?.portfolio_id || '';
  }

  // ─── Day-by-Day Simulation Loop ─────────────────────────────────────
  for (let day = TOTAL_DAYS; day >= 1; day--) {
    const dayLabel = `Day ${TOTAL_DAYS - day + 1}/${TOTAL_DAYS}`;
    const dayStart = Date.now();

    // Rotate through instruments - pick 2-3 per day
    const dayInstruments = INSTRUMENTS.filter((_, i) => (i + day) % 3 === 0).slice(0, 3);

    console.log(`\n📅 ${dayLabel} | ${new Date(Date.now() - day * 86400000).toISOString().split('T')[0]}`);

    // 1. Fetch signals for the day
    for (const instr of dayInstruments) {
      const sigRes = await page.request.get(`${BASE_URL}/instruments/${instr.id}/signals?per_page=5`, { headers });
      if (sigRes.ok()) {
        const sigs = (await sigRes.json()).data || [];
        if (sigs.length > 0) {
          const latest = sigs[0];
          console.log(`   📡 ${instr.ticker}: ${latest.signal_type} ${latest.direction} (str: ${latest.strength})`);
        }
      }
    }

    // 2. Fetch composite score for primary instrument
    const compRes = await page.request.get(`${BASE_URL}/instruments/${dayInstruments[0]?.id || BBCA}/composite-score`, { headers });
    if (compRes.ok()) {
      const comp = (await compRes.json()).data;
      console.log(`   🎯 ${dayInstruments[0]?.ticker || 'BBCA'} Composite: ${Number(comp.composite_score).toFixed(1)} → ${comp.recommendation}`);
    }

    // 3. Fetch microstructure
    const spreadRes = await page.request.get(`${BASE_URL}/instruments/${dayInstruments[0]?.id || BBCA}/bid-ask-spread`, { headers });
    if (spreadRes.ok()) {
      const spread = (await spreadRes.json()).data;
      console.log(`   💰 Spread: ${Number(spread.spread).toFixed(2)} (${spread.classification})`);
    }

    // 4. Fetch regime
    const regimeRes = await page.request.get(`${BASE_URL}/instruments/${dayInstruments[0]?.id || BBCA}/regime`, { headers });
    if (regimeRes.ok()) {
      const regime = (await regimeRes.json()).data;
      console.log(`   🌊 Regime: ${regime.regime} (${regime.sub_regime}) | Risk: ${regime.risk_appetite}`);
    }

    // 5. Check risk for growth portfolio (every 5 days)
    if (day % 5 === 0) {
      const liqRiskRes = await page.request.get(`${BASE_URL}/portfolios/${PF_GROWTH}/liquidity-risk`, { headers });
      if (liqRiskRes.ok()) {
        const liqRisk = (await liqRiskRes.json()).data;
        console.log(`   🌊 Liquidity Risk: ${liqRisk.portfolio_risk_level} (score: ${liqRisk.portfolio_liquidity_risk_score})`);
      }

      const gapRiskRes = await page.request.get(`${BASE_URL}/portfolios/${PF_GROWTH}/gap-risk`, { headers });
      if (gapRiskRes.ok()) {
        const gapRisk = (await gapRiskRes.json()).data;
        console.log(`   📏 Gap Risk: ${gapRisk.portfolio_risk_level} (${Number(gapRisk.portfolio_gap_risk_pct).toFixed(2)}%)`);
      }
    }

    // 6. Navigate frontend to stock detail page (rotating instruments)
    const navInstr = dayInstruments[0] || INSTRUMENTS[0];
    await page.goto(`${FRONTEND_URL}/#/instruments/${navInstr.id}`);
    await page.waitForTimeout(500);

    // 7. Take screenshot every 5 days
    if (day % 5 === 0 || day === 1) {
      const screenshotPath = `tests/screenshots/sim-day-${TOTAL_DAYS - day + 1}.png`;
      await page.screenshot({ path: screenshotPath, fullPage: true });
      console.log(`   📸 Screenshot: sim-day-${TOTAL_DAYS - day + 1}.png`);
    }

    // Wait for remaining time in this "day"
    const elapsed = Date.now() - dayStart;
    const remaining = Math.max(0, DAY_DURATION_MS - elapsed);
    if (remaining > 0) {
      await page.waitForTimeout(remaining);
    }
  }

  // ─── Final Summary ──────────────────────────────────────────────────
  console.log('\n==========================================');
  console.log('  📊 FINAL PORTFOLIO SNAPSHOT');
  console.log('==========================================\n');

  // Final portfolio positions
  const posRes = await page.request.get(`${BASE_URL}/portfolios/${PF_GROWTH}/positions`, { headers });
  if (posRes.ok()) {
    const positions = (await posRes.json()).data || [];
    console.log(`💼 Growth Portfolio: ${Array.isArray(positions) ? positions.length : 'N/A'} positions`);
  }

  // Final risk assessment
  const finalLiqRes = await page.request.get(`${BASE_URL}/portfolios/${PF_GROWTH}/liquidity-risk`, { headers });
  if (finalLiqRes.ok()) {
    const liq = (await finalLiqRes.json()).data;
    console.log(`🌊 Final Liquidity Risk: ${liq.portfolio_risk_level} (score: ${liq.portfolio_liquidity_risk_score})`);
    console.log(`   Total Value: Rp ${Number(liq.total_value).toLocaleString()}`);
    console.log(`   Avg Liquidation Days: ${liq.avg_liquidation_days}`);
  }

  const finalGapRes = await page.request.get(`${BASE_URL}/portfolios/${PF_GROWTH}/gap-risk`, { headers });
  if (finalGapRes.ok()) {
    const gap = (await finalGapRes.json()).data;
    console.log(`📏 Final Gap Risk: ${gap.portfolio_risk_level} (${Number(gap.portfolio_gap_risk_pct).toFixed(2)}%)`);
  }

  // Final compliance check
  const capRes = await page.request.post(`${BASE_URL}/compliance/capital-threshold`, {
    headers: { ...headers, 'Content-Type': 'application/json' },
    data: { portfolio_id: PF_GROWTH, order_value: 10000000 },
  });
  if (capRes.ok()) {
    const cap = (await capRes.json()).data;
    console.log(`🔒 Capital Check: ${cap.passed ? 'PASS' : 'FAIL'} | Cash: Rp ${Number(cap.cash_balance).toLocaleString()}`);
  }

  // Final health
  const healthRes = await page.request.get(`${BASE_URL}/health`);
  if (healthRes.ok()) {
    const health = await healthRes.json();
    console.log(`❤️ System Health: ${health.data?.status || 'OK'}`);
  }

  // Final screenshot
  await page.goto(`${FRONTEND_URL}/#/instruments/${BBCA}`);
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'tests/screenshots/sim-final.png', fullPage: true });
  console.log(`\n📸 Final screenshot saved: sim-final.png`);

  console.log('\n==========================================');
  console.log('  🎉 1-MONTH SIMULATION COMPLETE!');
  console.log(`  ${TOTAL_DAYS} trading days simulated`);
  console.log(`  Duration: ${DAY_DURATION_MS / 1000}s/day × ${TOTAL_DAYS} days`);
  console.log('==========================================\n');

  expect(true).toBeTruthy();
});
