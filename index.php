<?php
require_once __DIR__ . '/db.php';

$db     = get_db();
$filter = $_GET['status'] ?? 'pending';
$valid  = ['pending', 'approved', 'to_withdraw', 'submitted', 'all'];
if (!in_array($filter, $valid)) $filter = 'pending';

$where = $filter === 'all' ? '' : "WHERE status = '$filter'";
$rows  = $db->query("SELECT * FROM proposals $where ORDER BY created_at DESC")->fetchAll();

$counts = [];
foreach (['pending','approved','to_withdraw','submitted'] as $s) {
    $counts[$s] = (int)$db->query("SELECT COUNT(*) FROM proposals WHERE status='$s'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nintay â€” XPlace Proposals</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, Arial, sans-serif; background: #f0f2f5; color: #222; height: 100vh; display: flex; flex-direction: column; }

  header {
    background: #1a1a2e; color: #fff; padding: 12px 20px;
    display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
  }
  header h1 { font-size: 16px; font-weight: 600; }

  nav {
    background: #fff; border-bottom: 1px solid #e0e0e0;
    padding: 0 20px; display: flex; gap: 4px; flex-shrink: 0;
  }
  nav a {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 14px; text-decoration: none; color: #555;
    font-size: 13px; border-bottom: 3px solid transparent;
  }
  nav a.active { color: #1a1a2e; border-bottom-color: #1a1a2e; font-weight: 600; }
  nav a .badge {
    background: #e0e0e0; color: #555; border-radius: 10px;
    padding: 1px 6px; font-size: 11px;
  }
  nav a.active .badge { background: #1a1a2e; color: #fff; }

  /* â”€â”€ Main split layout â”€â”€ */
  .workspace {
    display: flex; flex: 1; overflow: hidden;
  }

  /* Left: card list */
  .card-list {
    flex: 0 0 480px; overflow-y: auto; padding: 16px;
    border-left: 1px solid #ddd;
  }

  /* Right: preview panel */
  .preview-panel {
    flex: 1; display: flex; flex-direction: column;
    background: #fff; overflow: hidden;
  }
  .preview-empty {
    flex: 1; display: flex; align-items: center; justify-content: center;
    color: #bbb; font-size: 14px; flex-direction: column; gap: 10px;
  }
  .preview-empty svg { opacity: .3; }
  .preview-header {
    padding: 12px 16px; border-bottom: 1px solid #eee;
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    background: #fafafa; flex-shrink: 0;
  }
  .preview-header h3 { font-size: 14px; font-weight: 600; color: #1a1a2e; flex: 1; }
  .preview-header a { font-size: 12px; color: #666; text-decoration: none; }
  .preview-header a:hover { color: #1a1a2e; }
  .preview-iframe {
    flex: 1; border: none; width: 100%;
  }

  .empty { text-align: center; padding: 60px 0; color: #aaa; font-size: 14px; }

  /* â”€â”€ Cards â”€â”€ */
  .card {
    background: #fff; border-radius: 8px; margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden;
    transition: box-shadow .15s;
  }
  .card.active-preview { box-shadow: 0 0 0 2px #1a1a2e; }

  .card-header {
    padding: 12px 14px; display: flex; align-items: flex-start;
    justify-content: space-between; gap: 10px; border-bottom: 1px solid #f0f0f0;
  }
  .card-title {
    font-size: 13px; font-weight: 600; color: #1a1a2e;
    cursor: pointer; text-decoration: none; display: block; line-height: 1.4;
  }
  .card-title:hover { text-decoration: underline; }
  .card-meta { font-size: 11px; color: #999; margin-top: 3px; }

  .status-badge {
    font-size: 10px; padding: 2px 8px; border-radius: 10px; white-space: nowrap; font-weight: 600;
  }
  .status-pending   { background: #fff3cd; color: #856404; }
  .status-approved  { background: #d1e7dd; color: #0a3622; }
  .status-dismissed { background: #f8d7da; color: #58151c; }
  .status-submitted { background: #d0e4ff; color: #084298; }

  .card-body { padding: 12px 14px; }

  textarea.proposal-text {
    width: 100%; min-height: 100px; border: 1px solid #ddd; border-radius: 6px;
    padding: 8px 10px; font-size: 12px; line-height: 1.6; resize: vertical;
    font-family: inherit; color: #333; direction: rtl;
  }
  textarea.proposal-text:focus { outline: none; border-color: #1a1a2e; }
  textarea.proposal-text.placeholder-text { color: #aaa; font-style: italic; }

  .price-row {
    display: flex; align-items: center; gap: 8px; margin: 8px 0;
    font-size: 12px;
  }
  .price-row input[type=number] {
    width: 70px; border: 1px solid #ddd; border-radius: 6px;
    padding: 4px 6px; font-size: 12px; text-align: center;
  }
  .price-row label { color: #666; }

  textarea.notes-text {
    width: 100%; min-height: 36px; border: 1px solid #eee; border-radius: 6px;
    padding: 5px 8px; font-size: 11px; resize: vertical;
    font-family: inherit; color: #888; margin-top: 6px; direction: rtl;
  }

  .card-footer {
    padding: 10px 14px; display: flex; gap: 6px; flex-wrap: wrap;
    border-top: 1px solid #f0f0f0; background: #fafafa;
  }
  button {
    padding: 6px 13px; border: none; border-radius: 6px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: inherit; transition: opacity .15s;
  }
  button:hover { opacity: .85; }
  button:disabled { opacity: .4; cursor: default; }

  .btn-submit   { background: #198754; color: #fff; }
  .btn-dismiss  { background: #dc3545; color: #fff; }
  .btn-submitted{ background: #0d6efd; color: #fff; }
  .btn-restore  { background: #6c757d; color: #fff; }
  .btn-save     { background: #e9ecef; color: #333; }
  .btn-xplace   { background: #f0f0f0; color: #333; text-decoration: none;
                  display: inline-flex; align-items: center; padding: 6px 12px;
                  border-radius: 6px; font-size: 12px; font-weight: 600; }
  .btn-xplace:hover { background: #e0e0e0; }

  .toast {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: #1a1a2e; color: #fff; padding: 8px 20px;
    border-radius: 20px; font-size: 12px; opacity: 0;
    transition: opacity .3s; pointer-events: none; z-index: 999;
  }
  .toast.show { opacity: 1; }

  @media (max-width: 900px) {
    .preview-panel { display: none; }
    .card-list { flex: 1; }
  }
</style>
</head>
<body>

<header>
  <h1>Nintay Â· XPlace Proposals</h1>
  <span style="font-size:12px;opacity:.5">×ž×ž×©×§ × ×™×”×•×œ ×”×¦×¢×•×ª</span>
</header>

<nav>
  <?php foreach (['pending'=>'×ž×ž×ª×™× ×•×ª','approved'=>'×œ×©×œ×™×—×”','submitted'=>'× ×©×œ×—×•','to_withdraw'=>'× ×“×—×•','all'=>'×”×›×œ'] as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s ? 'active' : '' ?>">
      <?= $label ?>
      <?php if ($s !== 'all'): ?>
        <span class="badge"><?= $counts[$s] ?? 0 ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<div class="workspace">

  <!-- Left: card list -->
  <div class="card-list">
    <?php if (empty($rows)): ?>
      <div class="empty">××™×Ÿ ×”×¦×¢×•×ª ×‘<?= htmlspecialchars($filter) ?></div>
    <?php else: ?>
      <?php foreach ($rows as $row):
        $isPlaceholder = ($row['proposal_text'] === '×ž×ž×ª×™×Ÿ ×œ×¡×§×™×¨×”' || $row['proposal_text'] === '');
      ?>
      <div class="card" id="card-<?= $row['id'] ?>">

        <div class="card-header">
          <div style="flex:1;min-width:0">
            <span class="card-title"
                  onclick="loadPreview(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['project_url'])) ?>', '<?= addslashes(htmlspecialchars($row['project_title'])) ?>')">
              <?= htmlspecialchars($row['project_title']) ?>
            </span>
            <div class="card-meta">
              ×¤×¨×•×™×§×˜ #<?= htmlspecialchars($row['project_id']) ?> Â·
              <?= date('d/m H:i', strtotime($row['created_at'])) ?>
            </div>
          </div>
          <span class="status-badge status-<?= $row['status'] ?>">
            <?= ['pending'=>'×ž×ž×ª×™×Ÿ','approved'=>'×œ×©×œ×™×—×”','to_withdraw'=>'× ×“×—×”','submitted'=>'× ×©×œ×—'][$row['status']] ?>
          </span>
        </div>

        <div class="card-body">
          <textarea class="proposal-text<?= $isPlaceholder ? ' placeholder-text' : '' ?>"
                    data-id="<?= $row['id'] ?>"
                    onfocus="clearPlaceholder(this)"><?= htmlspecialchars($row['proposal_text']) ?></textarea>

          <div class="price-row">
            <label>×ž×—×™×¨:</label>
            <input type="number" class="price-input" data-id="<?= $row['id'] ?>"
                   value="<?= (int)$row['price'] ?>" min="50" max="5000" step="10">
            <span>â‚ª / ×©×¢×”</span>
          </div>

          <textarea class="notes-text" data-id="<?= $row['id'] ?>"
                    placeholder="×”×¢×¨×•×ª ×¤× ×™×ž×™×•×ª..."><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
        </div>

        <div class="card-footer">
          <a class="btn-xplace" href="<?= htmlspecialchars($row['project_url']) ?>" target="_blank">XPlace â†—</a>

          <?php if ($row['status'] === 'pending'): ?>
            <button class="btn-submit"  onclick="doAction(<?= $row['id'] ?>, 'approve')">â†‘ ×”×’×©</button>
            <button class="btn-dismiss" onclick="doAction(<?= $row['id'] ?>, 'dismiss')">âœ• ×“×—×”</button>
            <button class="btn-save"    onclick="doAction(<?= $row['id'] ?>, 'save')">×©×ž×•×¨</button>

          <?php elseif ($row['status'] === 'approved'): ?>
            <button class="btn-submitted" onclick="doAction(<?= $row['id'] ?>, 'submitted')">âœ“ ×¡×ž×Ÿ ×›× ×©×œ×—</button>
            <button class="btn-dismiss"   onclick="doAction(<?= $row['id'] ?>, 'dismiss')">âœ• ×“×—×”</button>
            <button class="btn-save"      onclick="doAction(<?= $row['id'] ?>, 'save')">×©×ž×•×¨</button>

          <?php elseif (in_array($row['status'], ['to_withdraw','submitted'])): ?>
            <button class="btn-restore" onclick="doAction(<?= $row['id'] ?>, 'restore')">â†© ×”×—×–×¨ ×œ×ž×ž×ª×™× ×•×ª</button>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Right: preview panel -->
  <div class="preview-panel" id="previewPanel">
    <div class="preview-empty" id="previewEmpty">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
        <line x1="9" y1="21" x2="9" y2="9"/>
      </svg>
      ×œ×—×¥ ×¢×œ ×›×•×ª×¨×ª ×¤×¨×•×™×§×˜ ×œ×¦×¤×™×™×” ×‘×ž×•×“×¢×”
    </div>
    <div id="previewContent" style="display:none;flex:1;flex-direction:column;overflow:hidden">
      <div class="preview-header">
        <h3 id="previewTitle"></h3>
        <a id="previewLink" href="#" target="_blank">×¤×ª×— ×‘×˜××‘ â†—</a>
      </div>
      <iframe class="preview-iframe" id="previewIframe" src="about:blank"
              sandbox="allow-scripts allow-same-origin allow-forms"></iframe>
    </div>
  </div>

</div>

<div class="toast" id="toast"></div>

<script>
function showToast(msg, color) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = color || '#1a1a2e';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2200);
}

function clearPlaceholder(el) {
  if (el.classList.contains('placeholder-text')) {
    el.value = '';
    el.classList.remove('placeholder-text');
  }
}

function loadPreview(id, url, title) {
  // Highlight active card
  document.querySelectorAll('.card').forEach(c => c.classList.remove('active-preview'));
  const card = document.getElementById('card-' + id);
  if (card) card.classList.add('active-preview');

  // Show preview
  document.getElementById('previewEmpty').style.display   = 'none';
  const content = document.getElementById('previewContent');
  content.style.display = 'flex';
  document.getElementById('previewTitle').textContent = title;
  const link = document.getElementById('previewLink');
  link.href = url;
  document.getElementById('previewIframe').src = url;
}

function doAction(id, action) {
  const text  = document.querySelector(`.proposal-text[data-id="${id}"]`)?.value ?? '';
  const price = document.querySelector(`.price-input[data-id="${id}"]`)?.value ?? 200;
  const notes = document.querySelector(`.notes-text[data-id="${id}"]`)?.value ?? '';

  const body = new URLSearchParams({ action, id, proposal_text: text, price, notes });

  fetch('action.php', { method: 'POST', body })
    .then(async r => {
      const text = await r.text();
      try { return JSON.parse(text); }
      catch { throw new Error(text.substring(0, 150)); }
    })
    .then(data => {
      if (data.ok) {
        const msgs = {
          approve:   'â†‘ ×”×•×¢×‘×¨ ×œ×ª×•×¨ ×”×©×œ×™×—×”',
          dismiss:   'âœ• × ×“×—×” â€” ×™×•×¡×¨ ×ž-XPlace ×‘×¨×™×¦×” ×”×‘××”',
          submitted: 'âœ“ ×¡×•×ž×Ÿ ×›× ×©×œ×—',
          restore:   'â†© ×”×•×—×–×¨ ×œ×ž×ž×ª×™× ×•×ª',
          save:      'âœ“ × ×©×ž×¨',
        };
        showToast(msgs[action] ?? '×¢×•×“×›×Ÿ');
        if (action !== 'save') {
          const card = document.getElementById('card-' + id);
          if (card) {
            card.style.transition = 'opacity .4s';
            card.style.opacity    = '0';
            setTimeout(() => card.remove(), 450);
          }
        }
      } else {
        alert('×©×’×™××”: ' + (data.error ?? 'unknown'));
      }
    })
    .catch(err => alert('×©×’×™××ª ×¨×©×ª:\n' + err.message));
}
</script>
</body>
</html>


