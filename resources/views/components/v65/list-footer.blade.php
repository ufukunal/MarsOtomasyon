@props(['paginator', 'perPage' => null])

{{-- The same footer is used by DataTables and independent Livewire paginator lists.
     Do not render per-page or column controls when the owning component lacks them. --}}
<div {{ $attributes->class(['v65-list-footer', 'table-footer']) }}>
    <span class="v65-list-count">{{ $paginator->total() }} kayıt</span>
    <div class="v65-list-footer-controls">
        @if($perPage !== null)
            <label class="page-size-control">
                <span>Satır</span>
                <select wire:change="setPerPage($event.target.value)" aria-label="Sayfada gösterilecek satır sayısı">
                    @foreach([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @isset($columnControls)
            {{ $columnControls }}
        @endisset
        {{ $paginator->links('livewire.components.data-table-pagination') }}
    </div>
</div>
