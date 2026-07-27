// BRISK-68: verify the saved session is still valid, refresh the state file.
import { chromium } from 'playwright';
import { existsSync } from 'node:fs';
import { config } from '../lib/config.mjs';

const cfg = config();
const STATE = cfg.STORAGE_STATE;
if (!existsSync(STATE)) { console.error('SESSION_MISSING: no state file at ' + STATE); process.exit(2); }

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ storageState: STATE });
const page = await context.newPage();
try {
  await page.goto('https://www.xplace.com/il/rec', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(3000);
  const url = page.url();
  const loggedOut = url.includes('/signin') || await page.evaluate(() => !!document.querySelector('input[type="password"]'));
  if (loggedOut) { console.error('SESSION_EXPIRED url=' + url); await browser.close(); process.exit(1); }
  await context.storageState({ path: STATE });
  console.log('SESSION_OK url=' + url);
  await browser.close();
} catch (e) {
  console.error('SESSION_ERROR:', e.message);
  await browser.close(); process.exit(1);
}
