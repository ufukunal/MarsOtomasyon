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
                    <span>{{ $root->is_active ? 'Aktif' : 'Pasif' }}</span>

                </div>

                @foreach($root->children as $child)
                    <div class="category-node level-2">
                        <div class="category-node-row">
                            @can('product_categories.update')
                                <a class="v65-record-link" href="{{ route('categories.edit', ['category' => $child->id]) }}">{{ $child->name }}</a>
                            @else
                                <span>{{ $child->name }}</span>
                            @endcan
                            <span>{{ $child->is_active ? 'Aktif' : 'Pasif' }}</span>

                        </div>

                        @foreach($child->children as $grandchild)
                            <div class="category-node level-3">
                                <div class="category-node-row">
                                    @can('product_categories.update')
                                    <a class="v65-record-link" href="{{ route('categories.edit', ['category' => $grandchild->id]) }}">{{ $grandchild->name }}</a>
                                @else
                                    <span>{{ $grandchild->name }}</span>
                                @endcan
                                    <span>{{ $grandchild->is_active ? 'Aktif' : 'Pasif' }}</span>

                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @empty
            <div class="empty-state v65-category-empty"><p>Henüz kategori kaydı bulunmuyor.</p>
                @can('product_categories.create')
                    <a class="button-link table-add-action" href="{{ route('categories.create') }}">+ Yeni Kategori</a>
                @endcan
            </div>
        @endforelse
    </section>
</div>
