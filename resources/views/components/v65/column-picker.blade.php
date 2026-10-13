{{-- Shared modal for columns on every DataTable and sales/purchase document list.
     The dialog stays mounted during Livewire checkbox updates (wire:ignore.self). --}}
<div class="v65-column-picker" data-v65-column-picker>
    <button type="button"
            class="button-secondary v65-column-picker-trigger"
            data-v65-column-picker-open
            aria-haspopup="dialog"
            aria-label="Tabloda gösterilecek kolonları düzenle">
        Kolonlar
        <span class="v65-column-picker-symbol" aria-hidden="true">▦</span>
    </button>
    <dialog class="v65-column-picker-dialog"
            data-v65-column-picker-dialog
            wire:ignore.self
            aria-label="Tablo kolonlarını düzenle"
            aria-modal="true">
        <div class="v65-column-picker-header">
            <div>
                <strong>Kolonları Düzenle</strong>
                <p>Tabloda görmek istediğiniz alanları seçin.</p>
            </div>
            <button type="button" class="v65-column-picker-close"
                    data-v65-column-picker-close aria-label="Pencereyi kapat">×</button>
        </div>
        <div class="v65-column-picker-list">
            {{ $slot }}
        </div>
        <div class="v65-column-picker-footer">
            <small>Seçimler anında uygulanır.</small>
            <button type="button" class="button-link"
                    data-v65-column-picker-close>Bitir</button>
        </div>
    </dialog>
</div>
