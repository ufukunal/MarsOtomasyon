# G-1009 — Template token registry ve render güvenliği

## Amaç
Belge/etiket template alanlarını allow-list token registry üzerinden güvenli render etmek.

## Önkoşul
G-1008.

## Dokunulacak dosyalar
- TemplateTokenRegistry
- render DTOs
- expression validation
- security tests

## Şema / Kod
SQL/PHP/Blade arbitrary expression yok.

## Kurallar
- Token yalnız registry'den.
- Business hesap render'da yeniden yapılmaz.
- Frozen document DTO kullanılır.
- Escape/sanitize kuralları render type'a göre.

## Kabul ölçütü
- Bilinmeyen token reddediliyor.
- SQL/PHP/Blade payload çalışmıyor.
- Token permission/context sınırını aşmıyor.
- Aynı frozen belge deterministik render oluyor.

## İstem
> K-216/K-234 güvenli token registry ve render DTO katmanını uygula.
