# 30 — Dosya Önizleme, PDF / Görsel / CAD / 3D Görüntüleme

Bu belge MarsOtomasyon Files capability'sinin kullanıcı-visible önizleme ve ileride CAD/3D görüntüleme sınırlarını tanımlar.

## 1. Ana ilke
Orijinal dosya **Attachment/File authority** olarak saklanır. Görüntüleyici veya dönüştürücü hiçbir zaman orijinal dosyanın yerine geçmez.

Model:
`Original Attachment → Preview/Derivative Job → Preview Artifact/Manifest → Viewer`

Preview silinebilir ve yeniden üretilebilir; original file, checksum, MIME/type ve authorization source-of-truth'tur.

## 2. V1 — PDF Viewer
PDF dosyaları Mars içinde yeni sekme/modal/fullscreen viewer ile açılabilir.

Önerilen teknoloji: Mozilla PDF.js.

Minimum özellikler:
- sayfa ileri/geri
- sayfa numarasına git
- zoom + fit width/fit page
- rotate
- text search
- thumbnail/sidebar where useful
- fullscreen
- yazdır / indir aksiyonları permission'a bağlı
- parola korumalı PDF için kontrollü hata/şifre akışı where supported
- private file URL doğrudan public storage URL değildir; authorization kontrollü stream/signed short-lived URL kullanılır

PDF render sonucu business truth değildir.

## 3. V1 — Görsel Viewer
Desteklenen browser-safe görseller en az:
- JPEG/JPG
- PNG
- WebP
- GIF (read-only preview)

Opsiyonel destek gerçek ihtiyaçta:
- TIFF/TIF server-side preview conversion
- HEIC/HEIF server-side preview conversion
- SVG yalnız sanitization/security policy ile

Minimum özellikler:
- zoom/pan
- fit screen / actual size
- rotate
- next/previous gallery
- thumbnail strip where multiple attachments exist
- fullscreen
- image metadata summary where useful
- original download permission'a bağlı

Çok büyük teknik/ürün görsellerinde tile/deep-zoom ihtiyacı ölçülürse OpenSeadragon benzeri tile viewer kullanılabilir; V1'de zorunlu değildir.

## 4. Attachment preview metadata
Attachment/File metadata'ya doğrudan veya ayrı preview entity ile şu kavramlar eklenebilir:
- preview_status: pending|processing|ready|failed|unsupported
- preview_kind: pdf|image|cad_2d|model_3d|thumbnail|other
- preview_provider
- preview_version
- source_checksum
- derivative_checksum
- generated_at
- page/view count where known
- failure_code / normalized failure message

Preview artifact source checksum ile bağlıdır; source değişirse eski preview stale olur.

## 5. Güvenlik
- MIME yalnız extension'a güvenmez; server-side type validation/sniffing yapılır
- uploaded file private/quarantine policy'ye tabidir
- viewer authorization attachment owner/company permission'ını tekrar kontrol eder
- derivative/public CDN URL varsa kısa ömürlü veya access-controlled olmalıdır
- SVG/scriptable content sanitize veya disable edilir
- PDF active content viewer sandbox/CSP ile sınırlandırılır
- preview worker/business web process'ten izole edilebilir
- preview failure original dosyayı bozmaz

## 6. CAD / 3D — planlı genişleme
CAD/3D preview V1 core viewer değildir; **M32 — CAD / 3D Viewer** planlı post-V1 milestone'udur.

Hedef format aileleri:
- AutoCAD: DWG, DXF
- Autodesk/3D: MAX, 3DS
- interchange: FBX, OBJ, STL, STEP/STP, IGES/IGS
- BIM/AEC gerektiğinde: IFC, RVT/DWF capability provider'a göre
- web-native derivative: glTF/GLB where suitable

## 7. CAD/3D stratejisi
Tarayıcıya `.dwg` veya `.max` parser gömmek varsayılan yaklaşım değildir.

