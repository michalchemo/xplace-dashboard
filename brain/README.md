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
