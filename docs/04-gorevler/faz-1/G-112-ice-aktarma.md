# G-112 — Excel / JSON içe aktarma

## Amaç
Mevcut cari ve ürün verisinin sisteme alınması. Canlıya geçişin
(Faz 11) en kritik parçası burada hazırlanır.

## Önkoşul
G-104, G-106

## Desteklenen
- Excel (.xlsx), CSV, JSON
- Cari, ürün, fiyat listesi, açılış stok miktarı

## Akış
1. Dosya yüklenir
2. Kolon eşleştirme ekranı: dosyadaki kolon → sistem alanı
3. **Önizleme**: ilk 20 satır, hatalar kırmızı
4. Doğrulama: zorunlu alan, tip, benzersizlik, ilişki
5. Onay → kuyruğa atılır, parçalı işlenir
6. Sonuç raporu: başarılı / hatalı satır sayısı, hata listesi indirilebilir

## Kurallar
- **Tamamı tek işlemde** uygulanır; bir satır hatalıysa seçenek sunulur:
  hatalıları atla ya da tümünü iptal et
- Aynı dosya iki kez yüklenirse mükerrer kayıt oluşmaz (kod bazında kontrol)
- İçe aktarma `audit_log`'a düşer
- Büyük dosya kuyrukta işlenir, ekran beklemez

## Hata raporu
Satır no, kolon, değer, hata mesajı. Excel olarak indirilebilir.

## Kabul ölçütü
- 1000 satırlık dosya hatasız aktarılıyor
- Hatalı satır raporlanıyor, doğru satırlar etkilenmiyor (atla seçildiyse)
- Aynı dosya ikinci kez yüklenince mükerrer oluşmuyor
- Kuyruk işçisi durdurulursa aktarım yarım kalmıyor (işlem geri alınıyor)

## İstem
> Excel/CSV/JSON içe aktarma için Livewire sihirbazı yaz: dosya yükleme,
> kolon eşleştirme, önizleme, doğrulama, kuyruğa atma, sonuç raporu.
> Cari ve ürün için eşleştirme tanımlarını yaz. Kuyruk işi company_id
> taşısın ve handle başında CompanyContext::set çağırsın.
