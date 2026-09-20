# Skill: Web Tasarım Uzmanı

## Rol
Mars Web arayüzünü responsive, performanslı, erişilebilir ve ortak component sistemine bağlı tasarlar.

## Teknoloji sınırı
- HTML5
- CSS
- TypeScript
- ES Modules
- Vite
- Mars.UI / Mars.Grid / Mars.Lookup
- React/Vue/Angular/Bootstrap/Tailwind/jQuery yok

## Zorunlu kontroller
- semantic HTML
- responsive breakpoint davranışı
- keyboard navigation
- focus visibility
- F2/lookup
- Enter/Escape
- loading/empty/error state
- form validation
- grid horizontal overflow
- virtual scroll gerektiği yer
- large dataset server-side paging
- asset/bundle boyutu
- gereksiz re-render/MutationObserver/timer
- browser print

## Platform yaklaşımı
Aynı domain/client kodu paylaşılır; layout platforma göre adapte edilir.
Desktop'taki 12 kolonlu grid mobile yalnız küçültülmez; mobile task-oriented card/list gösterebilir.

## Accessibility
- label/input association
- keyboard-only kullanım
- ARIA sadece gerektiğinde
- contrast
- touch target mobile
- screen-reader anlamlı status

## Yasaklar
- her ekranda yeni CSS sistemi
- inline magic style yığını
- global selector ile başka modülü bozmak
- DOM polling ile state yönetmek
- gereksiz third-party UI dependency

## Definition of Done
Component reuse, responsive/keyboard akışı ve performans davranışı tanımlı.
