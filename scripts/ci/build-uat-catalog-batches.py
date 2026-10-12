#!/usr/bin/env python3
"""Build one reproducible 50-SKU UAT import batch using public catalog APIs.

This script executes at Docker image publication time, where outbound HTTP is
available. The isolated UAT container only imports the bundled JSON and bytes.
No guessed product names, prices or images, no partial batches.
"""
import html
import json
import os
import pathlib
import re
import sys
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
DIR = ROOT / "resources/demo/storefront-batches"
ALLOWED = ("avizelerim.com", "hekastore.com", "cdn.shopify.com", "cdn.shopifycdn.net")
TIMEOUT = 20


def safe_url(url):
    parsed = urllib.parse.urlsplit(url)
    host = (parsed.hostname or "").lower()
    return parsed.scheme == "https" and any(host == d or host.endswith("." + d) for d in ALLOWED)


def get(url, max_bytes=7_000_000):
    if not safe_url(url):
        raise ValueError("Unapproved source host: " + url)
    request = urllib.request.Request(url, headers={
        "User-Agent": "Mozilla/5.0 (compatible; MarsProjectUATCatalog/1.0)",
        "Accept": "application/json,text/html,image/avif,image/webp,image/png,image/jpeg,*/*",
    })
    with urllib.request.urlopen(request, timeout=TIMEOUT) as response:
        if not safe_url(response.geturl()):
            raise ValueError("Source redirected outside allowed hosts")
        data = response.read(max_bytes + 1)
        if len(data) > max_bytes:
            raise ValueError("Excessive source response")
        return data


def records_heka(total):
    # Shopify public collection feed: native variant prices, primary image and handle.
    found = []
    seen = set()
    for page in range(1, 12):
        url = f"https://hekastore.com/collections/avize/products.json?limit=250&page={page}"
        payload = json.loads(get(url))
        products = payload.get("products", [])
        if not products:
            break
        for item in products:
            handle = item.get("handle", "")
            if not re.fullmatch(r"[a-z0-9-]{3,240}", handle) or handle in seen:
                continue
            seen.add(handle)
            variants = [v for v in item.get("variants", []) if v.get("price")]
            primary = item.get("images", [{}])[0] if item.get("images") else {}
            if not variants or not primary.get("src"):
                continue
            price = str(variants[0]["price"]).replace(",", "")
            if not re.fullmatch(r"\d{1,9}\.\d{2}", price) or float(price) <= 0:
                continue
            name = html.unescape(re.sub("<[^>]+>", "", item.get("title", ""))).strip()
            if len(name) < 5:
                continue
            found.append({
                "name": name[:255], "source": "Heka Store",
                "price_including_vat": price,
                "url": "https://hekastore.com/collections/avize/products/" + handle,
                "image_url": primary["src"].split("?")[0],
            })
            if len(found) >= total:
                return found
    return found


def records_avizelerim(total):
    # WooCommerce public Store API exposes the user-visible (tax inclusive)
    # catalog price in integer minor units and a photo for each simple item.
    found = []
    seen = set()
    for page in range(1, 20):
        url = f"https://avizelerim.com/wp-json/wc/store/v1/products?per_page=100&page={page}"
        rows = json.loads(get(url))
        if not isinstance(rows, list) or not rows:
            break
        for item in rows:
            link = item.get("permalink") or ""
            images = item.get("images") or []
            prices = item.get("prices") or {}
            raw = prices.get("price", "")
            minor = prices.get("currency_minor_unit", 2)
            if not link.startswith("https://avizelerim.com/") or not images or link in seen:
                continue
            seen.add(link)
            if not str(raw).isdigit() or not isinstance(minor, int) or minor > 4:
                continue
            amount = int(raw) / (10 ** minor)
            if amount <= 0 or amount >= 1_000_000_000:
                continue
            name = html.unescape(re.sub("<[^>]+>", "", item.get("name", ""))).strip()
            if len(name) < 5:
                continue
            found.append({
                "name": name[:255], "source": "Avizelerim",
                "price_including_vat": f"{amount:.2f}",
                "url": link,
                "image_url": images[0]["src"].split("?")[0],
            })
            if len(found) >= total:
                return found
    return found


def image_bytes(url):
    binary = get(url)
    valid = (binary.startswith(bytes.fromhex("ffd8ff"))
             or binary.startswith(bytes.fromhex("89504e470d0a1a0a"))
             or (binary.startswith(b"RIFF") and binary[8:12] == b"WEBP"))
    if not valid or len(binary) < 4000:
        raise ValueError("Not a valid product JPEG, PNG or WebP: " + url)
    return binary


def main():
    batch = int(os.getenv("MARS_UAT_BUILD_BATCH", "1"))
    if not 1 <= batch <= 20:
        raise SystemExit("Only batches 1-20 supported")

    count_each = batch * 25
    av = records_avizelerim(count_each)
    hk = records_heka(count_each)
    start = (batch - 1) * 25
    selected = []
    for source, prefix, rows in ((av, "AV", av), (hk, "HK", hk)):
        selected.extend((prefix, index + 1, item) for index, item in enumerate(rows[start:count_each], start=start))
    if len(selected) != 50:
        raise SystemExit(f"Only {len(selected)}/50 source SKUs found for batch {batch}; no partial upload")

    batch_dir = DIR / f"images/batch-{batch:03d}"
    batch_dir.mkdir(parents=True, exist_ok=True)
    entries = []
    for prefix, number, row in selected:
        code = f"DM-{prefix}-{number:03d}"
        print(f"Retrieving {code} {row['name']}", flush=True)
        data = image_bytes(row["image_url"])
        (batch_dir / (code + ".bin")).write_bytes(data)
        entries.append({
            "code": code, "name": row["name"], "source": row["source"],
            "price_including_vat": row["price_including_vat"],
            "url": row["url"],
        })

    dest = DIR / f"batch-{batch:03d}.json"
    dest.write_text(json.dumps({
        "batch": batch,
        "snapshot_date": os.getenv("GITHUB_RUN_STARTED_AT", "2026-10-12")[:10],
        "vat_assumption_percent": 20,
        "price_note": "Storefront displayed price, assumed VAT included; 20% demo tax conversion is an assumption.",
        "products": entries,
    }, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"PASS: batch {batch} has 50 verified products/photos, manifest {dest}", flush=True)


if __name__ == "__main__":
    main()
