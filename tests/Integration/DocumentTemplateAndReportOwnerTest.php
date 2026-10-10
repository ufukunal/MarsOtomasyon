<?php

use App\Actions\DocumentTemplates\DeactivateDocumentTemplate;
use App\Actions\DocumentTemplates\SetDefaultDocumentTemplate;
use App\Actions\Reporting\DeleteReportPreset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\IsolatedPostgres;

it('denies inactive actors from setting default templates or deactivating revisions', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $actor = new User(['is_active' => false, 'name' => 'Disabled V4 User']);

        expect(fn () => app(SetDefaultDocumentTemplate::class)->handle(42, $actor))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(DeactivateDocumentTemplate::class)->handle(42, $actor))
            ->toThrow(AuthorizationException::class);
    });
});

it('denies inactive actors from deleting report filter presets', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $actor = new User(['is_active' => false, 'name' => 'Disabled V4 User']);

        expect(fn () => app(DeleteReportPreset::class)->handle(42, $actor))
            ->toThrow(AuthorizationException::class);
    });
});
