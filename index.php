<?php
require_once __DIR__ . '/auth.php';   // login gate (active once a password is set in config.php)
header('Content-Type: text/html; charset=UTF-8');
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
<title>Nintay &ndash; XPlace Proposals</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, Arial, sans-serif; background: #f0f2f5; color: #222; height: 100vh; display: flex; flex-direction: column; }

  header { background: #1a1a2e; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
  header h1 { font-size: 16px; font-weight: 600; }

  nav { background: #fff; border-bottom: 1px solid #e0e0e0; padding: 0 20px; display: flex; gap: 4px; flex-shrink: 0; }
  nav a { display: inline-flex; align-items: center; gap: 6px; padding: 10px 14px; text-decoration: none; color: #555; font-size: 13px; border-bottom: 3px solid transparent; }
  nav a.active { color: #1a1a2e; border-bottom-color: #1a1a2e; font-weight: 600; }
  nav a .badge { background: #e0e0e0; color: #555; border-radius: 10px; padding: 1px 6px; font-size: 11px; }
  nav a.active .badge { background: #1a1a2e; color: #fff; }

  .workspace { display: flex; flex: 1; overflow: hidden; }

  /* Card list - right side (RTL) */
  .card-list { flex: 0 0 460px; overflow-y: auto; padding: 16px; border-left: 1px solid #ddd; }

  /* Detail panel - left side */
  .detail-panel { flex: 1; display: flex; flex-direction: column; background: #fff; overflow: hidden; }
  .detail-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: 14px; flex-direction: column; gap: 10px; }
  .detail-empty svg { opacity: .3; }

  .detail-header { padding: 14px 18px; border-bottom: 1px solid #eee; background: #fafafa; flex-shrink: 0; }
  .detail-header h2 { font-size: 15px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; line-height: 1.4; }
  .detail-header a { font-size: 12px; color: #666; text-decoration: none; }
  .detail-header a:hover { color: #1a1a2e; text-decoration: underline; }

  /* Job ad content area */
  .job-content-box { background: #f8f9fa; border: 1px solid #e8e8e8; border-radius: 6px; padding: 12px 14px; font-size: 12px; color: #444; line-height: 1.7; direction: rtl; white-space: pre-wrap; max-height: 220px; overflow-y: auto; }
  .job-content-loading { color: #aaa; font-size: 12px; font-style: italic; padding: 8px 0; }

  .detail-body { flex: 1; padding: 18px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
  .detail-label { font-size: 11px; color: #888; font-weight: 600; margin-bottom: 4px; }
  .detail-textarea { width: 100%; border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; font-size: 13px; line-height: 1.7; resize: vertical; font-family: inherit; direction: rtl; min-height: 160px; }
  .detail-textarea:focus { outline: none; border-color: #1a1a2e; }
  .detail-textarea.placeholder-text { color: #aaa; font-style: italic; }

  /* Dismiss reason modal */
  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 900; display: none; align-items: center; justify-content: center; }
  .modal-overlay.open { display: flex; }
  .modal { background: #fff; border-radius: 10px; padding: 24px; width: 380px; max-width: 95vw; box-shadow: 0 8px 32px rgba(0,0,0,.18); direction: rtl; }
  .modal h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; margin-bottom: 16px; }
  .reason-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
  .reason-pill { padding: 6px 14px; border: 2px solid #ddd; border-radius: 20px; font-size: 12px; cursor: pointer; background: #fff; font-family: inherit; transition: all .15s; }
  .reason-pill.selected { border-color: #dc3545; background: #dc3545; color: #fff; }
  .modal-other { width: 100%; border: 1px solid #ddd; border-radius: 6px; padding: 8px 10px; font-size: 12px; font-family: inherit; direction: rtl; margin-bottom: 16px; }
  .modal-other:focus { outline: none; border-color: #1a1a2e; }
  .modal-actions { display: flex; gap: 8px; justify-content: flex-start; }

  .price-row { display: flex; align-items: center; gap: 8px; font-size: 13px; }
  .price-row input[type=number] { width: 80px; border: 1px solid #ddd; border-radius: 6px; padding: 5px 8px; font-size: 13px; text-align: center; }
  .price-row label { color: #666; }

  .detail-notes { width: 100%; border: 1px solid #eee; border-radius: 6px; padding: 8px 10px; font-size: 12px; resize: vertical; font-family: inherit; color: #888; direction: rtl; min-height: 50px; }

  .detail-footer { padding: 12px 18px; border-top: 1px solid #eee; background: #fafafa; display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }

  .empty { text-align: center; padding: 60px 0; color: #aaa; font-size: 14px; }

  /* Cards */
  .card { background: #fff; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; transition: box-shadow .15s; }
  .card.active-preview { box-shadow: 0 0 0 2px #1a1a2e; }
  .card-header { padding: 10px 12px; display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; cursor: pointer; }
  .card-header:hover { background: #f8f9fa; }
  .card-title { font-size: 13px; font-weight: 600; color: #1a1a2e; line-height: 1.4; }
  .card-meta { font-size: 11px; color: #999; margin-top: 2px; }
  .status-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; white-space: nowrap; font-weight: 600; flex-shrink: 0; }
  .status-pending   { background: #fff3cd; color: #856404; }
  .status-approved  { background: #d1e7dd; color: #0a3622; }
  .status-to_withdraw { background: #f8d7da; color: #58151c; }
  .status-submitted { background: #d0e4ff; color: #084298; }

  .card-footer { padding: 8px 12px; display: flex; gap: 5px; flex-wrap: wrap; border-top: 1px solid #f0f0f0; background: #fafafa; }

  button { padding: 5px 11px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: opacity .15s; }
  button:hover { opacity: .85; }
  button:disabled { opacity: .4; cursor: default; }
  .btn-submit   { background: #198754; color: #fff; }
  .btn-dismiss  { background: #dc3545; color: #fff; }
  .btn-submitted{ background: #0d6efd; color: #fff; }
  .btn-restore  { background: #6c757d; color: #fff; }
  .btn-save     { background: #e9ecef; color: #333; }
  .btn-xplace   { background: #f0f0f0; color: #333; text-decoration: none; display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
  .btn-xplace:hover { background: #e0e0e0; }

  .btn-request  { background: #6f42c1; color: #fff; }
  .btn-waiting  { background: #e9ecef; color: #888; font-style: italic; }
  .btn-confirm-reject { background: #b45309; color: #fff; }
  .btn-newcontent { background: #6f42c1; color: #fff; }

  .card-agent-note { font-size: 11px; color: #7a5800; background: #fffbe6; border-top: 1px solid #ffe066; padding: 5px 12px 6px; line-height: 1.5; direction: rtl; }
  .card-agent-note strong { color: #b45309; }

  .agent-notes-box { background: #fffbe6; border: 1px solid #ffe066; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #5a4a00; line-height: 1.7; direction: rtl; white-space: pre-wrap; }

  .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #1a1a2e; color: #fff; padding: 8px 20px; border-radius: 20px; font-size: 12px; opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 999; }
  .toast.show { opacity: 1; }

  /* ---- Mobile / small screens: stack the two columns instead of hiding detail ---- */
  @media (max-width: 860px) {
    html, body { height: auto; min-height: 100vh; }
    body { overflow-y: auto; }

    header { padding: 10px 14px; }
    header h1 { font-size: 15px; }

    /* status tabs scroll sideways instead of overflowing */
    nav { padding: 0 10px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    nav::-webkit-scrollbar { height: 0; }
    nav a { flex: 0 0 auto; padding: 10px 10px; white-space: nowrap; font-size: 13px; }

    /* stack: cards on top, detail panel below */
    .workspace { flex-direction: column; overflow: visible; }
    .card-list { order: 1; flex: none; width: 100%; padding: 12px; overflow-y: visible; }
    .detail-panel { order: 2; flex: none; width: 100%; overflow: visible; border-top: 1px solid #e0e0e0; }
    .detail-empty { padding: 24px; text-align: center; }
    .card { width: 100%; }

    /* modal fits the screen */
    .modal { width: 95vw; max-width: 95vw; padding: 18px; }
    .reason-pills { gap: 6px; }
  }
  @media (max-width: 480px) {
    header h1 { font-size: 14px; }
    nav a { font-size: 12px; padding: 9px 8px; }
  }
</style>
</head>
<body>

<header>
  <h1>Nintay &middot; XPlace Proposals</h1>
  <span style="display:flex;align-items:center;gap:14px">
    <span style="font-size:12px;opacity:.5">ממשק ניהול הצעות</span>
    <?php if (!empty($_SESSION['dash_user'])): ?>
      <a href="logout.php" style="color:#fff;font-size:12px;opacity:.7;text-decoration:none">יציאה</a>
    <?php endif; ?>
  </span>
</header>

<nav>
  <?php foreach (['pending'=>'ממתינות','approved'=>'לשליחה','submitted'=>'נשלחו','to_withdraw'=>'נדחו','all'=>'הכל'] as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter===$s ? 'active' : '' ?>">
      <?= $label ?>
      <?php if ($s !== 'all'): ?>
        <span class="badge"><?= $counts[$s] ?? 0 ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<div class="workspace">

  <!-- Left: detail/edit panel -->
  <div class="detail-panel" id="detailPanel">
    <div class="detail-empty" id="detailEmpty">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
        <line x1="9" y1="21" x2="9" y2="9"/>
      </svg>
      לחץ על כותרת פרויקט לעריכה
    </div>
    <div id="detailContent" style="display:none;flex:1;flex-direction:column;overflow:hidden">
      <div class="detail-header">
        <h2 id="detailTitle"></h2>
        <a id="detailLink" href="#" target="_blank">פתח ב-XPlace &#8599;</a>
      </div>
      <div class="detail-body">
        <div>
          <div class="detail-label">תוכן המודעה</div>
          <div id="jobContentBox" class="job-content-loading">טוען...</div>
        </div>
        <div id="agentNotesSection" style="display:none">
          <div class="detail-label">&#129302; הסוכן דחה — סיבה</div>
          <div id="agentNotesBox" class="agent-notes-box"></div>
        </div>
        <div>
          <div class="detail-label">הצעה</div>
          <textarea class="detail-textarea" id="detailText" onfocus="clearDetailPlaceholder()"></textarea>
        </div>
        <div>
          <div class="detail-label">מחיר</div>
          <div class="price-row">
            <input type="number" id="detailPrice" min="50" max="5000" step="10" value="200">
            <label>&#8362; / שעה</label>
          </div>
        </div>
        <div>
          <div class="detail-label">הערות פנימיות / הנחיה לסוכן</div>
          <textarea class="detail-notes" id="detailNotes" placeholder="הערות פנימיות. אם תלחצי 'בקש תוכן חדש', הטקסט כאן יישלח לסוכן כהנחיה לניסוח מחדש"></textarea>
        </div>
      </div>
      <div class="detail-footer" id="detailFooter"></div>
    </div>
  </div>

  <!-- Right: card list -->
  <div class="card-list">
    <?php if (empty($rows)): ?>
      <div class="empty">אין הצעות ב-<?= htmlspecialchars($filter) ?></div>
    <?php else: ?>
      <?php foreach ($rows as $row):
        $isPlaceholder = ($row['proposal_text'] === 'ממתין לסקירה' || $row['proposal_text'] === '');
      ?>
      <div class="card" id="card-<?= $row['id'] ?>">

        <div class="card-header"
             onclick="loadDetail(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['project_url']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['project_title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['proposal_text']), ENT_QUOTES) ?>, <?= (int)$row['price'] ?>, <?= htmlspecialchars(json_encode($row['notes'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['status']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['project_description'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['agent_notes'] ?? ''), ENT_QUOTES) ?>, <?= (int)($row['proposal_requested'] ?? 0) ?>)">
          <div style="flex:1;min-width:0">
            <div class="card-title"><?= htmlspecialchars($row['project_title']) ?></div>
            <div class="card-meta">פרויקט #<?= htmlspecialchars($row['project_id']) ?> &middot; <?= date('d/m H:i', strtotime($row['created_at'])) ?></div>
          </div>
          <span class="status-badge status-<?= $row['status'] ?>">
            <?= ['pending'=>'ממתין','approved'=>'לשליחה','to_withdraw'=>'נדחה','submitted'=>'נשלח'][$row['status']] ?? $row['status'] ?>
          </span>
        </div>

        <?php if (!empty($row['agent_notes'])): ?>
          <div class="card-agent-note"><strong>🤖 סוכן:</strong> <?= htmlspecialchars(mb_substr($row['agent_notes'], 0, 80, 'UTF-8')) ?><?= mb_strlen($row['agent_notes'], 'UTF-8') > 80 ? '…' : '' ?></div>
        <?php endif; ?>

        <div class="card-footer">
          <a class="btn-xplace" href="<?= htmlspecialchars($row['project_url']) ?>" target="_blank">XPlace &#8599;</a>

          <?php if ($row['status'] === 'pending'): ?>
            <?php if (!$isPlaceholder): ?>
            <button class="btn-submit"  onclick="doAction(<?= $row['id'] ?>, 'approve')">&#8593; הגש</button>
            <?php endif; ?>
            <button class="btn-dismiss" onclick="openDismissModal(<?= $row['id'] ?>)">&#10005; דחה</button>
            <?php if (!empty($row['agent_notes'])): ?>
              <button class="btn-confirm-reject" onclick="dismissAgent(<?= $row['id'] ?>, <?= htmlspecialchars(json_encode($row['agent_notes']), ENT_QUOTES) ?>)" title="דחה בלחיצה אחת, סיבת הסוכן נשמרת">&#10003; אשר דחייה</button>
            <?php endif; ?>
            <?php if ($isPlaceholder): ?>
              <?php if ($row['proposal_requested'] ?? 0): ?>
                <button class="btn-waiting" disabled>&#9203; ממתין לסוכן</button>
              <?php else: ?>
                <button class="btn-request" onclick="requestProposal(<?= $row['id'] ?>)">&#10022; בקשי הצעה</button>
              <?php endif; ?>
            <?php endif; ?>

          <?php elseif ($row['status'] === 'approved'): ?>
            <button class="btn-submitted" onclick="doAction(<?= $row['id'] ?>, 'submitted')">&#10003; סמן כנשלח</button>
            <button class="btn-dismiss"   onclick="openDismissModal(<?= $row['id'] ?>)">&#10005; דחה</button>

          <?php elseif (in_array($row['status'], ['to_withdraw','submitted'])): ?>
            <button class="btn-restore" onclick="doAction(<?= $row['id'] ?>, 'restore')">&#8617; החזר לממתינות</button>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<div class="toast" id="toast"></div>

<!-- Dismiss reason modal -->
<div class="modal-overlay" id="dismissModal">
  <div class="modal">
    <h3>סיבת דחייה</h3>
    <div class="reason-pills">
      <button class="reason-pill" onclick="togglePill(this)">לא רלוונטי לתחום</button>
      <button class="reason-pill" onclick="togglePill(this)">תחרות גבוהה</button>
      <button class="reason-pill" onclick="togglePill(this)">תקציב נמוך</button>
      <button class="reason-pill" onclick="togglePill(this)">לקוח לא ברור</button>
      <button class="reason-pill" onclick="togglePill(this)">אין זמן כרגע</button>
      <button class="reason-pill" onclick="togglePill(this)">כבר הוגש</button>
    </div>
    <input class="modal-other" id="dismissOther" type="text" placeholder="סיבה אחרת...">
    <div class="modal-actions">
      <button class="btn-dismiss" id="dismissConfirmBtn" onclick="confirmDismiss()">דחה</button>
      <button class="btn-restore" onclick="dismissNoReason()">דחה ללא סיבה</button>
      <button class="btn-save" onclick="closeDismissModal()">ביטול</button>
    </div>
  </div>
</div>

<script>
let currentId = null;
let dismissPendingId = null;
const CURRENT_FILTER = <?= json_encode($filter) ?>;

// ── Job content fetch ──────────────────────────────────────────
function loadJobContent(id, url) {
  const box = document.getElementById('jobContentBox');
  box.className = 'job-content-loading';
  box.textContent = 'טוען תוכן מודעה...';
  fetch(`/api/get_job_content.php?id=${id}&url=${encodeURIComponent(url)}`)
    .then(r => r.json())
    .then(data => {
      if (data.ok && data.description) {
        box.className = 'job-content-box';
        box.textContent = data.description;
      } else {
        box.className = 'job-content-loading';
        box.textContent = 'לא ניתן לטעון תוכן — פתח ב-XPlace ישירות';
      }
    })
    .catch(() => {
      box.className = 'job-content-loading';
      box.textContent = 'שגיאה בטעינת תוכן';
    });
}

// ── Dismiss modal ──────────────────────────────────────────────
function togglePill(el) {
  el.classList.toggle('selected');
}

function openDismissModal(id) {
  dismissPendingId = id;
  document.querySelectorAll('.reason-pill').forEach(p => p.classList.remove('selected'));
  document.getElementById('dismissOther').value = '';
  document.getElementById('dismissModal').classList.add('open');
}

function closeDismissModal() {
  document.getElementById('dismissModal').classList.remove('open');
  dismissPendingId = null;
}

function confirmDismiss() {
  const id = dismissPendingId; // save BEFORE closeDismissModal nulls it
  const selected = [...document.querySelectorAll('.reason-pill.selected')].map(p => p.textContent);
  const other    = document.getElementById('dismissOther').value.trim();
  if (other) selected.push(other);
  const reason = selected.join(', ');
  closeDismissModal();
  doAction(id, 'dismiss', undefined, undefined, undefined, reason);
}

// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.id === 'dismissModal') closeDismissModal();
});

function showToast(msg, color) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = color || '#1a1a2e';
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2200);
}

function clearDetailPlaceholder() {
  const ta = document.getElementById('detailText');
  if (ta.classList.contains('placeholder-text')) {
    ta.value = '';
    ta.classList.remove('placeholder-text');
  }
}

function loadDetail(id, url, title, text, price, notes, status, description, agentNotes, proposalRequested) {
  currentId = id;

  // highlight card
  document.querySelectorAll('.card').forEach(c => c.classList.remove('active-preview'));
  const card = document.getElementById('card-' + id);
  if (card) card.classList.add('active-preview');

  // show panel
  document.getElementById('detailEmpty').style.display = 'none';
  const content = document.getElementById('detailContent');
  content.style.display = 'flex';

  document.getElementById('detailTitle').textContent = title;
  document.getElementById('detailLink').href = url;

  const box = document.getElementById('jobContentBox');
  if (description && description.length > 20) {
    box.className = 'job-content-box';
    box.textContent = description;
  } else {
    box.className = 'job-content-loading';
    box.textContent = 'טוען...';
    fetch(`/api/get_job_content.php?id=${id}&url=${encodeURIComponent(url)}`)
      .then(r => r.json())
      .then(data => {
        if (data.ok && data.description) {
          box.className = 'job-content-box';
          box.textContent = data.description;
        } else {
          box.className = 'job-content-loading';
          box.textContent = 'לא ניתן לטעון תוכן';
        }
      })
      .catch(() => { box.className = 'job-content-loading'; box.textContent = 'שגיאה בטעינה'; });
  }

  const ta = document.getElementById('detailText');
  const isPlaceholder = (text === 'ממתין לסקירה' || text === '');
  ta.value = isPlaceholder ? 'ממתין לסקירה' : text;
  ta.className = 'detail-textarea' + (isPlaceholder ? ' placeholder-text' : '');

  document.getElementById('detailPrice').value = price || 200;
  document.getElementById('detailNotes').value = notes || '';

  // agent notes
  const notesSection = document.getElementById('agentNotesSection');
  const notesBox     = document.getElementById('agentNotesBox');
  if (agentNotes && agentNotes.trim()) {
    notesBox.textContent = agentNotes;
    notesSection.style.display = '';
  } else {
    notesSection.style.display = 'none';
  }

  // footer buttons
  const footer = document.getElementById('detailFooter');
  footer.innerHTML = '';

  const saveBtn = document.createElement('button');
  saveBtn.className = 'btn-save';
  saveBtn.textContent = 'שמור';
  saveBtn.onclick = () => doActionDetail('save');
  footer.appendChild(saveBtn);

  if (status === 'pending') {
    const sub = document.createElement('button');
    sub.className = 'btn-submit';
    sub.textContent = '↑ הגש';
    sub.onclick = () => doActionDetail('approve');
    footer.appendChild(sub);

    const dis = document.createElement('button');
    dis.className = 'btn-dismiss';
    dis.textContent = '✕ דחה';
    dis.onclick = () => openDismissModal(id);
    footer.appendChild(dis);

    // One-click confirm of an agent-proposed rejection (no reason needed)
    if (agentNotes && agentNotes.trim()) {
      const conf = document.createElement('button');
      conf.className = 'btn-confirm-reject';
      conf.textContent = '✓ אשר דחייה';
      conf.title = 'דחה בלחיצה אחת, סיבת הסוכן נשמרת';
      conf.onclick = () => dismissAgent(id, agentNotes);
      footer.appendChild(conf);
    }

    const isPlaceholder = (text === 'ממתין לסקירה' || text === '');
    if (isPlaceholder) {
      if (proposalRequested) {
        const waiting = document.createElement('button');
        waiting.className = 'btn-waiting';
        waiting.textContent = '⏳ ממתין לסוכן';
        waiting.disabled = true;
        footer.appendChild(waiting);
      } else {
        const reqBtn = document.createElement('button');
        reqBtn.className = 'btn-request';
        reqBtn.id = 'detailReqBtn';
        reqBtn.textContent = '✦ בקשי הצעה';
        reqBtn.onclick = () => requestProposal(id);
        footer.appendChild(reqBtn);
      }
    } else {
      // Item already has a draft — let Michal request a rewrite with a guidance note
      footer.appendChild(makeNewContentBtn(id, proposalRequested));
    }

  } else if (status === 'approved') {
    const sent = document.createElement('button');
    sent.className = 'btn-submitted';
    sent.textContent = '✓ סמן כנשלח';
    sent.onclick = () => doActionDetail('submitted');
    footer.appendChild(sent);

    const dis = document.createElement('button');
    dis.className = 'btn-dismiss';
    dis.textContent = '✕ דחה';
    dis.onclick = () => openDismissModal(id);
    footer.appendChild(dis);

    footer.appendChild(makeNewContentBtn(id, proposalRequested));

  } else {
    const rest = document.createElement('button');
    rest.className = 'btn-restore';
    rest.textContent = '↩ החזר לממתינות';
    rest.onclick = () => doActionDetail('restore');
    footer.appendChild(rest);
  }
}

function requestProposal(id) {
  doAction(id, 'request_proposal');
  // update card button immediately
  const card = document.getElementById('card-' + id);
  if (card) {
    const reqBtn = card.querySelector('.btn-request');
    if (reqBtn) {
      reqBtn.className = 'btn-waiting';
      reqBtn.textContent = '⏳ ממתין לסוכן';
      reqBtn.disabled = true;
      reqBtn.onclick = null;
    }
  }
  // update detail footer button too
  const detailReqBtn = document.getElementById('detailReqBtn');
  if (detailReqBtn) {
    detailReqBtn.className = 'btn-waiting';
    detailReqBtn.textContent = '⏳ ממתין לסוכן';
    detailReqBtn.disabled = true;
    detailReqBtn.onclick = null;
  }
}

// ── Feature 1: one-click dismiss ───────────────────────────────
// Confirm an agent-proposed rejection in one click. The agent's reason is
// kept as the rejection_reason (feeds learning) but Michal types nothing.
function dismissAgent(id, reason) {
  doAction(id, 'dismiss', undefined, undefined, undefined, reason || '');
}

// Dismiss with no reason at all (from the modal).
function dismissNoReason() {
  const id = dismissPendingId; // save before close nulls it
  closeDismissModal();
  if (id) doAction(id, 'dismiss', undefined, undefined, undefined, '');
}

// ── Feature 2: request new content with guidance note ──────────
function makeNewContentBtn(id, proposalRequested) {
  const b = document.createElement('button');
  if (proposalRequested) {
    b.className = 'btn-waiting';
    b.textContent = '⏳ ממתין לסוכן';
    b.disabled = true;
  } else {
    b.className = 'btn-newcontent';
    b.id = 'detailNewContentBtn';
    b.textContent = '↻ בקש תוכן חדש';
    b.title = 'הסוכן ינסח מחדש בריצה הבאה לפי ההערות הפנימיות';
    b.onclick = () => requestContent(id);
  }
  return b;
}

function requestContent(id) {
  const notes = document.getElementById('detailNotes')?.value ?? '';
  const body = new URLSearchParams({ action: 'request_proposal', id, notes });
  fetch('action.php', { method: 'POST', body })
    .then(async r => {
      const raw = await r.text();
      try { return JSON.parse(raw); }
      catch { throw new Error(raw.substring(0, 150)); }
    })
    .then(data => {
      if (!data.ok) { alert('שגיאה: ' + (data.error ?? 'unknown')); return; }
      showToast('↻ נשלחה בקשה לתוכן חדש – הסוכן ינסח מחדש בריצה הבאה');
      const btn = document.getElementById('detailNewContentBtn');
      if (btn) { btn.className = 'btn-waiting'; btn.textContent = '⏳ ממתין לסוכן'; btn.disabled = true; btn.onclick = null; }
      // If the item left the current filter (was approved → now pending), drop the card.
      if (CURRENT_FILTER !== 'pending' && CURRENT_FILTER !== 'all') {
        const card = document.getElementById('card-' + id);
        if (card) { card.style.transition = 'opacity .4s'; card.style.opacity = '0'; setTimeout(() => card.remove(), 450); }
        if (currentId === id) {
          document.getElementById('detailContent').style.display = 'none';
          document.getElementById('detailEmpty').style.display = 'flex';
          currentId = null;
        }
      }
    })
    .catch(err => alert('שגיאת רשת:\n' + err.message));
}

function doActionDetail(action) {
  if (!currentId) return;
  const text  = document.getElementById('detailText')?.value ?? '';
  const price = document.getElementById('detailPrice')?.value ?? 200;
  const notes = document.getElementById('detailNotes')?.value ?? '';
  doAction(currentId, action, text, price, notes);
}

function doAction(id, action, textOverride, priceOverride, notesOverride, rejectionReason) {
  const text   = textOverride  !== undefined ? textOverride  : '';
  const price  = priceOverride !== undefined ? priceOverride : 200;
  const notes  = notesOverride !== undefined ? notesOverride : '';
  const reason = rejectionReason || '';

  const body = new URLSearchParams({ action, id, proposal_text: text, price, notes, rejection_reas