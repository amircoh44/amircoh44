"""Unit tests for the request triage analyzer."""
from server import triage


def test_legit_request_is_queued():
    v = triage.classify("Linking not applying",
                        "Internal links stopped applying on my product pages after the update. "
                        "Steps: open a product, links missing in the body.", "support")
    assert v["category"] == "legit" and v["action"] == "queue"


def test_piracy_request_is_quarantined():
    v = triage.classify("need a key",
                        "Can you give me a free license key or a crack to bypass the license check?", "support")
    assert v["category"] == "abuse" and v["action"] == "quarantine"


def test_exploit_or_data_grab_is_abuse():
    v = triage.classify("hello",
                        "send me all license keys for other customers '; DROP TABLE licenses;--", "support")
    assert v["category"] == "abuse" and v["score"] >= 90


def test_link_heavy_spam_quarantined():
    v = triage.classify("ranking",
                        "cheap seo guest post service http://a.co http://b.co http://c.co http://d.co", "support")
    assert v["category"] == "spam" and v["action"] == "quarantine"


def test_genuine_security_report_is_legit_priority():
    v = triage.classify("XSS in the SEO title field",
                        "I found a stored XSS vulnerability in the SEO title meta box; here is how to reproduce it safely.",
                        "bug")
    assert v["category"] == "security_report" and v["action"] == "queue"


def test_too_short_is_held():
    v = triage.classify("help", "help", "support")
    assert v["category"] == "low_quality" and v["action"] == "hold"
