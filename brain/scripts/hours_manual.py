#!/usr/bin/env python3
"""Manual hours entry (24.09.26): append one row to session-log.csv, then run
hours_agent.py for that day so the entry reaches Toggl through the normal flow.

Usage:
  hours_manual.py --day 2026-09-24 --start 09:00 --end 10:30 --project 123
                  --client "שם לקוח" --description "מה נעשה"

Env: BRAIN_HOME (default /opt/brain), BRAIN_PYTHON (default sys.executable).
"""
import argparse
import csv
import os
import re
import subprocess
import sys

HOME = os.environ.get("BRAIN_HOME", "/opt/brain")
SESSION_LOG = os.environ.get("BRAIN_SESSION_LOG", os.path.join(HOME, "hours", "session-log.csv"))
HOURS_AGENT = os.environ.get("BRAIN_HOURS_AGENT", os.path.join(HOME, "hours", "hours_agent.py"))
HEADER = ["date", "start", "end", "toggl_project_id", "client", "description"]


def clean(s: str) -> str:
    return str(s or "").replace("\r", " ").replace("\n", " ").replace(",", ";").strip()


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--day", required=True)
    ap.add_argument("--start", required=True)
    ap.add_argument("--end", required=True)
    ap.add_argument("--project", required=True, type=int)
    ap.add_argument("--client", default="")
    ap.add_argument("--description", required=True)
    ap.add_argument("--no-agent", action="store_true", help="append only, skip hours_agent")
    a = ap.parse_args()

    if not re.match(r"^\d{4}-\d{2}-\d{2}$", a.day):
        print("bad day:", a.day); return 2
    for label, val in (("start", a.start), ("end", a.end)):
        if not re.match(r"^\d{2}:\d{2}(:\d{2})?$", val):
            print("bad %s: %s" % (label, val)); return 2
    if a.end <= a.start:
        print("end must be after start"); return 2
    desc = clean(a.description)
    if not desc:
        print("empty description"); return 2

    os.makedirs(os.path.dirname(SESSION_LOG), exist_ok=True)
    new_file = not os.path.exists(SESSION_LOG) or os.path.getsize(SESSION_LOG) == 0
    with open(SESSION_LOG, "a", encoding="utf-8", newline="") as fh:
        w = csv.writer(fh, lineterminator="\n")
        if new_file:
            w.writerow(HEADER)
        w.writerow([a.day, a.start, a.end, a.project, clean(a.client), desc])
    print("נוספה רשומה: %s %s-%s · %s · %s" % (a.day, a.start, a.end, clean(a.client) or a.project, desc))

    if a.no_agent:
        return 0
    if not os.path.exists(HOURS_AGENT):
        print("hours_agent.py not found at", HOURS_AGENT); return 3
    py = os.environ.get("BRAIN_PYTHON", sys.executable)
    print("מריץ את סוכן השעות ליום", a.day, flush=True)
    return subprocess.call([py, HOURS_AGENT, a.day], cwd=HOME if os.path.isdir(HOME) else None)


if __name__ == "__main__":
    sys.exit(main())
