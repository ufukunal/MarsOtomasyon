<div class="space-y-8 v65-list-page">
    @if($correctionDocumentId || $correctionMovementId || $correctionTransferGroup !== '')
        <x-v65.notice tone="info">
            İşlem düzenleniyor. Değişikliklerinizi Kaydet ile uygulayın.
            <button type="button" class="button-secondary" wire:click="cancelCorrection">Vazgeç</button>
        </x-v65.notice>
    @endif

    <section id="v65-finance-manual" class="panel stack v65-workspace-form">
        <h2>Manuel Kasa / Banka Hareketi</h2>
        <label>Tarih <input type="date" wire:model="date"></label>
        <label>Hesap Türü
            <select wire:model="accountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label>Hesap
            <select wire:model="accountId">
                <option value="">Seçin</option>
                @if($accountType === 'cash')
                    @foreach($cashAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }} · {{ $account->currency }}</option>
                    @endforeach
                @else
                    @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }} · {{ $account->currency }}</option>
                    @endforeach
                @endif
            </select>
        </label>
        <label>Yön
            <select wire:model="direction">
                <option value="in">Giriş</option>
                <option value="out">Çıkış</option>
            </select>
        </label>
        <label>Tutar <input wire:model="amount"></label>
        <label>Cari
            <select wire:model="contactId">
                <option value="">Cari etkisi yok</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <div>Para Birimi: TRY</div>
        <label>İşlem Türü <input wire:model="movementType"></label>
        <label>Referans <input wire:model="reference"></label>
        <label>Not <textarea wire:model="note"></textarea></label>
        <button type="button" wire:click="postManual"
