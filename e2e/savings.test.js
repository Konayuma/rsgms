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

  console.log('\n── Savings Tests ──\n');

  await test('login as admin', async () => {
    await loginAs(page, 'admin', 'admin123');
    const body = await page.textContent('body');
    if (!body.includes('Dashboard') && !body.includes('Good')) {
      throw new Error('Not on dashboard after login');
    }
  });

  await test('navigate to savings page', async () => {
    await navigateTo(page, `${BASE_URL}/savings.php`);
    await page.waitForSelector('.data-table', { timeout: 5000 });
    const heading = await page.textContent('h2');
    if (!heading.includes('Savings')) throw new Error('Not on savings page');
  });

  await test('savings records table loads', async () => {
    const rows = await page.$$('.data-table tbody tr');
    if (rows.length === 0) {
      const body = await page.textContent('body');
      if (!body.includes('No savings')) throw new Error('Savings table not rendering');
    }
  });

  await test('record savings page loads as member', async () => {
    await navigateTo(page, `${BASE_URL}/login.php`);
    await page.fill('input[name="username"]', 'alice');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1500);
    await navigateTo(page, `${BASE_URL}/record_savings.php`);
    const body = await page.textContent('body');
    if (!body.includes('Record') && !body.includes('Savings') && !body.includes('Contribution')) {
      throw new Error('Not on record savings page');
    }
  });

  await test('member views my savings page', async () => {
    await navigateTo(page, `${BASE_URL}/my_savings.php`);
    await page.waitForTimeout(500);
    const heading = await page.textContent('h2');
    if (!heading.includes('Savings')) throw new Error('Not on my savings page');
  });

  await test('member savings stats are visible', async () => {
    const cards = await page.$$('.stat-card');
    if (cards.length === 0) throw new Error('No stat cards on savings page');
  });

  await test('member savings history table loads', async () => {
    const body = await page.textContent('body');
    if (!body.includes('Contribution') && !body.includes('No savings')) {
      throw new Error('Savings history not displayed');
    }
  });

  await browser.close();
  console.log(`\n${failures === 0 ? '✓ All savings tests passed' : `✗ ${failures} test(s) failed`}\n`);
  process.exit(failures > 0 ? 1 : 0);
}

run().catch(e => { console.error('Fatal:', e); process.exit(1); });
