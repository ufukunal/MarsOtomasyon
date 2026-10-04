@props(['label', 'model', 'labelField' => 'name', 'codeField' => 'code', 'barcodeField' => null])
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <livewire:components.lookup
        :model="$model"
        :label-field="$labelField"
        :code-field="$codeField"
        :barcode-field="$barcodeField"
        {{ $attributes->except(['label','model','labelField','codeField','barcodeField']) }}
    />
</label>
