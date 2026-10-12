#!/usr/bin/env python3
"""Fetch vetted storefront og:image photos for the immutable UAT Docker build.

Runs on GitHub CI (public Internet), never inside isolated UAT, and does not
crawl unknown domains. Source URLs are versioned in storefront-catalog.json.
"""
import json
import pathlib
import re
import sys
import urllib.parse
import urllib.request
from html.parser import HTMLParser

ROOT = pathlib.Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "resources/demo/storefront-catalog.json"
DEST = ROOT / "resources/demo/catalog-images"
ALLOWED = ("avizelerim.com", "hekastore.com", "cdn.shopify.com", "cdn.shopifycdn.net")

def approved(url):
    p = urllib.parse.urlsplit(url)
    host = (p.hostname or "").lower()
    return p.scheme == "https" and any(host == domain or host.endswith("." + domain) for domain in ALLOWED)

class MetaImages(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = []
    def handle_starttag(self, tag, attrs):
        if tag != "meta":
            return
        a = dict(attrs)
        if (a.get("property") in ("og:image", "og:image:secure_url", "twitter:image")
                or a.get("name") == "twitter:image") and a.get("content"):
            self.urls.append(a["content"])

def fetch(url, limit=6_000_000):
    if not approved(url):
        raise ValueError("Blocked image host: " + url)
    req = urllib.request.Request(url, headers={"User-Agent": "MarsProject-UAT-Snapshot/1.0"})
    with urllib.request.urlopen(req, timeout=20) as res:
        if not approved(res.geturl()):
            raise ValueError("Blocked redirect: " + res.geturl())
        data = res.read(limit + 1)
        if len(data) > limit:
            raise ValueError("Response too large")
    return data

def main():
    DEST.mkdir(parents=True, exist_ok=True)
    products = json.loads(MANIFEST.read_text(encoding="utf-8"))["products"]
    counts = {"Avizelerim": 0, "Heka Store": 0}
    errors = []
    for item in products:
        code, source = item["code"], item["source"]
        target = DEST / (code + ".bin")
        try:
            page = fetch(item["url"], limit=2_000_000).decode("utf-8", "replace")
            parser = MetaImages()
            parser.feed(page)
            if not parser.urls:
                raise ValueError("Page has no og:image metadata")
            for raw in parser.urls:
                url = urllib.parse.urljoin(item["url"], raw)
                if not approved(url):
                    continue
                img = fetch(url)
                valid = (img.startswith(b"\\xff\\xd8\\xff")
                         or img.startswith(b"\\x89PNG\\r\\n\\x1a\\n")
                         or (img.startswith(b"RIFF") and img[8:12] == b"WEBP"))
                if valid and len(img) >= 4000:
                    target.write_bytes(img)
                    counts[source] += 1
                    print(f"OK {code} {len(img)} bytes", flush=True)
                    break
            else:
                raise ValueError("No valid JPEG/PNG/WebP OG product image")
        except Exception as error:
            errors.append(f"{code}: {error}")
            print("WARN " + errors[-1], file=sys.stderr, flush=True)
    print("Downloaded product images:", counts)
    # Never silently claim both storefronts were imported if an entire source fails.
    if min(counts.values()) == 0:
        raise SystemExit("Missing all photos for one storefront; not publishing incomplete UAT image")

if __name__ == "__main__":
    main()
