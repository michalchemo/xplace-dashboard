# PROJECT CONTEXT — XPlace Proposals Dashboard

קובץ קונטקסט מרכזי לפרויקט. המטרה: לעבוד על שדרוגים מכאן, בלי לחפור בתוך שרשור התזמונים.
עודכן לאחרונה: 05/07/2026.

---

## 1. בקצרה — מה זה הפרויקט

מערכת אוטומטית שרצה מדי יום ומגישה הצעות (proposals) בפלטפורמת הפרילנסרים **XPlace** בשם מיכל (Nintay).

זרימה כללית:

1. **סוכן מתוזמן** (Claude scheduled task) סורק את דף ההמלצות ב-XPlace, מסווג פרויקטים רלוונטיים, וכותב טיוטת הצעה בעברית לכל אחד.
2. הטיוטות נשמרות ל**דשבורד** (אתר PHP+MySQL על דרופלט) דרך API.
3. מיכל נכנסת לדשבורד, בודקת/עורכת טיוטות ומאשרת (`approved`) או דוחה.
4. בריצה הבאה הסוכן **מגיש אוטומטית** את מה שמיכל אישרה, ומדווח לה סיכום ב-WhatsApp.

הדשבורד עצמו הוא רק ממשק ניהול/אישור. הלוגיקה האמיתית יושבת ב-SKILL.md של המשימה המתוזמנת.

---

## 2. מי המשתמשת (רלוונטי לתוכן ההצעות)

מיכל, פרילנסרית ישראלית. תחומים: WordPress, WooCommerce, Shopify, Elementor, אינטגרציות AI, צ'אטבוטים, אוטומציות (Make/Zapier/n8n), API, CRM/ERP, וניהול פרויקטים טכני.
- טלפון בהצעות: 050-630-5200 · אימייל: michal@nintay.com
- דוגמאות עבודה: tourgolan.org.il · points-of-you.com · worker.co.il · jelixir.com
- ביקורות: xplace.com/u/nintay

---

## 3. סטאק וקוד

- PHP 8.x + MySQL 8.x. בלי פריימוורקים, בלי npm, בלי תלויות חיצוניות.
- **Git remote:** https://github.com/michalchemo/xplace-dashboard.git (branch: `main`)
- **מיקום מקומי:** C:\Users\micha\OneDrive\Desktop\ai\xplace-dashboard

### מבנה קבצים
```
xplace-dashboard/
├── config.sample.php   — תבנית קונפיג (committed)
├── config.php          — קונפיג אמיתי (gitignored, לא בריפו)
├── db.php              — חיבור PDO
├── auth.php            — שער התחברות (username+password) ל-UI
├── login.php / logout.php
├── index.php           — ה-UI הראשי של הדשבורד (login-gated, responsive)
├── learnings.php       — עמוד "לקחים" (login-gated): צפייה/הוספה/עריכה/אישור/ארכוב
├── learning_action.php — CRUD ללקחים (add/update/delete/confirm/toggle_active), session או Bearer
├── messages.php        — עמוד "הודעות" (login-gated): שיחות XPlace שממתינות למענה
├── message_action.php  — פעולות להודעות (handled/reopen/ignore/note), session או Bearer
├── action.php          — approve / dismiss / submitted / restore / request_proposal
├── schema.sql          — יצירת ה-DB (רץ פעם אחת)
├── deploy.ps1          — סקריפט דיפלוי לדרופלט
└── api/                — endpoints שהסוכן קורא (Bearer key)
    ├── add_proposal.php          — POST: הוספת טיוטה (כולל client_name אופציונלי; קיים=backfill לשם הלקוח בלבד)
    ├── fill_proposal.php         — POST: מילוי/כתיבה מחדש של הצעה שמיכל ביקשה
    ├── delete_proposal.php       — POST: מחיקה מהתור
    ├── get_approved.php          — GET: הצעות מאושרות להגשה
    ├── get_dashboard_status.php  — GET: change-gate (known_project_ids, pending/approved counts)
    ├── get_proposal_requests.php — GET: פרויקטים שמיכל ביקשה עבורם הצעה
    ├── get_rejection_patterns.php— GET: דפוסי דחייה קודמים
    ├── get_withdrawals.php       — GET: הצעות להסרה מ-XPlace
    ├── get_job_content.php       — GET
    ├── set_outcome.php           — POST: רישום/עדכון תוצאת ליד (upsert לפי project_id) לטבלת learnings
    ├── get_learnings.php         — GET: לקחים מאושרים+פעילים בלבד שהסוכן קורא בכל ריצה
    ├── get_outcome_candidates.php— GET: הצעות שנשלחו ואין להן לקח מאושר (לזיהוי תוצאה ע"י הסוכן)
    ├── sync_messages.php         — POST: סנכרון שרשורי ההודעות מ-XPlace, מחזיר התראות חדשות
    ├── get_messages.php          — GET: שיחות לפי סטטוס (ברירת מחדל needs_reply)
    ├── mark_messages_alerted.php — POST: סימון שההתראה נשלחה בוואטסאפ (מונע התראה כפולה)
    └── migrate.php               — הרצת עדכוני סכימה על ה-DB החי
```

