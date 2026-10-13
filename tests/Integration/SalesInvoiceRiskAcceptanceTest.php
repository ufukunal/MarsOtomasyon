<?php

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Livewire\Components\DataTable\Column;
use App\Livewire\Pages\Contacts\ContactList;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('requires an explicit acknowledgement before posting a sales invoice above a TRY risk limit', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = Contact::query()->create([
                'title' => 'Risk warning integration',
                'type' => 'legal',
                'risk_limit' => '1000.0000',
            ]);
            $draft = app(SaveSalesDocumentDraft::class)->handle(
                DocumentType::SalesInvoice,
                ['contact_id' => $contact->id, 'document_date' => '2026-10-13'],
                [[
                    'line_kind' => 'service',
                    'description' => 'Risk test service',
                    'quantity' => '1.000',
                    'unit_price' => '13000.0000',
                    'vat_rate' => '0',
                ]],
            );

            $key = 'risk-invoice-'.Str::random(18);
            $action = app(PostSalesInvoice::class);
            expect(fn () => $action->handle($draft, $key))->toThrow(
                \DomainException::class,
                'Cari risk limiti aşılıyor'
            );
            expect($draft->fresh()->status)->toBe('draft')
                ->and(DB::connection('period')->table('contact_transactions')
                    ->where('contact_id', $contact->id)->count())->toBe(0);

            $posted = $action->handle($draft, $key, true);
            expect($posted->status)->toBe('posted')
                ->and((string) $posted->grand_total)->toBe('13000.0000')
                ->and(DB::connection('period')->table('contact_transactions')
                    ->where('document_id', $posted->id)->value('direction'))->toBe('debit');

            $fresh = $contact->fresh();
            expect($fresh->balance())->toBe('13000.0000')
                ->and((new ContactList)->formattedValue($fresh, Column::make('risk_usage', 'Risk Kullanımı')))
                ->toBe('1.300%');

            expect($action->handle($draft, $key)->id)->toBe($posted->id);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
