from flask import Flask, request, abort, render_template
import time, json

from pyguardian.waf.rules import RuleSet
from pyguardian.waf.core import WafEngine
from pyguardian.waf.logger import log_event

app = Flask(__name__)

rules = RuleSet()
waf = WafEngine(rules)

BLOCK_THRESHOLD = 2

REQUEST_LOG = {}
RATE_LIMIT = 20
TIME_WINDOW = 60


@app.before_request
def waf_protect():
    ip = request.remote_addr
    now = time.time()

    # ---------- PERMANENT BAN ----------
    try:
        with open("banned_ips.json") as f:
            banned = json.load(f).get("banned", [])
    except:
        banned = []

    if ip in banned:
        abort(403)

    # ---------- RATE LIMIT ----------
    REQUEST_LOG.setdefault(ip, [])
    REQUEST_LOG[ip] = [t for t in REQUEST_LOG[ip] if now - t < TIME_WINDOW]
    REQUEST_LOG[ip].append(now)

    if len(REQUEST_LOG[ip]) > RATE_LIMIT:
        log_event({
            "ip": ip,
            "path": request.path,
            "report": {"total_score": 0, "details": []},
            "action": "rate_limited"
        })
        abort(429)

    # ---------- SAFE INSPECTION ----------
    req_data = {
        "path": request.path,
        "query": request.args.to_dict(),
        "body": request.get_data(as_text=True)
    }

    report = waf.inspect_request(req_data)

    # ---------- DECISION ----------
    if report["total_score"] >= BLOCK_THRESHOLD:
        log_event({
            "ip": ip,
            "path": request.path,
            "report": report,
            "action": "blocked"
        })
        abort(403)

    elif report["total_score"] > 0:
        log_event({
            "ip": ip,
            "path": request.path,
            "report": report,
            "action": "allowed_suspicious"
        })


@app.route("/")
def home():
    return "Welcome! This site is protected by PyGuardian WAF."


@app.route("/search")
def search():
    q = request.args.get("q", "")
    return f"Search results for: {q}"


# -------- DASHBOARD ROUTE (MUST BE HERE) --------

@app.route("/admin")
def dashboard():
    try:
        with open("waf_stats.json") as f:
            stats = json.load(f)
    except:
        stats = {}

    try:
        with open("banned_ips.json") as f:
            banned = json.load(f).get("banned", [])
    except:
        banned = []

    return render_template("dashboard.html", stats=stats, banned=banned)


@app.errorhandler(403)
def forbidden(e):
    return render_template("403.html"), 403


@app.errorhandler(429)
def rate_limit_page(e):
    return render_template("429.html"), 429


if __name__ == "__main__":
    app.run(debug=True)
