"""Nintay Brain - central notify service.
One webhook every Nintay system calls instead of configuring its own mail.
POST /notify {channel, to, subject, text} with Bearer token.

Transports, in priority order (DigitalOcean blocks outbound SMTP):
  1. Gmail API over HTTPS (GMAIL_CLIENT_ID/SECRET/REFRESH_TOKEN) - same
     proven flow BRISK uses (google_auth.py + toggl.send_email).
  2. Resend HTTPS API (RESEND_API_KEY).
  3. Plain SMTP - fallback for non-DO hosts only.
Config is read FRESH per request: editing /var/www/brain.env needs no restart.
"""
import base64
import json
import os
import smtplib
import ssl
import urllib.error
import urllib.parse
import urllib.request
from email.message import EmailMessage
from email.utils import formataddr

from fastapi import FastAPI, Header, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel

ENV_PATH = os.environ.get("BRAIN_ENV", "/var/www/brain.env")


def config() -> dict:
    out = {}
    try:
        with open(ENV_PATH, "r", encoding="utf-8") as fh:
            for line in fh:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                out[k.strip()] = v.strip()  # later duplicates win
    except FileNotFoundError:
        pass
    return out


app = FastAPI(title="Nintay Brain", docs_url=None, redoc_url=None)


def _transports(cfg) -> dict:
    return {
        "gmail": all(cfg.get(k) for k in
                     ("GMAIL_CLIENT_ID", "GMAIL_CLIENT_SECRET", "GMAIL_REFRESH_TOKEN")),
        "resend": bool(cfg.get("RESEND_API_KEY")),
        "smtp": all(cfg.get(k) for k in
                    ("SMTP_HOST", "SMTP_USER", "SMTP_PASS", "SMTP_FROM")),
    }


@app.get("/health")
def health():
    t = _transports(config())
    active = "gmail" if t["gmail"] else ("resend" if t["resend"] else ("smtp" if t["smtp"] else "none"))
    return {"ok": True, "service": "brain-notify",
            "email_ready": any(t.values()), "transport": active}


class NotifyIn(BaseModel):
    channel: str = "email"
    to: str
    subject: str = ""
    text: str = ""


def _mime(cfg, to, subject, text) -> EmailMessage:
    sender = cfg.get("GMAIL_SENDER") or cfg.get("EMAIL_FROM") or cfg.get("SMTP_FROM") or "me"
    msg = EmailMessage()
    msg["From"] = formataddr(("Nintay Brain", sender))
    msg["To"] = to
    msg["Subject"] = subject or "(no subject)"
    msg.set_content(text or "")
    return msg


def _gmail_access_token(cfg) -> str:
    payload = urllib.parse.urlencode({
        "client_id": cfg["GMAIL_CLIENT_ID"],
        "client_secret": cfg["GMAIL_CLIENT_SECRET"],
        "refresh_token": cfg["GMAIL_REFRESH_TOKEN"],
        "grant_type": "refresh_token",
    }).encode("utf-8")
    req = urllib.request.Request("https://oauth2.googleapis.com/token",
                                 data=payload, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            return json.loads(resp.read().decode("utf-8")).get("access_token", "")
    except urllib.error.HTTPError as e:
        raise HTTPException(status_code=502,
                            detail="gmail token: " + e.read().decode("utf-8", "ignore")[:200])


def _send_gmail(cfg, to, subject, text):
    access = _gmail_access_token(cfg)
    if not access:
        raise HTTPException(status_code=502, detail="gmail: no access token")
    raw = base64.urlsafe_b64encode(_mime(cfg, to, subject, text).as_bytes()).decode()
    req = urllib.request.Request(
        "https://gmail.googleapis.com/gmail/v1/users/me/messages/send",
        data=json.dumps({"raw": raw}).encode(), method="POST",
        headers={"Authorization": "Bearer " + access,
                 "Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            json.loads(r.read())
    except urllib.error.HTTPError as e:
        raise HTTPException(status_code=502,
                            detail="gmail send: " + e.read().decode("utf-8", "ignore")[:200])


def _send_resend(cfg, to, subject, text):
    sender = cfg.get("EMAIL_FROM") or cfg.get("SMTP_FROM") or "onboarding@resend.dev"
    payload = json.dumps({"from": sender, "to": [to],
                          "subject": subject or "(no subject)",
                          "text": text or ""}).encode("utf-8")
    req = urllib.request.Request(
        "https://api.resend.com/emails", data=payload,
        headers={"Authorization": "Bearer " + cfg["RESEND_API_KEY"],
                 "Content-Type": "application/json"}, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        raise HTTPException(status_code=502,
                            detail="resend: " + e.read().decode("utf-8", "ignore")[:300])


def _send_smtp(cfg, to, subject, text):
    host = cfg.get("SMTP_HOST")
    port = int(cfg.get("SMTP_PORT", "587"))
    user = cfg.get("SMTP_USER")
    password = cfg.get("SMTP_PASS")
    msg = _mime(cfg, to, subject, text)
    ctx = ssl.create_default_context()
    if port == 465:
        with smtplib.SMTP_SSL(host, port, context=ctx, timeout=20) as s:
            s.login(user, password)
            s.send_message(msg)
    else:
        with smtplib.SMTP(host, port, timeout=20) as s:
            s.starttls(context=ctx)
            s.login(user, password)
            s.send_message(msg)


@app.post("/notify")
def notify(body: NotifyIn, authorization: str = Header(default="")):
    cfg = config()
    token = cfg.get("NOTIFY_TOKEN", "")
    if not token or authorization != "Bearer " + token:
        raise HTTPException(status_code=401, detail="bad or missing token")
    if body.channel != "email":
        raise HTTPException(status_code=400, detail="unsupported channel: " + body.channel)
    if not body.to:
        raise HTTPException(status_code=400, detail="missing 'to'")
    t = _transports(cfg)
    if t["gmail"]:
        _send_gmail(cfg, body.to, body.subject, body.text)
    elif t["resend"]:
        _send_resend(cfg, body.to, body.subject, body.text)
    elif t["smtp"]:
        _send_smtp(cfg, body.to, body.subject, body.text)
    else:
        raise HTTPException(status_code=503, detail="no email transport configured")
    return JSONResponse({"ok": True, "sent": body.to})

# --- Brain board: the agents dashboard, behind basic auth (01.09.26) ---
import secrets as _secrets
from fastapi import Request as _Request
from fastapi.responses import FileResponse as _FileResponse, Response as _Response

_BOARD_PATH = os.environ.get("BRAIN_BOARD", "/opt/brain/board/index.html")


@app.get("/")
def board(request: _Request):
    cfg = config()
    user, pw = cfg.get("BOARD_USER", ""), cfg.get("BOARD_PASS", "")
    auth = request.headers.get("authorization", "")
    ok = False
    if user and pw and auth.lower().startswith("basic "):
        try:
            got = base64.b64decode(auth.split(None, 1)[1]).decode("utf-8")
            ok = _secrets.compare_digest(got, f"{user}:{pw}")
        except Exception:
            ok = False
    if not ok:
        return _Response(status_code=401,
                         headers={"WWW-Authenticate": 'Basic realm="Nintay Brain"'})
    if not os.path.exists(_BOARD_PATH):
        raise HTTPException(status_code=404, detail="board not published yet")
    return _FileResponse(_BOARD_PATH, media_type="text/html; charset=utf-8")
