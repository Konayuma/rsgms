#!/usr/bin/env node
const { startServer, stopServer } = require('./helpers');
const { spawn } = require('child_process');

const TEST_FILES = [
  'auth.test.js',
  'members.test.js',
  'savings.test.js',
  'loans.test.js',
  'dashboard.test.js',
];

async function runAll() {
  console.log('═══ RSGMS E2E Test Suite ═══\n');

  const port = parseInt(process.env.PORT || '8080', 10);
  console.log('Starting PHP dev server...');
  await startServer(port);
  const baseUrl = `http://localhost:${port}`;
  console.log(`Server running on ${baseUrl}\n`);

  let exitCode = 0;
  for (const file of TEST_FILES) {
    console.log(`═══ ${file} ═══`);
    const code = await new Promise((resolve) => {
      const proc = spawn('node', [file], {
        stdio: 'inherit',
        env: { ...process.env, BASE_URL: baseUrl },
      });
      proc.on('close', resolve);
    });
    if (code !== 0) {
      exitCode = code;
      break;
    }
  }

  if (exitCode === 0) {
    console.log('\n═══════════════════════════════\n  All test suites PASSED\n═══════════════════════════════');
  }

  stopServer();
  process.exit(exitCode);
}

runAll().catch(e => {
  console.error('Fatal error:', e.message);
  stopServer();
  process.exit(1);
});
