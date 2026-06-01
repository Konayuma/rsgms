const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { spawn } = require('child_process');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const CHROME_PATH = '/usr/bin/google-chrome';

let serverProcess = null;

async function startServer(port) {
  port = port || 8080;
  const host = `localhost:${port}`;
  return new Promise((resolve, reject) => {
    const docRoot = path.resolve(__dirname, '..');
    const phpBin = process.env.PHP_BIN || '/opt/lampp/bin/php-8.2.12';
    serverProcess = spawn(phpBin, [
      '-S', host,
      '-t', docRoot
    ], {
      stdio: ['pipe', 'pipe', 'pipe'],
      env: { ...process.env },
    });

    const timeout = setTimeout(() => {
      reject(new Error(`PHP server did not start on ${host}`));
    }, 10000);

    let started = false;
    serverProcess.stderr.on('data', (data) => {
      const msg = data.toString();
      if ((msg.includes('started') || msg.includes('Listening'))) {
        started = true;
        clearTimeout(timeout);
        setTimeout(() => resolve(), 500);
      }
    });

    serverProcess.on('error', (e) => {
      clearTimeout(timeout);
      reject(e);
    });

    serverProcess.on('exit', (code) => {
      clearTimeout(timeout);
      if (!started) {
        reject(new Error(`PHP server exited with code ${code} before starting`));
      }
    });
  });
}

function stopServer() {
  if (serverProcess) {
    try { serverProcess.kill('SIGTERM'); } catch(e) {}
    serverProcess = null;
  }
}

async function createBrowser() {
  return await chromium.launch({
    executablePath: CHROME_PATH,
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'],
  });
}

async function navigateTo(page, url) {
  // Use evaluate for navigation to avoid headless Chrome first-navigation bug
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 8000 });
  } catch (e) {
    // Fallback: use evaluate to set location, then wait for selector
    await page.evaluate((u) => { window.location.href = u; }, url).catch(() => {});
    await page.waitForTimeout(2000);
  }
}

async function loginAs(page, username, password) {
  await navigateTo(page, `${BASE_URL}/login.php`);
  await page.waitForSelector('form', { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(300);
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);
}

async function logout(page) {
  await page.goto(`${BASE_URL}/logout.php`);
}

module.exports = {
  BASE_URL, CHROME_PATH, startServer, stopServer, createBrowser, loginAs, logout, navigateTo
};
