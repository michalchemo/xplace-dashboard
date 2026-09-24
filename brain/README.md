# Nintay Brain

Central notify webhook for all Nintay systems. Lives on the existing droplet
(164.90.223.113), port 8001, behind nginx at https://brain.nintay.com.

- Config: /var/www/brain.env (NOT in git). Keys: NOTIFY_TOKEN, SMTP_HOST,
  SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM.
- Deploy: automatic via git push (droplet autodeploy pulls the repo).
- Service: systemd unit brain-notify, uvicorn on 127.0.0.1:8001.
- API:
  - GET  /health            -> {ok, smtp_configured}
  - POST /notify            -> Bearer NOTIFY_TOKEN; body {channel:"email", to, subject, text}
- Later: WhatsApp channel + Audit flow (BRISK-110), same /notify contract.

## Board (24.09.26)

Live agents board at GET / (session login, BOARD_USER/BOARD_PASS/BOARD_SECRET in brain.env).
HTML lives in this repo: `brain/board/index.html` (fallback /opt/brain/board/index.html,
override with env BRAIN_BOARD). Self-contained, polls the API every 30s.

- GET  /api/status          -> agents (last/next run), brisk counts (psql), projects, last jobs. Cached 20s.
- GET  /api/jobs?limit=15   -> job queue rows;  GET /api/jobs/{id} -> one row with full output_tail
- GET  /api/projects        -> active BRISK projects with a Toggl id (manual hours dropdown)
- POST /run/{job}           -> enqueue; optional JSON body = args. Returns {job_id, status:"queued"}
  jobs: hours {day?}, monthend, hours_manual {day,start,end,toggl_project_id,client,description}, refresh

Queue: `jobs.py`, SQLite at /opt/brain/jobs.db (BRAIN_JOBS_DB), one worker thread, one job at a
time, full output in /opt/brain/logs/jobs/<id>-<job>.log (dirs are created automatically).
Manual hours: `scripts/hours_manual.py` appends a row to /opt/brain/hours/session-log.csv and
runs hours_agent.py for that day.

Tests: `cd brain && python -m pytest tests -q` (needs fastapi, httpx, pytest; no server, no psql).
