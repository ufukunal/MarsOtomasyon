<div class="stack">
    @if($step === 1)
        <section class="panel stack">
            <x-field.select label="Aktarım Tipi" wire:model="type" :options="['contact'=>'Cari','product'=>'Ürün','price_list'=>'Fiyat Listesi','opening_stock'=>'Açılış Stok']" />
            <x-field.file label="Dosya" wire:model="file" />
            <p>XLSX, CSV ve JSON desteklenir. CSV ayıracı noktalı virgüldür.</p>
            <button type="button" wire:click="upload">Dosyayı Yükle</button>
        </section>
    @elseif($step === 2)
        <section class="panel stack">
            <h2>Kolon Eşleştirme</h2>
            @foreach($fields as $field => $definition)
                <label class="field">
                    <span class="field-label">{{ $definition['label'] }}{{ $definition['required'] ? ' *' : '' }}</span>
                    <select wire:model="mapping.{{ $field }}"><option value="">—</option>@foreach($headers as $header)<option value="{{ $header }}">{{ $header }}</option>@endforeach</select>
                </label>
                @error("mapping.$field")<div class="field-error">{{ $message }}</div>@enderror
            @endforeach
            <button type="button" wire:click="previewMapped">Önizle</button>
        </section>
    @elseif($step === 3)
        <section class="panel stack">
            <h2>İlk 20 Satır</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>#</th>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach<th>Doğrulama</th></tr></thead>
                    <tbody>
                    @foreach($preview as $index => $row)
                        @php($validation = $previewValidation[$index] ?? ['valid'=>true,'errors'=>[]])
                        <tr @class(['import-row-error' => !$validation['valid']])>
                            <td>{{ $index + 2 }}</td>
                            @foreach($headers as $header)<td>{{ $row[$header] ?? '' }}</td>@endforeach
                            <td>
                                @if($validation['valid'])
                                    <span>Uygun</span>
                                @else
                                    @foreach($validation['errors'] as $field => $message)
                                        <div class="field-error">{{ $field }}: {{ $message }}</div>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-field.select label="Hata Davranışı" wire:model="errorMode" :options="['cancel_all'=>'Bir hata varsa tümünü iptal et','skip_invalid'=>'Hatalı satırları atla']" />
            <button type="button" wire:click="queue">Kuyruğa Al</button>
        </section>
    @else
        <section class="panel">
            @if(!$batch)
                <p>Batch bekleniyor…</p>
            @else
                <dl>
                    <dt>Durum</dt><dd>{{ $batch->status }}</dd>
                    <dt>Toplam</dt><dd>{{ $batch->total_rows }}</dd>
                    <dt>Başarılı</dt><dd>{{ $batch->success_rows }}</dd>
                    <dt>Hatalı</dt><dd>{{ $batch->error_rows }}</dd>
                </dl>
                @if($batch->failure_message)<div class="alert alert-warning">{{ $batch->failure_message }}</div>@endif
                @if($batch->errors->isNotEmpty())
                    <p><a href="{{ route('imports.errors', ['batchId' => $batch->id]) }}">Hata Raporu XLSX indir</a></p>
                    <table class="data-table"><thead><tr><th>Satır</th><th>Kolon</th><th>Değer</th><th>Hata</th></tr></thead><tbody>
                    @foreach($batch->errors as $error)<tr><td>{{ $error->row_no }}</td><td>{{ $error->column_name }}</td><td>{{ $error->value }}</td><td>{{ $error->message }}</td></tr>@endforeach
                    </tbody></table>
                @endif
            @endif
        </section>
    @endif

    @foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
</div>
