<?php

use Tests\ManualAudit\Support\AuditSource;

test('MASS-187 period models company_id mass assignment alanı taşımaz', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Models/Period']) as $path) {
        $source = AuditSource::read($path);

        if (preg_match('/protected\s+\$fillable\s*=\s*\[[\s\S]*?[\'"]company_id[\'"][\s\S]*?\];/', $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('MASS-188 period_id request all ile model fill edilmez', function () {
    expect(AuditSource::grep(
        '/(?:fill|create|update)\(\s*\$request->all\(\)\s*\)/',
        ['app'],
    ))->toBe([]);
});

test('MASS-189 created_by request payloadından mass assign edilmez', function () {
    expect(AuditSource::grep(
        '/[\'"]created_by[\'"]\s*=>\s*\$request->/',
        ['app'],
    ))->toBe([]);
});

test('MASS-190 posted_by request payloadından mass assign edilmez', function () {
    expect(AuditSource::grep(
        '/[\'"]posted_by[\'"]\s*=>\s*\$request->/',
        ['app'],
    ))->toBe([]);
});

test('MASS-191 posted status request all ile doğrudan yazılmaz', function () {
    expect(AuditSource::grep(
        '/[\'"]status[\'"]\s*=>\s*\$request->[^;\n]*(?:posted|status)/i',
        ['app/Http', 'app/Livewire'],
    ))->toBe([]);
});

test('MASS-192 version istemci payloadından doğrudan update edilmez', function () {
    expect(AuditSource::grep(
        '/[\'"]version[\'"]\s*=>\s*\$request->/',
        ['app'],
    ))->toBe([]);
});

test('MASS-193 carried_from_period_id istemci tarafından doğrudan yazılmaz', function () {
    expect(AuditSource::grep(
        '/[\'"]carried_from_period_id[\'"]\s*=>\s*\$request->/',
        ['app'],
    ))->toBe([]);
});

test('MASS-194 encrypted credentials generic request all ile fill edilmez', function () {
    $source = AuditSource::read('app/Actions/Channels/SaveSalesChannelAccount.php');

    expect($source)->not->Contain('$request->all()');
});

test('MASS-195 financial lifecycle alanları generic Livewire payload ile fill edilmez', function () {
    expect(AuditSource::grep(
        '/->(?:fill|update)\(\s*\$(?:data|payload|form)\s*\)/',
        ['app/Livewire/Finance'],
    ))->toBe([]);
});
