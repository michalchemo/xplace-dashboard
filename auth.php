<?php
/*
 * auth.php — session login gate for the dashboard UI.
 * Included at the very top of index.php.
 *
 * The gate activates ONLY once DASH_USER + DASH_PASS_HASH are set in config.php,
 * so deploying this code never locks you out before you configure a password.
 * To turn it on, add to config.php on the server:
 *     define('DASH_USER', 'michal');
 *     define('DASH_PASS_HASH', '<output of password_hash()>');
 * Generate the hash:
 *     php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function dash_gate_active(): bool {
    return defined('DASH_USER') && defined('DASH_PASS_HASH')
        && DASH_PASS_HASH !== '' && DASH_PASS_HASH !== 'replace_with_password_hash';
}

if (dash_gate_active() && empty($_SESSION['dash_user'])) {
    header('Location: login.php');
    exit;
}
