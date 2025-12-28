from pyguardian.waf.rules import RuleSet
from pyguardian.waf.core import WafEngine
from pyguardian.waf.logger import log_event

req = {
    "path": "/search",
    "query": {"q": "1' OR 1=1"},
    "headers": {"User-Agent": "curl/7.0"},
    "body": ""
}

rules = RuleSet()
waf = WafEngine(rules)

report = waf.inspect_request(req)
print("WAF report:", report)

if report["total_score"] > 0:
    print("⚠️ Attack detected!")
    log_event({"ip": "127.0.0.1", "report": report, "action": "blocked"})
else:
    print("No attack detected.")
