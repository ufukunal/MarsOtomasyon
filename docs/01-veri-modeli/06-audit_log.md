# İşlem geçmişi (audit)

İki ayrı audit alanı vardır.

## Master activity_log

Login/başarısız login, kullanıcı/rol/izin değişikliği, şirket/dönem oluşturma, period erişim yetkisi, company_copy_permissions ve print profile yönetimi.

## Period activity_log

Kart oluşturma/değiştirme/pasifleştirme, belge kesinleştirme/ters kayıt, fiyat sapması, dönem açma/kapama, stok/cari kritik eylemleri ve şirketler arası kopyalama hedef işlemi.

Period activity_log'a `company_id` eklenmez. Period DB zaten şirket+yıldır.

Master user için period DB'de FK kurulmaz. Actor:
- actor_user_id bigint nullable
- actor_user_name string nullable
- correlation_id
- event
- subject_type / subject_id
- properties jsonb
- created_at

Posted kayıtlar silinmez. Audit log otomatik temizlenmez; gerekiyorsa arşiv politikasıyla ayrılır.

Liste görüntüleme/normal arama gibi gürültülü okuma olayları varsayılan olarak loglanmaz.
