const { chromium } = require('playwright');
const { BASE_URL, CHROME_PATH, createBrowser, loginAs, navigateTo } = require('./helpers');

async function run() {
  const browser = await createBrowser();
  const page = await browser.newPage();
  let failures = 0;

  async function test(name, fn) {
    try {
      await fn();
      console.log(`  ✓ ${name}`);
    } catch (e) {
      console.log(`  ✗ ${name}: ${e.message}`);
      failures++;
    }
  }

  console.log('\n── Auth Tests ──\n');

  await test('login as admin', async () => {
    await loginAs(page, 'admin', 'admin123');
    const body = await page.textContent('body');
    if (!body.includes('Dashboard') && !body.includes('dashboard') && !body.includes('Good')) {
      throw new Error('Not on dashboard after login');
    }
  });

  await test('dashboard shows for admin', async () => {
    const body = await page.textContent('body');
    if (!body.includes('Dashboard') && !body.includes('dashboard')) {
      throw new Error('Not on dashboard');
    }
  });

  await test('sidebar contains expected nav items', async () => {
    const sidebar = await page.textContent('.sidebar');
    if (!sidebar.includes('Members')) throw new Error('Missing Members nav');
    if (!sidebar.includes('Savings')) throw new Error('Missing Savings nav');
    if (!sidebar.includes('Loans')) throw new Error('Missing Loans nav');
    if (!sidebar.includes('Reports')) throw new Error('Missing Reports nav');
    if (!sidebar.includes('Dashboard')) throw new Error('Missing Dashboard nav');
  });

  await test('logout button exists in sidebar', async () => {
    const sidebar = await page.innerHTML('.sidebar-footer');
    if (!sidebar.includes('logout')) throw new Error('Missing logout in sidebar');
  });

  await test('logout works', async () => {
    await navigateTo(page, `${BASE_URL}/logout.php`);
    await page.waitForTimeout(500);
    const url = page.url();
    if (!url.includes('index') && !url.includes('login')) {
      throw new Error(`Expected index/login page, got ${url}`);
    }
  });

  await test('redirect to login when not authenticated', async () => {
    await navigateTo(page, `${BASE_URL}/dashboard.php`);
    await page.waitForTimeout(500);
    const body = await page.textContent('body');
    if (!body.includes('Sign in') && !body.includes('Login') && !body.includes('login') && !body.includes('Unauthorized')) {
      throw new Error('Not redirected to login');
    }
  });

  await test('invalid login shows error', async () => {
    await navigateTo(page, `${BASE_URL}/login.php`);
    await page.waitForTimeout(300);
    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000);
    const body = await page.textContent('body');
    if (!body.includes('Invalid') && !body.includes('incorrect') && !body.includes('error')) {
      throw new Error('No error message for invalid login');
    }
  });

  await test('member cannot access admin pages', async () => {
    await navigateTo(page, `${BASE_URL}/login.php`);
    await page.waitForTimeout(300);
    await page.fill('input[name="username"]', 'alice');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);
    await navigateTo(page, `${BASE_URL}/members.php`);
    await page.waitForTimeout(500);
    const url = page.url();
    if (!url.includes('login') && !url.includes('dashboard')) {
      const body = await page.textContent('body');
      if (!body.includes('denied') && !body.includes('Access') && !body.includes('redirect') && !body.includes('Unauthorized')) {
        throw new Error('Member not blocked from admin page');
      }
    }
  });

  await browser.close();
  console.log(`\n${failures === 0 ? '✓ All auth tests passed' : `✗ ${failures} auth test(s) failed`}\n`);
  process.exit(failures > 0 ? 1 : 0);
}

run().catch(e => { console.error('Fatal:', e); process.exit(1); });
