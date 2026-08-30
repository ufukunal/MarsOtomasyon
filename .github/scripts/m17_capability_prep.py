from pathlib import Path

patch = Path('.github/scripts/m17_capability_patch.py')
s = patch.read_text()

# Adapt the Retry-After patch to the already-delivered CommerceSyncGuard signature.
section = s.index('# ProcessIntegrationSync: Retry-After contract.')
needle = '    """    public function handle(ChannelService $channels): void\n'
pos = s.index(needle, section)
block_start = s.rfind('replace_once(', section, pos)
block_end = s.index('\n\n# ChannelCenterService', pos)
replacement = '''replace_once(
    "app/Modules/Operations/Jobs/ProcessIntegrationSync.php",
    """    public function handle(ChannelService $channels, CommerceSyncGuard $guard): void
    {
        if (! $guard->shouldSend($this->effectId)) {
            return;
        }

        try {
            $channels->processSync($this->effectId);
        } finally {
            $guard->reconcile($this->effectId);
        }
    }
""",
    """    public function handle(ChannelService $channels, CommerceSyncGuard $guard): void
    {
        if (! $guard->shouldSend($this->effectId)) {
            return;
        }

        try {
            $channels->processSync($this->effectId);
        } catch (ProviderRateLimitException $exception) {
            $this->release($exception->retryAfterSeconds);
        } finally {
            $guard->reconcile($this->effectId);
        }
    }
""",
    "ProcessIntegrationSync handle",
)'''
s = s[:block_start] + replacement + s[block_end:]

# Adapt route insertion to the current M17 publish route and preserve authorization middleware.
section = s.index('# Routes.')
block_start = s.index('replace_once(', section)
block_end = s.index('\n\n# UI.', block_start)
replacement = '''replace_once(
    "routes/operations.php",
    """        Route::post('/publish', [CommerceController::class, 'publish'])->middleware('can:integrations.manage')->name('publish');
        Route::post('/orders/{order}/retry', [CommerceController::class, 'retryOrder'])->where('order', '[0-9A-HJKMNP-TV-Z]{26}')->middleware('can:integrations.manage')->name('orders.retry');
""",
    """        Route::post('/publish', [CommerceController::class, 'publish'])->middleware('can:integrations.manage')->name('publish');
        Route::post('/poll-orders', [CommerceController::class, 'pollOrders'])->middleware('can:integrations.manage')->name('orders.poll');
        Route::post('/invoice-syncs', [CommerceController::class, 'queueInvoice'])->middleware('can:integrations.manage')->name('invoice-syncs.store');
        Route::post('/orders/{order}/retry', [CommerceController::class, 'retryOrder'])->where('order', '[0-9A-HJKMNP-TV-Z]{26}')->middleware('can:integrations.manage')->name('orders.retry');
""",
    "Commerce capability routes",
)'''
s = s[:block_start] + replacement + s[block_end:]

# Adapt the UI insertion anchor to the current multiline Sipariş Inbox section.
section = s.index('# UI.')
block_start = s.index('replace_once(', section)
block_end = s.index('\n\n# Acceptance:', block_start)
old_block = s[block_start:block_end]
old_marker = "    '<section class=\"statement-table-card\"><h2>Sipariş Inbox / Problem Center</h2>',\n    view_blocks + '<section class=\"statement-table-card\"><h2>Sipariş Inbox / Problem Center</h2>',"
new_marker = "    '<section class=\"statement-table-card\">\\n<h2>Sipariş Inbox</h2>',\n    view_blocks + '<section class=\"statement-table-card\">\\n<h2>Sipariş Inbox</h2>',"
if old_marker not in old_block:
    raise SystemExit('UI patch marker definition not found')
new_block = old_block.replace(old_marker, new_marker, 1)
s = s[:block_start] + new_block + s[block_end:]
patch.write_text(s)

# Record the now-proven M16 exact-main gate in the owner milestone matrix.
matrix = Path('plan/21_MILESTONE_DURUM_MATRISI.md')
m = matrix.read_text()
old = "| M16 | İthalat / Konteyner | **PENDING** | K-052 locked; feature branch PostgreSQL acceptance: file/container/package/component/location, finalized GoodsReceipt handoff, landed-cost allocation/posting, reports/lists/simulator | PR merge + exact final `main` Foundation 4/4 doğrulaması |"
new = "| M16 | İthalat / Konteyner | **DONE** | PR #75; file/container/package/component/location, finalized GoodsReceipt handoff, landed-cost allocation/posting, reports/lists/simulator; merge `98de2a0c65f0c2cec63e7aebc10660b6eca7cab9`; exact main Foundation run `33292866739` 4/4 | V1 milestone gap yok |"
if old not in m:
    raise SystemExit('M16 matrix row not found')
m = m.replace(old, new, 1)
old_order = """1. **M16 İthalat/Konteyner** — K-052 kilitli; vertical slice merge + exact main gate kapatılır.
2. **M17 E-Ticaret Core + WooCommerce** — A-04 public ID strategy önce kilitlenir.
3. **M18 adapter pack** — yalnız gerçek provider contract/fixture doğrulanan adapterlar.
4. **M19 B2B**.
5. **M20 Communication / API**.
6. **M21 Product Image Operations**.
7. **M22 Installation PDF Builder**.
8. **M23 Production Candidate hardening**.
9. **M24 Migration / Go-Live**."""
new_order = """1. **M17 E-Ticaret Core + WooCommerce** — K-053 public ULID policy kilitli; WooCommerce vertical exit gate kapatılır.
2. **M18 adapter pack** — yalnız gerçek provider contract/fixture doğrulanan adapterlar.
3. **M19 B2B**.
4. **M20 Communication / API**.
5. **M21 Product Image Operations**.
6. **M22 Installation PDF Builder**.
7. **M23 Production Candidate hardening**.
8. **M24 Migration / Go-Live**."""
if old_order not in m:
    raise SystemExit('next-order block not found')
m = m.replace(old_order, new_order, 1)
matrix.write_text(m)
