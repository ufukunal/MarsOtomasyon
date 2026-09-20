# Skill: UX/UI Uzmanı

## 1. Misyon
ERP kullanıcılarının yüksek hacimli işi minimum hata ve minimum gereksiz adımla tamamlamasını sağlar. Tasarım kararı "güzel" görünmekten önce görev başarısına göre değerlendirilir.

## 2. Persona
İlgili görevde persona seç:
- muhasebe
- finans
- satış
- satınalma
- depo operatörü
- depo yöneticisi
- üretim planlama
- üretim operatörü
- yönetici
- B2B müşteri
- mimar
- mobil saha kullanıcısı

Persona seçilmeden workflow tasarlanmaz.

## 3. Görev analizi
Her akış için:
- primary goal
- frequency
- urgency
- error cost
- data entry volume
- approval need
- keyboard/touch
- novice/expert
- exception frequency
- bulk operation
tanımlanır.

## 4. Primary action
Her ekranda primary action açık olmalı.
Bir ekranda 6 eşit güçlü primary button bulunmaz.

## 5. Form akışı
Alan sırası kullanıcı zihinsel modeline göre:
- kim
- ne
- nereden
- nereye
- ne kadar
- fiyat/vergi
- açıklama
gibi domain akışına oturur.

## 6. Validation UX
- hata ilgili alanın yanında
- neyin yanlış olduğu açık
- mümkünse nasıl düzeltileceği söylenir
- submit sonrası bütün hatalar görünür
- server error generic "bir hata oluştu" ile gizlenmez

## 7. Durum görünürlüğü
Kullanıcı şunu her zaman anlayabilmeli:
- draft mı
- approved mı
- posted mı
- partially completed mı
- cancelled/reversed mı
- sync pending mi
- provider error var mı

## 8. Partial operation UX
Sipariş/sevk/fatura gibi akışlarda satır bazında:
- original
- processed
- remaining
açık görünür olmalıdır.

## 9. Confirmation
Confirmation yalnız gerçek riskte:
- delete
- cancel
- post
- reverse
- destructive bulk
kullanılır.

Her save için gereksiz modal confirmation kullanılmaz.

## 10. Undo vs reversal
Draft edit için undo olabilir.
Posted finansal/ledger işlem için reversal gerekir.
UX bu farkı doğru kelimelerle gösterir.

## 11. Keyboard
ERP expert kullanıcı için:
- tab order
- default focus
- F2
- Enter
- Escape
- hotkeys
tasarlanır.

Mouse zorunluluğu azaltılır.

## 12. Bulk operation
Yüksek hacimli işte:
- multi-select
- bulk action
- progress
- partial failure
- retry
- result summary
tasarlanır.

## 13. Mobile
Mobile ekran:
- tek görev
- kısa bilgi
- scan-first
- large tap target
- offline visibility
- sync state
- camera permission
- quick retry
odaklıdır.

## 14. Notification UX
Notification:
- severity
- actionability
- deep link
- read/unread
- duplicate suppression
- channel preference
ile değerlendirilir.

## 15. Empty states
Empty state:
- gerçekten veri yok mu
- filtre sonucu mu
- yetki yok mu
- sync bekleniyor mu
ayrılır.

## 16. Error recovery
Kullanıcı hatadan sonra:
- kaybedecek mi
- tekrar girmek zorunda mı
- retry var mı
- draft korunuyor mu
bilmelidir.

## 17. Accessibility
- keyboard
- focus
- contrast
- label
- touch size
- status not color-only
- dialog behavior

## 18. Anti-patternler
- her şeyi modal yapmak
- primary action'ı saklamak
- aşırı wizard
- uzun formda bağlam kaybettirmek
- mobile'da desktop table küçültmek
- status'u yalnız renkle anlatmak
- hata sonrası formu sıfırlamak

## 19. Definition of Done
Ana user journey, edge-case, keyboard/touch, validation, state, error recovery ve platform davranışı açık.
