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

  console.log('\n── Loan Lifecycle Tests ──\n');

  await test('login as member', async () => {
    await loginAs(page, 'alice', 'password123');
    const body = await page.textContent('body');
    if (!body.includes('Dashboard') && !body.includes('Good')) {
      throw new Error('Not on dashboard after login');
    }
  });

  await test('my loans page loads', async () => {
    await navigateTo(page, `${BASE_URL}/my_loans.php`);
    const body = await page.textContent('body');
    if (!body.includes('Loan') && !body.includes('loan')) throw new Error('Not on my loans page');
  });

  await test('loan stats display', async () => {
    const cards = await page.$$('.stat-card');
    if (cards.length === 0) throw new Error('No stat cards on loans page');
  });

  await test('new loan application page loads', async () => {
    await navigateTo(page, `${BASE_URL}/new_loan.php`);
    const body = await page.textContent('body');
    if (!body.includes('Loan') && !body.includes('loan') && !body.includes('Application')) {
      throw new Error('Not on new loan page');
    }
  });

  await test('loan application form has required fields', async () => {
    const principal = await page.$('input[name="principal"]');
    const period = await page.$('select[name="repayment_period"], input[name="repayment_period"]');
    if (!principal) throw new Error('Missing principal field');
    if (!period) throw new Error('Missing repayment period field');
  });

  await test('login as loan officer', async () => {
    await loginAs(page, 'loanofficer', 'pass123');
    const body = await page.textContent('body');
    if (!body.includes('Dashboard') && !body.includes('Good')) {
      throw new Error('Not on dashboard after loan officer login');
    }
  });

  await test('loan officer can view loans page', async () => {
    await navigateTo(page, `${BASE_URL}/loans.php`);
    const body = await page.textContent('body');
    if (!body.includes('Loan') && !body.includes('loan')) throw new Error('Not on loans page');
  });

  await test('loan officer can view member details in loan list', async () => {
    const body = await page.textContent('body');
    if (!body.includes('Member') && !body.includes('Principal') && !body.includes('Status')) {
      throw new Error('Loan table columns not found');
    }
  });

  await test('login as admin to view full loan overview', async () => {
    await loginAs(page, 'admin', 'admin123');
    await navigateTo(page, `${BASE_URL}/loans.php`);
    const body = await page.textContent('body');
    if (!body.includes('Loan') && !body.includes('loan')) throw new Error('Not on loans page');
  });

  await browser.close();
  console.log(`\n${failures === 0 ? '✓ All loan tests passed' : `✗ ${failures} test(s) failed`}\n`);
  process.exit(failures > 0 ? 1 : 0);
}

run().catch(e => { console.error('Fatal:', e); process.exit(1); });
