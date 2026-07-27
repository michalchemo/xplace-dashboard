#!/bin/bash
# BRISK-68/69: keep the XPlace session alive. Runs session-check; if the
# session expired, re-logs in automatically. Logs every run with a timestamp.
# On unrecoverable failure, alerts through the Nintay Brain notify webhook
# (BRISK-108) so it never fails silently.
cd /var/www/xplace-dashboard/runner || exit 1
LOG=/var/log/xplace-session.log
ts() { date '+%Y-%m-%d %H:%M:%S'; }

notify() { # $1=subject $2=text
  TOK=$(grep "^NOTIFY_TOKEN=" /var/www/xplace-runner.env | cut -d= -f2)
  TO=$(grep "^ALERT_EMAIL=" /var/www/xplace-runner.env | cut -d= -f2)
  [ -z "$TOK" ] || [ -z "$TO" ] && { echo "$(ts) notify skipped - no token/email" >>"$LOG"; return; }
  curl -s -m 20 -X POST http://127.0.0.1:8001/notify \
    -H "Authorization: Bearer $TOK" -H "Content-Type: application/json" \
    --data "{\"channel\":\"email\",\"to\":\"$TO\",\"subject\":\"$1\",\"text\":\"$2\"}" \
    >>"$LOG" 2>&1
  echo >>"$LOG"
}

if node jobs/session-check.mjs >>"$LOG" 2>&1; then
  echo "$(ts) OK session valid" >>"$LOG"
  exit 0
fi

echo "$(ts) session-check failed - attempting re-login" >>"$LOG"
if node jobs/login.mjs >>"$LOG" 2>&1; then
  chmod 600 /var/www/xplace-runner-state/storageState.json
  echo "$(ts) RECOVERED via re-login" >>"$LOG"
  notify "XPlace Runner: session recovered" "The saved XPlace session expired and was renewed automatically by re-login. No action needed. Log: /var/log/xplace-session.log"
  exit 0
fi

echo "$(ts) ALERT re-login FAILED - manual attention needed" >>"$LOG"
notify "XPlace Runner: ALERT - re-login FAILED" "The XPlace session is dead and automatic re-login failed. The runner cannot work until this is fixed. Check /var/log/xplace-session.log on the droplet and the XPlace credentials in /var/www/xplace-runner.env."
exit 1