#!/bin/bash
# Auto-deploy for xplace-dashboard — runs on the droplet from cron every minute.
# Pulls origin/main when there's a new commit and re-runs DB migrations (idempotent).
# Install (once):
#   cp /var/www/xplace-dashboard/deploy/autodeploy.sh /usr/local/bin/xplace-autodeploy.sh
#   chmod +x /usr/local/bin/xplace-autodeploy.sh
#   (crontab -l; echo "* * * * * /usr/local/bin/xplace-autodeploy.sh >> /var/log/xplace-autodeploy.log 2>&1") | crontab -
# config.php is gitignored and never touched. reset --hard only affects tracked files.

REPO=/var/www/xplace-dashboard
cd "$REPO" || exit 1

git fetch origin main --quiet || exit 1
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)

if [ "$LOCAL" != "$REMOTE" ]; then
    echo "$(date '+%F %T') deploying $(git log -1 --oneline origin/main)"
    git reset --hard origin/main --quiet

    # keep this installed copy in sync with the repo copy
    if ! cmp -s "$REPO/deploy/autodeploy.sh" /usr/local/bin/xplace-autodeploy.sh; then
        cp "$REPO/deploy/autodeploy.sh" /usr/local/bin/xplace-autodeploy.sh
        chmod +x /usr/local/bin/xplace-autodeploy.sh
        echo "$(date '+%F %T') autodeploy script updated"
    fi

    # run idempotent DB migrations (API key read from config.php on this server)
    KEY=$(php -r "require '$REPO/config.php'; echo API_KEY;" 2>/dev/null)
    if [ -n "$KEY" ]; then
        curl -s -m 20 -H "Authorization: Bearer $KEY" https://xplace.nintay.com/api/migrate.php > /dev/null
        echo "$(date '+%F %T') migrations run"
    fi
fi
