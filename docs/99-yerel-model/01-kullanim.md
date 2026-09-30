# Yerel model ile çalışma

## Neden bu belgeler böyle yazıldı

Yerel modellerin bağlam penceresi dardır ve çıkarım yapmakta zayıftırlar.
Bu yüzden:

- Her görev dosyası **tek başına yeterlidir**
- Şema ve kod **kopyalanabilir halde** verilir, tarif edilmez
- Bağlam tekrar edilir; tekrar, eksik bilgiden iyidir
- Dosya başına 300-500 satır sınırı

## Bir görev nasıl çalıştırılır

1. Görev dosyasını **tamamen** modele ver
2. Sonundaki "İstem" bölümünü komut olarak kullan
3. Üretilen kodu kabul ölçütüyle doğrula
4. Geçmezse hatayı ve ilgili dosyayı ver, düzelttir
5. Geçtiyse commit at, sonraki göreve geç

## Kurallar

**Model bir seferde tek görev yapar.** "G-002 ve G-003'ü yap" deme.

**Kabul ölçütü geçmeden ilerleme.** Hatalı temel üstüne yazılan her şey
sonra yeniden yazılır.

**Model şemayı değiştirmesin.** Görev dosyasındaki şema birebir uygulanır.
Model "daha iyisini" önerirse önce karar günlüğüne işlenir, sonra yazılır.

**Model dosya uydurmasın.** "Dokunulacak dosyalar" listesi dışına çıkmamalı.

## Sık karşılaşılan hatalar

| Belirti | Sebep |
|---|---|
| Veri diğer şirkette görünüyor | `BelongsToCompany` trait'i eklenmemiş |
| İki belge aynı numarayı aldı | `lockForUpdate()` atlanmış |
| Maliyet satış rolünde görünüyor | Kolon gizlenmiş ama üretilmiş |
| Kuyrukta şirket bulunamıyor | İş `company_id` taşımıyor |
| Test geçiyor ama canlıda bozuk | `DB::table()` ile global scope atlanmış |
