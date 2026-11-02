// Playwright configuration for the A-to-Z flow test (see docs/08_E2E_Flow_Test.md).
// Uses the Google Chrome already installed on the computer (no browser download)
// and records one video per flow.
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  testMatch: 'flow.spec.js',
  timeout: 180_000,
  workers: 1,                        // the flows build on each other: run in order
  reporter: [['list'], ['json', { outputFile: 'results/results.json' }]],
  outputDir: 'results/raw',
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8080',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1280, height: 800 },
    video: { mode: 'on', size: { width: 1280, height: 800 } },
    actionTimeout: 15_000,
    ignoreHTTPSErrors: true,         // self-signed certificate on https://localhost:8443
    launchOptions: { slowMo: 250 },  // slow enough to follow in the video
  },
});
