# UI V16.3 reference

The visual source of truth for this port is the supplied `marsotomasyon_ui_v16_3_tasarim_genel_temizlik` prototype.

This change intentionally ports the final application shell and the late V11/V16.1/V16.2/V16.3 visual primitives without moving prototype-only business logic into production. Existing Laravel routes, permissions, controllers, and persisted data remain authoritative.

Key shell contracts:

- 238px grouped dark sidebar on desktop.
- 58px top bar and 40px workspace tab strip.
- `Ön Muhasebe · Operasyon` branding.
- Grouped ERP navigation labels from the reference.
- Compact 10px-radius cards, dense forms/tables, and the final V16.3 document primitives.
- Prototype-only top actions remain hidden in the final production shell.
