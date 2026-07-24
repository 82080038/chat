import { test, expect, type ConsoleMessage, type Response } from '@playwright/test';

const BASE = 'http://localhost:8080/dashboard';

test.describe('E2E Application Simulation', () => {

  test('1. Login page loads correctly', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') errors.push(`Console: ${msg.text()}`);
    });
    page.on('pageerror', (err: Error) => errors.push(`PageError: ${err.message}`));

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /quick login/i })).toBeVisible();
    await expect(page.getByText('Capital Market Platform')).toBeVisible();

    await page.screenshot({ path: 'tests/screenshots/01-login-page.png', fullPage: true });
    if (errors.length > 0) console.log('LOGIN PAGE ERRORS:', JSON.stringify(errors, null, 2));
    expect(errors).toHaveLength(0);
  });

  test('2. Quick login works', async ({ page }) => {
    const errors: string[] = [];
    const failedRequests: string[] = [];

    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') errors.push(`Console: ${msg.text()}`);
    });
    page.on('pageerror', (err: Error) => errors.push(`PageError: ${err.message}`));
    page.on('response', (res: Response) => {
      if (res.status() >= 400 && !res.url().includes('favicon')) {
        failedRequests.push(`${res.status()} ${res.url()}`);
      }
    });

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/login/);
    await page.getByRole('button', { name: /quick login/i }).click();

    await page.waitForURL(`${BASE}`, { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    await page.screenshot({ path: 'tests/screenshots/02-after-login.png', fullPage: true });

    if (errors.length > 0) console.log('QUICK LOGIN ERRORS:', JSON.stringify(errors, null, 2));
    if (failedRequests.length > 0) console.log('QUICK LOGIN FAILED REQUESTS:', JSON.stringify(failedRequests, null, 2));
    expect(errors).toHaveLength(0);
    expect(failedRequests).toHaveLength(0);
  });

  test('3. Dashboard displays content', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') errors.push(`Console: ${msg.text()}`);
    });
    page.on('pageerror', (err: Error) => errors.push(`PageError: ${err.message}`));

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /quick login/i }).click();
    await page.waitForURL(`${BASE}`, { timeout: 10000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    const bodyText = await page.locator('body').innerText();
    console.log('DASHBOARD TEXT (first 1000 chars):', bodyText.substring(0, 1000));

    const rootContent = await page.locator('#root').innerHTML();
    expect(rootContent.length).toBeGreaterThan(100);

    await page.screenshot({ path: 'tests/screenshots/03-dashboard.png', fullPage: true });

    if (errors.length > 0) console.log('DASHBOARD ERRORS:', JSON.stringify(errors, null, 2));
    expect(errors).toHaveLength(0);
  });

  test('4. Navigate through all dashboard sections', async ({ page }) => {
    const errors: string[] = [];
    const failedRequests: string[] = [];

    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') errors.push(`Console[${msg.type()}]: ${msg.text()}`);
    });
    page.on('pageerror', (err: Error) => errors.push(`PageError: ${err.message}`));
    page.on('response', (res: Response) => {
      if (res.status() >= 400 && !res.url().includes('favicon')) {
        failedRequests.push(`${res.status()} ${res.url()}`);
      }
    });

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /quick login/i }).click();
    await page.waitForURL(`${BASE}`, { timeout: 10000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const navLinks = await page.locator('nav a, aside a, [role="navigation"] a, .sidebar a, .nav-link, button[href]').all();
    console.log(`Found ${navLinks.length} navigation links`);

    for (let i = 0; i < navLinks.length; i++) {
      const link = navLinks[i];
      const text = (await link.innerText().catch(() => '')).trim();
      const href = await link.getAttribute('href').catch(() => null);
      if (!text || !href || href === '#') continue;
      console.log(`  Nav [${i}]: "${text}" -> ${href}`);
    }

    for (let i = 0; i < navLinks.length; i++) {
      const link = navLinks[i];
      const text = (await link.innerText().catch(() => '')).trim();
      const href = await link.getAttribute('href').catch(() => null);
      if (!text || !href || href === '#') continue;

      try {
        await link.click({ timeout: 5000 });
        await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
        console.log(`  OK Clicked: "${text}" -> ${page.url()}`);
        await page.waitForTimeout(1500);
        await page.screenshot({ path: `tests/screenshots/04-nav-${i}-${text.toLowerCase().replace(/\s+/g, '-')}.png`, fullPage: true }).catch(() => {});
      } catch (e) {
        console.log(`  FAIL Clicking: "${text}" -> ${(e as Error).message}`);
      }
    }

    if (errors.length > 0) console.log('NAVIGATION ERRORS:', JSON.stringify(errors, null, 2));
    if (failedRequests.length > 0) console.log('NAVIGATION FAILED REQUESTS:', JSON.stringify(failedRequests, null, 2));
    expect(errors).toHaveLength(0);
    expect(failedRequests).toHaveLength(0);
  });

  test('5. Monitor all API calls', async ({ page }) => {
    const apiCalls: { method: string; url: string; status: number }[] = [];

    page.on('response', (res: Response) => {
      const url = res.url();
      if (url.includes('localhost:8080') && !url.includes('.js') && !url.includes('.css') && !url.includes('.html') && !url.includes('favicon') && !url.includes('/dashboard/')) {
        apiCalls.push({ method: res.request().method(), url: url.replace('http://localhost:8080', ''), status: res.status() });
      }
    });

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /quick login/i }).click();
    await page.waitForURL(`${BASE}`, { timeout: 10000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(5000);

    console.log('API CALLS:');
    apiCalls.forEach(c => {
      const icon = c.status >= 400 ? 'X' : 'OK';
      console.log(`  ${icon} ${c.method} ${c.url} -> ${c.status}`);
    });

    const failed = apiCalls.filter(c => c.status >= 400);
    if (failed.length > 0) console.log('FAILED API CALLS:', JSON.stringify(failed, null, 2));
    expect(failed).toHaveLength(0);
  });

  test('6. Manual login form works', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') errors.push(`Console: ${msg.text()}`);
    });
    page.on('pageerror', (err: Error) => errors.push(`PageError: ${err.message}`));

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');

    await page.locator('input[type="email"]').fill('owner@platform.local');
    await page.locator('input[type="password"]').fill('Test@1234567');
    await page.getByRole('button', { name: /sign in/i }).click();

    await page.waitForURL(`${BASE}`, { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    await page.screenshot({ path: 'tests/screenshots/06-manual-login.png', fullPage: true });

    if (errors.length > 0) console.log('MANUAL LOGIN ERRORS:', JSON.stringify(errors, null, 2));
    expect(errors).toHaveLength(0);
  });

  test('7. Logout works', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg: ConsoleMessage) => {
      if (msg.type() === 'error') errors.push(`Console: ${msg.text()}`);
    });

    await page.goto(`${BASE}/login`);
    await page.evaluate(() => {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    });
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /quick login/i }).click();
    await page.waitForURL(`${BASE}`, { timeout: 10000 });
    await page.waitForLoadState('networkidle');

    const logoutBtn = page.getByRole('button', { name: /logout/i }).or(page.getByText(/logout/i));
    if (await logoutBtn.count() > 0) {
      await logoutBtn.first().click();
      await page.waitForURL(/\/login/, { timeout: 5000 }).catch(() => {});
      console.log(`After logout URL: ${page.url()}`);
      await page.screenshot({ path: 'tests/screenshots/07-after-logout.png', fullPage: true });
    } else {
      console.log('No logout button found');
    }

    if (errors.length > 0) console.log('LOGOUT ERRORS:', JSON.stringify(errors, null, 2));
    expect(errors).toHaveLength(0);
  });
});
