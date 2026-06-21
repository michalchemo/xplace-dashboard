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
