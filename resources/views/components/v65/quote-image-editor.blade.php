<section class="v65-custom-photo-editor" data-v65-quote-image-editor wire:ignore
    aria-label="Özel üretim görsel düzenleme alanı">
    <div class="v65-custom-photo-tools" role="toolbar" aria-label="Resim düzenleme araçları">
        <button type="button" data-v65-image-tool="crop">Kırp</button>
        <button type="button" data-v65-image-action="crop-apply">Kırpmayı Uygula</button>
        <button type="button" data-v65-image-action="rotate-left">↶ 90°</button>
        <button type="button" data-v65-image-action="rotate-right">↷ 90°</button>
        <button type="button" data-v65-image-action="flip-horizontal">Yatay Çevir</button>
        <button type="button" data-v65-image-action="flip-vertical">Dikey Çevir</button>
        <button type="button" data-v65-image-tool="text">Metin</button>
        <button type="button" data-v65-image-tool="arrow">Ok</button>
        <button type="button" data-v65-image-tool="rectangle">Dikdörtgen</button>
        <button type="button" data-v65-image-tool="circle">Daire</button>
        <button type="button" data-v65-image-tool="draw">Çizim</button>
        <button type="button" data-v65-image-action="undo">Geri Al</button>
        <button type="button" data-v65-image-action="redo">Yinele</button>
    </div>
    <div class="v65-custom-photo-canvas-scroll">
        <div class="v65-custom-photo-canvas-wrap">
            <canvas data-v65-image-canvas aria-label="Düzenlenen resim"></canvas>
            <canvas data-v65-image-overlay aria-label="Kırpma ve işaretleme önizlemesi"></canvas>
        </div>
    </div>
    <div class="v65-custom-photo-resize">
        <label>Genişlik (px) <input type="number" data-v65-image-width min="1" max="3200"></label>
        <button type="button" data-v65-image-action="resize">Boyutlandır</button>
        <label>İşaret rengi <input type="color" data-v65-image-color value="#e11d48"></label>
        <label>Metin <input type="text" data-v65-image-text maxlength="120" placeholder="Resme eklenecek metin"></label>
    </div>
    <div class="v65-custom-photo-savebar">
        <span role="status" aria-live="polite" data-v65-image-status>Soldan Düzenle ile fotoğraf seçin.</span>
        <button type="button" data-v65-image-action="reset">Sıfırla</button>
        <button type="button" data-v65-image-action="original">Orijinali Aç</button>
        <button type="button" data-v65-image-action="save-as">Farklı Kaydet</button>
        <button type="button" class="v65-btn v65-btn--primary v65-btn--sm"
            data-v65-image-action="save">Uygula ve Kaydet</button>
    </div>
</section>
