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

  console.log('\n── Member Management Tests ──\n');

  await test('login as admin', async () => {
    await loginAs(page, 'admin', 'admin123');
    const body = await page.textContent('body');
    if (!body.includes('Dashboard') && !body.includes('Good')) {
      throw new Error('Not on dashboard after login');
    }
  });

  await test('navigate to members page', async () => {
    await navigateTo(page, `${BASE_URL}/members.php`);
    await page.waitForSelector('.data-table', { timeout: 5000 });
    const heading = await page.textContent('h2');
    if (!heading.includes('Member')) throw new Error('Not on members page');
  });

  await test('members table displays', async () => {
    const rows = await page.$$('.data-table tbody tr');
    if (rows.length === 0) throw new Error('No member rows found');
  });

  await test('view member details modal opens', async () => {
    const viewBtn = await page.$('button.btn-view');
    if (viewBtn) {
      await viewBtn.click();
      await page.waitForSelector('#viewModal', { state: 'visible', timeout: 3000 });
      const modal = await page.textContent('#viewModal');
      if (!modal.includes('Member Details')) throw new Error('Modal not opened');
      const closeBtn = await page.$('#viewModal .close, #viewModal .modal-close');
      if (closeBtn) await closeBtn.click();
    }
  });

  await test('savings page loads', async () => {
    await navigateTo(page, `${BASE_URL}/savings.php`);
    const heading = await page.textContent('h2');
    if (!heading.includes('Savings')) throw new Error('Not on savings page');
  });

  await test('loans page loads', async () => {
    await navigateTo(page, `${BASE_URL}/loans.php`);
    const heading = await page.textContent('h2');
    if (!heading.includes('Loan')) throw new Error('Not on loans page');
  });

  await test('reports page loads', async () => {
    await navigateTo(page, `${BASE_URL}/reports.php`);
    const heading = await page.textContent('h2');
    if (!heading.includes('Report')) throw new Error('Not on reports page');
  });

  await test('meetings page loads', async () => {
    await navigateTo(page, `${BASE_URL}/meetings.php`);
    const heading = await page.textContent('h2');
    if (!heading.includes('Meeting')) throw new Error('Not on meetings page');
  });

  await browser.close();
  console.log(`\n${failures === 0 ? '✓ All member management tests passed' : `✗ ${failures} test(s) failed`}\n`);
  process.exit(failures > 0 ? 1 : 0);
}

run().catch(e => { console.error('Fatal:', e); process.exit(1); });
