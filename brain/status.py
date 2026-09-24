"""Nintay Brain - board status (24.09.26).

compute_status() -> dict, cached 20s. Never raises: every source degrades to
nulls so the board always renders. Sources:
  - agents: static definitions + last run from logs / jobs table + next cron run
  - brisk:  psql on the BRISK Postgres (DATABASE_URL from /opt/brisk/.env)
  - projects: active BRISK projects with a Toggl project id (manual hours form)
  - jobs:   last 15 rows of the job queue
Helpers _psql / _read_text are module level so tests can monkeypatch them.
"""
import datetime
import os
import re
import subprocess
import threading
import time

import jobs as _jobs

CACHE_TTL = 20
_cache = {"at": 0.0, "data": None}
_lock = threading.Lock()

# key, name, channel, schedule text, cron spec (None = no fixed time), job name
# cron spec: ("daily", "HH:MM") | ("monthly", day, "HH:MM") | ("monthly_range", d1, d2, "HH:MM")
#            | ("weekly", [weekday ints, Mon=0], "HH:MM")
AGENTS = [
    ("hours", "דוח שעות", "שרת + מחשב", "כל ערב 23:00", ("daily", "23:00"), "hours"),
    ("brisk", "סוכן הבריסק", "Cowork · MCP", "לפי דרישה", None, None),
    ("monthend", "חיוב סוף חודש", "שרת + בריסק", "1 לחודש 07:30", ("monthly", 1, "07:30"), "monthend"),
    ("approvals", "סוכן האישורים", "ענן · בריסק MCP", "1-4 לחודש 09:00", ("monthly_range", 1, 4, "09:00"), None),
    ("explace", "אקספלייס", "Claude on Chrome", "מתוזמן", None, None),
    ("morning", "טיוטות בוקר", "מתוזמן · Gmail", "א'-ה' 06:45", ("weekly", [6, 0, 1, 2, 3], "06:45"), None),
    ("nudge", "נודניק מיילים", "מתוזמן · Gmail", "יום א' 07:35", ("weekly", [6], "07:35"), None),
    ("audit_map", "מפת ה-Audit", "Cowork · סקיל", "אחרי כל שיחה", None, None),
    ("product_images", "תמונות מוצרים", "Cowork · סקיל", "לפי דרישה", None, None),
]


def brain_home() -> str:
    return _jobs.brain_home()


def brisk_env_path() -> str:
    return os.environ.get("BRISK_ENV", "/opt/brisk/.env")


# ---------------------------------------------------------------- helpers (patchable)
def _read_text(path: str) -> str:
    try:
        with open(path, "r", encoding="utf-8", errors="replace") as fh:
            return fh.read()
    except OSError:
        return ""


def _mtime_iso(path: str):
    try:
        ts = os.path.getmtime(path)
    except OSError:
        return None
    d = datetime.datetime.fromtimestamp(ts, _jobs.TZ) if _jobs.TZ else datetime.datetime.fromtimestamp(ts)
    return d.replace(microsecond=0).isoformat()


def _database_url() -> str:
    for line in _read_text(brisk_env_path()).splitlines():
        line = line.strip()
        if line.startswith("DATABASE_URL="):
            return line.split("=", 1)[1].strip().strip('"').strip("'")
    return ""


def _psql(sql: str) -> str:
    """Run a query through psql, return raw '|'-separated text. '' on any failure."""
    url = _database_url()
    if not url:
        return ""
    try:
        p = subprocess.run(["psql", url, "-tAc", sql], stdout=subprocess.PIPE,
                           stderr=subprocess.PIPE, timeout=10)
    except (OSError, subprocess.TimeoutExpired):
        return ""
    return p.stdout.decode("utf-8", "replace") if p.returncode == 0 else ""


# ---------------------------------------------------------------- next run
def _hm(s: str):
    h, m = s.split(":")
    return int(h), int(m)


def next_run(spec, now=None):
    """ISO time of the next firing of a cron spec, Israel time. None for on-demand agents."""
    if not spec:
        return None
    now = now or _jobs.now()
    kind = spec[0]
    if kind == "daily":
        h, m = _hm(spec[1])
        cand = now.replace(hour=h, minute=m, second=0)
        if cand <= now:
            cand += datetime.timedelta(days=1)
        return cand.isoformat()
    if kind == "weekly":
        days, (h, m) = spec[1], _hm(spec[2])
        for i in range(0, 8):
            cand = (now + datetime.timedelta(days=i)).replace(hour=h, minute=m, second=0)
            if cand.weekday() in days and cand > now:
                return cand.isoformat()
        return None
    if kind in ("monthly", "monthly_range"):
        d1 = spec[1]
        d2 = spec[2] if kind == "monthly_range" else spec[1]
        h, m = _hm(spec[-1])
        for i in range(0, 62):
            cand = (now + datetime.timedelta(days=i)).replace(hour=h, minute=m, second=0)
            if d1 <= cand.day <= d2 and cand > now:
                return cand.isoformat()
    return None


# ---------------------------------------------------------------- log parsers
_RE_BLOCK = re.compile(r"^דוח שעות אוטומטי\s*-\s*(\d{2}\.\d{2}\.\d{4})")


