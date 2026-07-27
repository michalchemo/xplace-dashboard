# XPlace Runner

Playwright automation that runs on the droplet (no LLM in this layer).

- Config: /var/www/xplace-runner.env (never in git). Keys: API_BASE, API_KEY, STORAGE_STATE, ALERT_EMAIL.
- Deploy: automatic via git push to main (droplet cron pulls every minute).
- Install deps on droplet: cd /var/www/xplace-dashboard/runner && npm install
- Smoke: node jobs/smoke.mjs
- Jobs land per BRISK phase: smoke (67), login/session (68), submit-approved (70), scan (72), messages/withdrawals/outcomes (74).
