# Mars Scanner Agent — Windows WIA tarama hizmeti

MarsProject için bağımsız, küçük Windows servisidir. Tarayıcısı bulunan Epson EcoTank ve diğer **Windows WIA** uyumlu cihazları listeler ve JPEG tarama yapar. Epson EcoTank bir ürün ailesidir; **tarayıcı donanımı ve WIA sürücüsü bulunan model** gereklidir. TWAIN-only modeller bu sürümde desteklenmez.

## Durum / kapsam

- Windows 10/11 x64 veya Windows on ARM (uygun derlemeyle); .NET 10 ile tek dosyalık bağımsız EXE
- Hizmet adı: `MarsScannerAgent`, otomatik başlangıç, servis çökmesinde otomatik yeniden başlatma
- Sadece **127.0.0.1:17864** üzerinde HTTP; dış ağ erişimi yoktur
- Yerel arayüz: `http://127.0.0.1:17864/`
- API: `GET /health`, `GET /api/scanners`, `POST /api/scan`
- Tarama: tek sayfa, JPEG, 150/300/600 DPI; sürücünün desteklemediği çözünürlük hatayla bildirilir
- Aynı anda tek tarama, en fazla 25 MB görüntü. Belge sunucuya otomatik gönderilmez.
- `agent.json` erişim anahtarı Windows ProgramData içinde yalnız SYSTEM/Administrators tam erişimli, LocalService salt okunur olacak şekilde korunur.
- Tamamlanmış tarama bilgisayardaki RAM üzerinden tarayıcıya dönülür, geçici JPG dosyası temizlenir.

## Gereksinimler

1. Tarama yapan Windows bilgisayarına Epson Scan 2 / modelin **WIA destekli** Windows tarayıcı sürücüsünü kurun. Epson ScanSmart tek başına WIA desteğini garanti etmez.
2. Tarayıcıyı Epson'un yerel tarama uygulamasında test edin. USB veya ağ cihazının **Windows WIA listesinde** göründüğünü doğrulayın.
3. Programı derlemek için Windows bilgisayara [.NET 10 SDK](https://dotnet.microsoft.com/download/dotnet/10.0) kurun. **Kurulum için** SDK gerekmez, paket self-contained yayımlanır.
4. Kurulum betiği için Windows PowerShell'i **Yönetici** olarak açın.

## Derleme ve hizmet kurulumu

```powershell
# Repoyu Windows bilgisayarına indirin veya agent/ klasörünü kopyalayın
cd agent
.\setup.ps1
# İsterseniz ayrı ayrı: .\build.ps1 ve .\install.ps1
```

Derleme sonrası: `agent/dist/win-x64/MarsScannerAgent.exe`

Kurulum çıktısında `ERISIM_ANAHTARI` gösterilir. Anahtarı kaydedin. Chrome veya Edge ile **taramanın yapılacağı Windows bilgisayarında** `http://127.0.0.1:17864/` adresini açın. Anahtarı girin, **Tarayıcıları Bul**, cihaz seçimi, **Belge Tara**, ardından **JPG İndir**.

Derleme için alternatif: GitHub **Actions → Mars Scanner Agent Windows Build → Run workflow**. Oluşan `mars-scanner-agent-win-x64` artifact'ını indirin ve Windows'ta açın. EXE'nin yanında gelen `install.ps1` betiğini yönetici çalıştırın.

Windows hizmetini kontrol etmek:

```powershell
Get-Service MarsScannerAgent
Invoke-RestMethod http://127.0.0.1:17864/health
.\diagnostics.ps1
```

Hizmeti kaldırmak (erişim anahtarı/ayar dosyası varsayılan olarak kalır):

```powershell
.\uninstall.ps1
# Ayarları da bilerek silmek için: .\uninstall.ps1 -DeleteSettings
```

## Güvenlik ve MarsProject ile entegrasyon sınırı

- Yetkili API çağrısında `Authorization: Bearer <32+ karakterli kurulum anahtarı>` gerekir; CORS yalnız yerel sayfaya ve `agent.json` içinde açıkça belirlenen HTTPS kaynaklarına izin verir. Kimliksiz istekler `401`, başka web kökenlerinden istekler `403` alır. Token'ı GitHub'a, form alanlarına, URL query string'ine veya sunucu loglarına kaydetmeyin.
- Erişim anahtarı sadece kurulum sırasında yöneticinin ekranında görülür. Gerekirse yönetici PowerShell üzerinden `$env:ProgramData\MarsProject\ScannerAgent\agent.json` dosyasına erişebilir.
- Normal kullanımda scanner görseli MarsProject sunucusuna otomatik yüklenmez; kullanıcı indirdiği JPG'yi mevcut belge eki alanına ekler. **ERP içinden tek tıkla tarama** için ayrıca kimlik doğrulamalı belge eki entegrasyonu ve HTTPS gereklidir.
- Özellikle mevcut MarsProject UAT adresi `http://100.127.235.30:18082`, Chrome'un Local Network Access (LNA) güvenlik ilkeleri altında yerel agent'a otomatik istek yapamaz. LNA izinleri için ERP'nin **HTTPS** altında sunulması gerekir. HTTP'ye tarayıcı güvenlik istisnası eklemeyin.
- Hizmet tarayıcıyı kullanıcı ekranı açmadan WIA COM üzerinden kullanır. Bazı Epson modellerinin sürücüleri veya tarama düğmeleri **etkileşimli kullanıcı oturumu** gerektirebilir. Bu durumda LocalSystem altında çalışan servis tarayamayabilir. Modellerde gerçekte denenmeden tam cihaz uyumluluğu iddia edilmez.
- Servis **LocalSystem** hesabıyla çalışır; yalnız loopback dinler, şifreli API token ve kaynak denetimi uygular. Daha az ayrıcalıklı kullanıcıyla çalıştırma, kullanılan WIA sürücüsünün servis hesabı altında çalışabildiği teyit edildikten sonra ayrıca yapılandırılmalıdır.
- Endpoint'ler internetten veya LAN'dan erişilebilir değildir. Dışarı port açmayın, reverse proxy bağlamayın.

## HTTP API örneği

```powershell
$token = Read-Host 'Mars Scanner erişim anahtarı'
$headers = @{ Authorization = "Bearer $token" }
$devices = Invoke-RestMethod 'http://127.0.0.1:17864/api/scanners' -Headers $headers
$devices.devices | Format-Table id, name

$body = @{ deviceId = $devices.devices[0].id; dpi = 300 } | ConvertTo-Json
Invoke-WebRequest 'http://127.0.0.1:17864/api/scan' -Method Post -Headers $headers -ContentType 'application/json' -Body $body -OutFile "$env:USERPROFILE\Desktop\mars-tarama.jpg"
```

## Bilinen kısıtlar / sonraki kapsam

Bu sürümde ADF çok sayfa besleme, otomatik duplex, PDF üretimi, TWAIN-only sürücüler, tarama profilleri, renk/monokrom kip seçimi, ERP'ye otomatik yükleme, kullanıcı oturumu gerektiren üretici kontrol pencereleri **yoktur**. Bunlar ayrıca geliştirilmeden mevcut gibi gösterilmemelidir.

## Doğrulama

Bu depodan yalnız kaynak kodu yazılmıştır. Windows .NET derlemesi / Epson ile cihaz testi erişilebilen bu çalışma ortamında **çalıştırılmadı**. Hazır EXE GitHub Actions veya Windows SDK ile **derlendikten sonra** kullanılır; **başarılı yayın/cihaz testi iddiası yoktur**.
