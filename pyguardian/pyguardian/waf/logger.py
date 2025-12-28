import json
from datetime import datetime

LOG_FILE = "waf_logs.jsonl"
STATS_FILE = "waf_stats.json"
BAN_FILE = "banned_ips.json"


def log_event(event):
    report = event.get("report", {})
    ip = event.get("ip")

    # Attach IP inside report for stats
    report["ip"] = ip

    update_stats(report)

    simplified = {
        "time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "ip": ip,
        "path": event.get("path"),
        "action": event.get("action"),
        "score": report.get("total_score", 0),
        "matches": []
    }

    for detail in report.get("details", []):
        for m in detail.get("matches", []):
            simplified["matches"].append(f"{m['group']}: {m['pattern']}")

    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(json.dumps(simplified) + "\n")


def update_stats(report):
    try:
        with open(STATS_FILE, "r") as f:
            stats = json.load(f)
    except:
        stats = {
            "total_attacks": 0,
            "sql_injection": 0,
            "xss": 0,
            "path_traversal": 0,
            "per_ip": {}
        }

    ip = report.get("ip", "unknown")
    stats["total_attacks"] += 1
    stats["per_ip"][ip] = stats["per_ip"].get(ip, 0) + 1

    # Auto-ban after 3 attacks
    if stats["per_ip"][ip] >= 3:
        ban_ip(ip)

    groups = []
    for detail in report.get("details", []):
        for m in detail.get("matches", []):
            groups.append(m["group"])

    for g in set(groups):
        stats[g] = stats.get(g, 0) + 1

    with open(STATS_FILE, "w") as f:
        json.dump(stats, f, indent=2)


def ban_ip(ip):
    try:
        with open(BAN_FILE, "r") as f:
            data = json.load(f)
    except:
        data = {"banned": []}

    if ip not in data["banned"]:
        data["banned"].append(ip)

    with open(BAN_FILE, "w") as f:
        json.dump(data, f, indent=2)
