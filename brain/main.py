"""Nintay Brain - central notify service.
One webhook every Nintay system calls instead of configuring its own mail.
POST /notify {channel, to, subject, text} with Bearer token.
Runs on the existing droplet, port 8001, behind nginx at brain.nintay.com.
"""
import os
import smtplib
import ssl
from email.message import EmailMessage
from email.utils import formataddr

from fastapi import FastAPI, Header, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel

ENV_PATH = os.environ.get("BRAIN_ENV", "/var/www/brain.env")


def _load_env(path: str) -> dict:
    out = {}
    try:
        with open(path, "r", encoding="utf-8") as fh:
            for line in fh:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                out[k.strip()] = v.strip()
    except FileNotFoundError:
        pass
    return out


CFG = _load_env(ENV_PATH)
app = FastAPI(title="Nintay Brain", docs_url=None, redoc_url=None)


@app.get("/health")
def health():
    smtp_ready = all(CFG.get(k) for k in ("SMTP_HOST", "SMTP_USER", "SMTP_PASS", "SMTP_FROM"))
    return {"ok": True, "service": "brain-notify", "smtp_configured": smtp_ready}


class NotifyIn(BaseModel):
    channel: str = "email"
    to: str
    subject: str = ""
    text: str = ""


def _send_email(to: str, subject: str, text: str) -> None:
    host = CFG.get("SMTP_HOST")
    port = int(CFG.get("SMTP_PORT", "587"))
    user = CFG.get("SMTP_USER")
    password = CFG.get("SMTP_PASS")
    sender = CFG.get("SMTP_FROM", user)
    if not (host and user and password and sender):
        raise HTTPException(status_code=503, detail="SMTP not configured on Brain")
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
    token = CFG.get("NOTIFY_TOKEN", "")
    if not token or authorization != f"Bearer {token}":
        raise HTTPException(status_code=401, detail="bad or missing token")
    if body.channel != "email":
        raise HTTPException(status_code=400, detail=f"unsupported channel: {body.channel}")
    if not body.to:
        raise HTTPException(status_code=400, detail="missing 'to'")
    _send_email(body.to, body.subject, body.text)
    return JSONResponse({"ok": True, "sent": body.to})
