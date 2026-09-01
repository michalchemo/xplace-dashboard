# Brain board auth - session login like BRISK (01.09.26).
# Config in brain.env: BOARD_USER, BOARD_PASS, BOARD_SECRET. Read fresh per request.
import hashlib
import hmac as hm
import os
import time
from urllib.parse import parse_qs

BOARD_PATH = os.environ.get("BRAIN_BOARD", "/opt/brain/board/index.html")
SESSION_DAYS = 30

LOGIN_HTML = """<!doctype html><html lang="he" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>כניסה - המוח של נינתאי</title><style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
background:#F7F6FA;font-family:Assistant,system-ui,sans-serif;color:#26223A}
.card{background:#fff;border:1px solid #E6E2F0;border-radius:16px;padding:36px 32px;
width:320px;box-shadow:0 8px 24px rgba(38,34,58,.08)}
.dot{width:12px;height:12px;border-radius:50%;background:#6B4BCC;
box-shadow:0 0 0 4px #EFEAFB;display:inline-block;margin-inline-end:8px}
h1{font-size:20px;margin:0 0 4px}p{color:#8A84A0;font-size:14px;margin:0 0 20px}
label{display:block;font-weight:600;font-size:14px;margin:12px 0 4px}
input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #E6E2F0;
border-radius:9px;font-size:15px;font-family:inherit}
input:focus{outline:2px solid #6B4BCC;border-color:#6B4BCC}
button{width:100%;margin-top:20px;padding:11px;border:0;border-radius:9px;
background:#6B4BCC;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
button:hover{background:#5a3eb3}.err{color:#B3261E;font-size:13.5px;margin-top:12px}
</style></head><body><form class="card" method="post" action="/login">
<h1><span class="dot"></span>המוח של נינתאי</h1><p>מרכז הבקרה של הסוכנים</p>
<label>משתמש</label><input name="user" autocomplete="username" required>
<label>סיסמה</label><input name="password" type="password" autocomplete="current-password" required>
<button type="submit">כניסה</button><div class="err">{err}</div>
</form></body></html>"""


def sign(val: str, secret: str) -> str:
    return hm.new(secret.encode(), val.encode(), hashlib.sha256).hexdigest()


def session_ok(request, cfg) -> bool:
    secret = cfg.get("BOARD_SECRET", "")
    tok = request.cookies.get("brain_session", "")
    if not secret or "." not in tok:
        return False
    exp, sig = tok.rsplit(".", 1)
    try:
        if int(exp) < time.time():
            return False
    except ValueError:
        return False
    return hm.compare_digest(sig, sign(exp, secret))


def check_login(body: str, cfg) -> bool:
    form = {k: v[0] for k, v in parse_qs(body).items()}
    user, pw = cfg.get("BOARD_USER", ""), cfg.get("BOARD_PASS", "")
    return bool(user and pw
                and hm.compare_digest(form.get("user", ""), user)
                and hm.compare_digest(form.get("password", ""), pw))
