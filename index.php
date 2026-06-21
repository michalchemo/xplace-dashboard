<?php
require_once __DIR__ . '/db.php';

$db     = get_db();
$filter = $_GET['status'] ?? 'pending';
$valid  = ['pending', 'approved', 'dismissed', 'submitted', 'all'];
if (!in_array($filter, $valid)) $filter = 'pending';

$where = $filter === 'all' ? '' : "WHERE status = '$filter'";
$rows  = $db->query("SELECT * FROM proposals $where ORDER BY created_at DESC")->fetchAll();

$counts = [];
foreach (['pending','approved','dismissed','submitted'] as $s) {
    $counts[$s] = (int)$db->query("SELECT COUNT(*) FROM proposals WHERE status='$s'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nintay — XPlace Proposals</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, Arial, sans-serif; background: #f5f5f5; color: #222; }

  header {
    background: #1a1a2e; color: #fff; padding: 16px 24px;
    display: flex; align-items: center; justify-content: space-between;
  }
  header h1 { font-size: 18px; font-weight: 600; }
  header span { font-size: 13px; opacity: .6; }

  nav {
    background: #fff; border-bottom: 1px solid #e0e0e0;
    padding: 0 24px; display: flex; gap: 4px;
  }
  nav a {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 12px 16px; text-decoration: none; color: #555;
    font-size: 14px; border-bottom: 3px solid transparent;
  }
  nav a.active { color: #1a1a2e; border-bottom-color: #1a1a2e; font-weight: 600; }
  nav a .badge {
    background: #e0e0e0; color: #555; border-radius: 10px;
    padding: 1px 7px; font-size: 11px;
  }
  nav a.active .badge { background: #1a1a2e; color: #fff; }

  .container { max-width: 860px; margin: 0 auto; padding: 24px; }
  .empty { text-align: center; padding: 60px 0; color: #aaa; font-size: 15px; }

  .card {
    background: #fff; border-radius: 8px; margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
    overflow: hidden;
  }
  .card-header {
    padding: 14px 18px; display: flex; align-items: flex-start;
    justify-content: space-between; gap: 12px; border-bottom: 1px solid #f0f0f0;
  }
  .card-header h2 { font-size: 15px; font-weight: 600; }
  .card-header h2 a { color: #1a1a2e; text-decoration: none; }
  .card-header h2 a:hover { text-decoration: underline; }
  .card-header .meta { font-size: 12px; color: #999; margin-top: 3px; }

  .status-badge {
    font-size: 11px; padding: 3px 10px; border-radius: 12px; white-space: nowrap;
    font-weight: 600;
  }
  .status-pending   { background: #fff3cd; color: #856404; }
  .status-approved  { background: #d1e7dd; color: #0a3622; }
  .status-dismissed { background: #f8d7da; color: #58151c; }
  .status-submitted { background: #d0e4ff; color: #084298; }

  .card-body { padding: 16px 18px; }

  textarea.proposal-text {
    width: 100%; min-height: 130px; border: 1px solid #ddd; border-radius: 6px;
    padding: 10px 12px; font-size: 13px; line-height: 1.6; resize: vertical;
    font-family: inherit; color: #333;
    direction: rtl;
  }
  textarea.proposal-text:focus { outline: none; border-color: #1a1a2e; }

  .price-row {
    display: flex; align-items: center; gap: 10px; margin: 10px 0;
    font-size: 13px;
  }
  .price-row input[type=number] {
    width: 80px; border: 1px solid #ddd; border-radius: 6px;
    padding: 5px 8px; font-size: 13px; text-align: center;
  }
  .price-row label { color: #666; }

  textarea.notes-text {
    width: 100%; min-height: 40px; border: 1px solid #eee; border-radius: 6px;
    padding: 6px 10px; font-size: 12px; resize: vertical;
    font-family: inherit; color: #888; margin-top: 8px;
    direction: rtl;
  }
  textarea.notes-text:focus { outline: none; border-color: #bbb; }

  .card-footer {
    padding: 12px 18px; display: flex; gap: 8px;
    border-top: 1px solid #f0f0f0; background: #fafafa;
  }
  button {
    padding: 7px 16px; border: none; border-radius: 6px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: opacity .15s;
  }
  button:hover { opacity: .85; }
  button:disabled { opacity: .4; cursor: default; }

  .btn-approve  { background: #198754; color: #fff; }
  .btn-dismiss  { background: #dc3545; color: #fff; }
  .btn-submitted{ background: #0d6efd; color: #fff; }
  .btn-restore  { background: #6c757d; color: #fff; }
  .btn-xplace   { background: #f0f0f0; color: #333; text-decoration: none;
                  display: inline-flex; align-items: center; padding: 7px 14px;
                  border-radius: 6px; font-size: 13px; font-weight: 600; }
  .btn-xplace:hover { background: #e0e0e0; }

  .toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    background: #1a1a2e; color: #fff; padding: 10px 22px;
    border-radius: 20px; font-size: 13px; opacity: 0;
    transition: opacity .3s; pointer-events: none; z-index: 999;
  }
  .toast.show { opacity: 1; }
</style>
</head>
<body>

<header>
  <h1>Nintay · XPlace Proposals</h1>
  <span>ממשק ניהול הצעות</span>
</header>

<nav>
  <?php foreach (['pending'=>'ממתינות','approved'=>'מאושרות','submitted'=>'נשלחו','dismissed'=>'נדחו','all'=>'הכל'] as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s ? 'active' : '' ?>">
      <?= $label ?>
      <?php if ($s !== 'all'): ?>
        <span class="badge"><?= $counts[$s] ?? 0 ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<div class="container">
<?php if (empty($rows)): ?>
  <div class="empty">אין הצעות ב<?= htmlspecialchars($filter) ?></div>
<?php else: ?>
  <?php foreach ($rows as $row): ?>
  <div class="card" id="card-<?= $row['id'] ?>">

    <div class="card-header">
      <div>
        <h2>
          <a href="<?= htmlspecialchars($row['project_url']) ?>" target="_blank">
            <?= htmlspecialchars($row['project_title']) ?>
          </a>
        </h2>
        <div class="meta">
          פרויקט #<?= htmlspecialchars($row['project_id']) ?> ·
          <?= date('d/m H:i', strtotime($row['created_at'])) ?>
        </div>
      </div>
      <span class="status-badge status-<?= $row['status'] ?>">
        <?= ['pending'=>'ממתין','approved'=>'מאושר','dismissed'=>'נדחה','submitted'=>'נשלח'][$row['status']] ?>
      </span>
    </div>

    <div class="card-body">
      <textarea class="proposal-text" data-id="<?= $row['id'] ?>"><?= htmlspecialchars($row['proposal_text']) ?></textarea>

      <div class="price-row">
        <label>מחיר:</label>
        <input type="number" class="price-input" data-id="<?= $row['id'] ?>"
               value="<?= (int)$row['price'] ?>" min="50" max="5000" step="10">
        <span>₪ / שעה</span>
      </div>

      <textarea class="notes-text" data-id="<?= $row['id'] ?>"
                placeholder="הערות פנימיות..."><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
    </div>

    <div class="card-footer">
      <a class="btn-xplace" href="<?= htmlspecialchars($row['project_url']) ?>" target="_blank">XPlace ↗</a>

      <?php if ($row['status'] === 'pending'): ?>
        <button class="btn-approve"  onclick="doAction(<?= $row['id'] ?>, 'approve')">✓ אשר</button>
        <button class="btn-dismiss"  onclick="doAction(<?= $row['id'] ?>, 'dismiss')">✕ דחה</button>

      <?php elseif ($row['status'] === 'approved'): ?>
        <button class="btn-submitted" onclick="doAction(<?= $row['id'] ?>, 'submitted')">סמן כנשלח</button>
        <button class="btn-dismiss"   onclick="doAction(<?= $row['id'] ?>, 'dismiss')">✕ דחה</button>

      <?php elseif (in_array($row['status'], ['dismissed','submitted'])): ?>
        <button class="btn-restore" onclick="doAction(<?= $row['id'] ?>, 'restore')">החזר לממתינות</button>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script>
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2200);
}

function doAction(id, action) {
  const text  = document.querySelector(`.proposal-text[data-id="${id}"]`)?.value ?? '';
  const price = document.querySelector(`.price-input[data-id="${id}"]`)?.value ?? 200;
  const notes = document.querySelector(`.notes-text[data-id="${id}"]`)?.value ?? '';

  const body  = new URLSearchParams({ action, id, proposal_text: text, price, notes });

  fetch('action.php', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        const msgs = {
          approve:   '✓ הצעה אושרה',
          dismiss:   '✕ הצעה נדחתה',
          submitted: '✓ סומן כנשלח',
          restore:   '↩ הוחזר לממתינות',
        };
        showToast(msgs[action] ?? 'עודכן');

        // Fade out and remove the card after a short delay
        const card = document.getElementById('card-' + id);
        if (card) {
          card.style.transition = 'opacity .4s';
          card.style.opacity    = '0';
          setTimeout(() => card.remove(), 450);
        }
      } else {
        alert('שגיאה: ' + (data.error ?? 'unknown'));
      }
    })
    .catch(() => alert('שגיאת רשת'));
}
</script>
</body>
</html>
