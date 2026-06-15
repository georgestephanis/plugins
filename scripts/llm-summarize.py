#!/usr/bin/env python3
"""Summarize a plugin release with an OpenAI-compatible LLM (e.g. a self-hosted vLLM).

Reads the release context (commit log, pull-request list, changelog) on stdin and
prints a short Markdown summary to stdout. Uses only the Python standard library so
it needs no `pip install` in CI.

Model selection: if --model is omitted (or empty), the served model is auto-detected
via `GET {url}/models` (first entry in `.data`).

Any failure (unreachable endpoint, HTTP error, empty completion) exits non-zero with a
message on stderr, so the caller can treat the summary as optional and continue.

Usage:
  llm-summarize.py --url URL --token TOKEN [--model NAME] --plugin SLUG --version X.Y.Z < context
"""
import argparse
import json
import sys
import urllib.error
import urllib.request

TIMEOUT = 120


def _post(url, token, payload):
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(url, data=data, method="POST")
    req.add_header("Content-Type", "application/json")
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8"))


def _get(url, token):
    req = urllib.request.Request(url, method="GET")
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8"))


def list_models(base, token):
    info = _get(base + "/models", token)
    return [m["id"] for m in (info.get("data") or []) if m.get("id")]


def resolve_model(base, token, cached):
    """Return (model_id, source) for the model to use.

    The cached model (e.g. from the LLM_MODEL repo variable) is validated against
    the live /models list every run, so a model that's no longer served is
    transparently replaced by the first available one — letting the caller
    refresh its cache. If /models can't be reached but a cached model is given,
    fall back to it unverified rather than failing.
    """
    try:
        available = list_models(base, token)
    except (urllib.error.URLError, urllib.error.HTTPError, ValueError) as exc:
        if cached:
            sys.stderr.write(f"could not list models ({exc}); using cached model {cached}\n")
            return cached, "cached-unverified"
        raise
    if not available:
        if cached:
            return cached, "cached-unverified"
        raise RuntimeError("no models reported by /models endpoint")
    if cached and cached in available:
        return cached, "cached"
    return available[0], "detected"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--url", required=True, help="OpenAI-compatible base URL, e.g. http://host:port/v1/")
    parser.add_argument("--token", default="")
    parser.add_argument("--model", default="", help="Preferred/cached model id; validated against /models each run")
    parser.add_argument("--model-out", default="", help="Write the resolved model id to this file (for caching)")
    parser.add_argument("--plugin", required=True)
    parser.add_argument("--version", required=True)
    args = parser.parse_args()

    base = args.url.rstrip("/")
    context = sys.stdin.read().strip()
    if not context:
        sys.stderr.write("no release context on stdin\n")
        return 1

    try:
        model, source = resolve_model(base, args.token, args.model)
    except (urllib.error.URLError, urllib.error.HTTPError, KeyError, RuntimeError, ValueError) as exc:
        sys.stderr.write(f"could not determine model: {exc}\n")
        return 1

    # Emit the resolved model so the caller can cache it (e.g. in LLM_MODEL).
    if args.model_out:
        try:
            with open(args.model_out, "w", encoding="utf-8") as handle:
                handle.write(model)
        except OSError as exc:
            sys.stderr.write(f"could not write --model-out: {exc}\n")
    sys.stderr.write(f"using model {model} ({source})\n")

    system = (
        "You write concise, friendly release notes for WordPress plugins. "
        "Given commit messages, merged pull requests, and a changelog, write a short "
        "Markdown overview (2-5 sentences or a few bullet points) of what changed in this "
        "release, aimed at site owners. Focus on user-facing changes and notable fixes. "
        "Do not invent changes that aren't supported by the input. Do not include a heading; "
        "output only the summary body."
    )
    user = (
        f"Plugin: {args.plugin}\n"
        f"Version: {args.version}\n\n"
        f"Release context:\n{context}\n"
    )

    payload = {
        "model": model,
        "messages": [
            {"role": "system", "content": system},
            {"role": "user", "content": user},
        ],
        "temperature": 0.3,
        "max_tokens": 600,
    }

    try:
        result = _post(base + "/chat/completions", args.token, payload)
    except (urllib.error.URLError, urllib.error.HTTPError, ValueError) as exc:
        sys.stderr.write(f"chat completion failed: {exc}\n")
        return 1

    try:
        summary = result["choices"][0]["message"]["content"].strip()
    except (KeyError, IndexError, TypeError) as exc:
        sys.stderr.write(f"unexpected completion shape: {exc}\n")
        return 1

    if not summary:
        sys.stderr.write("model returned an empty summary\n")
        return 1

    print(summary)
    return 0


if __name__ == "__main__":
    sys.exit(main())
