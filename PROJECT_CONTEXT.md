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
├── action.php          — approve / dismiss / submitted / restore / request_proposal
├── schema.sql          — יצירת ה-DB (רץ פעם אחת)
├── deploy.ps1          — סקריפט דיפלוי לדרופלט
└── api/                — endpoints שהסוכן קורא (Bearer key)
    ├── add_proposal.php          — POST: הוספת טיוטה
    ├── fill_proposal.php         — POST: מילוי/כתיבה מחדש של הצעה שמיכל ביקשה
    ├── delete_proposal.php       — POST: מחיקה מהתור
    ├── get_approved.php          — GET: הצעות מאושרות להגשה
    ├── get_dashboard_status.php  — GET: change-gate (known_project_ids, pending/approved counts)
    ├── get_proposal_requests.php — GET: פרויקטים שמיכל ביקשה עבורם הצעה
    ├── get_rejection_patterns.php— GET: דפוסי דחייה קודמים
    ├── get_withdrawals.php       — GET: הצעות להסרה מ-XPlace
    ├── get_job_content.php       — GET
    └── migrate.php               — הרצת עדכוני סכימה על ה-DB החי
```

### סכימת DB (טבלה `proposals`)
עמודות ליבה: `id, project_id (UNIQUE), project_title, project_url, proposal_text, price, price_type ('hourly'|'fixed'), status, notes, created_at, updated_at`.
`status` ENUM: `pending | approved | dismissed | submitted`.
עמודות שנוספו דרך `migrate.php`: `proposal_requested`, `agent_notes`, `withdrawal_done`.

- `notes` = ההנחיה של מיכל לטיוטה (עוקף את הפריימינג הדיפולטי).
- `agent_notes` = הערת הסוכן (למשל סיבת דחייה). לא כותבים "תחרות גבוהה" לעולם.
- `proposal_text` לא ריק על בקשה = בקשת **rewrite**.

---

## 4. שרת ודיפלוי

- **Host:** DigitalOcean droplet, LAMP. RemoteDir: `/var/www/xplace-dashboard`.
- **Domain של ה-API/דשבורד:** https://xplace.nintay.com
- **דיפלוי:** מהמחשב של מיכל: `powershell -ExecutionPolicy Bypass -File .\deploy.ps1`
  - הסקריפט מזהה אוטומטית את הדרופלט מרשימת IP מועמדים, מגבה קבצים (תיקיית `_backup_<timestamp>`), מעלה ב-scp (או `-UseGit`), ומוודא. שום דבר לא נמחק.
  - IP מועמדים בסקריפט: 46.101.85.13, 167.99.130.154, 161.35.78.39, 164.90.223.113.
- **הגנה:** ה-UI מאחורי login (username+password). ה-API endpoints מאחורי `Authorization: Bearer <API_KEY>`.
- **API key (בשימוש הסוכן):** לא נשמר כאן. הערך נמצא ב-`SECRETS.local.md` (gitignored) ובזיכרון של Claude.

> חשוב לרשת: הסנדבוקס (web_fetch / bash curl) חסום מ-xplace.nintay.com (403 allowlist). כל קריאות ה-API של הסוכן עוברות דרך הדפדפן האמיתי (Chrome MCP): לנווט תחילה ל-origin של xplace.nintay.com ואז להריץ `fetch()` same-origin עם ה-Bearer header בתוך `javascript_tool`.

---

## 5. המשימות המתוזמנות

| taskId | מתי | מצב | תפקיד |
|---|---|---|---|
| `xplace-proposals-agent` | 08/11/14/17/20 יומי | **פעיל** | הליבה: סריקה, טיוטות, הגשה אוטומטית, סיכום WhatsApp |
| `daily-inbox-digest` | 09:00 יומי | **פעיל** | תקציר תיבת מייל יומי, לפי דחיפות, בקשות בעברית מתורגמות מראש |
| `markitdown-skill-reminder` | חד-פעמי | כבוי | — |
| `monitor-running-tasks` | כל 15 דק' | כבוי | ניטור משימות תקועות |
| `quota-reset-alarm` | חד-פעמי | כבוי | — |
| `restart-quota-stuck-tasks` | חד-פעמי | כבוי | — |

SKILL.md של הסוכן נמצא ב: `C:\Users\micha\Claude\Scheduled\xplace-proposals-agent\SKILL.md`

---

## 6. לוגיקת הסוכן (xplace-proposals-agent) — שלבים

- **STEP 0 — הסרת הצעות שנדחו (תמיד ראשון):** `get_withdrawals.php` → מסיר "הסרת המלצה" ב-XPlace → `delete_proposal.php`. פרויקט בלי כפתור הסרה: מנקים מהתור בלבד ומדווחים בנפרד.
- **STEP 0.5 — מילוי הצעות שמיכל ביקשה:** `get_proposal_requests.php`. `notes` = הנחיה מחייבת. `proposal_text` לא ריק = rewrite. → `fill_proposal.php` (נשאר `pending`).
- **STEP 1 — דפוסי דחייה:** `get_rejection_patterns.php`. דפוס שחוזר 2+ פעמים = כלל סינון. **תחרות/מספר מגישים לעולם לא סיבת דחייה.**
- **STEP 1.5 — change-gate:** `get_dashboard_status.php` מחזיר `known_project_ids` + מונים. סורק `/il/rec`, מחשב `new_ids`. אם אין חדש, אין הסרות, אין בקשות, ואין מאושרים לשליחה — **מדלגים על שאר הריצה ולא שולחים WhatsApp**. זו התנהגות תקינה.
- **STEP 2-3 — סריקה:** פותח רק את דפי ה-`new_ids`.
- **STEP 4 — סיווג:** לא-רלוונטי (פחות מ-3 ימים, פיתוח טהור ללא ניהול, כתיבה/תרגום/עיצוב, נוכחות פיזית, תחומים מחוץ לקו) נשמר עם `agent_notes` והצעה ריקה. רלוונטי (WordPress/Woo/Shopify/AI/אוטומציות/API/CRM/eCommerce/ניהול) → טיוטה.
- **STEP 5 — כתיבת הצעה:** ראה כללי כתיבה למטה.
- **STEP 5.5 — הגשה אוטומטית:** רק סטטוס `approved`. גארד לפני הגשה: טופס קיים, לא הוגש כבר, הפרויקט פתוח, `proposal_text` לא ריק. retry אחד על 5xx/timeout. אחרי הגשה מאומתת → `action.php action=submitted&id=ROWID`.
- **STEP 6 — סיכום WhatsApp (מותנה):** רק אם משהו קרה בפועל. יעד: 048373730 (בינ"ל 97248373730). fallback: טיוטת Gmail ל-michal@nintay.com אם WhatsApp Web לא מחובר. שורה ראשונה תמיד: `[N] פרויקטים ממתינים לאישורך`.

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

- **01/07:** מעבר מפורמט-הוק לפתיח ישיר ("היי, שמי מיכל") בגלל אפס המרות בפורמט הישן.
- ה-change-gate נוסף כדי לחסוך ריצות יקרות כשרשימת ההמלצות לא השתנתה.
- ה-UI login-gated — לעולם לא להסתמך על scraping של ה-UI המחובר; רק endpoints + Bearer.

---

## 9. לעבודה על שדרוגים מכאן

- שינוי לוגיקת הסוכן → עורכים את `C:\Users\micha\Claude\Scheduled\xplace-proposals-agent\SKILL.md`.
- שינוי בקוד הדשבורד/API → עורכים כאן ואז `deploy.ps1` להעלאה לדרופלט.
- שינוי סכימת DB → מוסיפים migration ב-`api/migrate.php` וקוראים לו פעם אחת עם ה-Bearer key.
- קובץ זה הוא הסינגל-סורס לקונטקסט. לעדכן אותו כשמשתנה: לוגיקה, שרת, מפתחות, כללי כתיבה, או מבנה DB.
