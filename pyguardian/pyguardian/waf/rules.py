import yaml
import re
from pathlib import Path

DEFAULT_RULES_PATH = Path(__file__).resolve().parents[2] / "config" / "rules.yaml"

class RuleSet:
    def __init__(self, rules_path=None):
        self.rules_path = Path(rules_path) if rules_path else DEFAULT_RULES_PATH
        self.rules = {}
        self.load_rules()

    def load_rules(self):
        with open(self.rules_path, "r", encoding="utf-8") as f:
            data = yaml.safe_load(f)

        for group, info in data.items():
            patterns = info.get("patterns", [])
            compiled = []
            for p in patterns:
                try:
                    compiled.append(re.compile(p, re.IGNORECASE))
                except:
                    # If regex is invalid, escape it
                    compiled.append(re.compile(re.escape(p), re.IGNORECASE))

            self.rules[group] = compiled
