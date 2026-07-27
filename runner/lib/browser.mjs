import { chromium } from 'playwright';
import { existsSync } from 'node:fs';
import { config } from './config.mjs';

export async function launch({ withState = false } = {}) {
  const browser = await chromium.launch({ headless: true });
  const statePath = config().STORAGE_STATE || '';
  const context = await browser.newContext(
    withState && statePath && existsSync(statePath) ? { storageState: statePath } : {}
  );
  return { browser, context };
}
