from .rules import RuleSet

class WafEngine:
    def __init__(self, ruleset=None):
        self.ruleset = ruleset or RuleSet()

    def inspect_string(self, text):
        text = str(text)
        result = {"score": 0, "matches": []}

        for group, patterns in self.ruleset.rules.items():
            for pat in patterns:
                if pat.search(text):
                    result["score"] += 1
                    result["matches"].append({"group": group, "pattern": pat.pattern})

        return result

    def inspect_request(self, req):
        # req is a dict: path, query, headers, body
        total = {"total_score": 0, "details": []}

        # path
        r = self.inspect_string(req.get("path", ""))
        if r["score"] > 0:
            total["total_score"] += r["score"]
            total["details"].append({"location": "path", **r})

        # query
        for k, v in req.get("query", {}).items():
            r = self.inspect_string(v)
            if r["score"] > 0:
                total["total_score"] += r["score"]
                total["details"].append({"location": f"query:{k}", "value": v, **r})

        # headers
        for k, v in req.get("headers", {}).items():
            r = self.inspect_string(v)
            if r["score"] > 0:
                total["total_score"] += r["score"]
                total["details"].append({"location": f"header:{k}", "value": v, **r})

        # body
        r = self.inspect_string(req.get("body", ""))
        if r["score"] > 0:
            total["total_score"] += r["score"]
            total["details"].append({"location": "body", **r})

        return total
