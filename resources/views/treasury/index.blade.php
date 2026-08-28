@extends('layouts.app')

@section('title', 'Kasa / Banka')

@section('app-content')
@php
    $cashAccounts = $treasuryAccounts->where('type', 'cash');
    $bankAccounts = $treasuryAccounts->where('type', 'bank');
    $posAccounts = $treasuryAccounts->where('type', 'pos');
    $activeAccounts = $treasuryAccounts->where('is_active', true);
@endphp

<section class="workspace-hero">
    <div>
        <p class="eyebrow">Finans / Treasury</p>
        <h1>Kasa / Banka</h1>
        <p>Tek authority <code>treasury_movements</code>: tahsilat, ödeme, POS, virman, masraf, kasa sayımı ve banka mutabakatı.</p>
    </div>
    <div class="page-actions">
        <span class="status-badge">Same-currency V1</span>
    </div>
</section>

<section class="detail-card">
    <h2>Hesap Bakiyeleri</h2>
    <div class="statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kod</th>
                <th>Hesap</th>
                <th>Tip</th>
                <th>Banka / POS</th>
                <th>Para Birimi</th>
                <th>Bakiye</th>
            </tr>
            </thead>
            <tbody>
            @forelse($treasuryAccounts as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td>{{ strtoupper($account->type) }}</td>
                    <td>{{ $account->bank_name ?: $account->pos_provider ?: '—' }}</td>
                    <td>{{ $account->currency_code }}</td>
                    <td>{{ $account->balance }} {{ $account->currency_code }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Henüz kasa, banka veya POS hesabı yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@can('treasury.manage')
<section class="detail-card">
    <h2>Hesap ve Ödeme Yöntemi Tanımları</h2>
    <div class="form-grid">
        <form method="POST" action="{{ route('treasury.accounts.store') }}" class="detail-card">
            @csrf
            <h3>Yeni Treasury Hesabı</h3>
            <label for="account_type">Tip</label>
            <select id="account_type" name="type" required>
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
                <option value="pos">POS</option>
            </select>
            <label for="account_code">Kod</label>
            <input id="account_code" name="code" required maxlength="64" placeholder="KASA-MERKEZ">
            <label for="account_name">Ad</label>
            <input id="account_name" name="name" required maxlength="160" placeholder="Merkez Kasa">
            <label for="account_currency">Para Birimi</label>
            <input id="account_currency" name="currency_code" required maxlength="3" value="TRY">
            <label for="bank_name">Banka</label>
            <input id="bank_name" name="bank_name" maxlength="160">
            <label for="iban">IBAN</label>
            <input id="iban" name="iban" maxlength="34">
            <label for="account_number">Hesap No</label>
            <input id="account_number" name="account_number" maxlength="80">
            <label for="pos_provider">POS Sağlayıcı</label>
            <input id="pos_provider" name="pos_provider" maxlength="120">
            <button type="submit" class="button-primary">Hesap Oluştur</button>
        </form>

        <form method="POST" action="{{ route('treasury.methods.store') }}" class="detail-card">
            @csrf
            <h3>Ödeme Yöntemi</h3>
            <label for="method_code">Kod</label>
            <input id="method_code" name="code" required maxlength="64" placeholder="NAKIT-MERKEZ">
            <label for="method_name">Ad</label>
            <input id="method_name" name="name" required maxlength="160" placeholder="Merkez Nakit">
            <label for="method_kind">Tip</label>
            <select id="method_kind" name="kind" required>
                <option value="cash">Nakit</option>
                <option value="bank">Banka</option>
                <option value="pos">POS</option>
                <option value="virtual_pos">Sanal POS</option>
                <option value="cheque">Çek</option>
                <option value="promissory_note">Senet</option>
                <option value="other">Diğer</option>
            </select>
            <label for="method_account">Varsayılan Treasury Hesabı</label>
            <select id="method_account" name="treasury_account_id">
                <option value="">Serbest seçim</option>
                @foreach($activeAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ $account->currency_code }})</option>
                @endforeach
            </select>
            <button type="submit" class="button-primary">Yöntem Oluştur</button>
        </form>
    </div>
</section>

