# M32 — CAD / 3D Viewer provider evidence

Bu kayıt M32 pilot provider, fixture, güvenlik ve operasyon kararlarını sabitler.

- Pilot cloud provider: Autodesk Platform Services (APS) Model Derivative + Viewer.
- Pilot local/privacy provider: Mars read-only browser renderer (DXF + OBJ only).
- Pilot formatlar: DWG, DXF, OBJ. `.max` Mars tarafından native parse edilmez.
- Cloud upload company-level explicit opt-in; varsayılan kapalıdır.
- Maksimum kaynak boyutu: 50 MiB.
- APS translation timeout budget: 300 saniye.
- Transient preview retention hedefi: 1 gün; derivative yeniden üretilebilir ve business authority değildir.
- Original Attachment/FileAsset immutable/private authority olarak kalır.
- Viewer erişimi company/file authorization üzerinden yeniden doğrulanır; APS browser token scope yalnız `viewables:read` olur.
- Source SHA-256 → provider/version → derivative identity deterministic ve idempotent tutulur.
- Gerçek DWG fixture kanıtı Autodesk APS resmi/public viewer örneği ile; local CI fixture'ları gerçek ASCII DXF ve OBJ dosyaları ile doğrulanır.
- ODA self-hosted alternatif olarak tutulur; ticari SDK/lisans erişimi doğrulanmadan production provider olarak aktif edilmez.

## Provider contract notları

APS production akışı OAuth v2 client-credentials, transient OSS bucket, direct-to-S3 upload, Model Derivative SVF2 translation/manifest ve kısa ömürlü Viewer tokenından oluşur. Credential değerleri DB/job manifestine veya loglara yazılmaz.

## Lisans / fixture

Autodesk'in `autocad-da-acc-model-viewer` örnek reposu MIT lisanslıdır ve gerçek `House.dwg` içerir. Binary fixture Mars reposuna kopyalanmaz; canlı doğrulama exact upstream source/URN ile yapılır. Bu sayede lisans/provenance kaydı açık kalır.
