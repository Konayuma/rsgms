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

  console.log('\n── Dashboard & Reports Tests ──\n');

  await test('login as admin and view dashboard', async () => {
    await loginAs(page, 'admin', 'admin123');
    const body = await page.textContent('body');
    if (!body.includes('Good') && !body.includes('Welcome') && !body.includes('Dashboard')) {
      throw new Error('Dashboard greeting not found');
    }
  });

  await test('dashboard shows stat cards', async () => {
    const cards = await page.$$('.stat-card');
    if (cards.length < 3) throw new Error(`Expected at least 3 stat cards, got ${cards.length}`);
  });

  await test('dashboard shows recent transactions', async () => {
    const body = await page.textContent('body');
    if (!body.includes('Recent') && !body.includes('Transaction') && !body.includes('transactions')) {
      throw new Error('Recent transactions section not found');
    }
  });

  await test('dashboard shows quick actions', async () => {
    const body = await page.textContent('body');
    if (!body.includes('Quick') && !body.includes('Actions')) {
      const sections = await page.$$('.section-title');
      if (sections.length < 2) throw new Error('Dashboard sections missing');
    }
  });

  await test('navigate to reports page', async () => {
    await navigateTo(page, `${BASE_URL}/reports.php`);
    const heading = await page.textContent('h2');
    if (!heading.includes('Report')) throw new Error('Not on reports page');
  });

  await test('reports show summary stats', async () => {
    const cards = await page.$$('.stat-card');
    if (cards.length < 4) throw new Error(`Expected at least 4 stat cards, got ${cards.length}`);
  });

  await test('reports show savings and loan tables', async () => {
    const body = await page.textContent('body');
    if (!body.includes('Savings') && !body.includes('Portfolio')) {
      throw new Error('Report tables not found');
    }
  });

  await test('export buttons exist', async () => {
    const buttons = await page.$$('.btn-export');
    if (buttons.length < 2) throw new Error('Export buttons missing');
  });

  await test('notifications page loads', async () => {
    await navigateTo(page, `${BASE_URL}/notifications.php`);
    const heading = await page.textContent('h1');
    if (!heading.includes('Notification')) throw new Error('Not on notifications page');
  });

  await test('profile page loads', async () => {
    await navigateTo(page, `${BASE_URL}/profile.php`);
    const heading = await page.textContent('h2');
    if (!heading.includes('Profile') && !heading.includes('Settings')) {
      throw new Error('Not on profile page');
    }
  });

  await browser.close();
  console.log(`\n${failures === 0 ? '✓ All dashboard/reports tests passed' : `✗ ${failures} test(s) failed`}\n`);
  process.exit(failures > 0 ? 1 : 0);
}

run().catch(e => { console.error('Fatal:', e); process.exit(1); });
