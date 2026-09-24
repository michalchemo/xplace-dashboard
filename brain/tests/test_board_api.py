"""Board API tests (24.09.26). Run from brain/:  python -m pytest tests -q
Everything is redirected to a temp dir through env vars, the worker is off,
and psql is never called (status._psql is patched)."""
import os
import sys
import time

import pytest

BRAIN_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if BRAIN_DIR not in sys.path:
    sys.path.insert(0, BRAIN_DIR)

SECRET = "test-secret"


@pytest.fixture()
def env(tmp_path, monkeypatch):
    home = tmp_path / "brain"
    (home / "hours" / "logs").mkdir(parents=True)
    (home / "hours" / "logs" / "run.log").write_text(
        "דוח שעות אוטומטי - 23.09.2026\n\nהוזן ל-Toggl:\n- 09:00-10:00 לקוח א\n- 10:30-11:00 לקוח ב\n"
        "לא הוזן (להשלמה):\n- 14:00-15:00 ?\n", encoding="utf-8")
    envfile = tmp_path / "brain.env"
    envfile.write_text("BOARD_USER=u\nBOARD_PASS=p\nBOARD_SECRET=%s\n" % SECRET, encoding="utf-8")
    monkeypatch.setenv("BRAIN_ENV", str(envfile))
    monkeypatch.setenv("BRAIN_HOME", str(home))
    monkeypatch.setenv("BRAIN_JOBS_DB", str(tmp_path / "jobs.db"))
    monkeypatch.setenv("BRAIN_JOBS_LOGS", str(tmp_path / "logs" / "jobs"))
    monkeypatch.setenv("BRAIN_JOBS_WORKER", "0")
    monkeypatch.setenv("BRAIN_PYTHON", sys.executable)
    monkeypatch.setenv("BRISK_ENV", str(tmp_path / "no-brisk.env"))
    monkeypatch.setenv("BRAIN_BOARD", os.path.join(BRAIN_DIR, "board", "index.html"))
    for mod in ("main", "board_auth", "status", "jobs"):
        sys.modules.pop(mod, None)
    return tmp_path


@pytest.fixture()
def app(env, monkeypatch):
    import jobs, status, main  # noqa: E401 - fresh imports after env is set
    jobs.init_db()
    status.invalidate()

    def fake_psql(sql):
        if "from tasks" in sql:
            return "draft|3\nin_progress|2\ndone|10\n"
        if "from projects" in sql:
            return "1|לקוח א|111\n2|לקוח ב|222\n"
        return ""
    monkeypatch.setattr(status, "_psql", fake_psql)
    return main.app


def session_cookie():
    import board_auth
    exp = str(int(time.time()) + 3600)
    return {"brain_session": exp + "." + board_auth.sign(exp, SECRET)}


@pytest.fixture()
def anon(app):
    from fastapi.testclient import TestClient
    return TestClient(app)


@pytest.fixture()
def client(app):
    from fastapi.testclient import TestClient
    return TestClient(app, cookies=session_cookie())


def test_status_requires_session(anon):
    r = anon.get("/api/status")
    assert r.status_code == 401
    assert r.json()["detail"]
    assert anon.post("/run/hours").status_code == 401
    assert anon.get("/api/jobs").status_code == 401


def test_status_ok_with_session(client):
    r = client.get("/api/status")
    assert r.status_code == 200
    s = r.json()
    assert s["generated_at"]
    assert s["brisk"]["open"] == 5 and s["brisk"]["by_state"]["done"] == 10
    assert [p["toggl_project_id"] for p in s["projects"]] == [111, 222]
    hours = next(a for a in s["agents"] if a["key"] == "hours")
    assert hours["can_run"] and hours["job"] == "hours"
    assert hours["last_result"] == "23.09.2026 · 2 הוזנו · 1 להשלמה"
    assert hours["last_run"]
    assert "T23:00:00" in hours["next_run"]
    assert s["hours_log"]["entries"] == 2 and s["hours_log"]["runs"] == 1
    brisk = next(a for a in s["agents"] if a["key"] == "brisk")
    assert brisk["can_run"] is False and brisk["job"] is None and brisk["next_run"] is None
    monthend = next(a for a in s["agents"] if a["key"] == "monthend")
    assert monthend["next_run"][8:10] == "01" and "T07:30:00" in monthend["next_run"]