<section class="detail-card">
    <h2>Tahsilat / Ödeme</h2>
    <form method="POST" action="{{ route('treasury.payments.store') }}" class="form-grid">
        @csrf
        <div>
            <label for="payment_direction">İşlem</label>
            <select id="payment_direction" name="direction" required>
                <option value="collection">Tahsilat</option>
                <option value="payment">Ödeme</option>
            </select>
        </div>
        <div>
            <label for="commercial_account">Cari</label>
            <select id="commercial_account" name="account_id" required>
                @foreach($commercialAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->legal_name }} / {{ $account->type }} / {{ $account->book_currency_code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_treasury">Treasury Hesabı</label>
            <select id="payment_treasury" name="treasury_account_id" required>
                @foreach($activeAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} / {{ $account->currency_code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_method">Ödeme Yöntemi</label>
            <select id="payment_method" name="payment_method_id" required>
                @foreach($paymentMethods->where('is_active', true) as $method)
                    <option value="{{ $method->id }}">{{ $method->name }} / {{ $method->kind }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payment_date">Tarih</label>
            <input id="payment_date" type="date" name="payment_date" value="{{ now()->toDateString() }}" required>
        </div>
        <div>
            <label for="payment_amount">Tutar</label>
            <input id="payment_amount" name="amount" inputmode="decimal" required placeholder="1000.00">
        </div>
        <div>
            <label for="payment_reference">Referans</label>
            <input id="payment_reference" name="reference" maxlength="120">
        </div>
        <div>
            <label for="payment_note">Not</label>
            <input id="payment_note" name="note" maxlength="5000">
        </div>
        <div class="page-actions"><button type="submit" class="button-primary">Taslak Oluştur</button></div>
    </form>

    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>#</th><th>Tarih</th><th>İşlem</th><th>Cari</th><th>Treasury</th><th>Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td>{{ $payment->payment_date }}</td>
                    <td>{{ $payment->direction === 'collection' ? 'Tahsilat' : 'Ödeme' }} / {{ $payment->payment_kind }}</td>
                    <td>{{ $payment->account_name }}</td>
                    <td>{{ $payment->treasury_account_name }}</td>
                    <td>{{ $payment->amount }} {{ $payment->currency_code }}</td>
                    <td>{{ $payment->status }} @if($payment->pos_status) / {{ $payment->pos_status }} @endif</td>
                    <td>
                        @if($payment->status === 'draft')
                            <form method="POST" action="{{ route('treasury.payments.finalize', $payment->id) }}">@csrf<button type="submit">Kesinleştir</button></form>
                        @elseif($payment->status === 'finalized' && $payment->pos_status !== 'settled' && $payment->pos_status !== 'chargeback')
                            <form method="POST" action="{{ route('treasury.payments.reverse', $payment->id) }}">@csrf<button type="submit">Ters Kayıt</button></form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Tahsilat/ödeme bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <h2>POS / Sanal POS Lifecycle</h2>
    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>#</th><th>Cari</th><th>Brüt</th><th>Durum</th><th>Settlement / Chargeback</th></tr></thead>
            <tbody>
            @forelse($posPayments as $payment)
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td>{{ $payment->account_name }}</td>
                    <td>{{ $payment->amount }} {{ $payment->currency_code }}</td>
                    <td>{{ $payment->status }} / {{ $payment->pos_status }}</td>
                    <td>
                        @if($payment->status === 'finalized' && $payment->pos_status === 'pending')
                            <form method="POST" action="{{ route('treasury.payments.settle-pos', $payment->id) }}" class="form-grid">
                                @csrf
                                <select name="bank_account_id" required>
                                    @foreach($bankAccounts->where('is_active', true) as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }} / {{ $bank->currency_code }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="settlement_date" value="{{ now()->toDateString() }}" required>
                                <input name="commission_amount" value="0" inputmode="decimal" required aria-label="Komisyon">
                                <button type="submit">Settled</button>
                            </form>
                        @elseif($payment->status === 'finalized' && $payment->pos_status === 'settled')
                            <form method="POST" action="{{ route('treasury.payments.chargeback', $payment->id) }}" class="form-grid">
                                @csrf
                                <input type="date" name="chargeback_date" value="{{ now()->toDateString() }}" required>
                                <button type="submit">Chargeback</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">POS tahsilatı bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <h2>Manuel Kasa / Banka Hareketi</h2>
    <form method="POST" action="{{ route('treasury.manual-movements.store') }}" class="form-grid">
        @csrf
        <div>
            <label for="manual_account">Hesap</label>
            <select id="manual_account" name="treasury_account_id" required>
                @foreach($activeAccounts->whereIn('type', ['cash', 'bank']) as $account)
                    <option value="{{ $account->id }}">{{ $account->name }} / {{ $account->type }} / {{ $account->currency_code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="manual_operation">İşlem</label>
            <select id="manual_operation" name="operation" required>
                <option value="cash_in">Kasa Giriş</option>
                <option value="cash_out">Kasa Çıkış</option>
                <option value="bank_in">Banka Giriş</option>
                <option value="bank_out">Banka Çıkış</option>
            </select>
        </div>
        <div><label for="manual_date">Tarih</label><input id="manual_date" type="date" name="movement_date" value="{{ now()->toDateString() }}" required></div>
        <div><label for="manual_amount">Tutar</label><input id="manual_amount" name="amount" inputmode="decimal" required></div>
        <div><label for="manual_note">Not</label><input id="manual_note" name="note" maxlength="5000"></div>
        <div class="page-actions"><button type="submit" class="button-primary">Taslak Oluştur</button></div>
    </form>
    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>#</th><th>Tarih</th><th>Hesap</th><th>İşlem</th><th>Tutar</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @forelse($manualMovements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td><td>{{ $movement->movement_date }}</td><td>{{ $movement->treasury_account_name }}</td>
                    <td>{{ $movement->operation }}</td><td>{{ $movement->amount }} {{ $movement->currency_code }}</td><td>{{ $movement->status }}</td>
                    <td>@if($movement->status === 'draft')<form method="POST" action="{{ route('treasury.manual-movements.finalize', $movement->id) }}">@csrf<button type="submit">Kesinleştir</button></form>@endif</td>
                </tr>
            @empty<tr><td colspan="7">Manuel hareket bulunamadı.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <h2>Virman</h2>
    <form method="POST" action="{{ route('treasury.transfers.store') }}" class="form-grid">
        @csrf
        <div><label for="transfer_from">Çıkış Hesabı</label><select id="transfer_from" name="from_account_id" required>@foreach($activeAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} / {{ $account->currency_code }}</option>@endforeach</select></div>
        <div><label for="transfer_to">Giriş Hesabı</label><select id="transfer_to" name="to_account_id" required>@foreach($activeAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} / {{ $account->currency_code }}</option>@endforeach</select></div>
        <div><label for="transfer_date">Tarih</label><input id="transfer_date" type="date" name="transfer_date" value="{{ now()->toDateString() }}" required></div>
        <div><label for="transfer_amount">Tutar</label><input id="transfer_amount" name="amount" inputmode="decimal" required></div>
        <div><label for="transfer_note">Not</label><input id="transfer_note" name="note" maxlength="5000"></div>
        <div class="page-actions"><button type="submit" class="button-primary">Virman Taslağı</button></div>
    </form>
    <div class="statement-table-card">
        <table class="data-table"><thead><tr><th>#</th><th>Tarih</th><th>Çıkış</th><th>Giriş</th><th>Tutar</th><th>Durum</th><th></th></tr></thead><tbody>
        @forelse($transfers as $transfer)
            <tr><td>{{ $transfer->id }}</td><td>{{ $transfer->transfer_date }}</td><td>{{ $transfer->from_name }}</td><td>{{ $transfer->to_name }}</td><td>{{ $transfer->amount }} {{ $transfer->currency_code }}</td><td>{{ $transfer->status }}</td><td>@if($transfer->status === 'draft')<form method="POST" action="{{ route('treasury.transfers.finalize', $transfer->id) }}">@csrf<button type="submit">Kesinleştir</button></form>@endif</td></tr>
        @empty<tr><td colspan="7">Virman bulunamadı.</td></tr>@endforelse
        </tbody></table>
    </div>
</section>

<section class="detail-card">
    <h2>Masraf</h2>
    <form method="POST" action="{{ route('treasury.expenses.store') }}" class="form-grid">
        @csrf
        <div><label for="expense_account">Hesap</label><select id="expense_account" name="treasury_account_id" required>@foreach($activeAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} / {{ $account->currency_code }}</option>@endforeach</select></div>
        <div><label for="expense_date">Tarih</label><input id="expense_date" type="date" name="expense_date" value="{{ now()->toDateString() }}" required></div>
        <div><label for="expense_amount">Tutar</label><input id="expense_amount" name="amount" inputmode="decimal" required></div>
        <div><label for="expense_category">Kategori</label><input id="expense_category" name="category" maxlength="120" required></div>
        <div><label for="expense_note">Not</label><input id="expense_note" name="note" maxlength="5000"></div>
        <div class="page-actions"><button type="submit" class="button-primary">Masraf Taslağı</button></div>
    </form>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Tarih</th><th>Hesap</th><th>Kategori</th><th>Tutar</th><th>Durum</th><th></th></tr></thead><tbody>
        @forelse($expenses as $expense)<tr><td>{{ $expense->id }}</td><td>{{ $expense->expense_date }}</td><td>{{ $expense->treasury_account_name }}</td><td>{{ $expense->category }}</td><td>{{ $expense->amount }} {{ $expense->currency_code }}</td><td>{{ $expense->status }}</td><td>@if($expense->status === 'draft')<form method="POST" action="{{ route('treasury.expenses.finalize', $expense->id) }}">@csrf<button type="submit">Kesinleştir</button></form>@endif</td></tr>
        @empty<tr><td colspan="7">Masraf bulunamadı.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="detail-card">
    <h2>Kasa Sayımı / Kupür</h2>
    <form method="POST" action="{{ route('treasury.cash-counts.store') }}" class="form-grid">
        @csrf
        <div><label for="cash_count_account">Kasa</label><select id="cash_count_account" name="treasury_account_id" required>@foreach($cashAccounts->where('is_active', true) as $account)<option value="{{ $account->id }}">{{ $account->name }} / {{ $account->currency_code }}</option>@endforeach</select></div>
        <div><label for="cash_count_date">Tarih</label><input id="cash_count_date" type="date" name="count_date" value="{{ now()->toDateString() }}" required></div>
        <div><label for="cash_count_note">Not</label><input id="cash_count_note" name="note" maxlength="5000"></div>
        @foreach([200,100,50,20,10,5,1] as $index => $denomination)
            <div><label>{{ $denomination }} x adet</label><input type="hidden" name="lines[{{ $index }}][denomination]" value="{{ $denomination }}"><input type="number" min="0" name="lines[{{ $index }}][quantity]" value="0" required></div>
        @endforeach
        <div class="page-actions"><button type="submit" class="button-primary">Sayım Taslağı</button></div>
    </form>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Tarih</th><th>Kasa</th><th>Ledger</th><th>Sayılan</th><th>Fark</th><th>Durum</th><th></th></tr></thead><tbody>
        @forelse($cashCounts as $count)<tr><td>{{ $count->id }}</td><td>{{ $count->count_date }}</td><td>{{ $count->treasury_account_name }}</td><td>{{ $count->ledger_balance ?? '—' }}</td><td>{{ $count->counted_total ?? '—' }}</td><td>{{ $count->variance ?? '—' }}</td><td>{{ $count->status }}</td><td>@if($count->status === 'draft')<form method="POST" action="{{ route('treasury.cash-counts.finalize', $count->id) }}">@csrf<button type="submit">Kesinleştir</button></form>@endif</td></tr>
        @empty<tr><td colspan="8">Kasa sayımı bulunamadı.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endcan

@can('treasury.reconcile')
<section class="detail-card">
    <h2>Banka Ekstresi / Mutabakat</h2>
    <form method="POST" action="{{ route('treasury.statements.import') }}" enctype="multipart/form-data" class="form-grid">
        @csrf
        <div><label for="statement_account">Banka Hesabı</label><select id="statement_account" name="treasury_account_id" required>@foreach($bankAccounts->where('is_active', true) as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} / {{ $bank->currency_code }}</option>@endforeach</select></div>
        <div><label for="statement_format">Format</label><select id="statement_format" name="format" required><option value="csv">CSV</option><option value="xlsx">Excel XLSX</option><option value="mt940">MT940</option></select></div>
        <div><label for="statement_file">Dosya</label><input id="statement_file" type="file" name="statement" required></div>
        <div class="page-actions"><button type="submit" class="button-primary">Ekstreyi Aktar</button></div>
    </form>

    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>#</th><th>Tarih</th><th>Tutar</th><th>Referans</th><th>Açıklama</th><th>Durum</th><th>Eşleştir</th></tr></thead>
            <tbody>
            @forelse($statementLines as $line)
                <tr>
                    <td>{{ $line->id }}</td><td>{{ $line->booking_date }}</td><td>{{ $line->signed_amount }} {{ $line->currency_code }}</td><td>{{ $line->reference ?: '—' }}</td><td>{{ $line->description ?: '—' }}</td><td>{{ $line->match_status }}</td>
                    <td>
                        @if($line->match_status === 'unmatched')
                            <form method="POST" action="{{ route('treasury.statements.match', $line->id) }}" class="form-grid">
                                @csrf
                                <input name="movement_id" type="number" min="1" placeholder="Treasury movement #" required>
                                <button type="submit">Eşleştir</button>
                            </form>
                            <form method="POST" action="{{ route('treasury.statements.ignore', $line->id) }}">@csrf<button type="submit">Yok Say</button></form>
                        @elseif($line->match_status === 'matched')
                            #{{ $line->matched_treasury_movement_id }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="7">Ekstre satırı bulunamadı.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</section>
@endcan

<section class="detail-card">
    <h2>Immutable Treasury Ledger</h2>
    <div class="statement-table-card">
        <table class="data-table">
            <thead><tr><th>#</th><th>Tarih</th><th>Hesap</th><th>Tip</th><th>Tutar</th><th>Kaynak</th><th>Effect</th><th>Not</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td><td>{{ $movement->posting_date }}</td><td>{{ $movement->treasury_account_name }}</td><td>{{ $movement->movement_type }}</td><td>{{ $movement->signed_amount }} {{ $movement->currency_code }}</td><td>{{ $movement->source_type }}:{{ $movement->source_id }}</td><td>{{ $movement->effect_type }}</td><td>{{ $movement->memo ?: '—' }}</td>
                </tr>
            @empty<tr><td colspan="8">Treasury hareketi bulunamadı.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
