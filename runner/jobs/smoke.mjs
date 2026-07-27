// Smoke job: config + dashboard API + headless browser. No XPlace login, no writes.
import { config } from '../lib/config.mjs';
import { getDashboardStatus } from '../lib/api.mjs';
import { launch } from '../lib/browser.mjs';

const checks = [];
try {
  config();
  checks.push('CONFIG_OK');
  const st = await getDashboardStatus();
  checks.push(`API_OK pending=${st.pending_count ?? '?'} approved=${st.approved_count ?? '?'}`);
  const { browser, context } = await launch();
  const page = await context.newPage();
  await page.goto('https://example.com');
  checks.push(`BROWSER_OK title="${await page.title()}"`);
  await browser.close();
  console.log(checks.join('\n'));
  console.log('SMOKE_OK');
} catch (e) {
  console.log(checks.join('\n'));
  console.error('SMOKE_FAILED:', e.message);
  process.exit(1);
}
