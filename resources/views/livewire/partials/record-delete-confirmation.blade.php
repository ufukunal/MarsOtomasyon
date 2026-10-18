@if($recordDeleteMethod !== null)
    <div class="v65-record-delete-overlay" role="presentation">
        <section class="v65-record-delete-dialog" role="alertdialog" aria-modal="true"
            aria-labelledby="v65-record-delete-title" aria-describedby="v65-record-delete-description"
            x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())"
            @keydown.escape.window="$wire.cancelRecordDeletion()">
            <h2 id="v65-record-delete-title">Kayıt silme onayı</h2>
            <p id="v65-record-delete-description">
                Bu kaydı silmek istediğinizden emin misiniz?
                İşlem geri alınamayabilir. Mali kayıtların geçmişi ve denetim izi korunur.
            </p>
            <form wire:submit="confirmRecordDeletion">
                <label class="v65-record-delete-math">
                    <strong>Güvenlik doğrulaması</strong>
                    <span>Devam etmek için işlemin sonucunu yazın:
                        <b>{{ $recordDeleteFirst }} + {{ $recordDeleteSecond }} = ?</b>
                    </span>
                    <input type="text" inputmode="numeric" pattern="[0-9]*"
                        autocomplete="off" wire:model="recordDeleteAnswer"
                        aria-invalid="{{ $errors->has('recordDeleteAnswer') ? 'true' : 'false' }}"
                        required>
                </label>
                @error('recordDeleteAnswer')
                    <p class="v65-record-delete-error" role="alert">{{ $message }}</p>
                @enderror
                <div class="v65-record-delete-dialog-actions">
                    <x-v65.button type="button" variant="secondary"
                        wire:click="cancelRecordDeletion">Vazgeç</x-v65.button>
                    <x-v65.button type="submit" variant="danger"
                        wire:loading.attr="disabled" wire:target="confirmRecordDeletion">
                        Silme İşlemini Onayla
                    </x-v65.button>
                </div>
            </form>
        </section>
    </div>
@endif
