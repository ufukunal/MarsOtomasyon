<?php

use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders the internal login form', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('MarsOtomasyon')
        ->assertSee('Giriş Yap');
});

it('authenticates an active user case-insensitively and records the login time', function (): void {
    $user = User::query()->create([
        'name' => 'Mars User',
        'email' => 'Mars.User@Example.Test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    $response = $this
        ->withSession(['pre_login_marker' => 'preserved'])
        ->post('/login', [
            'email' => 'MARS.USER@EXAMPLE.TEST',
            'password' => 'correct-password',
        ]);

    $response
        ->assertRedirect('/')
        ->assertSessionHas('pre_login_marker', 'preserved');

    $this->assertAuthenticatedAs($user);

    expect($user->fresh()?->last_login_at)->not->toBeNull();
});

it('rejects inactive users with the same public credential error', function (): void {
    User::query()->create([
        'name' => 'Inactive User',
        'email' => 'inactive@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Inactive,
    ]);

    $this->post('/login', [
        'email' => 'inactive@example.test',
        'password' => 'correct-password',
    ])
        ->assertSessionHasErrors([
            'email' => 'Giriş bilgileri geçersiz.',
        ]);

    $this->assertGuest();
});

it('rejects wrong passwords without revealing whether the account exists', function (): void {
    User::query()->create([
        'name' => 'Known User',
        'email' => 'known@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    foreach (['known@example.test', 'missing@example.test'] as $email) {
        $this->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors([
                'email' => 'Giriş bilgileri geçersiz.',
            ]);
    }

    $this->assertGuest();
});

it('rate limits repeated credential failures per normalized email and client address', function (): void {
    User::query()->create([
        'name' => 'Rate Limited User',
        'email' => 'rate@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post('/login', [
            'email' => 'RATE@EXAMPLE.TEST',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/login', [
        'email' => 'rate@example.test',
        'password' => 'correct-password',
    ])
        ->assertSessionHasErrors([
            'email' => 'Çok fazla giriş denemesi. Kısa süre sonra tekrar deneyin.',
        ]);

    $this->assertGuest();
});

it('logs out by invalidating authentication and clearing the previous session data', function (): void {
    $user = User::query()->create([
        'name' => 'Logout User',
        'email' => 'logout@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ])->refresh();

    $response = $this
        ->actingAs($user)
        ->withSession([
            'active_company_id' => 42,
            'sensitive_marker' => 'must-disappear',
        ])
        ->post('/logout');

    $response
        ->assertRedirect('/login')
        ->assertSessionMissing('active_company_id')
        ->assertSessionMissing('sensitive_marker');

    $this->assertGuest();
});