Tercih sırası:
1. **Autodesk Platform Services (APS) Model Derivative + Viewer SDK** — çok formatlı cloud translation/viewer; özellikle DWG/Autodesk/3ds Max ekosistemi için ana aday.
2. **Open Design Alliance (ODA) SDK/Web SDK** — DWG/DXF ve desteklenen CAD/BIM formatları için lisanslı/self-hosted veya kontrollü alternatif aday.
3. Web-native formatlar için server-side/offline converter → `glTF/GLB` + Three.js benzeri viewer, ancak converter'ın lisans/format doğruluğu ayrı doğrulanır.

Tek provider hard-code edilmez. `cad_viewer` / `model_derivative` provider family ve capability contract kullanılır.

## 8. Autodesk APS yaklaşımı
APS seçilirse tipik akış:
`Private Attachment → APS upload/object reference → Model Derivative translation → SVF/SVF2/thumbnail/metadata → Viewer SDK`

Mars şu metadata'yı saklar:
- provider job id/URN mapping
- source checksum
- translation status
- derivative manifest reference
- viewer capability/version
- expires/retention metadata where relevant

Provider callback/result business Attachment authority'yi overwrite etmez.

Cloud'a tasarım dosyası gönderme KVKK/ticari gizlilik açısından company-level policy ve explicit admin enablement ister.

## 9. ODA yaklaşımı
ODA seçilirse DWG/DXF başta olmak üzere desteklenen formatları local/self-hosted rendering/translation için kullanabiliriz.

Avantaj:
- hassas CAD dosyalarını üçüncü taraf cloud'a göndermeme seçeneği.

Dezavantaj:
- ticari SDK/lisans maliyeti
- deployment/runtime entegrasyon karmaşıklığı
- desteklenen format/capability sürümlerini takip gereği

ODA provider gerçek lisans ve SDK erişimi doğrulanmadan production implementation yapılmaz.

## 10. `.MAX` özel kuralı
3ds Max `.max` dosyaları proprietary scene formatıdır. Mars içinde kendi `.max` parser'ımızı yazmayacağız.

Destek seçenekleri:
- Autodesk APS Model Derivative ile web-viewable derivative üretmek
- şirket içinde 3ds Max/Autodesk automation veya kontrollü workstation/worker üzerinden FBX/OBJ/glTF gibi interchange preview türetmek

Orijinal `.max` saklanır; preview için türetilen model ayrı artifact'tır.

## 11. Viewer özellikleri — M32
2D CAD:
- pan/zoom
- layer visibility where provider supports
- model/layout/sheet selection
- object/property inspect where supported
- measure where supported
- print/snapshot

3D:
- orbit/pan/zoom
- fit model
- camera/view presets
- object tree
- hide/isolate
- property inspect
- section/cut where supported
- measure where supported
- fullscreen
- thumbnail generation

İlk milestone **read-only viewer**dır. CAD/3D editing, geometry authoring veya AutoCAD/3ds Max replacement değildir.

## 12. M32 entry gate
- gerçek format öncelik listesi belirlenmiş
- cloud CAD upload policy belirlenmiş
- APS vs ODA vs local-conversion provider kararı en az pilot için kapanmış
- provider lisans/fiyat/API/SDK contract doğrulanmış
- 3 gerçek fixture: DWG + DXF + MAX veya kullanılan gerçek formatlar
- max file size/timeout/retention belirlenmiş

## 13. M32 DoD
- original file immutable/private
- source checksum → derivative lineage
- duplicate upload/translation idempotent
- failed translation normalized error
- cross-company access BLOCK
- provider secret/token masked/redacted
- provider unavailable iken original download erişimi bozulmuyor
- DWG/DXF ve seçilen 3D fixture gerçek viewer'da açılıyor
- `.max` direct parse iddiası yok; provider/conversion sonucu gösteriliyor
- derivative delete/rebuild original'i etkilemiyor
- no CAD/3D business authority introduced

## 14. V1 UI konumu
PDF/görsel viewer yeni top-level menü oluşturmaz.

Her attachment bulunan yerde ortak `Önizle` action kullanılır:
- Cari dosyaları
- Ürün teknik dosyaları/görseller
- Çek/senet taramaları
- Satış/alış belgeleri
- İthalat teknik dosyaları
- Üretim/fason talimatları

Unsupported formatta action:
`Önizleme desteklenmiyor · İndir`
şeklinde açık davranır; boş/no-op viewer açılmaz.
