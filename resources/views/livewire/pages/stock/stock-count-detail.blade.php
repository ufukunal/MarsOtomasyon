<div class="stack v65-stock-workspace">
<section class="panel stack v65-stock-document">
<nav class="v65-stock-document-tabs" aria-label="Stok sayımı bölümleri">
    <a href="{{ route('stock.counts.index') }}">Stok Sayımları</a>
    <a href="#stock-document-details">Bilgiler</a>
    <a href="#stock-document-lines">{{ $count && $count->status !== 'draft' ? 'Sayım / Farklar' : 'Sayılacak Ürünler' }}</a>
</nav>
@if(!$count || $count->status === 'draft')
<div class="form-grid" id="stock-document-details">
<label class="field"><span class="field-label">Lokasyon</span><select wire:model="locationId"><option value="">Seçin</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>@endforeach</select></label>
<label class="field"><span class="field-label">Tarih</span><input type="date" wire:model="countDate"></label>
<label class="field"><span class="field-label">Not</span><textarea wire:model="note"></textarea></label>
</div>
<div class="stack" id="stock-document-lines">
    <div class="v65-stock-product-toolbar">
        <strong>Sayılacak Ürünler ({{ count($productIds) }} seçildi)</strong>
        <form wire:submit.prevent="addProductByBarcode" class="form-row">
            <input type="search" wire:model.live.debounce.300ms="productSearch" placeholder="Ürün kodu / barkod / ad ile ara…" aria-label="Sayılacak ürün ara" @if($quickMode) autofocus @endif>
            <button type="submit" class="button-secondary">Kodu / Barkodu Ekle</button>
        </form>
    </div>
    @error('productSearch')<div class="field-error" role="alert">{{ $message }}</div>@enderror
    <div class="v65-stock-product-selector">
        @forelse($products as $product)
            <label><input type="checkbox" wire:model="productIds" value="{{ $product->id }}"> {{ $product->code }} · {{ $product->name }}</label>
        @empty
            <span class="empty-state">Aramaya uygun ürün bulunamadı.</span>
        @endforelse
    </div>
</div>
<div class="form-row"><button type="button" wire:click="saveDraft">Taslağı Kaydet</button>@if($count && auth()->user()?->can('stock_counts.update'))<button type="button" wire:click="start">Sayımı Başlat</button>@endif</div>
@else
<div class="alert alert-warning">Sayım sırasında bu lokasyonda hareket yapılmaması önerilir.</div>
<div class="form-row" id="stock-document-details"><strong>{{ $count->number ?: 'Henüz numara verilmedi' }}</strong><span>Durum: {{ \App\Support\Formatting\BusinessLabelFormatter::status($count->status) }}</span><label><input type="checkbox" wire:model.live="differencesOnly"> Yalnızca farklıları göster</label></div>
<div class="table-scroll v65-grid-scroll" id="stock-document-lines"><table class="data-table"><thead><tr><th>Ürün</th><th>Sistem</th><th>Sayılan</th><th>Fark</th><th>Onay</th><th>Not</th><th></th></tr></thead><tbody>
@foreach($countLines as $line)<tr>
<td>{{ $line->product->code }} · {{ $line->product->name }}</td>
<td>{{ \App\Support\Formatting\TrFormatter::quantity((string)$line->system_quantity) }}</td>
<td>@if(in_array($count->status,['counting','review'],true))<input data-tr-decimal wire:model="countedQuantities.{{ $line->id }}" inputmode="decimal">@else{{ $line->counted_quantity !== null ? \App\Support\Formatting\TrFormatter::quantity((string)$line->counted_quantity) : '—' }}@endif</td>
<td>{{ \App\Support\Formatting\TrFormatter::quantity((string)$line->difference) }}</td>
<td>@if($count->status==='review')<input type="checkbox" wire:model="approved.{{ $line->id }}">@else{{ $line->is_approved ? 'Evet':'Hayır' }}@endif</td>
<td>@if(in_array($count->status,['counting','review'],true))<input wire:model="lineNotes.{{ $line->id }}">@else{{ $line->note }}@endif</td>
<td>@if(in_array($count->status,['counting','review'],true))<button class="button-secondary" type="button" wire:click="saveLine({{ $line->id }})">Kaydet</button>@endif</td>
</tr>@endforeach
</tbody></table></div>
<div class="form-row">@if($count->status==='counting' && auth()->user()?->can('stock_counts.update'))<button type="button" class="button-secondary" wire:click="review">Farkları Göster / İncelemeye Al</button>@endif @if($count->status==='review' && auth()->user()?->can('stock_counts.update'))<button type="button" wire:click="post">Sayımı Kesinleştir</button>@endif</div>
@endif
</section>
@foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
</div>