def parse_hours_log(text: str) -> dict:
    """Summary of hours_agent run.log: last block date + entries, totals over all blocks."""
    blocks = []
    cur = None
    for line in text.splitlines():
        m = _RE_BLOCK.match(line.strip())
        if m:
            cur = {"date": m.group(1), "entered": 0, "missing": 0, "in_missing": False}
            blocks.append(cur)
            continue
        if cur is None:
            continue
        s = line.strip()
        if "לא הוזן" in s and not s.startswith("- "):
            cur["in_missing"] = True
            continue
        if "הוזן ל-Toggl" in s and not s.startswith("- "):
            cur["in_missing"] = False
            continue
        if s.startswith("- "):
            cur["missing" if cur["in_missing"] else "entered"] += 1
    last = blocks[-1] if blocks else None
    out = {"runs": len(blocks), "entries": sum(b["entered"] for b in blocks), "last": None}
    if last:
        out["last"] = {"date": last["date"], "entered": last["entered"], "missing": last["missing"]}
    return out


def _hours_status(home: str) -> dict:
    log_path = os.path.join(home, "hours", "logs", "run.log")
    summary = parse_hours_log(_read_text(log_path))
    last_run = _mtime_iso(log_path)
    result = None
    if summary["last"]:
        b = summary["last"]
        result = "%s · %d הוזנו" % (b["date"], b["entered"])
        if b["missing"]:
            result += " · %d להשלמה" % b["missing"]
    row = _jobs.last_job("hours")
    if row and row.get("finished_at") and (not last_run or row["finished_at"] > last_run):
        last_run = row["finished_at"]
        if row["status"] == "failed":
            result = "ריצה ידנית נכשלה"
    return {"last_run": last_run, "last_result": result, "summary": summary}


def _monthend_status(home: str) -> dict:
    row = _jobs.last_job("monthend")
    if row and row.get("finished_at"):
        res = "הסתיים" if row["status"] == "done" else "נכשל"
        fl = row.get("first_line") or (row.get("output_tail") or "").strip().splitlines()
        if isinstance(fl, list):
            fl = fl[-1].strip() if fl else ""
        return {"last_run": row["finished_at"], "last_result": (res + " · " + fl[:80]) if fl else res}
    log_path = os.path.join(home, "billing", "run.log")
    lines = [ln.strip() for ln in _read_text(log_path).splitlines() if ln.strip()]
    return {"last_run": _mtime_iso(log_path), "last_result": lines[-1][:100] if lines else None}


# ---------------------------------------------------------------- brisk / projects
CLOSED_STATES = {"archived", "done", "closed", "cancelled", "canceled", "rejected", "released"}


def brisk_summary() -> dict:
    raw = _psql("select state, count(*) from tasks where archived_at is null group by state") \
        or _psql("select state, count(*) from tasks group by state")
    if not raw.strip():
        return {"open": None, "by_state": None}
    by_state = {}
    for line in raw.splitlines():
        if "|" not in line:
            continue
        state, cnt = line.rsplit("|", 1)
        try:
            by_state[state.strip() or "none"] = int(cnt.strip())
        except ValueError:
            continue
    if not by_state:
        return {"open": None, "by_state": None}
    open_n = sum(v for k, v in by_state.items() if k.lower() not in CLOSED_STATES)
    return {"open": open_n, "by_state": by_state}


def projects() -> list:
    raw = _psql("select id, name, toggl_project_id from projects "
                "where archived_at is null and toggl_project_id is not null order by name")
    out = []
    for line in raw.splitlines():
        parts = line.split("|")
        if len(parts) < 3:
            continue
        try:
            out.append({"id": int(parts[0]), "name": parts[1].strip(),
                        "toggl_project_id": int(parts[2])})
        except ValueError:
            continue
    return out


# ---------------------------------------------------------------- assemble
def _safe(fn, default):
    try:
        return fn()
    except Exception:
        return default


def _agents(home: str) -> list:
    now = _jobs.now()
    hours = _safe(lambda: _hours_status(home), {"last_run": None, "last_result": None, "summary": None})
    monthend = _safe(lambda: _monthend_status(home), {"last_run": None, "last_result": None})
    out = []
    for key, name, channel, sched, spec, job in AGENTS:
        a = {"key": key, "name": name, "channel": channel, "schedule": sched,
             "last_run": None, "last_result": None,
             "next_run": _safe(lambda: next_run(spec, now), None),
             "can_run": bool(job), "job": job}
        if key == "hours":
            a["last_run"], a["last_result"] = hours["last_run"], hours["last_result"]
        elif key == "monthend":
            a["last_run"], a["last_result"] = monthend["last_run"], monthend["last_result"]
        out.append(a)
    return out, hours.get("summary")


def build_status() -> dict:
    home = brain_home()
    agents, hours_summary = _agents(home)
    return {
        "generated_at": _jobs.now_iso(),
        "agents": agents,
        "hours_log": hours_summary,
        "brisk": _safe(brisk_summary, {"open": None, "by_state": None}),
        "projects": _safe(projects, []),
        "jobs": _safe(lambda: _jobs.list_jobs(15), []),
    }


def compute_status(force: bool = False) -> dict:
    with _lock:
        if not force and _cache["data"] and time.time() - _cache["at"] < CACHE_TTL:
            return _cache["data"]
        data = build_status()
        _cache["data"], _cache["at"] = data, time.time()
        return data


def invalidate():
    with _lock:
        _cache["at"] = 0.0
        _cache["data"] = None
