<?php
/*
 * partials/footer.php — Nintay footer (logo + feedback + WhatsApp).
 * Change any of these three in one place:
 */
if (!defined('NINTAY_WHATSAPP'))  { define('NINTAY_WHATSAPP', '97248373730'); }
if (!defined('NINTAY_BAR_COLOR')) { define('NINTAY_BAR_COLOR', '#0b2f66'); }
if (!defined('NINTAY_LOGO_URL'))  { define('NINTAY_LOGO_URL', 'https://www.nintay.com/wp-content/uploads/2019/12/3-logo-1024x412-2-1.png'); }

$wa_text = rawurlencode("היי Nintay, יש לי הערה או רעיון לשיפור לגבי הדשבורד:");
$wa_url  = 'https://wa.me/' . NINTAY_WHATSAPP . '?text=' . $wa_text;
?>
<footer style="margin-top:48px;background:<?= NINTAY_BAR_COLOR ?>;color:#fff;
  font-family:'Segoe UI',Arial,sans-serif;direction:rtl">
  <div style="max-width:1100px;margin:0 auto;padding:16px 18px;display:flex;flex-wrap:wrap;
    align-items:center;justify-content:space-between;gap:14px">
    <div style="display:flex;align-items:center;gap:12px">
      <span style="background:#fff;border-radius:10px;padding:8px 12px;display:inline-flex;align-items:center">
        <img src="<?= NINTAY_LOGO_URL ?>" alt="Nintay" style="height:26px;width:auto;display:block">
      </span>
      <span style="font-size:12px;opacity:.8">מופעל על ידי Nintay</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;font-size:13px">
      <span style="opacity:.95">יש לך הערה או רעיון לשיפור? נשמח לשמוע</span>
      <a href="<?= $wa_url ?>" target="_blank" rel="noopener"
         style="display:inline-flex;align-items:center;gap:6px;background:#25D366;color:#fff;
         text-decoration:none;padding:8px 14px;border-radius:8px;font-weight:600">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M17.5 14.4c-.3-.2-1.7-.8-2-.9-.3-.1-.5-.2-.7.2-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-1.8-.9-3-1.6-4.2-3.6-.3-.5.3-.5.8-1.6.1-.2 0-.4 0-.5 0-.2-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.3 5.1 4.6 1.9.8 2.6.9 3.5.8.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3z"/><path d="M12 2A10 10 0 0 0 3.5 17.2L2 22l4.9-1.4A10 10 0 1 0 12 2zm0 18.3c-1.5 0-2.9-.4-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8.3 8.3 0 1 1 12 20.3z"/></svg>
        וואטסאפ ל-Nintay
      </a>
    </div>
  </div>
</footer>