def test_run_hours_enqueues(client):
    r = client.post("/run/hours")
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "queued" and body["started"] == "hours" and body["job_id"] >= 1
    j = client.get("/api/jobs/%d" % body["job_id"]).json()
    assert j["status"] == "queued" and j["job"] == "hours" and j["args"] == {}
    assert client.post("/run/hours", json={"day": "2026-09-20"}).status_code == 200
    assert client.post("/run/hours", json={"day": "20/09/2026"}).status_code == 400
    assert client.post("/run/nope").status_code == 404
    assert client.post("/run/hours", content=b"not json",
                       headers={"Content-Type": "application/json"}).status_code == 400


def test_hours_manual_validation(client):
    good = {"day": "2026-09-24", "start": "09:00", "end": "10:30", "toggl_project_id": 111,
            "client": "לקוח א", "description": "עבודה, עם פסיק"}
    r = client.post("/run/hours_manual", json=dict(good, start="9am"))
    assert r.status_code == 400 and "שעה" in r.json()["detail"]
    assert client.post("/run/hours_manual", json=dict(good, end="08:00")).status_code == 400
    assert client.post("/run/hours_manual", json=dict(good, description="  ")).status_code == 400
    assert client.post("/run/hours_manual", json=dict(good, toggl_project_id="x")).status_code == 400
    r = client.post("/run/hours_manual", json=good)
    assert r.status_code == 200 and r.json()["status"] == "queued"
    j = client.get("/api/jobs/%d" % r.json()["job_id"]).json()
    assert j["args"]["description"] == "עבודה, עם פסיק"


def test_jobs_list(client):
    client.post("/run/hours")
    client.post("/run/monthend")
    r = client.get("/api/jobs?limit=1")
    assert r.status_code == 200
    jobs = r.json()["jobs"]
    assert len(jobs) == 1 and jobs[0]["job"] == "monthend" and jobs[0]["label"] == "חיוב סוף חודש"
    assert len(client.get("/api/jobs").json()["jobs"]) == 2
    assert client.get("/api/jobs/999").status_code == 404
    assert len(client.get("/api/status").json()["jobs"]) == 2


def test_worker_runs_refresh_job(app, env):
    import jobs
    jid = jobs.enqueue("refresh", {})
    jobs.run_one(jid, "refresh", {})
    j = jobs.get_job(jid)
    assert j["status"] == "done" and "refresh ok" in j["output_tail"] and j["exit_code"] == 0
    assert os.path.exists(os.path.join(str(env), "logs", "jobs", "%d-refresh.log" % jid))


def test_hours_manual_script_appends_csv(env, tmp_path):
    import subprocess
    script = os.path.join(BRAIN_DIR, "scripts", "hours_manual.py")
    log = tmp_path / "brain" / "hours" / "session-log.csv"
    p = subprocess.run([sys.executable, script, "--day", "2026-09-24", "--start", "09:00",
                        "--end", "10:30", "--project", "111", "--client", "לקוח א",
                        "--description", "בדיקה, פסיק", "--no-agent"],
                       stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                       env=dict(os.environ, PYTHONIOENCODING="utf-8"))
    assert p.returncode == 0, p.stdout.decode("utf-8", "replace")
    lines = log.read_text(encoding="utf-8").splitlines()
    assert lines[0] == "date,start,end,toggl_project_id,client,description"
    assert lines[1] == "2026-09-24,09:00,10:30,111,לקוח א,בדיקה; פסיק"


def test_board_served_from_repo(client, anon):
    assert "action=\"/login\"" in anon.get("/").text
    r = client.get("/")
    assert r.status_code == 200 and "/api/status" in r.text and "תור עבודות" in r.text