### סכימת DB (טבלה `proposals`)
עמודות ליבה: `id, project_id (UNIQUE), project_title, project_url, proposal_text, price, price_type ('hourly'|'fixed'), status, notes, created_at, updated_at`.
`status` ENUM: `pending | approved | dismissed | submitted`.
עמודות שנוספו דרך `migrate.php`: `proposal_requested`, `agent_notes`, `withdrawal_done`.

- `notes` = ההנחיה של מיכל לטיוטה (עוקף את הפריימינג הדיפולטי).
- `agent_notes` = הערת הסוכן (למשל סיבת דחייה). לא כותבים "תחרות גבוהה" לעולם.
- `proposal_text` לא ריק על בקשה = בקשת **rewrite**.
- `rejection_reason` = סיבת דחייה ידנית של מיכל, מוזן ל-`get_rejection_patterns.php`.

### טבלת `learnings` (לולאת למידה, חדש 05/07)
מעקב **תוצאה אחרי הגשה** שהטבלה `proposals` לא תפסה. עמידה גם אחרי שה-change-gate מוחק שורה.
עמודות: `id, project_id (UNIQUE), project_title, project_url, outcome, lesson, price, price_type, active, confirmed, created_at, updated_at`.
- `outcome` (vocabulary סגור): `rejected_price | advanced | won | in_progress | bad_fit | lost | other`.
- `lesson` = הלקח שהסוכן מיישם (עברית/חופשי).
- `active=0` = מאורכב, הסוכן מתעלם. `confirmed=0` = טיוטת סוכן שממתינה לאישור מיכל.
- **מי ממלא:** מיכל ידנית דרך `learnings.php` (view/add/edit/confirm/archive), *או* הסוכן מזהה אוטומטית (STEP 5.7) ופותח טיוטה `confirmed=0`. הסוכן לא ממציא סיבות, רק מה שהעמוד ב-XPlace מראה (נסגר/הוענק).
- כתיבה: `set_outcome.php` (upsert, מקבל `confirmed`) / `learning_action.php` (UI). קריאה לסוכן: `get_learnings.php` (מחזיר `active=1 AND confirmed=1` בלבד).
- הסוכן קורא ב-**STEP 1.2**: לא מתמחר מעל מחיר שכבר נדחה בפרויקט דומה (`rejected_price`), חוזר על זווית/מחיר שסגרו (`advanced/won`), ומוריד עדיפות לפרויקטים עם אותם סימני `bad_fit`. הלקחים מייעצים בלבד, לא מגישים ולא דוחים אוטומטית. טיוטות (`confirmed=0`) לא משפיעות עד אישור.

