<?php

use App\Actions\Finance\PostCollection;
use App\Actions\Purchases\PostPayment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

function marsFinancePostingFixtures(): array
{
    $user = User::query()->create([
        'name' => 'V4 isolated finance actor',
        'email' => 'v4-'.strtolower(Str::random(12)).'@invalid.test',
        'password' => 'disposable-only',
        'is_active' => true,
    ]);
    Auth::login($user);
    Gate::before(fn (User $actor, string $ability): bool => $actor->id === $user->id);

    $period = DB::connection('period');
    $contact = $period->table('contacts')->insertGetId([
        'title' => 'V4 isolated contact',
        'type' => 'legal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $cash = $period->table('cash_accounts')->insertGetId([
        'code' => 'V4-'.Str::random(10),
        'name' => 'V4 isolated cash',
        'currency' => 'TRY',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [(int) $contact, (int) $cash];
}

it('posts a collection with matching positive cash and contact credit movements', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        try {
            [$contactId, $cashId] = marsFinancePostingFixtures();
            $key = 'v4-'.Str::random(20);
            $action = app(PostCollection::class);
            $posted = $action->handle($contactId, '120.0000', '2026-10-10', 'cash', $cashId, $key);

            expect($posted->status)->toBe('posted')
                ->and($posted->number)->not->toBeNull();

            $db = DB::connection('period');
            $contactRow = $db->table('contact_transactions')->where('document_id', $posted->id)->first();
            $cashRow = $db->table('cash_movements')->where('document_id', $posted->id)->first();

            expect($contactRow->direction)->toBe('credit')
                ->and((string) $contactRow->amount)->toBe('120.0000')
                ->and($cashRow->direction)->toBe('in')
                ->and((string) $cashRow->amount)->toBe('120.0000');

            $replay = $action->handle($contactId, '120.0000', '2026-10-10', 'cash', $cashId, $key);
            expect($replay->id)->toBe($posted->id)
                ->and($db->table('cash_movements')->where('document_id', $posted->id)->count())->toBe(1);
        } finally {
            Auth::logout();
        }
    });
});

it('posts a TRY supplier payment with a contact debit and matching cash outflow', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        try {
            [$contactId, $cashId] = marsFinancePostingFixtures();
            $posted = app(PostPayment::class)->handle(
                $contactId, '75', 'TRY', '1', '2026-10-10',
                'cash', $cashId, 'v4-'.Str::random(20),
            );
            $db = DB::connection('period');

            expect($posted->status)->toBe('posted')
                ->and($db->table('contact_transactions')->where('document_id', $posted->id)->value('direction'))->toBe('debit')
                ->and($db->table('cash_movements')->where('document_id', $posted->id)->value('direction'))->toBe('out');
        } finally {
            Auth::logout();
        }
    });
});

it('rolls back an invalid collection amount before writing finance ledger rows', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        try {
            [$contactId, $cashId] = marsFinancePostingFixtures();
            $db = DB::connection('period');
            $before = $db->table('documents')->count();

            expect(fn () => app(PostCollection::class)->handle(
                $contactId, '0', '2026-10-10', 'cash', $cashId, 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);

            expect($db->table('documents')->count())->toBe($before)
                ->and($db->table('cash_movements')->count())->toBe(0);
        } finally {
            Auth::logout();
        }
    });
});
