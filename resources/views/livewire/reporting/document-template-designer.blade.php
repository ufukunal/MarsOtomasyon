<div class="stack">
    @if($message)
        <div class="alert">{{ $message }}</div>
    @endif

    <section class="panel stack">
        <h2>Template Revizyonu</h2>

        <div class="report-filter-grid">
            <label class="field">
                <span class="field-label">Template key</span>
                <input type="text" wire:model.defer="templateKey" placeholder="sales.invoice">
            </label>
            <label class="field">
                <span class="field-label">Ad</span>
                <input type="text" wire:model.defer="name">
            </label>
            <label class="field">
                <span class="field-label">Render tipi</span>
                <select wire:model.defer="renderType">
                    <option value="html_pdf">HTML / PDF</option>
                    <option value="zpl">ZPL</option>
                    <option value="text">Text</option>
                </select>
            </label>
            <label class="field">
                <span class="field-label">Kağıt</span>
                <input type="text" wire:model.defer="paperCode">
            </label>
            <label class="field">
                <span class="field-label">Genişlik mm</span>
                <input type="number" step="0.01" wire:model.defer="widthMm">
            </label>
            <label class="field">
                <span class="field-label">Yükseklik mm</span>
                <input type="number" step="0.01" wire:model.defer="heightMm">
            </label>
            <label class="field">
                <span class="field-label">Varsayılan</span>
                <input type="checkbox" wire:model.defer="makeDefault">
            </label>
        </div>

        <div class="report-toolbar">
            <select wire:model.defer="newSectionType">
                @foreach($sectionTypes as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="addSection">Section Ekle</button>
            <button type="button" class="button-secondary" wire:click="preview">Önizle</button>
            @can('document_templates.update')
                <button type="button" wire:click="saveRevision">Yeni Revizyon Kaydet</button>
            @endcan
        </div>

        <div class="stack">
            @forelse($sections as $index => $section)
                <div class="panel" wire:key="template-section-{{ $index }}">
                    <div class="report-toolbar">
                        <strong>{{ $index + 1 }}. {{ $section['type'] }}</strong>
                        <label>
                            <input type="checkbox" wire:model.defer="sections.{{ $index }}.visible">
                            Görünür
                        </label>
                        <button type="button" class="button-secondary" wire:click="moveSection({{ $index }}, -1)">Yukarı</button>
                        <button type="button" class="button-secondary" wire:click="moveSection({{ $index }}, 1)">Aşağı</button>
                        <button type="button" class="button-secondary" wire:click="removeSection({{ $index }})">Sil</button>
                    </div>

                    @if(in_array($section['type'], ['header','footer','notes','custom_text','signature'], true))
                        <label class="field">
                            <span class="field-label">Metin / başlık</span>
                            <textarea wire:model.defer="sections.{{ $index }}.settings.text"></textarea>
                        </label>
                    @endif
                </div>
            @empty
                <div class="empty-state">Section eklenmedi.</div>
            @endforelse
        </div>
    </section>

    @if($previewHtml)
        <section class="panel stack">
            <h2>Önizleme</h2>
            <iframe
                title="Template önizleme"
                sandbox=""
                srcdoc="{{ $previewHtml }}"
                style="width:100%;min-height:520px;border:1px solid #ccc"
            ></iframe>
        </section>
    @endif

    <section class="panel">
        <h2>Revizyon Geçmişi</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Key</th>
                    <th>Ad</th>
                    <th>Rev.</th>
                    <th>Tip</th>
                    <th>Aktif</th>
                    <th>Varsayılan</th>
                    <th>İşlem</th>
                </tr>
                </thead>
                <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>{{ $template->template_key }}</td>
                        <td>{{ $template->name }}</td>
                        <td>{{ $template->revision_no }}</td>
                        <td>{{ $template->render_type }}</td>
                        <td>{{ $template->is_active ? 'Evet' : 'Hayır' }}</td>
                        <td>{{ $template->is_default ? 'Evet' : 'Hayır' }}</td>
                        <td>
                            <button type="button" class="button-secondary" wire:click="loadRevision({{ $template->id }})">Yükle</button>
                            @can('document_templates.update')
                                @if($template->is_active && !$template->is_default)
                                    <button type="button" class="button-secondary" wire:click="setDefault({{ $template->id }})">Varsayılan</button>
                                @endif
                                @if($template->is_active)
                                    <button type="button" class="button-secondary" wire:click="deactivate({{ $template->id }})">Pasifleştir</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">Template revizyonu yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