### טבלת `messages` (בדיקת הודעות, חדש 13/07)
מעקב אחרי שרשורי הצ'אט ב-XPlace. המטרה: לא לפספס לקוח שכתב ולא נענה. **הסוכן מתריע בלבד, לעולם לא עונה בשם מיכל.**
עמודות: `id, thread_id (UNIQUE), project_id, project_title, participant, last_message_date, last_message_text, last_from_me, status, alerted, notes, created_at, updated_at`.
- `thread_id` בפורמט של XPlace: `<michal>g<client>p<projectId>`, למשל `81571g340350p214738`. הלינק לשיחה: `https://www.xplace.com/il/m#/<thread_id>`.
- `status`: `needs_reply` (הלקוח כתב אחרון ומיכל לא ענתה) | `handled` (מיכל ענתה או סימנה שטופל) | `ignored` (מושתק, הסוכן לא יחייה אותו לעולם).
- `alerted=1` = כבר דווח בוואטסאפ. מונע התראה כפולה על אותה הודעה.
- **מקור האמת ל-XPlace:** רשימת השרשורים היא AngularJS. כל שורה `.message_entry` נושאת את הנתונים ב-`angular.element(row).scope().topicListItem` (`foundThreadBriefId / ProjectId / ProjectTitle / Title / RecentMessageDate / RecentMessageText / IsUnread`). זהות השולח האחרון נקבעת רק בתוך השרשור: ה-`.msg` האחרון עם `outgoing_msg` = ההודעה האחרונה ממיכל.
- זרימה: הסוכן (STEP 1.4) מסנכרן → `sync_messages.php` מחליט סטטוס ומחזיר `new_alerts` + `needs_draft` → שורה בוואטסאפ → `mark_messages_alerted.php`. מיכל עונה בעצמה ב-XPlace ומסמנת "טופל" ב-`messages.php`.
- **מדיניות טיוטות (STEP 1.4b):** `draft_reply` נכתב **רק כשההודעה האחרונה מהלקוח מכילה שאלה** (מחיר, היקף, לו"ז, זמינות). הודעת תודה/סגירה = התראה בלבד, בלי טיוטה. הטיוטה נשמרת דרך `save_message_draft.php` ומוצגת ב-`messages.php` בתיבה ניתנת לעריכה (העתק / שמור טיוטה). **הסוכן לעולם לא שולח הודעה ב-XPlace.**
- `sync_messages.php` משתמש ב-COALESCE על עמודות המטא: payload חלקי (thread_id + תאריך בלבד) לא מוחק כותרת/שם לקוח קיימים.

---

## 4. שרת ודיפלוי

- **Host:** DigitalOcean droplet, LAMP. RemoteDir: `/var/www/xplace-dashboard`. **IP הדרופלט: 164.90.223.113** (hostname: xplace; הדומיין מאחורי Cloudflare אז DNS לא מגלה אותו).
- **Domain של ה-API/דשבורד:** https://xplace.nintay.com
- **דיפלוי (מ-15/07/26): אוטומטי לגמרי — commit + push ל-main וזהו.**
  - cron על הדרופלט מריץ `/usr/local/bin/xplace-autodeploy.sh` כל דקה: `git fetch` → אם יש commit חדש → `git reset --hard origin/main` → מריץ `api/migrate.php` (idempotent). המקור: `deploy/autodeploy.sh` בריפו (מעדכן את עצמו).
  - `config.php` gitignored ולא נפגע. לוג: `/var/log/xplace-autodeploy.log`.
  - זרימה: עריכה מקומית → `git add/commit/push` → תוך דקה חי ב-https://xplace.nintay.com.
  - `deploy.ps1` הישן (scp) נשאר כגיבוי ידני בלבד.
- **הגנה:** ה-UI מאחורי login (username+password). ה-API endpoints מאחורי `Authorization: Bearer <API_KEY>`.
- **API key (בשימוש הסוכן):** לא נשמר כאן. הערך נמצא ב-`SECRETS.local.md` (gitignored) ובזיכרון של Claude.

> חשוב לרשת: הסנדבוקס (web_fetch / bash curl) חסום מ-xplace.nintay.com (403 allowlist). כל קריאות ה-API של הסוכן עוברות דרך הדפדפן האמיתי (Chrome MCP): לנווט תחילה ל-origin של xplace.nintay.com ואז להריץ `fetch()` same-origin עם ה-Bearer header בתוך `javascript_tool`.

---

## 5. המשימות המתוזמנות

| taskId | מתי | מצב | תפקיד |
|---|---|---|---|
| `xplace-proposals-agent` | 08/11/14/17/20 יומי | **פעיל** | הליבה: סריקה, טיוטות, הגשה אוטומטית, סיכום WhatsApp |
| `xplace-scan-only` | ידני בלבד (כפתור) | **פעיל** | חלקי: STEP 1-5 בלבד — סריקת חדשות + טיוטות. בלי הסרות/הגשה/תוצאות |
| `xplace-submit-only` | ידני בלבד (כפתור) | **פעיל** | חלקי: get_approved + STEP 5.5 בלבד — הגשת מאושרות. בלי סריקה |
| `xplace-requests-only` | ידני בלבד (כפתור) | **פעיל** | חלקי: STEP 0.5 בלבד — מילוי בקשות להצעה וכתיבה מחדש |
| `daily-inbox-digest` | 09:00 יומי | **פעיל** | תקציר תיבת מייל יומי, לפי דחיפות, בקשות בעברית מתורגמות מראש |
| `markitdown-skill-reminder` | חד-פעמי | כבוי | — |
| `monitor-running-tasks` | כל 15 דק' | כבוי | ניטור משימות תקועות |
| `quota-reset-alarm` | חד-פעמי | כבוי | — |
| `restart-quota-stuck-tasks` | חד-פעמי | כבוי | — |

SKILL.md של הסוכן נמצא ב: `C:\Users\micha\Claude\Scheduled\xplace-proposals-agent\SKILL.md`

**כפתורי הרצה ידנית (נוסף 06/07):** האטרפקט `xplace-run-button` בסיידבר מכיל 4 כפתורים: הרצה מלאה / סריקת חדשות בלבד / הגשת מאושרות בלבד / מילוי בקשות להצעה. המשימות החלקיות הן ad-hoc (בלי cron), הפרומפט שלהן מפנה ל-SKILL.md הראשי ומגביל לשלבים ספציפיים — כך שינוי לוגיקה נעשה רק ב-SKILL.md הראשי. להוספת כפתור חלקי נוסף: יוצרים משימה ad-hoc חדשה באותו פורמט ומוסיפים כפתור באטרפקט.

---

## 6. לוגיקת הסוכן (xplace-proposals-agent) — שלבים

> **יעילות (06/07): בלי צילומי מסך.** כל הקריאות והאימותים דרך ה-DOM בלבד (get_page_text / read_page / javascript_tool), כולל אימות הגשה, מצב התחברות לוואטסאפ ואימות שליחת ההודעה. צילום מסך רק כמוצא אחרון. יעד: 0 לריצה.

- **STEP 0 — הסרת הצעות שנדחו (תמיד ראשון):** `get_withdrawals.php` → מסיר "הסרת המלצה" ב-XPlace → `delete_proposal.php`. פרויקט בלי כפתור הסרה: מנקים מהתור בלבד ומדווחים בנפרד.
- **STEP 0.5 — מילוי הצעות שמיכל ביקשה:** `get_proposal_requests.php`. `notes` = הנחיה מחייבת. `proposal_text` לא ריק = rewrite. → `fill_proposal.php` (נשאר `pending`).
- **STEP 1 — דפוסי דחייה:** `get_rejection_patterns.php`. דפוס שחוזר 2+ פעמים = כלל סינון. **תחרות/מספר מגישים לעולם לא סיבת דחייה.**
- **STEP 1.2 — לקחים:** `get_learnings.php`. מיישם תוצאות אמת: כיול מחיר כלפי מטה על `rejected_price`, שחזור זווית/מחיר על `advanced/won`, הורדת עדיפות על `bad_fit`. ייעוץ בלבד.
- **STEP 1.4 — בדיקת הודעות (התראה בלבד):** `get_messages.php` → סורק `/il/m` (DOM בלבד) → פותח רק שרשורים שהשתנו (עד 8) כדי לקבוע מי כתב אחרון → `sync_messages.php`. **הסוכן לא עונה ולא שולח כלום ב-XPlace.** `message_alerts` נכנס ל-change-gate: אם רק הודעות השתנו, הריצה מדלגת על השאר אבל כן שולחת וואטסאפ עם סעיף "הודעות ממתינות למענה" בלבד.
- **STEP 1.5 — change-gate:** `get_dashboard_status.php` מחזיר `known_project_ids` + מונים. סורק `/il/rec`, מחשב `new_ids`. אם אין חדש, אין הסרות, אין בקשות, ואין מאושרים לשליחה — **מדלגים על שאר הריצה ולא שולחים WhatsApp**. זו התנהגות תקינה.
- **STEP 2-3 — סריקה:** פותח רק את דפי ה-`new_ids`.
- **STEP 4 — סיווג:** לא-רלוונטי (פחות מ-3 ימים, פיתוח טהור ללא ניהול, כתיבה/תרגום/עיצוב, נוכחות פיזית, תחומים מחוץ לקו) נשמר עם `agent_notes` והצעה ריקה. רלוונטי (WordPress/Woo/Shopify/AI/אוטומציות/API/CRM/eCommerce/ניהול) → טיוטה.
- **STEP 5 — כתיבת הצעה:** ראה כללי כתיבה למטה.
- **STEP 5.5 — הגשה אוטומטית:** רק סטטוס `approved`. גארד לפני הגשה: טופס קיים, לא הוגש כבר, הפרויקט פתוח, `proposal_text` לא ריק. retry אחד על 5xx/timeout. אחרי הגשה מאומתת → `action.php action=submitted&id=ROWID`.
- **STEP 5.7 — זיהוי תוצאות:** `get_outcome_candidates.php` → פותח דפי פרויקטים שנשלחו וללא לקח מאושר. רק אות ברור בעמוד (נסגר/הוענק) → טיוטת לקח `confirmed=0` דרך `set_outcome.php`. לא ממציא סיבות רכות. מיכל מאשרת ב-`learnings.php`.
- **STEP 6 — סיכום WhatsApp (מותנה):** רק אם משהו קרה בפועל. יעד: 048373730 (בינ"ל 97248373730). fallback: טיוטת Gmail ל-michal@nintay.com אם WhatsApp Web לא מחובר. שורה ראשונה תמיד: `[N] פרויקטים ממתינים לאישורך`. **פורמט רזה (עודכן 06/07): בלי מדד פעילות בוואטסאפ** (עובר ל-run-summary בלבד), בלי מונים בכותרות, כותרות פרויקטים מקוצרות לעד ~5 מילים, מונה בשורה רק כשגדול מ-0, לינק אחד בלבד (הדשבורד) בשורה האחרונה. יעד: 10-15 שורות קצרות. כל פריט בשורה נפרדת, כל פרויקט עם "• ", שורת רווח בין קטגוריות, בלי "|" ובלי רשימות בפסיק. השליחה בונה מערך `lines` ומכניסה שורה-שורה עם Shift+Enter (כי WhatsApp Web מקריס `\n` מ-insertText לרווח), הכל בהודעה אחת.

---

## 7. כללי כתיבת הצעות (קריטי — משפיע על תוצאות)

- **אסור em dash "—" בשום מקום.** פסיק/נקודה/נקודתיים במקום.
- **אסור "מיכל כאן".**
- **בלי הוק, בלי משפט פתיחה לפני ההתחלה.** מתחילים ישירות במילים "היי, שמי מיכל". (עדכון 01/07: פורמט ההוק הישן הביא אפס תגובות ואפס פרויקטים סגורים — זה שינוי מכוון, לא להחזיר הוק.)
- מיד אחרי "היי, שמי מיכל" — להוביל עם 1-2 היכולות שהפרויקט **ביקש במפורש**, לא בלורב גנרי. להתאים את הפתיח לתחום (אתרים/וורדפרס מול AI/אוטומציות), לא לכפות פריימינג WooCommerce על פרויקט שאינו כזה.
- טון ישיר ועובדתי, בלי מילוי ובלי התנצלויות.
- **סגירה חייבת:** דוגמה קונקרטית אחת של עבודה רלוונטית + שאלה ספציפית אחת שמחייבת תשובה, קשורות בדיוק לפרויקט.
- אורך: עד ~120 מילים. חתימה: `מיכל | 050-630-5200 | michal@nintay.com`.
- מחיר דיפולט: **260** (`price_type: hourly`) אלא אם הפרויקט מחייב אחרת.

---

## 8. לקחים / החלטות פתוחות

- **13/07: נוספה בדיקת הודעות** (טבלת `messages`, שלושה endpoints, עמוד `messages.php`, STEP 1.4 בסוכן). ההחלטה המפורשת: **התראה בלבד, בלי מענה אוטומטי ובלי טיוטות מענה.** הסוכן מזהה שלקוח כתב ולא נענה, מיכל עונה בעצמה.
- **13/07: `partials/footer.php` היה חסר בשרת** (index.php כלל אותו והפיל PHP Warning בלוג). הועלה ותוקן.

- **07/07: רידיזיין ב-XPlace שבר את "תוכן המודעה".** `og:description` נעלם ו-meta description הפך לטקסט שיווקי גנרי ("פרסמו פרויקט ב-XPlace..."). התיאור האמיתי יושב ב-HTML בתוך `<p class="project_description__XXXX">` (server-rendered, נגיש גם ל-curl). תוקן ב-`get_job_content.php` (חילוץ מה-p, פסילת הטקסט הגנרי, ריפוי cache ישן אוטומטי) וב-`index.php` (תיאור גנרי שמור ב-DB נחשב חסר ומרוענן). ה-URL גם עושה redirect מ-`/il/job/ID` ל-`/project?id=ID`.
- **07/07 דיפלוי:** `ssh.exe` של Windows לא רץ מתוך תהליכים אוטומטיים (נכשל שקט). מה שעובד: `plink`/`pscp` של PuTTY עם `C:\Users\micha\.ssh\id_rsa.ppk` ו-hostkey `SHA256:1W1DDLRaB9CUSd6DuFajgCSyY1nzl2bjFWzSui1CJU4` מול 164.90.223.113. מהטרמינל האינטראקטיבי של מיכל `deploy.ps1` עובד רגיל.

- **05/07:** נוספה לולאת למידה (`learnings` + `set_outcome.php` + `get_learnings.php` + STEP 1.2 בסוכן). זרעים ראשונים: 214461 (`rejected_price`, מחיר גבוה מדי), 214584 (`advanced`, זום מוצלח, ממתין לתשובה תוך השבוע), 214565 (`bad_fit`, דרש פתרון פנים-מול-פנים + למידה תוך כדי במחיר מגוחך).
- **01/07:** מעבר מפורמט-הוק לפתיח ישיר ("היי, שמי מיכל") בגלל אפס המרות בפורמט הישן.
- ה-change-gate נוסף כדי לחסוך ריצות יקרות כשרשימת ההמלצות לא השתנתה.
- ה-UI login-gated — לעולם לא להסתמך על scraping של ה-UI המחובר; רק endpoints + Bearer.

---

## 8.5 מסלול מהיר — מודעה בודדת (נוסף 06/07)

כשמיכל רוצה טיפול מיידי במודעה אחת בלי לחכות לריצה המתוזמנת המלאה (~20 דק'):

**טריגר:** מיכל כותבת בצ'אט של הפרויקט `מודעה בודדת: <URL>` (או כל ניסוח דומה עם לינק אחד למודעה ב-XPlace).

**מה הסוכן עושה (ורק את זה):**
1. פותח את דף המודעה בלבד (Chrome MCP, DOM בלבד, בלי צילומי מסך). מדלג לגמרי על STEP 0-4 והסריקה המלאה.
2. קורא `get_learnings.php` + `get_rejection_patterns.php` (שתי קריאות מהירות) כדי לכייל מחיר וזווית.
3. כותב טיוטה לפי כללי סעיף 7, ומציג אותה למיכל **בצ'אט** (טקסט מלא + מחיר) לאישור/עריכה.
4. אחרי אישור בצ'אט: מגיש ב-XPlace מיד (עם הגארדים של STEP 5.5), ואז רושם בדשבורד: `add_proposal.php` + `action.php action=submitted` — כדי שה-change-gate והלמידה יכירו את הפרויקט.
5. אם מיכל דוחה/עוזבת באמצע: שומר כ-`pending` בדשבורד ולא מגיש.

יעד זמן: 3-5 דקות מלינק להגשה. לא שולחים WhatsApp במסלול הזה (מיכל כבר בצ'אט).

## 9. לעבודה על שדרוגים מכאן

- שינוי לוגיקת הסוכן → עורכים את `C:\Users\micha\Claude\Scheduled\xplace-proposals-agent\SKILL.md`.
- שינוי בקוד הדשבורד/API → עורכים כאן, `git commit + push` — הדרופלט מושך אוטומטית תוך דקה.
- שינוי סכימת DB → מוסיפים migration ב-`api/migrate.php` וקוראים לו פעם אחת עם ה-Bearer key.
- רישום/עריכת לקח → הכי פשוט דרך העמוד `learnings.php` (מיכל). תכנותית: POST ל-`set_outcome.php` עם `{project_id, outcome, lesson, price?, confirmed?}` (upsert) דרך הדפדפן (Bearer), כי הסנדבוקס חסום מ-xplace.nintay.com.
- קובץ זה הוא הסינגל-סורס לקונטקסט. לעדכן אותו כשמשתנה: לוגיקה, שרת, מפתחות, כללי כתיבה, או מבנה DB.
