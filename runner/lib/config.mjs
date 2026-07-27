import { readFileSync } from 'node:fs';

const ENV_PATH = process.env.RUNNER_ENV || '/var/www/xplace-runner.env';

function parseEnv(text) {
  const out = {};
  for (const line of text.split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const i = t.indexOf('=');
    if (i > 0) out[t.slice(0, i).trim()] = t.slice(i + 1).trim();
  }
  return out;
}

let cfg;
export function config() {
  if (!cfg) {
    let text;
    try { text = readFileSync(ENV_PATH, 'utf8'); }
    catch { throw new Error(`runner env file missing: ${ENV_PATH}`); }
    cfg = parseEnv(text);
    for (const k of ['API_BASE', 'API_KEY']) {
      if (!cfg[k]) throw new Error(`missing ${k} in ${ENV_PATH}`);
    }
  }
  return cfg;
}
