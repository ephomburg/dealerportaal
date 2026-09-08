#!/usr/bin/env python3
"""PreToolUse-guard voor de dealerportaal-repo.

Dwingt een bevestiging af vóór:
  - elke `git push`  -> deployt AUTOMATISCH naar de LIVE site (GitHub Actions);
  - elke `git commit` terwijl de huidige branch `main` is.

Faalt nooit hard: bij twijfel geen output + exit 0, zodat de tool gewoon
verdergaat met de normale permissie-afhandeling. Zie CLAUDE.md.
"""

import json
import subprocess
import sys

PUSH_REASON = (
    "git push — dit deployt AUTOMATISCH naar de LIVE dealerportaal-site "
    "(plugin + thema via GitHub Actions). Alleen doorgaan als de gebruiker voor "
    "DEZE specifieke wijziging expliciet toestemming gaf om live te zetten "
    "(\"zet live\" / \"push maar\"). \"pak op\" / \"akkoord\" / \"ga door\" is dat NIET. "
    "Zie CLAUDE.md."
)
COMMIT_REASON = (
    "git commit op branch main — commits op main gaan bij de eerstvolgende "
    "push naar de LIVE site. Bevestig dat committen op main de bedoeling is, of "
    "werk op een feature-branch. Zie CLAUDE.md."
)


def ask(reason):
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "ask",
            "permissionDecisionReason": reason,
        }
    }))
    sys.exit(0)


def main():
    try:
        data = json.load(sys.stdin)
    except Exception:
        sys.exit(0)

    cmd = (data.get("tool_input") or {}).get("command", "") or ""

    if "git push" in cmd:
        ask(PUSH_REASON)

    if "git commit" in cmd:
        try:
            branch = subprocess.run(
                ["git", "rev-parse", "--abbrev-ref", "HEAD"],
                capture_output=True, text=True, timeout=5,
            ).stdout.strip()
        except Exception:
            branch = ""
        if branch == "main":
            ask(COMMIT_REASON)

    sys.exit(0)


if __name__ == "__main__":
    main()
