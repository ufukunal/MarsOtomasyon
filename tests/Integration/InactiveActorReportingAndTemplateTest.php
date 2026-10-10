<?php

use App\Actions\DocumentTemplates\CreateDocumentTemplateRevision;
use App\Actions\Reporting\QueueReportExport;
use App\Actions\Reporting\SaveReportPreset;
use App\Models\User;
use App\Support\Reporting\ReportRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\IsolatedPostgres;

it('rejects inactive users before scheduling a report export or saving presets', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $actor = new User(['name' => 'Inactive V4', 'is_active' => false]);

        expect(fn () => app(QueueReportExport::class)->handle(
            'stock', new ReportRequest, 'csv', $actor,
        ))->toThrow(AuthorizationException::class);

        expect(fn () => app(SaveReportPreset::class)->handle(
            'stock', 'V4', [], [], [], false, $actor,
        ))->toThrow(AuthorizationException::class);
    });
});

it('refuses inactive actors creating document template revisions before allocating a revision number', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $actor = new User(['name' => 'Inactive V4', 'is_active' => false]);

        expect(fn () => app(CreateDocumentTemplateRevision::class)->handle(
            'test-template', 'Test', 'text', ['sections' => []], actor: $actor,
        ))->toThrow(AuthorizationException::class);
    });
});
