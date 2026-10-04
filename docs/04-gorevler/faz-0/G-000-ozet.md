# Faz 0 — özet ve sıra

Faz 0, Master + fiziksel şirket/yıl period DB mimarisinin temelidir.

## Sıra

G-001…G-021 mevcut numaralarıyla uygulanır. Her görev 300–500 satırlık standalone standarda yükseltilmiştir.

Özellikle:
- G-002: companies + periods (Master)
- G-003: master + period bağlantısı, company_user + period_user_access
- G-004: Master company_copy_permissions
- G-005: kullanıcı/rol/izin + dönem erişimi
- G-006: period number_series
- G-007: document_date bazlı dönem kilidi
- G-008: Master/period ayrı audit
- G-009: period attachments
- G-010: **Master print_profiles**
- G-011: fiziksel DB izolasyon testleri
- G-016: period-aware cache
- G-017: integrity altyapısı
- G-018: Money/BCMath + idempotency + optimistic lock
- G-021: migrate:periods

## Bitiş ölçütü

- [ ] Master + en az iki şirket ve iki period DB kurulabiliyor
- [ ] Period tablolarında company_id/BelongsToCompany/global scope yok
- [ ] company_user + period_user_access ayrı çalışıyor
- [ ] Erişim doğrulanmadan PeriodContext kurulamıyor
- [ ] Master user'a period DB'den cross-DB FK yok
- [ ] Actor id + name snapshot kullanılabiliyor
- [ ] number_series lockForUpdate ile period içinde çalışıyor
- [ ] dönem kilidi document_date üzerinden
- [ ] Master ve period audit ayrı
- [ ] print_profiles Master'da company+user+machine+type kapsamında
- [ ] etiket ölçüsü paper_code + width_mm + height_mm
- [ ] CacheKey period anahtarı şirket+yıl bağlamı taşıyor
- [ ] stok/cari bakiyesi cache edilmiyor
- [ ] Money + BCMath; PHP float yok
- [ ] idempotency ve version testleri geçiyor
- [ ] pg_trgm/normalize arama gerçek PostgreSQL'de çalışıyor
- [ ] SVG reddediliyor, MIME içerikten doğrulanıyor
- [ ] migrate:periods tüm kayıtlı period DB'leri güncelliyor
- [ ] backup/restore provasında restored period önce migrate edilip closed açılıyor
- [ ] Pint, Larastan, Pest temiz
