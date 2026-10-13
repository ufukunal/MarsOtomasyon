<section class="panel stack v65-list-page">
    <x-v65.list-heading title="Ayar Kayıtları" :description="$description" />
    <div class="table-scroll v65-list-table">
        <table class="data-table">
            <thead><tr>
                @foreach ($columns as $label)
                    <th scope="col">{{ $label }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="master-setting-{{ $section }}-{{ $row->id }}">
                        @foreach ($columns as $field => $label)
                            @php
                                $value = $row->{$field};
                            @endphp
                            <td>
                                @if (is_bool($value))
                                    {{ $value ? 'Evet' : 'Hayır' }}
                                @elseif ($field === 'is_active')
                                    {{ $value ? 'Evet' : 'Hayır' }}
                                @else
                                    {{ $value ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="empty-state">Bu kapsamda kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>{{ $rows->total() }} kayıt</span>
        {{ $rows->links() }}
    </div>
</section>
