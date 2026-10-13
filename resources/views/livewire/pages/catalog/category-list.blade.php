<div class="stack v65-list-page">
    @can('product_categories.create')
        @teleport('.page-actions.v65-page-actions')
            <div class="v65-list-heading-actions"><a class="button-link table-add-action" href="{{ route('categories.create') }}">+ Yeni Kategori</a></div>
        @endteleport
    @endcan

    <section class="panel category-tree">
        @forelse($roots as $root)
            <div class="category-node level-1">
                <div class="category-node-row">
                    @can('product_categories.update')
                        <a class="v65-record-link" href="{{ route('categories.edit', ['category' => $root->id]) }}"><strong>{{ $root->name }}</strong></a>
                    @else
                        <strong>{{ $root->name }}</strong>
                    @endcan
                    <x-v65.status-badge :tone="$root->is_active ? 'success' : 'danger'">{{ $root->is_active ? 'Aktif' : 'Pasif' }}</x-v65.status-badge>

                </div>

                @foreach($root->children as $child)
                    <div class="category-node level-2">
                        <div class="category-node-row">
                            @can('product_categories.update')
                                <a class="v65-record-link" href="{{ route('categories.edit', ['category' => $child->id]) }}">{{ $child->name }}</a>
                            @else
                                <span>{{ $child->name }}</span>
                            @endcan
                            <x-v65.status-badge :tone="$child->is_active ? 'success' : 'danger'">{{ $child->is_active ? 'Aktif' : 'Pasif' }}</x-v65.status-badge>

                        </div>

                        @foreach($child->children as $grandchild)
                            <div class="category-node level-3">
                                <div class="category-node-row">
                                    @can('product_categories.update')
                                    <a class="v65-record-link" href="{{ route('categories.edit', ['category' => $grandchild->id]) }}">{{ $grandchild->name }}</a>
                                @else
                                    <span>{{ $grandchild->name }}</span>
                                @endcan
                                    <x-v65.status-badge :tone="$grandchild->is_active ? 'success' : 'danger'">{{ $grandchild->is_active ? 'Aktif' : 'Pasif' }}</x-v65.status-badge>

                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <x-v65.empty-state title="Henüz kategori kaydı bulunmuyor." class="v65-category-empty">
                @can('product_categories.create')
                    <a class="button-link table-add-action" href="{{ route('categories.create') }}">+ Yeni Kategori</a>
                @endcan
            </x-v65.empty-state>
        @endforelse
    </section>
</div>
