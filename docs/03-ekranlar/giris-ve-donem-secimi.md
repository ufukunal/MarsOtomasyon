# Ekran — Giriş ve şirket/dönem seçimi

**Veritabanı: MASTER.** Kullanıcı ve dönem bilgisi master'dadır; dönem
bağlantısı ancak seçim yapıldıktan sonra kurulur.

## Rota
`/giris` → `/secim` → `/`

## Akış

```
1. Giriş: e-posta + parola
2. Şirket seçimi — yalnızca company_user'da bağlı olduğu şirketler
3. Dönem seçimi — o şirketin periods kayıtları
4. company_user + period_user_access doğrulanır
5. PeriodContext::use($companyId, $periodId) → bağlantı kurulur
5. Ana sayfaya yönlendirme
```

## Giriş ekranı alanları
E-posta, parola, "beni hatırla", parola sıfırlama bağlantısı.
Başarısız giriş `activity_log`'a IP ile yazılır. 5 hatalı denemeden
sonra 15 dakika kilit.

## Şirket/dönem seçimi

| Alan | Kural |
|---|---|
| Şirket | Yalnızca yetkili olduğu şirketler; tek şirketse otomatik seçilir |
| Dönem | O şirketin dönemleri; varsayılan `users.last_company_id` + `users.last_period_id`; erişim yoksa seçim zorunlu |
| Bağlanılacak veritabanı | Ekranda **gösterilir** (`ABCHolding_2026`) — kullanıcı nerede çalıştığını bilmeli |

**Durum davranışı:** `active` seçilebilir · `closed` seçilebilir ama
salt okunur, uyarı gösterilir · `archived` seçilemez, "önce geri yükleyin".

## Kısayol
Üst çubuktaki şirket/dönem seçici aynı işi yapar; yeniden giriş gerekmez.
`PeriodContext::use()` çağrılır ve sayfa yenilenir.

## Etki zinciri — "Devam"
```
 → yetki kontrolü (company_user + period_user_access)
 → period kaydının seçilen şirkete ait olduğu doğrulanır
 → dönem durumu kontrolü
 → PeriodContext::use(company_id, period_id) → config + DB::purge + DB::reconnect
 → session'a company_id, period_id ve year
 → users.last_company_id + users.last_period_id güncellenir
 → activity_log (master)
```
