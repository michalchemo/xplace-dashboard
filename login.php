<?php
session_start();
require_once __DIR__ . '/config.php';

$configured = defined('DASH_USER') && defined('DASH_PASS_HASH')
    && DASH_PASS_HASH !== '' && DASH_PASS_HASH !== 'replace_with_password_hash';

// Already logged in → go to dashboard.
if (!empty($_SESSION['dash_user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($configured && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === DASH_USER && password_verify($p, DASH_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['dash_user'] = $u;
        header('Location: index.php');
        exit;
    }
    $error = 'שם משתמש או סיסמה שגויים';
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>כניסה &ndash; Nintay XPlace</title>
<?php include __DIR__ . '/partials/favicon.php'; ?>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, Arial, sans-serif; background: #f0f2f5; color: #222;
         min-height: 100vh; display: flex; flex-direction: column; }
  .main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; width: 100%; }
  .box { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 28px;
         width: 100%; max-width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
  .box h1 { font-size: 18px; color: #1a1a2e; margin-bottom: 18px; text-align: center; }
  label { display: block; font-size: 13px; color: #555; margin: 12px 0 6px; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; }
  button { width: 100%; margin-top: 18px; background: #1a1a2e; color: #fff; border: 0;
           border-radius: 8px; padding: 11px; font-size: 15px; cursor: pointer; }
  .err { background: #fde8e8; color: #a12121; border-radius: 8px; padding: 10px;
         font-size: 13px; margin-bottom: 6px; text-align: center; }
  .note { background: #fff7e6; color: #8a6d1a; border-radius: 8px; padding: 12px;
          font-size: 13px; line-height: 1.5; }
  code { background: #f0f0f0; padding: 1px 5px; border-radius: 4px; direction: ltr; display: inline-block; }
</style>
</head>
<body>
  <main class="main">
  <div class="box">
    <h1>Nintay &middot; XPlace</h1>
    <?php if (!$configured): ?>
      <div class="note">
        הגדרת הכניסה טרם הושלמה. הוסיפי ל-<code>config.php</code> בשרת:<br>
        <code>define('DASH_USER','michal');</code><br>
        <code>define('DASH_PASS_HASH','...');</code><br>
        וצרי hash עם:<br>
        <code>php -r "echo password_hash('סיסמה', PASSWORD_DEFAULT);"</code>
      </div>
    <?php else: ?>
      <form method="post" autocomplete="off">
        <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <label>שם משתמש</label>
        <input type="text" name="username" required autofocus>
        <label>סיסמה</label>
        <input type="password" name="password" required>
        <button type="submit">כניסה</button>
      </form>
    <?php endif; ?>
  </div>
  </main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
