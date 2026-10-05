#!/usr/bin/env python3
"""Stage 0 API baseline (wall time via curl). Credentials: DEALER_USER, DEALER_PASS env vars."""

from __future__ import annotations

import json
import os
import statistics
import subprocess
import sys
import urllib.parse
from dataclasses import dataclass


@dataclass
class Scenario:
    label: str
    path: str
    dealer: bool = False


def curl_once(base: str, path: str, token: str | None, timeout_s: int = 120) -> tuple[float, int, int]:
    url = base.rstrip("/") + path
    cmd = [
        "curl",
        "-sS",
        "--connect-timeout",
        "15",
        "--max-time",
        str(timeout_s),
        "-o",
        "/dev/null",
        "-w",
        "%{time_total}\t%{http_code}\t%{size_download}",
        url,
    ]
    if token:
        cmd[6:6] = ["-H", f"Authorization: Bearer {token}"]
    proc = subprocess.run(cmd, capture_output=True, text=True, check=False)
    if proc.returncode != 0:
        raise RuntimeError(proc.stderr.strip() or "curl failed")
    t, code, size = proc.stdout.strip().split("\t")
    return float(t), int(code), int(size)


def bench(base: str, path: str, token: str | None, runs: int = 5) -> dict:
    samples: list[tuple[float, int, int]] = []
    last_err: str | None = None
    for _ in range(runs):
        for attempt in range(2):
            try:
                samples.append(curl_once(base, path, token))
                break
            except RuntimeError as exc:
                last_err = str(exc)
                if attempt == 0:
                    continue
                raise RuntimeError(last_err) from exc
    if not samples:
        raise RuntimeError(last_err or "no samples")
    times = sorted(x[0] for x in samples)
    code = samples[0][1]
    size = samples[0][2]
    return {
        "http": code,
        "size_bytes": size,
        "runs": runs,
        "min_s": round(times[0], 3),
        "p50_s": round(times[len(times) // 2], 3),
        "max_s": round(times[-1], 3),
        "mean_s": round(statistics.mean(times), 3),
    }


def login(base: str, user: str, password: str) -> str:
    payload = json.dumps({"username": user, "password": password})
    proc = subprocess.run(
        [
            "curl",
            "-sS",
            "-X",
            "POST",
            f"{base.rstrip('/')}/api/v1/dealer/auth/login",
            "-H",
            "Content-Type: application/json",
            "-d",
            payload,
        ],
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode != 0:
        raise RuntimeError(proc.stderr.strip() or "login curl failed")
    data = json.loads(proc.stdout)
    token = data.get("access_token") or data.get("token")
    if not token:
        raise RuntimeError("login response has no access_token")
    return str(token)


def fetch_json(base: str, path: str, token: str | None) -> dict:
    url = base.rstrip("/") + path
    cmd = [
        "curl",
        "-sS",
        "--connect-timeout",
        "15",
        "--max-time",
        "120",
        url,
    ]
    if token:
        cmd[6:6] = ["-H", f"Authorization: Bearer {token}"]
    proc = subprocess.run(cmd, capture_output=True, text=True, check=False)
    if proc.returncode != 0:
        raise RuntimeError(proc.stderr.strip() or "fetch failed")
    try:
        return json.loads(proc.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"invalid JSON: {proc.stdout[:200]!r}") from exc


def main() -> int:
    base = os.environ.get("BENCH_BASE", "https://dev.back-p-833.tw1.ru")
    user = os.environ.get("DEALER_USER", "")
    password = os.environ.get("DEALER_PASS", "")
    runs = int(os.environ.get("BENCH_RUNS", "5"))

    q = urllib.parse.quote("диван")
    scenarios = [
        Scenario("search/bootstrap", "/api/v1/search/bootstrap"),
        Scenario("search autocomplete q=диван limit=5", f"/api/v1/search?q={q}&limit=5"),
        Scenario("search autocomplete (dealer)", f"/api/v1/search?q={q}&limit=5", dealer=True),
        Scenario("search/products p1", f"/api/v1/search/products?q={q}&page=1&perPage=24"),
        Scenario("search/products p1 (dealer)", f"/api/v1/search/products?q={q}&page=1&perPage=24", dealer=True),
        Scenario("catalog/menu", "/api/v1/catalog/menu"),
        Scenario("catalog/menu (dealer)", "/api/v1/catalog/menu", dealer=True),
        Scenario("catalog/products all p1", "/api/v1/catalog/products?page=1&perPage=24&sort=default"),
        Scenario("catalog/products all p1 (dealer)", "/api/v1/catalog/products?page=1&perPage=24&sort=default", dealer=True),
        Scenario("catalog/products collection p1", "/api/v1/catalog/products?page=1&perPage=24&sort=default&collection=true"),
        Scenario("catalog/products collection p1 (dealer)", "/api/v1/catalog/products?page=1&perPage=24&sort=default&collection=true", dealer=True),
    ]

    token: str | None = None
    if user and password:
        token = login(base, user, password)
        print(f"dealer_login: ok (token length {len(token)})", file=sys.stderr)
    else:
        print("dealer_login: skipped (set DEALER_USER / DEALER_PASS)", file=sys.stderr)

    print(f"base={base} runs={runs}\n")
    print("scenario\thttp\tbytes\tmin\tp50\tmax\tmean")
    results: dict[str, dict] = {}
    for s in scenarios:
        tkn = token if s.dealer else None
        if s.dealer and not token:
            continue
        try:
            r = bench(base, s.path, tkn, runs=runs)
        except RuntimeError as exc:
            print(f"{s.label}\tERR\t-\t-\t-\t-\t- ({exc})")
            continue
        results[s.label] = r
        print(
            f"{s.label}\t{r['http']}\t{r['size_bytes']}\t{r['min_s']}\t{r['p50_s']}\t{r['max_s']}\t{r['mean_s']}"
        )

    if token:
        for label, path in [
            ("search/products", f"/api/v1/search/products?q={q}&page=1&perPage=24"),
            ("catalog/products", "/api/v1/catalog/products?page=1&perPage=24&sort=default"),
        ]:
            try:
                g = fetch_json(base, path, None)
                d = fetch_json(base, path, token)
            except RuntimeError as exc:
                print(f"\norder_invariant {label}: skipped ({exc})")
                continue
            g_slugs = [x.get("slug") or x.get("id") for x in g.get("items", [])]
            d_slugs = [x.get("slug") or x.get("id") for x in d.get("items", [])]
            print(f"\norder_invariant {label} guest==dealer: {g_slugs == d_slugs} (n={len(g_slugs)})")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
