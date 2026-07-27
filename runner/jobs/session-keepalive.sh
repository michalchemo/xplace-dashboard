#!/bin/bash
# BRISK-68: keep the XPlace session alive. Runs session-check; if the session
# expired, re-logs in automatically. Logs every run with a timestamp.
# Alerts (Brain webhook, BRISK-108/109) are wired in later - until then this
# log is the source of truth and a failed re-login stays visible here.
cd /var/www/xplace-dashboard/runner || exit 1
LOG=/var/log/xplace-session.log
ts() { date '+%Y-%m-%d %H:%M:%S'; }

if node jobs/session-check.mjs >>"$LOG" 2>&1; then
  echo "$(ts) OK session valid" >>"$LOG"
  exit 0
fi

echo "$(ts) session-check failed - attempting re-login" >>"$LOG"
if node jobs/login.mjs >>"$LOG" 2>&1; then
  chmod 600 /var/www/xplace-runner-state/storageState.json
  echo "$(ts) RECOVERED via re-login" >>"$LOG"
  exit 0
fi

echo "$(ts) ALERT re-login FAILED - manual attention needed" >>"$LOG"
# TODO(BRISK-109): POST to brain.nintay.com/notify here.
exit 1
