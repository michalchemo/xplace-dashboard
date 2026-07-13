<?php
require_once __DIR__ . '/auth.php';   // same login gate as the dashboard
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/db.php';

$db   = get_db();
// Drafts (confirmed=0) first, then most-recently updated.
$rows = $db->query(
    "SELECT * FROM learnings ORDER BY confirmed ASC, updated_at DESC"
)->fetchAll();

$draftCount = 0;
foreach ($rows as $r) { if ((int)$r['confirmed'] === 0) $draftCount++; }

$OUTCOMES = [
    'rejected_price' => 'נדחה (מחיר גבוה)',
    'advanced'       => 'התקדם',
    'won'            => 'נסגר (זכיתי)',
    'in_progress'    => 'בתהליך',
    'bad_fit'        => 'לא מתאים',
    'lost'           => 'אבד',
    'other'          => 'אחר',
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nintay &ndash; לקחים</title>
<?php include __DIR__ . '/partials/favicon.php'; ?>
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
  nav a .badge.draft { background:#b45309; color:#fff; }

  .wrap { max-width: 900px; margin: 0 auto; padding: 18px; }
  .intro { font-size:12px; color:#777; line-height:1.7; margin-bottom:14px; }

  .card { background:#fff; border-radius:8px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px; }
  .card.draft { border:1px solid #ffca7a; box-shadow:0 0 0 2px #ffe0b2; }
  .card.inactive { opacity:.55; }
  .draft-banner { background:#fff4e5; color:#b45309; font-size:12px; font-weight:600; border-radius:6px; padding:6px 10px; margin-bottom:10px; }
  .row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-start; }
  .title { font-size:13px; font-weight:700; color:#1a1a2e; flex:1; min-width:200px; line-height:1.4; }
  .title a { color:#666; font-size:11px; font-weight:400; text-decoration:none; margin-inline-start:6px; }
  .title a:hover { text-decoration:underline; }
  .meta { font-size:11px; color:#aaa; margin-top:2px; }
  label { font-size:11px; color:#888; font-weight:600; display:block; margin-bottom:3px; }
  select, input[type=number], textarea { border:1px solid #ddd; border-radius:6px; padding:7px 9px; font-size:13px; font-family:inherit; direction:rtl; }
  select:focus, input:focus, textarea:focus { outline:none; border-color:#1a1a2e; }
  textarea { width:100%; min-height:56px; resize:vertical; line-height:1.6; }
  .fields { display:flex; gap:10px; flex-wrap:wrap; margin:10px 0; }
  .f-outcome select { min-width:150px; }
  .f-price input { width:90px; text-align:center; }
  .actions { display:flex; gap:6px; flex-wrap:wrap; margin-top:6px; }
  button { padding:6px 12px; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; }
  button:hover { opacity:.85; }
  .btn-save { background:#198754; color:#fff; }
  .btn-confirm { background:#b45309; color:#fff; }
  .btn-del { background:#dc3545; color:#fff; }
  .btn-toggle { background:#e9ecef; color:#333; }
  .badge-outcome { font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; background:#eef; color:#334; white-space:nowrap; }

  .addbox { background:#fff; border:1px dashed #bbb; border-radius:8px; padding:14px 16px; margin-bottom:16px; }
  .addbox h3 { font-size:13px; color:#1a1a2e; margin-bottom:10px; }
  .empty { text-align:center; color:#aaa; padding:40px 0; font-size:14px; }
  .toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#1a1a2e; color:#fff; padding:8px 20px; border-radius:20px; font-size:12px; opacity:0; transition:opacity .3s; pointer-events:none; z-index:999; }
  .toast.show { opacity:1; }
</style>
</head>
<body>
<header>
  <h1>Nintay &middot; לקחים</h1>
  <span style="display:flex;align-items:center;gap:14px">
    <span style="font-size:12px;opacity:.5">מה עבד, מה לא, ולמה</span>
    <?php if (!empty($_SESSION['dash_user'])): ?>
      <a class="logout" href="logout.php">יציאה</a>
    <?php endif; ?>
  </span>
</header>
<nav>
  <a href="index.php">הצעות</a>
  <?php
    $msgWaiting = 0;
    try { $msgWaiting = (int)$db->query("SELECT COUNT(*) FROM messages WHERE status='needs_reply'")->fetchColumn(); }
    catch (Exception $e) { /* table not migrated yet */ }
  ?>
  <a href="messages.php">הודעות
    <?php if ($msgWaiting): ?><span class="badge" style="background:#dc3545;color:#fff"><?= $msgWaiting ?></span><?php endif; ?>
  </a>
  <a href="learnings.php" class="active">לקחים
    <span class="badge<?= $draftCount ? ' draft' : '' ?>"><?= count($rows) ?></span>
  </a>
</nav>

<div class="wrap">
  <div class="intro">
    כל שורה כאן היא לקח שהסוכן קורא בכל ריצה: לא מתמחר מעל מחיר שנדחה, משחזר זווית/מחיר שסגרו, ומוריד עדיפות ללקוחות שלא התאימו.
    שורות עם מסגרת כתומה הן טיוטות שהסוכן זיהה אוטומטית וממתינות לאישורך. רק שורות מאושרות ופעילות משפיעות על הסוכן.
  </div>

  <!-- Add new learning -->
  <div class="addbox">
    <h3>+ הוספת לקח ידני</h3>
    <div class="fields">
      <div class="f-outcome">
        <label>תוצאה</label>
        <select id="add_outcome">
          <?php foreach ($OUTCOMES as $k => $lbl): ?>
            <option value="<?= $k ?>"><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f-price">
        <label>מחיר (אופ')</label>
        <input type="number" id="add_price" min="0" max="5000" step="10" placeholder="—">
      </div>
      <div style="flex:1;min-width:160px">
        <label>project_id (אופ')</label>
        <input type="number" id="add_pid" style="width:100%" placeholder="למשל 214461">
      </div>
    </div>
    <label>הלקח</label>
    <textarea id="add_lesson" placeholder="מה קרה ומה ללמוד מזה"></textarea>
    <div class="actions"><button class="btn-save" onclick="addLearning()">הוסף</button></div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">עדיין אין לקחים.</div>
  <?php else: ?>
    <?php foreach ($rows as $r):
      $isDraft = (int)$r['confirmed'] === 0;
      $isActive = (int)$r['active'] === 1;
    ?>
    <div class="card<?= $isDraft ? ' draft' : '' ?><?= $isActive ? '' : ' inactive' ?>" id="lrow-<?= $r['id'] ?>">
      <?php if ($isDraft): ?>
        <div class="draft-banner">🤖 טיוטת סוכן — זוהתה אוטומטית. בדקי, תקני את התוצאה/הלקח, ואשרי.</div>
      <?php endif; ?>
      <div class="row">
        <div class="title">
          <?= htmlspecialchars($r['project_title'] ?: '(ללא כותרת)') ?>
          <?php if (!empty($r['project_url'])): ?>
            <a href="<?= htmlspecialchars($r['project_url']) ?>" target="_blank">XPlace ↗</a>
          <?php endif; ?>
          <div class="meta">
            <?= $r['project_id'] ? 'פרויקט #'.htmlspecialchars($r['project_id']).' · ' : '' ?>
            עודכן <?= date('d/m/y', strtotime($r['updated_at'])) ?>
            <?= $isActive ? '' : ' · <b>מאורכב</b>' ?>
          </div>
        </div>
      </div>
      <div class="fields">
        <div class="f-outcome">
          <label>תוצאה</label>
          <select id="out-<?= $r['id'] ?>">
            <?php foreach ($OUTCOMES as $k => $lbl): ?>
              <option value="<?= $k ?>" <?= $r['outcome']===$k?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="f-price">
          <label>מחיר</label>
          <input type="number" id="price-<?= $r['id'] ?>" min="0" max="5000" step="10" value="<?= $r['price']!==null?(int)$r['price']:'' ?>" placeholder="—">
        </div>
      </div>
      <label>הלקח</label>
      <textarea id="lesson-<?= $r['id'] ?>"><?= htmlspecialchars($r['lesson']) ?></textarea>
      <div class="actions">
        <button class="btn-save" onclick="saveLearning(<?= $r['id'] ?>)">שמור</button>
        <?php if ($isDraft): ?>
          <button class="btn-confirm" onclick="confirmLearning(<?= $r['id'] ?>)">✓ אשר</button>
        <?php endif; ?>
        <button class="btn-toggle" onclick="toggleActive(<?= $r['id'] ?>)"><?= $isActive ? 'ארכב' : 'הפעל' ?></button>
        <button class="btn-del" onclick="delLearning(<?= $r['id'] ?>)">מחק</button>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="toast" id="toast"></div>
<script>
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2000);}
async function post(body){
  const r = await fetch('learning_action.php',{method:'POST',body:new URLSearchParams(body)});
  const raw = await r.text();
  let j; try { j = JSON.parse(raw); } catch { throw new Error(raw.slice(0,150)); }
  if(!j.ok) throw new Error(j.error||'שגיאה');
  return j;
}
async function addLearning(){
  const outcome=document.getElementById('add_outcome').value;
  const lesson=document.getElementById('add_lesson').value.trim();
  if(!lesson){alert('כתבי את הלקח');return;}
  try{ await post({action:'add',outcome,lesson,price:document.getElementById('add_price').value,project_id:document.getElementById('add_pid').value});
    toast('נוסף'); setTimeout(()=>location.reload(),600);
  }catch(e){alert(e.message);}
}
async function saveLearning(id){
  const outcome=document.getElementById('out-'+id).value;
  const lesson=document.getElementById('lesson-'+id).value.trim();
  const price=document.getElementById('price-'+id).value;
  if(!lesson){alert('הלקח לא יכול להיות ריק');return;}
  try{ await post({action:'update',id,outcome,lesson,price});
    toast('נשמר');
    const c=document.getElementById('lrow-'+id); c.classList.remove('draft');
    const b=c.querySelector('.draft-banner'); if(b)b.remove();
    const cb=c.querySelector('.btn-confirm'); if(cb)cb.remove();
  }catch(e){alert(e.message);}
}
async function confirmLearning(id){
  try{ await post({action:'confirm',id}); toast('אושר');
    const c=document.getElementById('lrow-'+id); c.classList.remove('draft');
    const b=c.querySelector('.draft-banner'); if(b)b.remove();
    const cb=c.querySelector('.btn-confirm'); if(cb)cb.remove();
  }catch(e){alert(e.message);}
}
async function toggleActive(id){ try{ await post({action:'toggle_active',id}); location.reload(); }catch(e