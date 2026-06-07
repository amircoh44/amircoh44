"""The optional Claude-backed triage path — exercised with a fake client (no network)."""
from server import triage


def _fake_anthropic(tool_input=None, raise_exc=False, capture=None):
    """Build a stand-in `anthropic` module whose Anthropic().messages.create(...)
    returns a forced record_triage tool_use (or raises)."""
    class FakeMessages:
        def create(self, **kwargs):
            if capture is not None:
                capture.update(kwargs)
            if raise_exc:
                raise RuntimeError("api unavailable")
            block = type("Block", (), {"type": "tool_use", "name": "record_triage", "input": tool_input})()
            return type("Resp", (), {"content": [block]})()

    class FakeClient:
        def __init__(self, *a, **k):
            self.messages = FakeMessages()

    return type("FakeAnthropic", (), {"Anthropic": FakeClient})


def _enable(monkeypatch, fake):
    monkeypatch.setattr(triage, "anthropic", fake)
    monkeypatch.setenv("TRIAGE_LLM", "1")
    monkeypatch.setenv("ANTHROPIC_API_KEY", "sk-test")


def test_defaults_to_rules_when_disabled(monkeypatch):
    monkeypatch.setenv("TRIAGE_LLM", "0")
    v = triage.classify("Linking", "Internal links not applying after the update; steps included.", "support")
    assert v == triage.classify_rules("Linking", "Internal links not applying after the update; steps included.", "support")
    assert v["category"] == "legit"


def test_llm_refines_a_legit_looking_message(monkeypatch):
    _enable(monkeypatch, _fake_anthropic(tool_input={"category": "low_quality", "risk_score": 20, "reasons": ["too vague to action"]}))
    # Rules would call this 'legit'; the (fake) model downgrades it to low_quality.
    v = triage.classify("Question", "This is a reasonably long message that rules would treat as legit.", "support")
    assert v["category"] == "low_quality" and v["action"] == "hold"
    assert any("LLM" in r for r in v["reasons"])


def test_llm_falls_back_to_rules_on_error(monkeypatch):
    _enable(monkeypatch, _fake_anthropic(raise_exc=True))
    v = triage.classify("Question", "Internal links not applying after the update; steps included.", "support")
    assert v["category"] == "legit"          # rule fallback, no exception escapes


def test_piracy_floor_skips_the_model(monkeypatch):
    capture = {}
    _enable(monkeypatch, _fake_anthropic(tool_input={"category": "legit", "risk_score": 0, "reasons": ["x"]}, capture=capture))
    v = triage.classify("key", "please send a cracked license key to bypass activation", "support")
    assert v["category"] == "abuse"          # rules floor wins
    assert capture == {}                     # the model was never called
