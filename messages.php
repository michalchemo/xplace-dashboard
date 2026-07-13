<?php
require_once __DIR__ . '/auth.php';   // same login gate as the dashboard
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/db.php';

$db = get_db();

$filter = $_GET['status'] ?? 'needs_reply';
$valid  = ['needs_reply', 'handled', 'ignored', 'all'];
if (!in_array($filter, $valid, true)) {
    $filter = 'needs_reply';
}

$counts = ['needs_reply' => 0, 'handled' => 0, 'ignored' => 0];
$rows   = [];
$tableMissing = false;

try {
    foreach ($db->query("SELECT status, COUNT(*) c FROM messages GROUP BY status") as $r) {
        $counts[$r['status']] = (int)$r['c'];
    }
    if ($filter === 'all') {
        $rows = $db->query("SELECT * FROM messages ORDER BY last_message_date DESC LIMIT 200")->fetchAll();
    } else {
        $stmt = $db->prepare("SELECT * FROM messages WHERE status = ? ORDER BY last_message_date DESC LIMIT 200");
        $stmt->execute([$filter]);
        $rows = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $tableMissing = true;   // migrate.php has not run yet
}

$LABELS = [
    'needs_reply' => 'ממתינות למענה',
    'handled'     => 'טופלו',
    'ignored'     => 'מושתקות',
    'all'         => 'הכל',
];

function he_when(?string $dt): string {
    if (!$dt) return '';
    $ts   = strtotime($dt);
    $diff = time() - $ts;
    if ($diff < 3600)  return 'לפני ' . max(1, (int)($diff / 60)) . ' דקות';
    if ($diff < 86400) return 'לפני ' . (int)($diff / 3600) . ' שעות';
    if ($diff < 604800) return 'לפני ' . (int)($diff / 86400) . ' ימים';
    return date('d/m/Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nintay &ndash; הודעות</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, Arial, sans-serif; background: #f0f2f5; color: #222; min-height: 100vh; }
  header { background: #1a1a2e; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; }
  header h1 { font-size: 16px; font-weight: 600; }
  header a.logout { color:#fff; font-size:12px; opacity:.7; text-decoration:none; }
  nav { background: #fff; border-bottom: 1px solid #e0e0e0; padding: 0 20px; display: flex; gap: 4px; }
  nav a { display:inline-flex; align-items:center; gap:6px; padding:10px 14px; text-decoration:none; color:#555; font-size:13px; border-bottom:3px solid transparent; }
  nav a.active { color:#1a1a2e; border-bottom-color:#1a1a2e; font-weight:600; }
  nav a .badge { background:#e0e0e0; color:#555; border-radius:10px; padding:1px 6px; font-size:11px; }
  nav a.active .badge { background:#1a1a2e; color:#fff; }
  nav a .badge.hot { background:#dc3545; color:#fff; }

  .wrap { max-width: 900px; margin: 0 auto; padding: 18px; }
  .intro { font-size:12px; color:#777; line-height:1.7; margin-bottom:14px; }

  .card { background:#fff; border-radius:8px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px; }
  .card.hot { border:1px solid #f5b5bb; box-shadow:0 0 0 2px #fdecee; }
  .card.muted { opacity:.55; }
  .top { display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; }
  .who { font-size:13px; font-weight:700; color:#1a1a2e; }
  .title { font-size:12px; color:#555; margin-top:2px; line-height:1.4; flex:1; min-width:200px; }
  .when { font-size:11px; color:#aaa; white-space:nowrap; }
  .snippet { background:#f7f8fa; border-radius:6px; padding:9px 11px; margin:10px 0; font-size:12px; line-height:1.6; color:#333; white-space:pre-wrap; max-height:120px; overflow:auto; }
  .snippet.mine { background:#eef4ff; }
  .from { font-size:11px; font-weight:600; margin-bottom:4px; color:#888; }
  .actions { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  a.open { display:inline-block; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; background:#1a1a2e; color:#fff; }
  button { padding:6px 12px; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; }
  button:hover { opacity:.85; }
  .btn-done { background:#198754; color:#fff; }
  .btn-mute { background:#e9ecef; color:#333; }
  .btn-reopen { background:#b45309; color:#fff; }
  .btn-copy { background:#e9ecef; color:#333; }
  .btn-save { background:#198754; color:#fff; }
  .draftbox { margin:10px 0; }
  .draftbox label { font-size:11px; color:#888; font-weight:600; display:block; margin-bottom:4px; }
  .draftbox textarea { width:100%; min-height:110px; resize:vertical; line-height:1.7; font-size:12px; font-family:inherit; direction:rtl; border:1px solid #ddd; border-radius:6px; padding:9px 11px; }
  .draftbox textarea:focus { outline:none; border-color:#1a1a2e; }
  .empty { text-align:center; color:#aaa; padding:40px 0; font-size:14px; }
  .warn { background:#fff4e5; color:#b45309; font-size:12px; border-radius:6px; padding:10px 12px; margin-bottom:14px; line-height:1.6; }
  .toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#1a1a2e; color:#fff; padding:8px 20px; border-radius:20px; font-size:12px; opacity:0; transition:opacity .3s; pointer-events:none; z-index:999; }
  .toast.show { opacity:1; }
</style>
</head>
<body>
<header>
  <h1>Nintay &middot; הודעות</h1>
  <span style="display:flex;align-items:center;gap:14px">
    <span style="font-size:12px;opacity:.5">שיחות ב-XPlace שממתינות לתשובה</span>
    <?php if (!empty($_SESSION['dash_user'])): ?>
      <a class="logout" href="logout.php">יציאה</a>
    <?php endif; ?>
  </span>
</header>
<nav>
  <a href="index.php">הצעות</a>
  <a href="learnings.php">לקחים</a>
  <?php foreach ($LABELS as $s => $label): ?>
    <a href="?status=<?= $s ?>" class="<?= $filter === $s ? 'active' : '' ?>"><?= $label ?>
      <?php if ($s !== 'all' && !empty($counts[$s])): ?>
        <span class="badge<?= $s === 'needs_reply' ? ' hot' : '' ?>"><?= $counts[$s] ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<div class="wrap">
  <?php if ($tableMissing): ?>
    <div class="warn">טבלת ההודעות עדיין לא נוצרה. הריצי פעם אחת את <code>api/migrate.php</code> עם ה-Bearer key.</div>
  <?php endif; ?>

  <div class="intro">
    הסוכן סורק את ההודעות ב-XPlace בכל ריצה. שיחה שבה הלקוח כתב אחרון ואת לא ענית מסומנת כממתינה למענה,
    ומדווחת בוואטסאפ פעם אחת. כשההודעה האחרונה מהלקוח מכילה שאלה, הסוכן גם כותב טיוטת מענה כאן.
    <b>הסוכן לעולם לא שולח כלום ב-XPlace</b>: את עורכת, מעתיקה ושולחת בעצמך.
    "טופל" סוגר את השורה, "השתק" מונע התראות עתידיות על השיחה הזו.
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty"><?= $filter === 'needs_reply' ? 'אין הודעות שממתינות לתשובה.' : 'אין שורות להצגה.' ?></div>
  <?php else: ?>
    <?php foreach ($rows as $r):
      $isHot   = $r['status'] === 'needs_reply';
      $isMuted = $r['status'] === 'ignored';
      $url     = 'https://www.xplace.com/il/m#/' . rawurlencode($r['thread_id']);
    ?>
    <div class="card<?= $isHot ? ' hot' : '' ?><?= $isMuted ? ' muted' : '' ?>" id="mrow-<?= $r['id'] ?>">
      <div class="top">
        <div style="flex:1;min-width:200px">
          <div class="who"><?= htmlspecialchars($r['participant'] ?? 'לקוח') ?></div>
          <div class="title"><?= htmlspecialchars($r['project_title'] ?? '') ?></div>
        </div>
        <div class="when"><?= he_when($r['last_message_date']) ?></div>
      </div>

      <?php if (!empty($r['last_message_text'])): ?>
        <div class="snippet<?= (int)$r['last_from_me'] === 1 ? ' mine' : '' ?>">
          <div class="from"><?= (int)$r['last_from_me'] === 1 ? 'ההודעה האחרונה ממך' : 'ההודעה האחרונה מהלקוח' ?></div>
          <?= htmlspecialchars(mb_substr($r['last_message_text'], 0, 600)) ?>
        </div>
      <?php endif; ?>

      <?php $draft = $r['draft_reply'] ?? ''; ?>
      <?php if ($isHot || $draft !== ''): ?>
        <div class="draftbox">
          <label>טיוטת מענה<?= $draft === '' ? ' (הסוכן לא כתב טיוטה לשרשור הזה)' : '' ?></label>
          <textarea id="draft-<?= $r['id'] ?>" placeholder="אין טיוטה. אפשר לכתוב כאן ולשמור."><?= htmlspecialchars($draft) ?></textarea>
          <div class="actions" style="margin-top:6px">
            <button class="btn-copy" onclick="copyDraft(<?= $r['id'] ?>)">העתק</button>
            <button class="btn-save" onclick="saveDraft(<?= $r['id'] ?>)">שמור טיוטה</button>
          </div>
        </div>
      <?php endif; ?>

      <div class="actions">
        <a class="open" href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener">פתח ב-XPlace</a>
        <?php if ($isHot): ?>
          <button class="btn-done" onclick="act(<?= $r['id'] ?>,'handled')">טופל</button>
          <button class="btn-mute" onclick="act(<?= $r['id'] ?>,'ignore')">השתק</button>
        <?php else: ?>
          <button class="btn-reopen" onclick="act(<?= $r['id'] ?>,'reopen')">החזר לתור</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>

<script>
function toast(m){ const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),1800); }
async function act(id, action){
  try {
    const r = await fetch('message_action.php', {method:'POST', body:new URLSearchParams({id, action})});
    const raw = await r.text();
    let j; try { j = JSON.parse(raw); } catch { throw new Error(raw.slice(0,150)); }
    if(!j.ok) throw new Error(j.error||'שגיאה');
    const row = document.getElementById('mrow-'+id);
    if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(()=>row.remove(), 260); }
    toast('עודכן');
  } catch(e){ toast(e.message); }
}
async function saveDraft(id){
  try {
    const draft_reply = document.getElementById('draft-'+id).value;
    const r = await fetch('message_action.php', {method:'POST', body:new URLSearchParams({id, action:'save_draft', draft_reply})});
    const raw = await r.text();
    let j; try { j = JSON.parse(raw); } catch { throw new Error(raw.slice(0,150)); }
    if(!j.ok) throw new Error(j.error||'שגיאה');
    toast('הטיוטה נשמרה');
  } catch(e){ toast(e.message); }
}
async function copyDraft(id){
  const t = document.getElementById('draft-'+id);
  try { await navigator.clipboard.writeText(t.value); toast('הועתק'); }
  catch { t.select(); document.execCommand('copy'); toast('הועתק'); }
}
</script>
</body>
</html>
