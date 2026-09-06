@extends('layouts.app')

@section('title', 'CRM / Fırsatlar')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Yönetimi</p>
            <h1>CRM / Fırsatlar</h1>
            <p>Lead, fırsat, takip ve ticari bağlantılar. CRM finansal veya stok hareketi üretmez.</p>
        </div>
        <a href="{{ route('customers.index') }}" data-workspace-link>Carilere Dön</a>
    </section>

    @can('crm.manage')
        <section class="detail-card">
            <h2>Yeni Lead</h2>
            <form method="post" action="{{ route('crm.leads.store') }}">
                @csrf
                <div class="form-grid">
                    <label>Ad / Yetkili<input name="name" required maxlength="191"></label>
                    <label>Firma<input name="company_name" maxlength="191"></label>
                    <label>E-posta<input type="email" name="email" maxlength="191"></label>
                    <label>Telefon<input name="phone" maxlength="64"></label>
                    <label>Satış sorumlusu
                        <select name="owner_user_id">
                            <option value="">Atanmamış</option>
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="page-actions"><span></span><button type="submit">Lead Oluştur</button></div>
            </form>
        </section>

        <section class="detail-card">
            <h2>Yeni Fırsat</h2>
            <form method="post" action="{{ route('crm.opportunities.store') }}">
                @csrf
                <div class="form-grid">
                    <label>Fırsat adı<input name="name" required maxlength="191"></label>
                    <label>Lead
                        <select name="lead_id"><option value="">Bağlantı yok</option>@foreach ($leads as $lead)<option value="{{ $lead->id }}">#{{ $lead->id }} · {{ $lead->name }}</option>@endforeach</select>
                    </label>
                    <label>Cari
                        <select name="account_id"><option value="">Bağlantı yok</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->legal_name }}</option>@endforeach</select>
                    </label>
                    <label>Satış sorumlusu
                        <select name="owner_user_id"><option value="">Atanmamış</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select>
                    </label>
                    <label>Beklenen değer<input type="number" step="0.000001" min="0" name="expected_value"></label>
                    <label>Para birimi<input name="currency_code" maxlength="3" placeholder="TRY"></label>
                    <label>Kapanış tarihi<input type="date" name="expected_close_date"></label>
                </div>
                <div class="page-actions"><span></span><button type="submit">Fırsat Oluştur</button></div>
            </form>
        </section>

        <section class="detail-card">
            <h2>Takip / Aktivite</h2>
            <form method="post" action="{{ route('crm.activities.store') }}">
                @csrf
                <div class="form-grid">
                    <label>Lead<select name="lead_id"><option value="">Yok</option>@foreach ($leads as $lead)<option value="{{ $lead->id }}">#{{ $lead->id }} · {{ $lead->name }}</option>@endforeach</select></label>
                    <label>Fırsat<select name="opportunity_id"><option value="">Yok</option>@foreach ($opportunities as $opportunity)<option value="{{ $opportunity->id }}">#{{ $opportunity->id }} · {{ $opportunity->name }}</option>@endforeach</select></label>
                    <label>Tür<input name="activity_type" required maxlength="64" placeholder="follow_up"></label>
                    <label>Konu<input name="subject" required maxlength="191"></label>
                    <label>Sorumlu<select name="owner_user_id"><option value="">Atanmamış</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></label>
                    <label>Hatırlatma / Son tarih<input type="datetime-local" name="due_at"></label>
                    <label>Not<textarea name="note" maxlength="10000"></textarea></label>
                </div>
                <div class="page-actions"><span></span><button type="submit">Aktivite Ekle</button></div>
            </form>
        </section>
    @endcan

    <section class="detail-card statement-table-card">
        <h2>Leadler</h2>
        <table class="data-table">
            <thead><tr><th>ID</th><th>Ad</th><th>Firma</th><th>Durum</th><th>Sorumlu</th><th>İşlemler</th></tr></thead>
            <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td>#{{ $lead->id }}</td><td>{{ $lead->name }}</td><td>{{ $lead->company_name ?: '—' }}</td><td>{{ $lead->status }}</td><td>{{ $lead->owner_user_id ?: '—' }}</td>
                    <td>
                        @can('crm.manage')
                            @if ($lead->converted_account_id === null)
                                <form method="post" action="{{ route('crm.leads.convert', $lead->id) }}">
                                    @csrf
                                    <select name="account_id" required><option value="">Cari seç</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->legal_name }}</option>@endforeach</select>
                                    <button type="submit">Cariye Dönüştür</button>
                                </form>
                            @else
                                Cari #{{ $lead->converted_account_id }}
                            @endif
                            <form method="post" action="{{ route('crm.leads.files.store', $lead->id) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" required><input name="label" placeholder="Dosya etiketi"><button type="submit">Dosya Yükle</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Lead bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="detail-card statement-table-card">
        <h2>Fırsatlar</h2>
        <table class="data-table">
            <thead><tr><th>ID</th><th>Ad</th><th>Aşama</th><th>Değer</th><th>Cari</th><th>Teklif</th><th>Sipariş</th><th>İşlemler</th></tr></thead>
            <tbody>
            @forelse ($opportunities as $opportunity)
                <tr>
                    <td>#{{ $opportunity->id }}</td><td>{{ $opportunity->name }}</td><td>{{ $opportunity->stage }}</td><td>{{ $opportunity->expected_value ?? '—' }} {{ $opportunity->currency_code ?? '' }}</td><td>{{ $opportunity->account_id ?: '—' }}</td><td>{{ $opportunity->quote_id ?: '—' }}</td><td>{{ $opportunity->sales_order_id ?: '—' }}</td>
                    <td>
                        @can('crm.manage')
                            <form method="post" action="{{ route('crm.opportunities.stage', $opportunity->id) }}">
                                @csrf @method('PATCH')
                                <select name="stage">@foreach (['new','qualified','proposal','won','lost','cancelled'] as $stage)<option value="{{ $stage }}" @selected($opportunity->stage === $stage)>{{ $stage }}</option>@endforeach</select>
                                <button type="submit">Aşamayı Güncelle</button>
                            </form>
                            <form method="post" action="{{ route('crm.opportunities.links', $opportunity->id) }}">
                                @csrf @method('PATCH')
                                <input type="number" name="account_id" value="{{ $opportunity->account_id }}" placeholder="Cari ID">
                                <input type="number" name="quote_id" value="{{ $opportunity->quote_id }}" placeholder="Teklif ID">
                                <input type="number" name="sales_order_id" value="{{ $opportunity->sales_order_id }}" placeholder="Sipariş ID">
                                <button type="submit">Bağlantıları Güncelle</button>
                            </form>
                            <form method="post" action="{{ route('crm.opportunities.files.store', $opportunity->id) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" required><input name="label" placeholder="Dosya etiketi"><button type="submit">Dosya Yükle</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Fırsat bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="detail-card statement-table-card">
        <h2>Aktiviteler / Takipler</h2>
        <table class="data-table">
            <thead><tr><th>ID</th><th>Tür</th><th>Konu</th><th>Lead</th><th>Fırsat</th><th>Sorumlu</th><th>Son tarih</th></tr></thead>
            <tbody>
            @forelse ($activities as $activity)
                <tr><td>#{{ $activity->id }}</td><td>{{ $activity->activity_type }}</td><td>{{ $activity->subject }}</td><td>{{ $activity->lead_id ?: '—' }}</td><td>{{ $activity->opportunity_id ?: '—' }}</td><td>{{ $activity->owner_user_id ?: '—' }}</td><td>{{ $activity->due_at ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="7">Aktivite bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
