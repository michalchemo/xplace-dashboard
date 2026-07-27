"""Nintay Brain - central notify service.
One webhook every Nintay system calls instead of configuring its own mail.
POST /notify {channel, to, subject, text} with Bearer token.

Transport: DigitalOcean blocks outbound SMTP on droplets, so email goes out
over an HTTPS provider API (Resend) when RESEND_API_KEY is set. SMTP is kept
as a fallback for the day the block is lifted or on a non-DO host.
Config is read FRESH per request, so editing /var/www/brain.env takes effect
without restarting the service.
"""
import json
import os
import smtplib
import ssl
import urllib.request
import urllib.error
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


@app.get("/health")
def health():
    cfg = config()
    resend = bool(cfg.get("RESEND_API_KEY"))
    smtp = all(cfg.get(k) for k in ("SMTP_HOST", "SMTP_USER", "SMTP_PASS", "SMTP_FROM"))
    transport = "resend" if resend else ("smtp" if smtp else "none")
    return {"ok": True, "service": "brain-notify",
            "email_ready": resend or smtp, "transport": transport}


class NotifyIn(BaseModel):
    channel: str = "email"
    to: str
    subject: str = ""
    text: str = ""


def _send_resend(cfg, to, subject, text):
    sender = cfg.get("EMAIL_FROM") or cfg.get("SMTP_FROM") or "onboarding@resend.dev"
    payload = json.dumps({
        "from": sender, "to": [to],
        "subject": subject or "(no subject)", "text": text or "",
    }).encode("utf-8")
    req = urllib.request.Request(
        "https://api.resend.com/emails", data=payload,
        headers={"Authorization": "Bearer " + cfg["RESEND_API_KEY"],
                 "Content-Type": "application/json"}, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        detail = e.read().decode("utf-8", "ignore")[:300]
        raise HTTPException(status_code=502, detail="resend: " + detail)


def _send_smtp(cfg, to, subject, text):
    host = cfg.get("SMTP_HOST")
    port = int(cfg.get("SMTP_PORT", "587"))
    user = cfg.get("SMTP_USER")
    password = cfg.get("SMTP_PASS")
    sender = cfg.get("EMAIL_FROM") or cfg.get("SMTP_FROM", user)
    msg = EmailMessage()
    msg["From"] = formataddr(("Nintay Brain", sender))
    msg["To"] = to
    msg["Subject"] = subject or "(no subject)"
    msg.set_content(text or "")
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
    if cfg.get("RESEND_API_KEY"):
        _send_resend(cfg, body.to, body.subject, body.text)
    elif all(cfg.get(k) for k in ("SMTP_HOST", "SMTP_USER", "SMTP_PASS")):
        _send_smtp(cfg, body.to, body.subject, body.text)
    else:
        raise HTTPException(status_code=503, detail="no email transport configured (set RESEND_API_KEY)")
    return JSONResponse({"ok": True, "sent": body.to})