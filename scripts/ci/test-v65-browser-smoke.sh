#!/usr/bin/env bash
# Verify the approved standalone v65 renders an actual navigation and page in Chromium.
# No backend, production service, PostgreSQL, or Valkey access.
set -Eeuo pipefail
cd "$(dirname "$0")/../.."
mkdir -p artifacts
browser="$(command -v google-chrome || command -v chromium || command -v chromium-browser || true)"
test -n "$browser" || { echo 'FAIL: no Chromium browser installed'; exit 1; }
python3 -m http.server 18765 --bind 127.0.0.1 >artifacts/v65-browser-server.log 2>&1 &
server_pid=$!
cleanup() { kill "$server_pid" 2>/dev/null || true; }
trap cleanup EXIT
for i in $(seq 1 25); do
  if curl --fail --silent --output /dev/null http://127.0.0.1:18765/reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html; then
    break
  fi
  sleep 0.2
done
url='http://127.0.0.1:18765/reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html#dashboard'
timeout 45s "$browser" --headless=new --no-sandbox --disable-gpu \
  --disable-dev-shm-usage --disable-extensions --no-first-run \
  --window-size=1440,900 --virtual-time-budget=8000 \
  --screenshot=artifacts/v65-browser-dashboard.png \
  --dump-dom "$url" >artifacts/v65-browser-dashboard-dom.html \
  2>artifacts/v65-browser-stderr.log || {
    echo 'FAIL: Chromium could not load the v65 page' >&2
    exit 1
  }

python3 - <<'PY'
from html.parser import HTMLParser
from pathlib import Path

class ContentProbe(HTMLParser):
    def __init__(self):
        super().__init__()
        self.depth = 0
        self.mode = None
        self.nav_text = []
        self.main_text = []
        self.groups = 0
        self.pageheads = 0
        self.content = 0
        self.ignored_depth = 0

    def handle_starttag(self, tag, attrs):
        d = dict(attrs)
        void = tag in {'area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'}
        if self.mode is not None and not void:
            self.depth += 1
        elif self.mode is None and d.get('id') == 'nav':
            self.mode = 'nav'
            self.depth = 1
        elif self.mode is None and d.get('id') == 'main':
            self.mode = 'main'
            self.depth = 1
        if self.mode == 'nav' and 'group' in d.get('class', '').split():
            self.groups += 1
        if self.mode == 'main' and 'pagehead' in d.get('class', '').split():
            self.pageheads += 1
        if self.mode == 'main' and 'content' in d.get('class', '').split():
            self.content += 1

    def handle_startendtag(self, tag, attrs):
        # XHTML-style self-closing elements do not change nesting depth.
        pass

    def handle_endtag(self, tag):
        if self.mode is not None:
            self.depth -= 1
            if self.depth <= 0:
                self.mode = None
                self.depth = 0

    def handle_data(self, data):
        if self.mode == 'nav':
            self.nav_text.append(data)
        elif self.mode == 'main':
            self.main_text.append(data)

html = Path('artifacts/v65-browser-dashboard-dom.html').read_text()
probe = ContentProbe()
probe.feed(html)
nav = ' '.join(probe.nav_text).strip()
main = ' '.join(probe.main_text).strip()
print(f'Browser nav groups={probe.groups}, pageheads={probe.pageheads}, content nodes={probe.content}')
print(f'Browser nav text length={len(nav)}, page text length={len(main)}')
print(f'Browser rendered main preview: {main[:180]!r}')
if (probe.groups < 5 or probe.pageheads < 1 or probe.content < 1
        or len(main) < 60 or len(main) > 50000):
    raise SystemExit('FAIL: v65 rendered blank/incomplete app. See screenshot and DOM artifact.')
print('PASS: approved v65 renders navigation groups, page header, and nonempty dashboard in Chromium.')
PY

# Preserve separate canonical stock screens for module-by-module visual acceptance.
# Fail if a route falls back to the blank dashboard instead of its expected screen.
for stock_route in stock_status stock_movements warehouses transfer_list count_list warehouse_slip_list warehouse_slip_new; do
  timeout 45s "$browser" --headless=new --no-sandbox --disable-gpu \
    --disable-dev-shm-usage --disable-extensions --no-first-run \
    --window-size=1440,900 --virtual-time-budget=8000 \
    --screenshot="artifacts/v65-browser-${stock_route}.png" \
    --dump-dom="http://127.0.0.1:18765/reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html#${stock_route}" \
    >"artifacts/v65-browser-${stock_route}-dom.html" \
    2>"artifacts/v65-browser-${stock_route}-stderr.log" || {
      echo "FAIL: Chromium could not render stock route ${stock_route}" >&2
      exit 1
    }
done
python3 - <<'PY_STOCK'
from html.parser import HTMLParser
from pathlib import Path

routes = {
    'stock_status': 'Stok Durumu',
    'stock_movements': 'Stok Hareketleri',
    'warehouses': 'Depolar',
    'transfer_list': 'Depo Transferleri',
    'count_list': 'Stok Sayımları',
    'warehouse_slip_list': 'Ambar Fişleri',
    'warehouse_slip_new': 'Yeni Ambar Fişi',
}

class MainText(HTMLParser):
    def __init__(self):
        super().__init__()
        self.inside = False
        self.depth = 0
        self.words = []

    def handle_starttag(self, tag, attrs):
        if self.inside:
            self.depth += 1
        elif dict(attrs).get('id') == 'main':
            self.inside = True
            self.depth = 1

    def handle_endtag(self, tag):
        if self.inside and tag not in {'meta', 'br', 'img', 'input', 'link', 'hr'}:
            self.depth -= 1
            if self.depth <= 0:
                self.inside = False

    def handle_data(self, data):
        if self.inside:
            self.words.append(data)

for route, title in routes.items():
    page = Path(f'artifacts/v65-browser-{route}-dom.html').read_text()
    parser = MainText()
    parser.feed(page)
    visible_text = ' '.join(parser.words).strip()
    if title not in visible_text or len(visible_text) < 90:
        raise SystemExit(f'FAIL: {route} did not render expected screen: {visible_text[:220]!r}')
    if not Path(f'artifacts/v65-browser-{route}.png').is_file():
        raise SystemExit(f'FAIL: missing screenshot for {route}')
    print(f'PASS: v65 {route} rendered; main text length={len(visible_text)}')
PY_STOCK
