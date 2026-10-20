<section class="v65-custom-photo-editor" data-v65-quote-image-editor wire:ignore
    aria-label="Özel üretim görsel düzenleme alanı">
    <header class="v65-photo-header">
        <div><strong>Görsel Düzenleyici</strong><small>Nesneleri seçin, taşıyın ve tekrar düzenleyin</small></div>
        <div class="v65-photo-header-actions">
            <button type="button" data-v65-image-action="undo" title="Geri al (Ctrl+Z)">↶ Geri Al</button>
            <button type="button" data-v65-image-action="redo" title="Yinele (Ctrl+Y)">↷ Yinele</button>
            <button type="button" data-v65-image-action="original">Orijinali Aç</button>
        </div>
    </header>
    <div class="v65-photo-layout">
        <aside class="v65-custom-photo-tools" aria-label="Düzenleme araçları">
            <button type="button" class="is-active" data-v65-image-tool="select" title="Seç ve taşı">↖ <span>Seç</span></button>
            <button type="button" data-v65-image-tool="text" title="Metin ekle">T <span>Metin</span></button>
            <button type="button" data-v65-image-tool="arrow" title="Ok ekle">↗ <span>Ok</span></button>
            <button type="button" data-v65-image-tool="rectangle" title="Dikdörtgen çiz">▭ <span>Kutu</span></button>
            <button type="button" data-v65-image-tool="circle" title="Daire çiz">◯ <span>Daire</span></button>
            <button type="button" data-v65-image-tool="draw" title="Serbest çizim">✎ <span>Çizim</span></button>
            <button type="button" data-v65-image-tool="crop" title="Kırpılacak alanı seç">⌗ <span>Kırp</span></button>
        </aside>
        <div class="v65-photo-stage">
            <div class="v65-photo-raster-actions">
                <button type="button" data-v65-image-action="crop-apply">Kırpmayı Uygula</button>
                <button type="button" data-v65-image-action="rotate-left">↶ 90°</button>
                <button type="button" data-v65-image-action="rotate-right">↷ 90°</button>
                <button type="button" data-v65-image-action="flip-horizontal">Yatay Çevir</button>
                <button type="button" data-v65-image-action="flip-vertical">Dikey Çevir</button>
            </div>
            <div class="v65-custom-photo-canvas-scroll">
                <div class="v65-custom-photo-canvas-wrap">
                    <canvas data-v65-image-canvas aria-label="Düzenlenen görsel"></canvas>
                    <canvas data-v65-image-overlay aria-label="Seçilebilir görsel nesneleri" tabindex="0"></canvas>
                </div>
            </div>
            <div class="v65-custom-photo-zoom" aria-label="Yakınlaştırma">
                <button type="button" data-v65-image-zoom-step="-10" aria-label="Uzaklaştır">−</button>
                <input type="range" data-v65-image-zoom min="50" max="200" step="10" value="100"
                    aria-label="Yakınlaştırma yüzdesi">
                <button type="button" data-v65-image-zoom-step="10" aria-label="Yakınlaştır">+</button>
                <span data-v65-image-zoom-label>% 100</span>
                <button type="button" data-v65-image-action="fit">Ekrana Sığdır</button>
            </div>
        </div>
        <aside class="v65-photo-inspector" aria-label="Nesne özellikleri ve katmanlar">
            <h3>Özellikler</h3>
            <div class="v65-photo-defaults">
                <label>Yeni metin <input type="text" data-v65-image-text maxlength="120" value=""
                    placeholder="Görsele tıklayarak yerleştirin"></label>
                <label>İşaret rengi <input type="color" data-v65-image-color value="#e11d48"></label>
                <label>Yeni metin boyutu <input type="number" data-v65-image-default-font min="10" max="180" value="24"></label>
                <label>Yeni çizgi kalınlığı <input type="number" data-v65-image-default-stroke min="1" max="30" value="3"></label>
            </div>
            <div data-v65-image-properties hidden class="v65-photo-selected-properties">
                <h3>Seçili nesne</h3>
                <label>Metin <input type="text" data-v65-image-object-text maxlength="120"></label>
                <label>Yazı boyutu <input type="number" data-v65-image-font-size min="10" max="180"></label>
                <label>Çizgi kalınlığı <input type="number" data-v65-image-stroke-width min="1" max="30"></label>
                <div class="v65-photo-object-actions">
                    <button type="button" data-v65-image-action="duplicate">Çoğalt</button>
                    <button type="button" data-v65-image-action="front">Öne Al</button>
                    <button type="button" data-v65-image-action="back">Arkaya Al</button>
                    <button type="button" data-v65-image-action="delete" class="is-danger">Sil</button>
                </div>
            </div>
            <h3>Katmanlar</h3>
            <div data-v65-image-layers class="v65-photo-layers">
                <small>Henüz işaretleme yok</small>
            </div>
            <div class="v65-photo-resize">
                <label>Genişlik (px) <input type="number" data-v65-image-width min="1" max="3200"></label>
                <button type="button" data-v65-image-action="resize">Görseli Boyutlandır</button>
            </div>
        </aside>
    </div>
    <footer class="v65-custom-photo-savebar">
        <span role="status" aria-live="polite" data-v65-image-status>Soldaki görsellerden birini düzenlemek için seçin.</span>
        <button type="button" data-v65-image-action="reset">Sıfırla</button>
        <button type="button" data-v65-image-action="save-as">Farklı Kaydet</button>
        <button type="button" class="v65-btn v65-btn--primary v65-btn--sm"
            data-v65-image-action="save">Uygula ve Kaydet</button>
    </footer>
</section>
