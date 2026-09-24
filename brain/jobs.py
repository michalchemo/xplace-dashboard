"""Nintay Brain - tiny job queue on SQLite (24.09.26).

One background worker thread runs queued jobs one at a time via subprocess.
stdout+stderr are captured: the last 4000 chars land in jobs.output_tail and
the full output is appended to <BRAIN_HOME>/logs/jobs/<id>-<job>.log.

Env overrides (tests / other hosts):
  BRAIN_HOME      default /opt/brain
  BRAIN_JOBS_DB   default <BRAIN_HOME>/jobs.db
  BRAIN_JOBS_LOGS default <BRAIN_HOME>/logs/jobs
  BRAIN_PYTHON    default /usr/bin/python3
  BRAIN_JOBS_WORKER=0 disables the worker (tests)
"""
import datetime
import json
import os
import re
import sqlite3
import subprocess
import threading

try:
    from zoneinfo import ZoneInfo
    TZ = ZoneInfo("Asia/Jerusalem")
except Exception:  # pragma: no cover - tzdata missing
    TZ = None

HERE = os.path.dirname(os.path.abspath(__file__))
TAIL_CHARS = 4000
JOB_TIMEOUT = 3600

JOB_LABELS = {
    "hours": "דוח שעות",
    "monthend": "חיוב סוף חודש",
    "hours_manual": "שעות ידני",
    "refresh": "רענון",
}

_RE_DAY = re.compile(r"^\d{4}-\d{2}-\d{2}$")
_RE_TIME = re.compile(r"^\d{2}:\d{2}(:\d{2})?$")


class JobError(ValueError):
    """Bad job name or bad args - maps to HTTP 400."""


def brain_home() -> str:
    return os.environ.get("BRAIN_HOME", "/opt/brain")


def db_path() -> str:
    return os.environ.get("BRAIN_JOBS_DB", os.path.join(brain_home(), "jobs.db"))


def log_dir() -> str:
    return os.environ.get("BRAIN_JOBS_LOGS", os.path.join(brain_home(), "logs", "jobs"))


def python_bin() -> str:
    return os.environ.get("BRAIN_PYTHON", "/usr/bin/python3")


def now() -> datetime.datetime:
    d = datetime.datetime.now(TZ) if TZ else datetime.datetime.now()
    return d.replace(microsecond=0)


def now_iso() -> str:
    return now().isoformat()


# ---------------------------------------------------------------- validation
def validate_day(day: str) -> str:
    day = (day or "").strip()
    if not _RE_DAY.match(day):
        raise JobError("יום לא תקין (YYYY-MM-DD)")
    try:
        datetime.date.fromisoformat(day)
    except ValueError:
        raise JobError("יום לא קיים")
    return day


def validate_time(val: str, label: str) -> str:
    val = (val or "").strip()
    if not _RE_TIME.match(val):
        raise JobError("%s: שעה לא תקינה (HH:MM)" % label)
    h, m = int(val[:2]), int(val[3:5])
    if h > 23 or m > 59:
        raise JobError("%s: שעה לא קיימת" % label)
    return val


def validate_manual(args) -> dict:
    args = args or {}
    if not isinstance(args, dict):
        raise JobError("args must be an object")
    day = validate_day(args.get("day", ""))
    start = validate_time(args.get("start", ""), "התחלה")
    end = validate_time(args.get("end", ""), "סיום")
    if end <= start:
        raise JobError("סיום חייב להיות אחרי ההתחלה")
    try:
        pid = int(str(args.get("toggl_project_id", "")).strip())
    except ValueError:
        raise JobError("toggl_project_id חייב להיות מספר")
    desc = str(args.get("description", "")).replace("\r", " ").replace("\n", " ")
    desc = desc.replace(",", ";").strip()
    if not desc:
        raise JobError("תיאור חסר")
    client = str(args.get("client", "")).replace("\r", " ").replace("\n", " ")
    client = client.replace(",", ";").strip()
    return {"day": day, "start": start, "end": end, "toggl_project_id": pid,
            "client": client, "description": desc}


# ---------------------------------------------------------------- job builders
def _build_hours(args):
    argv = [python_bin(), os.path.join(brain_home(), "hours", "hours_agent.py")]
    day = (args or {}).get("day") if isinstance(args, dict) else None
    if day:
        argv.append(validate_day(day))
    return argv


def _build_monthend(args):
    return ["/bin/bash", "-c",
            "cd /opt/brisk && set -a && . ./.env && set +a && "
            ".venv/bin/python /opt/brain/billing/monthend_agent.py"]


def _build_hours_manual(args):
    v = validate_manual(args)
    return [python_bin(), os.path.join(HERE, "scripts", "hours_manual.py"),
            "--day", v["day"], "--start", v["start"], "--end", v["end"],
            "--project", str(v["toggl_project_id"]), "--client", v["client"],
            "--description", v["description"]]


def _build_refresh(args):
    return [python_bin(), "-c", "print('refresh ok')"]


JOBS = {
    "hours": _build_hours,
    "monthend": _build_monthend,
    "hours_manual": _build_hours_manual,
    "refresh": _build_refresh,
}


def build_argv(job: str, args) -> list:
    builder = JOBS.get(job)
    if not builder:
        raise JobError("unknown job: " + job)
    return builder(args)


