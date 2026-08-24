# 22 — Transactional Outbox

## Problem
DB transaction başarılı olup queue/webhook/e-posta gönderimi başarısız olursa business state ile external side-effect ayrışabilir. Dış servisi DB transaction içinde çağırmak da lock süresini ve failure riskini artırır.

## Çözüm
Business transaction içinde domain değişiklikleriyle birlikte `outbox_messages` kaydı yazılır. Commit sonrası worker mesajı işler.

## Outbox alanları
En az:
- id
- event_type
- aggregate/source type + id
- payload/version
- correlation/idempotency key
- available_at
- attempts
- processed_at
- last_error metadata
- timestamps

Secret plaintext payload'a yazılmaz.

## İşleme
Worker:
1. uygun pending mesajı atomik claim eder
2. handler çalıştırır
3. başarılıysa processed işaretler
4. transient hatada backoff
5. kalıcı/limit aşımı hatada failed/dead görünürlüğü

## Exactly-once beklentisi
Distributed sistemde gerçek exactly-once varsayılmaz. Outbox **at-least-once delivery + idempotent consumer** ile tasarlanır.

## Kullanım alanları
- marketplace/webhook export
- SMS/e-posta/WhatsApp
- projection/read model update gereken asenkron işler
- entegrasyon bildirimleri
- ağır rapor/export tetikleri

## Kullanılmayacak yer
Stok/cari ledger gibi aynı DB transaction'da atomik olması gereken core business effect outbox'a ertelenmez.

## Observability
Outbox backlog, oldest pending age, failure count ve retry sayıları Sistem Sağlığı'nda izlenebilir.

## Replay
Admin replay kontrollü permission + audit ile yapılır. Replay consumer idempotency'sini bypass etmez.
