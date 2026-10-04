<div class="space-y-8">
    <h1>Çek / Senet</h1>

    <section class="space-y-3">
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
        <h2>Portföy</h2>
        <table>
            <thead>
            <tr><th>Seç</th><th>No</th><th>Tür</th><th>Yön</th><th>Cari</th><th>Vade</th><th>Tutar</th><th>Durum</th></tr>
            </thead>
            <tbody>
            @foreach($securities as $security)
                <tr>
                    <td><input type="checkbox" wire:model="selectedSecurityIds" value="{{ $security->id }}"></td>
                    <td>{{ $security->instrument_no }}</td>
                    <td>{{ $security->kind }}</td>
                    <td>{{ $security->direction }}</td>
                    <td>{{ $security->contact?->title }}</td>
                    <td>{{ $security->due_date?->format('d.m.Y') }}</td>
                    <td>{{ $security->amount }}</td>
                    <td>{{ $security->status }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="space-y-3">
        <h2>Bordro İşlemi</h2>
        <label>İşlem
            <select wire:model="payrollAction">
                <option value="endorsement">Ciro Et</option>
                <option value="bank_deposit">Bankaya Ver</option>
                <option value="collection">Tahsil Et</option>
                <option value="payment">Öde</option>
                <option value="return">İade</option>
                <option value="protest">Protesto</option>
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

    <section>
        <h2>Son Bordrolar</h2>
        <table>
            <thead><tr><th>No</th><th>İşlem</th><th>Tarih</th><th>Toplam</th><th>Adet</th><th>Durum</th></tr></thead>
            <tbody>
            @foreach($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->number }}</td>
                    <td>{{ $payroll->action }}</td>
                    <td>{{ $payroll->payroll_date?->format('d.m.Y') }}</td>
                    <td>{{ $payroll->total_amount }}</td>
                    <td>{{ count($payroll->security_ids) }}</td>
                    <td>{{ $payroll->status }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
