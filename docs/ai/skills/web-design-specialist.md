# Skill: Web Tasarım Uzmanı

## 1. Misyon
Mars'ın web arayüzünü framework bağımsız, hızlı, responsive, erişilebilir ve ortak component sistemiyle sürdürülebilir biçimde tasarlar.

## 2. Teknoloji sınırı
Varsayılan:
- HTML5
- CSS
- TypeScript
- ES Modules
- Vite
- Mars.UI
- Mars.Grid
- Mars.Lookup

Kullanılmaz:
- React
- Vue
- Angular
- Bootstrap
- Tailwind
- jQuery
- hazır ağır admin template

Yeni framework ancak kullanıcı açıkça karar verirse eklenir.

## 3. Semantic HTML
- button için div click kullanılmaz
- label/input ilişkisi
- table gerçekten tabular data için
- nav/main/aside gerektiğinde semantic
- heading order mantıklı
- form elementleri native davranıştan yararlanır

## 4. Component ilkesi
Yeni UI yazmadan:
1. Mars component var mı?
2. Varsa extend edilebilir mi?
3. Aynı pattern başka modülde var mı?
4. Yeni reusable component gerçekten gerekli mi?

Her ekran için bağımsız mini-framework kurulmaz.

## 5. Form standardı
- field height token
- label standardı
- required
- error
- help
- readonly
- disabled
- lookup
- date/number
- keyboard tab order
tutarlı olmalıdır.

## 6. Lookup
Büyük master data için select kullanılmaz.
Mars.Lookup:
- F2
- search
- keyboard navigation
- filter
- pagination/server query
- selection
desteklemelidir.

## 7. Grid
Büyük ERP listeleri:
- server-side paging/filter/sort
- virtual scroll gerekirse
- column resize
- hide/show
- saved view
- keyboard nav
- selection
- totals
- inline edit gerektiğinde
kullanır.

Tüm satırları DOM'a basmak varsayılan değildir.

## 8. Responsive
Responsive = desktop layout'ı scale etmek değildir.

Desktop:
- dense table
- toolbar
- multi-column form

Mobile:
- task-focused list/card
- critical fields only
- drill-down
- bottom/compact actions
- touch-friendly target

## 9. Accessibility
- keyboard-only kullanım
- visible focus
- contrast
- accessible name
- aria yalnız gerekli olduğunda
- live region gerektiğinde
- dialog focus trap
- Escape close policy
- screen reader status

## 10. Performance
Kontrol:
- initial bundle
- route/module lazy load gerekirse
- image size
- request count
- duplicate fetch
- layout thrash
- DOM size
- event listener leak
- timer/MutationObserver abuse

## 11. State management
Framework olmadan da:
- source-of-truth belli
- component ownership açık
- global mutable state minimum
- DOM'dan business state türetilmez
- polling yerine event/explicit refresh tercih edilir

## 12. Error/loading/empty
Her async ekran:
- loading
- empty
- partial
- error
- retry
- stale
durumlarını düşünür.

## 13. Keyboard ERP akışı
- F2 lookup
- Enter/Tab policy
- Escape
- Ctrl+S gerekiyorsa
- row navigation
- modal focus
standartlaştırılır.

## 14. Print
Rapor ve belge ekranı browser print ile uyumlu olmalı.
Print CSS ayrı kontrol edilir.

## 15. Security sınırı
Frontend:
- permission görünürlüğü
- UX restriction
sağlar; gerçek authorization server'dadır.

## 16. Anti-patternler
- inline onclick
- global CSS ile başka modülü bozmak
- repeated DOM polling
- random z-index
- her ekran için yeni modal implementation
- giant single JS file
- UI state'i data attribute karmaşasıyla yönetmek

## 17. Definition of Done
Ekran ortak componentleri kullanıyor, semantic/keyboard/responsive/error-state/performance davranışı tanımlı.
