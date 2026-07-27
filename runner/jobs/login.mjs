// BRISK-68: log in to XPlace from the droplet and save the session state.
// Login page: https://www.xplace.com/signin - two inputs (text=email, password),
// submit button labeled "כניסה", no captcha (probed 27/07). React ids are not
// stable, so we select by input type and by button text.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { config } from '../lib/config.mjs';

const cfg = config();
const USER = cfg.XPLACE_USER, PASS = cfg.XPLACE_PASS;
const STATE = cfg.STORAGE_STATE;
if (!USER || !PASS) { console.error('LOGIN_FAILED: missing XPLACE_USER / XPLACE_PASS'); process.exit(2); }

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();
try {
  await page.goto('https://www.xplace.com/signin', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(2000);

  // captcha guard - never bypass; stop and report.
  const cap = await page.evaluate(() => !!document.querySelector(
    'iframe[src*="recaptcha"], .g-recaptcha, iframe[src*="hcaptcha"], [class*="captcha"]'));
  if (cap) { console.error('LOGIN_BLOCKED: captcha present - manual login needed'); await browser.close(); process.exit(3); }

  await page.fill('input[type="text"]', USER);
  await page.fill('input[type="password"]', PASS);
  await Promise.all([
    page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {}),
    page.getByRole('button', { name: 'כניסה' }).click(),
  ]);
  await page.waitForTimeout(4000);

  // Logged-in check: signin page is left, and no login form remains.
  const url = page.url();
  const stillForm = await page.evaluate(() => !!document.querySelector('input[type="password"]'));
  if (url.includes('/signin') || stillForm) {
    const err = await page.evaluate(() => (document.body.innerText.match(/.{0,80}(שגוי|לא נכון|incorrect|invalid).{0,40}/i) || [''])[0]);
    console.error('LOGIN_FAILED: still on signin. url=' + url + ' hint=' + err.replace(/\s+/g,' ').slice(0,120));
    await browser.close(); process.exit(1);
  }

  mkdirSync(dirname(STATE), { recursive: true });
  await context.storageState({ path: STATE });
  console.log('LOGIN_OK url=' + url + ' state=' + STATE);
  await browser.close();
} catch (e) {
  console.error('LOGIN_ERROR:', e.message);
  await browser.close(); process.exit(1);
}