>
            Kaydet
        </button>
    </section>

    <section id="v65-finance-transfer" class="panel stack v65-workspace-form">
        <h2>Virman</h2>
        <label>Kaynak Tür
            <select wire:model="transferSourceType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label>Kaynak Hesap
            <select wire:model="transferSourceId">
                <option value="">Seçin</option>
                @foreach($transferSourceType === 'cash' ? $cashAccounts : $bankAccounts as $account)
                    <option value="{{ $account->id }}">
                        {{ $account->code }} · {{ $transferSourceType === 'cash' ? $account->name : ($account->bank_name.' · '.$account->account_name) }} · {{ $account->currency }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>Hedef Tür
            <select wire:model="transferTargetType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label>Hedef Hesap
            <select wire:model="transferTargetId">
                <option value="">Seçin</option>
                @foreach($transferTargetType === 'cash' ? $cashAccounts : $bankAccounts as $account)
                    <option value="{{ $account->id }}">
                        {{ $account->code }} · {{ $transferTargetType === 'cash' ? $account->name : ($account->bank_name.' · '.$account->account_name) }} · {{ $account->currency }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>Tutar <input wire:model="transferAmount"></label>
        <button type="button" wire:click="postTransfer"
>
            Kaydet
        </button>
        <p>Virman aynı para birimli hesaplar arasında yapılır; döviz hesabı virmanı kur dönüşümü yapmaz.</p>
    </section>

    <section id="v65-finance-expense" class="panel stack v65-workspace-form">
        <h2>Gider</h2>
        <label>Gider Türü <input wire:model="expenseCategory"></label>
        <label>Net Tutar <input wire:model="expenseNet"></label>
        <label>KDV % <input wire:model="expenseVatRate"></label>
        <div>Para Birimi: TRY</div>
        <label>Ödeme Yeri
            <select wire:model="expenseAccountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label>Hesap
            <select wire:model="expenseAccountId">
                <option value="">Seçin</option>
                @foreach($expenseAccountType === 'cash' ? $cashAccounts : $bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $expenseAccountType === 'cash' ? $account->name : ($account->bank_name.' · '.$account->account_name) }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" wire:click="postExpense"
>
            Kaydet
        </button>
    </section>

    <section id="v65-finance-advance" class="panel stack v65-workspace-form">
        <h2>Avans</h2>
        <label>İşlem
            <select wire:model="advanceOperation">
                <option value="give">Avans Ver</option>
                <option value="return">Avans İade Al</option>
            </select>
        </label>
        <label>Cari / Personel
            <select wire:model="advanceContactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Tutar <input wire:model="advanceAmount"></label>
        <div>Para Birimi: TRY</div>
        <label>Hesap Türü
            <select wire:model="advanceAccountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label>Hesap
            <select wire:model="advanceAccountId">
                <option value="">Seçin</option>
                @foreach($advanceAccountType === 'cash' ? $cashAccounts : $bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $advanceAccountType === 'cash' ? $account->name : ($account->bank_name.' · '.$account->account_name) }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" wire:click="postAdvance"
>
            Kaydet
        </button>
    </section>

    <section class="panel stack" aria-label="Finans belgesi düzeltme listesi">
        <h2>Gider ve Avans Belgeleri</h2>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Belge</th><th>Tür</th><th>Tarih</th><th>Tutar</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($financeDocuments as $record)
                    <tr wire:key="finance-doc-edit-{{ $record->id }}">
                        <td>{{ $record->number ?? '#'.$record->id }}</td>
                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::documentType($record->document_type) }}</td>
                        <td>{{ $record->document_date?->format('d.m.Y') }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::money((string) $record->grand_total) }} TRY</td>
                        <td>
                            @if(($record->document_type === \App\Enums\DocumentType::Expense
                                    && auth()->user()?->can('expenses.cancel') && auth()->user()?->can('expenses.create'))
                                || ($record->document_type !== \App\Enums\DocumentType::Expense
                                    && auth()->user()?->can('advances.cancel') && auth()->user()?->can('advances.create')))
                                <button type="button" class="button-secondary" wire:click="prepareDocumentCorrection({{ $record->id }})">
                                    Düzenle
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Düzeltilebilir gider/avans belgesi bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel stack" aria-label="Manuel finans hareketi düzeltme listesi">
        <h2>Manuel Hareketler</h2>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Hesap Türü</th><th>Tarih</th><th>Yön</th><th>Tutar</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($manualCorrectable as $record)
                    <tr>
                        <td>{{ $record['account'] === 'cash' ? 'Kasa' : 'Banka' }}</td>
                        <td>{{ $record['movement']->movement_date?->format('d.m.Y') }}</td>
                        <td>{{ $record['movement']->direction === 'in' ? 'Giriş' : 'Çıkış' }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::money((string) $record['movement']->amount) }}</td>
                        <td>@if(auth()->user()?->can('finance_movements.view') && auth()->user()?->can('finance_movements.cancel') && auth()->user()?->can('finance_movements.create'))
                            <button type="button" class="button-secondary"
                                wire:click="prepareManualCorrection('{{ $record['account'] }}', {{ $record['movement']->id }})">Düzenle</button>
                            @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Düzeltilebilir manuel hareket bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel stack" aria-label="Finans virmanı düzeltme listesi">
        <h2>Finans Virmanları</h2>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>İşlem Grubu</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($transferCorrectable as $group)
                    <tr>
                        <td>{{ substr((string) $group, 0, 20) }}…</td>
                        <td>@if(auth()->user()?->can('finance_transfers.view') && auth()->user()?->can('finance_transfers.cancel') && auth()->user()?->can('finance_transfers.create'))
                            <button type="button" class="button-secondary"
                                wire:click="prepareTransferCorrection('{{ $group }}')">Düzenle</button>
                            @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="2">Düzeltilebilir finans virmanı bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel stack">
        <x-v65.list-heading title="Son Kasa Hareketleri" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>ID</th><th>Tarih</th><th>Tür</th><th>Yön</th><th>Tutar</th><th>Referans</th><th>Grup</th></tr></thead>
            <tbody>
            @foreach($cashMovements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td>
                    <td>{{ $movement->movement_date?->format('d.m.Y') }}</td>
                    <td>{{ $movement->movement_type }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::direction($movement->direction) }}</td>
                    <td>{{ $movement->amount }}</td>
                    <td>{{ $movement->reference }}</td>
                    <td>{{ $movement->group_key }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="panel stack">
        <x-v65.list-heading title="Son Banka Defter Hareketleri" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>ID</th><th>Tarih</th><th>Tür</th><th>Yön</th><th>Tutar</th><th>Referans</th><th>Grup</th></tr></thead>
            <tbody>
            @foreach($bankMovements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td>
                    <td>{{ $movement->movement_date?->format('d.m.Y') }}</td>
                    <td>{{ $movement->movement_type }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::direction($movement->direction) }}</td>
                    <td>{{ $movement->amount }}</td>
                    <td>{{ $movement->reference }}</td>
                    <td>{{ $movement->group_key }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
</div>
