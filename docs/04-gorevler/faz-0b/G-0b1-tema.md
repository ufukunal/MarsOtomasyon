# G-0b1 — Tema dosyası

## Amaç
Tüm arayüzün tek CSS dosyası. Prototipin 86 stil bloğu ve 210 KB'ı burada
tek bir düzenli temaya iner.

## Önkoşul
G-012

## Dokunulacak dosyalar
- `resources/css/app.css` (TEK dosya)

## Yapı

```css
/* 1. Değişkenler */
:root{
  --bg:#f2f5f7; --surface:#fff; --soft:#f8fafb;
  --line:#d7e0e5; --line2:#e9eef1;
  --text:#263741; --muted:#75858d;
  --primary:#1f8ac8; --primary-600:#1673aa;
  --green:#0aa56a; --red:#e64e43; --amber:#d38c00;
  --side:252px; --top:48px; --tabs:34px;
  --ctl:30px; --fs:12px; --radius:0;
}

/* 2. Temel öğeler */   html, body, tablo, form alanları, buton
/* 3. Kabuk */          .sidebar .top .worktabs .pagehead .content
/* 4. Bileşenler */     .card .table .badge .modal .toast .tabs .pager
/* 5. Yardımcılar */    .num .muted .up .down
/* 6. Yazdırma */       @media print
```

## Kurallar
- **Ekran bazında sınıf yazılmaz.** `.contact-list-table` gibi bir şey olmaz.
- Yeni görünüm gerekiyorsa bileşen bölümüne eklenir.
- Renk doğrudan yazılmaz, değişkenden gelir.
- `!important` kullanılmaz. Gerekiyorsa seçici yanlıştır.

## Kabul ölçütü
- Dosya 40 KB'ın altında
- `grep -c '!important' app.css` → 0
- Giriş ve ana sayfa doğru görünüyor

## İstem
> resources/css/app.css dosyasını yukarıdaki altı bölüm sırasıyla yaz.
> Değişkenler verilen değerlerle olsun. !important kullanma. Ekran bazında
> sınıf yazma. Başka CSS dosyası oluşturma.
