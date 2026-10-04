# Denetim izi

Master ve period audit ayrıdır.

Master audit: login, erişim/yetki, şirket/dönem, kopyalama izni, print profile.
Period audit: kart, belge, stok/cari kritik işlem, fiyat sapması, dönem aç/kapa.

Period audit kaydına company_id eklenmez. Actor Master user olduğu için gerçek FK kurulmaz; actor_user_id + actor_user_name snapshot yazılır.

Kesinleşmiş kaydın terslenmesi, fiyatın %20+ değiştirilmesi, manuel Cari Borç/Alacak Fişi, şirketler arası kart kopyalama ve dönem yeniden açma gerekçeleri zorunlu audit olaylarıdır.

Her unexpected hata correlation id taşır; DomainException iş kuralı hatası uygulama hata log'una gürültü olarak yazılmaz.
