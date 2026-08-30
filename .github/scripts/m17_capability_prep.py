from pathlib import Path

patch = Path('.github/scripts/m17_capability_patch.py')
s = patch.read_text()
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
patch.write_text(s)

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
