"""Request triage: decide whether an incoming ticket is a legitimate, reasonable
request (queue it for the admin digest), a genuine security *report* (legit +
priority), or an abuse / piracy / spam / low-quality message (quarantine or hold).

Pure and rule-based so it runs offline and is fully testable. `classify()` is the
single seam — swap in an LLM call later and keep the same return shape.
"""
import re

# --- Signal lists ------------------------------------------------------------

# Requests to pirate / crack / bypass licensing — never legitimate.
PIRACY_TERMS = [
    "crack", "cracked", "nulled", "keygen", "warez", "torrent", "pirate",
    "gpl club", "free license key", "free licence key", "bypass the license",
    "bypass the licence", "license bypass", "skip activation", "without paying",
    "share your key", "share my key", "give me a key", "generate a key",
    "free premium key", "patch the plugin", "remove the license check",
]

# Actual exploit payloads / attempts to reach other people's data — quarantine.
EXPLOIT_PAYLOADS = [
    "<script", "</script", "onerror=", "javascript:", "union select",
    "drop table", "' or '1'='1", "or 1=1", "; drop", "/etc/passwd",
    "../../", "{{", "${", "sleep(", "base64_decode", "eval(",
    "all license keys", "other customers", "another customer",
    "everyone's keys", "admin password", "give me access to the",
    "dump the database", "list all users",
]

# Words that, in a BUG report, indicate a genuine security disclosure (valuable).
SECURITY_WORDS = [
    "vulnerability", "vulnerable", "xss", "csrf", "sql injection", "sqli",
    "security issue", "security bug", "security flaw", "rce", "ssrf",
    "privilege escalation", "data leak", "exposed", "disclosure",
]

# Obvious marketing spam.
SPAM_PHRASES = [
    "buy followers", "cheap seo", "guest post", "casino", "viagra", "cialis",
    "crypto investment", "forex", "loan offer", "make money fast", "click here to win",
    "bulk email", "increase your ranking overnight", "best smm panel",
]

_URL_RE = re.compile(r"https?://", re.I)
_WORD_RE = re.compile(r"[a-z]{2,}", re.I)


def _hits(text, terms):
    return [t for t in terms if t in text]


def classify(subject, message, ttype="support"):
    """Return {category, action, score, reasons}.

    category: legit | security_report | spam | abuse | low_quality
    action:   queue (digest) | quarantine | hold
    score:    0-100 risk (higher = more suspicious), for sorting
    """
    subject = (subject or "").strip()
    message = (message or "").strip()
    text = (subject + " \n " + message).lower()
    reasons = []

    # 1) Piracy / cracking requests, or exploit attempts -> abuse, quarantine.
    piracy = _hits(text, PIRACY_TERMS)
    payloads = _hits(text, EXPLOIT_PAYLOADS)
    if piracy:
        reasons.append("piracy/cracking language: " + ", ".join(piracy[:3]))
    if payloads:
        reasons.append("exploit/abuse payload: " + ", ".join(payloads[:3]))
    if piracy or payloads:
        return {"category": "abuse", "action": "quarantine", "score": 95, "reasons": reasons}

    # 2) Spam: marketing phrases or link-heavy.
    spam = _hits(text, SPAM_PHRASES)
    n_urls = len(_URL_RE.findall(text))
    if spam:
        reasons.append("spam phrases: " + ", ".join(spam[:3]))
    if n_urls >= 4:
        reasons.append("link-heavy (%d URLs)" % n_urls)
    if spam or n_urls >= 4:
        return {"category": "spam", "action": "quarantine", "score": 75, "reasons": reasons}

    # 3) Genuine security disclosure in a bug report -> legit + priority.
    sec = _hits(text, SECURITY_WORDS)
    if sec and ttype == "bug":
        reasons.append("security disclosure: " + ", ".join(sec[:3]))
        return {"category": "security_report", "action": "queue", "score": 50, "reasons": reasons}

    # 4) Low-quality: too short or no real words -> hold (ask for detail).
    words = _WORD_RE.findall(message)
    if len(message) < 15 or len(words) < 3:
        reasons.append("too short / not enough detail")
        return {"category": "low_quality", "action": "hold", "score": 25, "reasons": reasons}

    # 5) Otherwise: a reasonable, legitimate request -> queue for the digest.
    reasons.append("reads as a genuine, reasonable request")
    return {"category": "legit", "action": "queue", "score": 0, "reasons": reasons}
