# 13 — UI/UX ve Raporlama V4.1

## Otorite
Kullanıcı-visible referans: **MarsOtomasyon V16.3 — Genel Tasarım Temizliği**. Ayrıntılı sözleşme `26_V16_3_TASARIM_UYUMU.md`.

## Shell
- 252px sol navigasyon
- üst toolbar
- workspace tabs
- global arama
- hızlı işlemler / command palette
- sticky sayfa ve form aksiyonları
- drawer/modal/toast
- keyboard/scanner odaklı kullanım

## Workspace tabs
- dirty olmayan sekme direkt kapanır
- dirty sekme kapanırken kaydet/vazgeç uyarısı
- dirty dot görünür
- save dirty state'i temizler
- readonly sekme direkt kapanır
- search typing dirty oluşturmaz

## Ekran deseni
Listeler:
`başlık → hızlı aksiyon → filtre → tablo → pagination/export`.

Formlar domain'e özgüdür. Generic her modüle aynı form/table renderer yaklaşımı acceptance değildir.

## Detail/Edit
Detail readonly bilgi merkezi; edit ayrı route. Finalized belge form inputlarıyla gösterilmez.

## Kullanıcı dili
Türkçe ve iş alanı odaklı. Internal state key, queue, outbox, idempotency, provider payload gibi teknik terimler normal kullanıcıya gösterilmez.

## Dead button yasağı
Görünür her aksiyon:
- gerçek route açmalı,
- gerçek modal/drawer çalıştırmalı,
- veya permission/availability nedeniyle açıkça disabled ve açıklamalı olmalıdır.

Sessiz no-op yasaktır.

## Belge satır grid'i
Ürün arama kod/barkod/QR/ad ile çalışır. Sonuçta kod, ad, net/gerekli display fiyat, stok, rezerve, kullanılabilir gösterilir. Scanner Enter ile seçim ve quantity focus desteklenir.

## Rapor Merkezi
Hedef **tam 40 hazır rapor**, 8 kategori × 5 rapordur. Generic visual report designer yoktur.

Akış:
`Filtreler → KPI → Sonuç Tablosu → Kaydet → Zamanla → Excel/CSV → PDF/Yazdır`.

Her rapor stabil `report_key` taşır; route/test/permission bu key üzerinden doğrulanabilir. Raporlar domain milestone'ları tamamlandıkça eklenir; M13'te yalnız M0–M12 domain raporları zorunludur, final 40 katalog M23 production-candidate gate'inde tamamlanır.

### A. Cari & Finans — 5
1. `ACC-01` Cari Bakiye Listesi
2. `ACC-02` Cari Ekstre / Running Balance
3. `ACC-03` Vade / Aging Analizi
4. `ACC-04` Tahsilat ve Ödeme Özeti
5. `ACC-05` Cari Risk / Exposure ve Limit Aşımları

### B. Satış — 5
6. `SAL-01` Satış Özeti — Gün/Ay/Dönem
7. `SAL-02` Müşteri Bazlı Satış
8. `SAL-03` Ürün/Kategori Bazlı Satış
9. `SAL-04` Satış Siparişi Sevk/Fatura/Kalan Progress
10. `SAL-05` Satış İade ve Net Satış Özeti

### C. Alış — 5
11. `PUR-01` Alış Özeti — Gün/Ay/Dönem
12. `PUR-02` Tedarikçi Bazlı Alış
13. `PUR-03` Ürün/Kategori Bazlı Alış
14. `PUR-04` Satınalma Siparişi Kabul/Fatura/Kalan Progress
15. `PUR-05` Mal Kabul Kalite / Bekleyen / Red Özeti

### D. Stok — 5
16. `STK-01` Depo Bazlı Stok Bakiyesi ve Kullanılabilir
17. `STK-02` Stok Hareketleri
18. `STK-03` Rezervasyon / Kullanılabilir / Stok Eksikliği
19. `STK-04` Depo Transferi / Yoldaki Stok
20. `STK-05` Stok Sayım Farkları ve Stok Değeri

### E. Üretim / Fason — 5
21. `PRD-01` Üretim Emri Durum / Progress
22. `PRD-02` Malzeme Tüketimi
23. `PRD-03` Mamul Girişi / Üretim Miktarı
24. `PRD-04` Fire / Eksik / Sapma Özeti
25. `SUB-01` Fason Gönderilen / Gelen / Kalan Custody

### F. İthalat — 5
26. `IMP-01` Shipment / Konteyner Durum Özeti
27. `IMP-02` Ürün–Konteyner–Koli Dağılımı
28. `IMP-03` İthalat Maliyet Kalemleri ve Allocation Reconciliation
29. `IMP-04` Ürün Bazlı Landed Cost
30. `IMP-05` Konteyner Hacim / Ağırlık / Yükleme Planı

### G. E-Ticaret / B2B — 5
31. `COM-01` Kanal Bazlı Sipariş / Ciro Özeti
32. `COM-02` Ürün Mapping ve Listing Durumu
33. `COM-03` Kanal Stok/Fiyat Sync ve Problem Özeti
34. `COM-04` E-Ticaret İade / Soru / Operasyon Sorunları
35. `COM-05` Marketplace Clearing / Payout / Komisyon / Katkı Özeti

### H. Yönetim — 5
36. `MGT-01` Yönetim KPI Özeti
37. `MGT-02` Satış–Alış Dönemsel Trend
38. `MGT-03` Stok Değeri / Hareketsiz Stok / Devir Göstergeleri
39. `MGT-04` Cari Alacak–Borç / Nakit–Banka Pozisyon Özeti
40. `MGT-05` İstisna ve Kontrol Raporu — negatif giriş denemesi, limit aşımı, reconciliation/problem uyarıları

## Rapor doğruluk kuralları
- rapor authority değildir; source ledger/document'tan türetilir
- cari aging OpenItem allocation yapmaz
- stock value moving weighted average policy'yi kullanır
- marketplace actual fee/contribution yalnız provider evidence varsa actual gösterilir
- scheduled run runtime authorization/company context kontrol eder
- report count yalnız route varlığıyla değil meaningful data/test ile tamamlanmış sayılır

## Yazdırma
- A4 portrait/landscape otomatik seçimi
- action column gizleme
- fit/wrap
- repeat table header
- firma/tarih/kayıt sayısı header
- footer/page no
- scrollbar/screenshot print yok

## Erişilebilirlik
Keyboard focus görünür, form label'ları gerçek, hata mesajları alanla ilişkili ve yalnız renge bağlı olmayan durum göstergeleri kullanılmalıdır.

## Responsive
Öncelik desktop operasyon ekranıdır; dar viewport'ta temel kullanım bozulmamalı, ancak masaüstü yoğun tablo verimliliği feda edilmez.
