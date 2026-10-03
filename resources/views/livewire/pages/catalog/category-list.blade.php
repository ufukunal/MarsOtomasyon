<div class="stack">
    @can('product_categories.create')
        <div><a class="button-link" href="{{ route('categories.create') }}">Yeni Kategori</a></div>
    @endcan

    <section class="panel category-tree">
        @forelse($roots as $root)
            <div class="category-node level-1">
                <div class="category-node-row">
                    <strong>{{ $root->name }}</strong>
                    <span>{{ $root->is_active ? 'Aktif' : 'Pasif' }}</span>
                    @can('product_categories.update')
                        <a href="{{ route('categories.edit', ['category' => $root->id]) }}">Düzenle</a>
                    @endcan
                </div>

                @foreach($root->children as $child)
                    <div class="category-node level-2">
                        <div class="category-node-row">
                            <span>{{ $child->name }}</span>
                            <span>{{ $child->is_active ? 'Aktif' : 'Pasif' }}</span>
                            @can('product_categories.update')
                                <a href="{{ route('categories.edit', ['category' => $child->id]) }}">Düzenle</a>
                            @endcan
                        </div>

                        @foreach($child->children as $grandchild)
                            <div class="category-node level-3">
                                <div class="category-node-row">
                                    <span>{{ $grandchild->name }}</span>
                                    <span>{{ $grandchild->is_active ? 'Aktif' : 'Pasif' }}</span>
                                    @can('product_categories.update')
                                        <a href="{{ route('categories.edit', ['category' => $grandchild->id]) }}">Düzenle</a>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <p>Kategori bulunamadı.</p>
        @endforelse
    </section>
</div>
