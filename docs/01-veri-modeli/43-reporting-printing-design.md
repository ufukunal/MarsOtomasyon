# Faz 10 — Raporlama, çıktı ve tasarım veri modeli

## Master DB

### report_filter_presets
- id
- company_id
- user_id nullable
- report_key
- name
- filters jsonb
- columns jsonb nullable
- sort jsonb nullable
- is_shared boolean
- version
- timestamps

### document_templates
- id
- company_id
- template_key
- name
- revision_no
- render_type: html_pdf | zpl | text
- paper_code nullable
- width_mm nullable
- height_mm nullable
- definition jsonb
- is_active
- is_default
- version
- created_by
- timestamps

K-214/K-235: revizyon immutable Rev.N. Aynı company+template_key için tek default active revision.

### report_export_jobs
- id
- company_id
- user_id
- report_key
- format: pdf|xlsx|csv
- filters jsonb
- periods jsonb
- parameters_hash
- status: queued|processing|done|failed
- progress nullable
- storage_disk/path nullable
- error_summary nullable
- created_at/started_at/finished_at

### print_jobs
- id
- company_id
- user_id nullable
- machine_key nullable
- print_type
- template_id nullable
- template_revision_no nullable
- profile_id nullable
- source_type/source_id nullable
- quantity
- status
- result_metadata jsonb nullable
- parameters_hash nullable
- timestamps

## Period DB

### document_print_snapshots

Kesinleşmiş belgenin çıktı provenance bilgisidir:
- document_id
- template_key
- template_revision_no
- rendered_at nullable
- rendered_by scalar snapshot
- output_hash nullable

Belgenin ticari gerçek kaynağı değildir.

## Tasarım definition yapısı

Serbest kod yok. JSON definition yalnız allow-list bölüm ve token'ları taşır.

Section tipleri:
- header
- company
- customer
- supplier
- document_info
- address
- line_table
- totals
- payment
- notes
- signature
- footer
- barcode
- qr
- custom_text
- spacer/page_break

Token registry server-side tanımlıdır. SQL/PHP/Blade expression kabul edilmez.

## Rapor definition

ReportDefinition kod tarafı metadata:
- key
- title
- permission
- cost_sensitive
- supported_filters
- columns
- default_sort
- totals
- drill_down
- exporters

Rapor sonucunun kendisi kalıcı gerçek kaynak olarak saklanmaz.

## Integrity

- integrity:report-presets
- integrity:templates
- integrity:print-provenance

Rapor sonuçlarının business truth ile eşleşmesi domain integrity komutlarının sorumluluğundadır.
