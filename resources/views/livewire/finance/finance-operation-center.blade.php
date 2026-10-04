<div class="space-y-8">
    <h1>Finans İşlemleri</h1>

    <section class="space-y-3">
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
        <label>Kur <input wire:model="exchangeRate"></label>
        <label>İşlem Türü <input wire:model="movementType"></label>
        <label>Referans <input wire:model="reference"></label>
        <label>Not <textarea wire:model="note"></textarea></label>
        <button type="button" wire:click="postManual">Hareketi Kesinleştir</button>
    </section>

    <section class="space-y-3">
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
        <button type="button" wire:click="postTransfer">Virmanı Kesinleştir</button>
        <p>Virman aynı para birimli hesaplar arasında yapılır.</p>
    </section>

    <section class="space-y-3">
        <h2>Gider</h2>
        <label>Gider Türü <input wire:model="expenseCategory"></label>
        <label>Net Tutar <input wire:model="expenseNet"></label>
        <label>KDV % <input wire:model="expenseVatRate"></label>
        <label>Para Birimi <input wire:model="expenseCurrency" maxlength="3"></label>
        <label>Kur <input wire:model="expenseExchangeRate"></label>
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
        <button type="button" wire:click="postExpense">Gideri Kesinleştir</button>
    </section>

    <section class="space-y-3">
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
        <label>Para Birimi <input wire:model="advanceCurrency" maxlength="3"></label>
        <label>Kur <input wire:model="advanceExchangeRate"></label>
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
        <button type="button" wire:click="postAdvance">Avans İşlemini Kesinleştir</button>
    </section>

    <section class="space-y-3">
        <h2>Ters Kayıt</h2>
        <label>Tarih <input type="date" wire:model="reversalDate"></label>
        <label>Gerekçe <input wire:model="reversalReason"></label>

        <div>
            <h3>Manuel Hareket</h3>
            <select wire:model="reverseAccountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
            <input type="number" wire:model="reverseMovementId" placeholder="Hareket ID">
            <button type="button" wire:click="reverseManual">Hareketi Tersle</button>
        </div>

        <div>
            <h3>Virman</h3>
            <input wire:model="reverseTransferGroupKey" placeholder="Virman group_key">
            <button type="button" wire:click="reverseTransfer">Virmanı Tersle</button>
        </div>

        <div>
            <h3>Gider / Avans Belgesi</h3>
            <select wire:model="reverseDocumentId">
                <option value="">Belge seçin</option>
                @foreach($financeDocuments as $document)
                    <option value="{{ $document->id }}">
                        {{ $document->number }} · {{ $document->document_type->value }} · {{ $document->grand_total }} {{ $document->currency }}
                    </option>
                @endforeach
            </select>
            <button type="button" wire:click="reverseDocument">Belgeyi Tersle</button>
        </div>
    </section>

    <section>
        <h2>Son Kasa Hareketleri</h2>
        <table>
            <thead><tr><th>ID</th><th>Tarih</th><th>Tür</th><th>Yön</th><th>Tutar</th><th>Referans</th><th>Grup</th></tr></thead>
            <tbody>
            @foreach($cashMovements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td>
                    <td>{{ $movement->movement_date?->format('d.m.Y') }}</td>
                    <td>{{ $movement->movement_type }}</td>
                    <td>{{ $movement->direction }}</td>
                    <td>{{ $movement->amount }}</td>
                    <td>{{ $movement->reference }}</td>
                    <td>{{ $movement->group_key }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section>
        <h2>Son Banka Defter Hareketleri</h2>
        <table>
            <thead><tr><th>ID</th><th>Tarih</th><th>Tür</th><th>Yön</th><th>Tutar</th><th>Referans</th><th>Grup</th></tr></thead>
            <tbody>
            @foreach($bankMovements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td>
                    <td>{{ $movement->movement_date?->format('d.m.Y') }}</td>
                    <td>{{ $movement->movement_type }}</td>
                    <td>{{ $movement->direction }}</td>
                    <td>{{ $movement->amount }}</td>
                    <td>{{ $movement->reference }}</td>
                    <td>{{ $movement->group_key }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
