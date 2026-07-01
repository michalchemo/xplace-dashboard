# XPlace Proposals Dashboard

PHP + MySQL dashboard for reviewing and managing XPlace proposal drafts before submission.

## Stack
- PHP 8.x
- MySQL 8.x
- No frameworks, no npm, no external dependencies

---

## Setup (DigitalOcean Droplet with LAMP)

### 1. Database

```bash
mysql -u root -p
```
```sql
source /var/www/xplace-dashboard/schema.sql;

CREATE USER 'xplace_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON xplace_dashboard.* TO 'xplace_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Config

```bash
cp config.sample.php config.php
nano config.php          # fill in DB credentials and generate an API key
```

Generate a strong API key:
```bash
openssl rand -hex 32
```

### 3. Web server

Point your Apache/Nginx vhost to `/var/www/xplace-dashboard`.

Apache example:
```apache
<VirtualHost *:80>
    ServerName proposals.yourdomain.com
    DocumentRoot /var/www/xplace-dashboard
    <Directory /var/www/xplace-dashboard>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add basic auth to protect the dashboard (no public access):
```bash
htpasswd -c /etc/apache2/.htpasswd michal
```

Add to vhost or `.htaccess`:
```apache
AuthType Basic
AuthName "Nintay"
AuthUserFile /etc/apache2/.htpasswd
Require valid-user
```

---

## API (for Claude scheduled task)

### Add a proposal

```
POST /api/add_proposal.php
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "project_id":    "213965",
  "project_title": "פרילנסר לבניית אתר אינדקס",
  "project_url":   "https://www.xplace.com/il/job/213965",
  "proposal_text": "...",
  "price":         200,
  "price_type":    "hourly"
}
```

### Get approved proposals (for submission)

```
GET /api/get_approved.php
Authorization: Bearer YOUR_API_KEY
```

### Get dashboard status (change-gate check)

```
GET /api/get_dashboard_status.php
Authorization: Bearer YOUR_API_KEY
```

Returns `{ known_project_ids: [...], pending_count, approved_count }`. The scheduled
task calls this first and only runs a full scan/classify/WhatsApp cycle if there are
project IDs on XPlace not in `known_project_ids`, or if withdrawals/proposal-requests/
approved items are waiting. Keeps repeat runs cheap when nothing changed.

---

## File structure

```
xplace-dashboard/
├── .gitignore
├── README.md
├── schema.sql          — run once to create the DB table
├── config.sample.php   — template (committed)
├── config.php          — real config (gitignored)
├── db.php              — PDO connection
├── index.php           — main dashboard UI
├── action.php          — approve / dismiss / submitted / restore
└── api/
    ├── add_proposal.php  — POST: add draft from scheduled task
    └── get_approved.php  — GET: fetch approved proposals for submission
```

---

## Workflow

1. Claude scheduled task (08:00 / 15:00) scrapes XPlace → drafts proposals → POSTs to `/api/add_proposal.php`
2. Michal opens the dashboard → reviews drafts → edits text / price → clicks **אשר** or **דחה**
3. Michal triggers Claude → Claude fetches approved via `/api/get_approved.php` → submits to XPlace → marks as **submitted**
