<div class="space-y-8 v65-list-page">


    <section class="panel stack">
        <h2>Yeni Çek / Senet</h2>
        <label>Yön
            <select wire:model="direction">
                <option value="incoming">Alınan</option>
                <option value="outgoing">Verilen</option>
            </select>
        </label>
        <label>Tür
            <select wire:model="kind">
                <option value="check">Çek</option>
                <option value="note">Senet</option>
            </select>
        </label>
        <label>No <input wire:model="instrumentNo"></label>
        <label>Cari
            <select wire:model="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Tutar <input wire:model="amount"></label>
        <label>İşlem Tarihi <input type="date" wire:model="transactionDate"></label>
        <label>Vade <input type="date" wire:model="dueDate"></label>
        <label>Banka Adı <input wire:model="bankName"></label>
        <label>Banka Hesabı
            <select wire:model="bankAccountId">
                <option value="">Seçim yok</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        <label>Not <textarea wire:model="note"></textarea></label>
        <button type="button" wire:click="create">Kaydet</button>
    </section>

    <section>
        <x-v65.list-heading title="Portföy" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead>
            <tr><th>Seç</th><th>No</th><th>Tür</th><th>Yön</th><th>Cari</th><th>Vade</th><th class="align-end">Tutar</th><th>Durum</th></tr>
            </thead>
            <tbody>
            @foreach($securities as $security)
                <tr>
                    <td><input type="checkbox" wire:model="selectedSecurityIds" value="{{ $security->id }}"></td>
                    <td>{{ $security->instrument_no }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('security_kind', $security->kind) }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::direction($security->direction) }}</td>
                    <td>{{ $security->contact?->title }}</td>
                    <td>{{ $security->due_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ $security->amount }}</td>
                    <td><x-v65.status-badge>{{ \App\Support\Formatting\BusinessLabelFormatter::status($security->status) }}</x-v65.status-badge></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <x-v65.bulk-actions :count="count($selectedSecurityIds)" label="evrak seçildi">
            <span>Bordro İşlemi bölümünden işlem türünü ve tarihi belirleyin.</span>
        </x-v65.bulk-actions>
    </section>

    <section class="panel stack">
        <h2>Bordro İşlemi</h2>
        <label>İşlem
            <select wire:model="payrollAction">
                <option value="endorsement">Ciro Et</option>
                <option value="bank_deposit">Bankaya Ver</option>
                <option value="collection">Tahsil Et</option>
                <option value="payment">Öde</option>
                <option value="return">İade</option>
                <option value="protest">Protesto</option>
                <option value="cancel">Verilen Evrakı İptal Et</option>
            </select>
        </label>
        <label>Tarih <input type="date" wire:model="payrollDate"></label>
        <label>Hedef Cari
            <select wire:model="payrollContactId">
                <option value="">Seçim yok</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Banka
            <select wire:model="payrollBankAccountId">
                <option value="">Seçim yok</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" wire:click="postPayroll">Bordroyu Kesinleştir</button>
    </section>

    <section class="panel stack">
        <h2>Bordro Ters Kayıt</h2>
        <label>Bordro
            <select wire:model="reversePayrollId">
                <option value="">Seçin</option>
                @foreach($payrolls as $payroll)
                    @if($payroll->action !== 'reversal')
                        <option value="{{ $payroll->id }}">{{ $payroll->number }} · {{ \App\Support\Formatting\BusinessLabelFormatter::value('security_action', $payroll->action) }} · {{ $payroll->total_amount }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label>Tarih <input type="date" wire:model="reversalDate"></label>
        <label>Gerekçe <input wire:model="reversalReason"></label>
        <x-v65.button variant="danger" data-v65-modal-open="v65-security-reversal-confirm">Bordroyu Tersle</x-v65.button>
    </section>

    <section>
        <x-v65.list-heading title="Son Bordrolar" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>No</th><th>İşlem</th><th>Tarih</th><th class="align-end">Toplam</th><th class="align-end">Adet</th><th>Terslenen</th><th>Durum</th></tr></thead>
            <tbody>
            @foreach($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->number }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('security_action', $payroll->action) }}</td>
                    <td>{{ $payroll->payroll_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ $payroll->total_amount }}</td>
                    <td class="align-end">{{ count($payroll->security_ids) }}</td>
                    <td>{{ $payroll->reversal_of_id ?? '—' }}</td>
                    <td><x-v65.status-badge>{{ \App\Support\Formatting\BusinessLabelFormatter::status($payroll->status) }}</x-v65.status-badge></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <x-v65.modal id="v65-security-reversal-confirm" title="Bordroyu Ters Kaydet" size="sm">
        <p>Seçilen bordro için ters kayıt oluşturulacaktır. İşlem tarihi ve gerekçeyi kontrol edin.</p>
        <p><strong>Bordro:</strong> {{ $reversePayrollId ?: 'Seçilmedi' }}</p>
        <p><strong>Gerekçe:</strong> {{ $reversalReason ?: 'Girilmedi' }}</p>
        <x-slot:footer>
            <x-v65.button variant="secondary" data-v65-modal-close>Vazgeç</x-v65.button>
            <x-v65.button variant="danger" wire:click="reversePayroll" wire:loading.attr="disabled" wire:target="reversePayroll" data-v65-modal-close>Ters Kaydı Onayla</x-v65.button>
        </x-slot:footer>
    </x-v65.modal>
</div>
