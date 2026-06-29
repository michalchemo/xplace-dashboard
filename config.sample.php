<?php
// Copy this file to config.php and fill in your values.
// config.php is gitignored — never commit real credentials.

define('DB_HOST', 'localhost');
define('DB_NAME', 'xplace_dashboard');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// API key used by the scheduled task to add proposals.
// Generate a strong random string, e.g.: openssl rand -hex 32
define('API_KEY', 'replace_with_strong_random_key');

// Dashboard login (username + password gate for the UI).
// The gate turns on only once DASH_PASS_HASH is a real hash (not the placeholder).
// Generate the hash:  php -r "echo password_hash('your_password', PASSWORD_DEFAULT), PHP_EOL;"
define('DASH_USER', 'michal');
define('DASH_PASS_HASH', 'replace_with_password_hash');