# ---------------------------------------------------------------- storage
_SCHEMA = """
create table if not exists jobs (
  id integer primary key autoincrement,
  job text not null,
  args_json text not null default '{}',
  status text not null default 'queued',
  created_at text not null,
  started_at text,
  finished_at text,
  output_tail text,
  exit_code integer
);
create index if not exists jobs_status on jobs(status, id);
"""
_COLS = ("id", "job", "args_json", "status", "created_at", "started_at",
         "finished_at", "output_tail", "exit_code")


def _conn():
    os.makedirs(os.path.dirname(db_path()) or ".", exist_ok=True)
    c = sqlite3.connect(db_path(), timeout=15)
    c.row_factory = sqlite3.Row
    return c


def init_db():
    with _conn() as c:
        c.executescript(_SCHEMA)
        # A service restart kills a running subprocess: do not leave it 'running' forever.
        c.execute("update jobs set status='failed', finished_at=?, "
                  "output_tail=coalesce(output_tail,'') || '\n[השירות הופעל מחדש באמצע הריצה]' "
                  "where status='running'", (now_iso(),))


def _row(r) -> dict:
    d = {k: r[k] for k in _COLS}
    try:
        d["args"] = json.loads(d.pop("args_json") or "{}")
    except ValueError:
        d["args"] = {}
    d["label"] = JOB_LABELS.get(d["job"], d["job"])
    return d


def enqueue(job: str, args=None) -> int:
    args = args or {}
    if not isinstance(args, dict):
        raise JobError("args must be an object")
    build_argv(job, args)  # validates now, so the caller gets a 400 instead of a failed job
    with _conn() as c:
        cur = c.execute("insert into jobs(job, args_json, status, created_at) values(?,?,?,?)",
                        (job, json.dumps(args, ensure_ascii=False), "queued", now_iso()))
        jid = cur.lastrowid
    _wake.set()
    return jid


def get_job(jid: int):
    with _conn() as c:
        r = c.execute("select * from jobs where id=?", (jid,)).fetchone()
    return _row(r) if r else None


def list_jobs(limit: int = 15, with_tail: bool = False) -> list:
    limit = max(1, min(int(limit or 15), 200))
    with _conn() as c:
        rows = c.execute("select * from jobs order by id desc limit ?", (limit,)).fetchall()
    out = []
    for r in rows:
        d = _row(r)
        tail = d.get("output_tail") or ""
        d["first_line"] = next((ln.strip() for ln in tail.splitlines() if ln.strip()), "")
        if not with_tail:
            d["output_tail"] = tail[-600:]
        out.append(d)
    return out


def last_job(job: str):
    with _conn() as c:
        r = c.execute("select * from jobs where job=? order by id desc limit 1", (job,)).fetchone()
    return _row(r) if r else None


# ---------------------------------------------------------------- worker
_wake = threading.Event()
_worker = None


def run_one(jid: int, job: str, args: dict) -> None:
    """Run a single job to completion and record the result. Never raises."""
    started = now_iso()
    with _conn() as c:
        c.execute("update jobs set status='running', started_at=? where id=?", (started, jid))
    os.makedirs(log_dir(), exist_ok=True)
    log_file = os.path.join(log_dir(), "%d-%s.log" % (jid, job))
    output, code = "", -1
    try:
        argv = build_argv(job, args)
        proc = subprocess.run(argv, cwd=brain_home() if os.path.isdir(brain_home()) else None,
                              stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                              timeout=JOB_TIMEOUT)
        output = proc.stdout.decode("utf-8", "replace")
        code = proc.returncode
    except subprocess.TimeoutExpired as e:
        output = (e.stdout or b"").decode("utf-8", "replace") + "\n[timeout after %ds]" % JOB_TIMEOUT
    except Exception as e:  # missing script, bad args, ...
        output = "[brain worker] %s: %s" % (type(e).__name__, e)
    try:
        with open(log_file, "a", encoding="utf-8") as fh:
            fh.write("=== %s job %d %s exit=%s\n%s\n" % (started, jid, job, code, output))
    except OSError:
        pass
    status = "done" if code == 0 else "failed"
    with _conn() as c:
        c.execute("update jobs set status=?, finished_at=?, output_tail=?, exit_code=? where id=?",
                  (status, now_iso(), output[-TAIL_CHARS:], code, jid))


def _loop():
    while True:
        with _conn() as c:
            r = c.execute("select id, job, args_json from jobs where status='queued' "
                          "order by id limit 1").fetchone()
        if not r:
            _wake.wait(timeout=5)
            _wake.clear()
            continue
        try:
            args = json.loads(r["args_json"] or "{}")
        except ValueError:
            args = {}
        try:
            run_one(r["id"], r["job"], args)
        except Exception as e:  # pragma: no cover - last line of defence
            with _conn() as c:
                c.execute("update jobs set status='failed', finished_at=?, output_tail=? where id=?",
                          (now_iso(), "[brain worker crashed] %s" % e, r["id"]))


def start_worker():
    global _worker
    if _worker and _worker.is_alive():
        return _worker
    init_db()
    _worker = threading.Thread(target=_loop, name="brain-jobs", daemon=True)
    _worker.start()
    return _worker
