<?php

use Tests\ManualAudit\Support\AuditSource;

test('CH-196 Trendyol credential değerleri error metninden redact edilir', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php');
    expect($source)->toContain('Trendyol')->toContain('[REDACTED]');
});

test('CH-197 Hepsiburada credential değerleri error metninden redact edilir', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php');
    expect($source)->toContain('Hepsiburada')->toContain('[REDACTED]');
});

test('CH-198 N11 credential değerleri error metninden redact edilir', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php');
    expect($source)->toContain('N11')->toContain('[REDACTED]');
});

test('CH-199 WooCommerce credential değerleri error metninden redact edilir', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php');
    expect($source)->toContain('WooCommerce')->toContain('[REDACTED]');
});

test('CH-200 authorization api key secret token password patternleri redact edilir', function () {
    expect(AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php'))
        ->toMatch('/authorization\|api\[-_ \]\?key\|secret\|token\|password/i');
});

test('CH-201 channel sync recorder raw payload yerine safe metadata sınırı uygular', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSyncRecorder.php');

    expect($source)
        ->toContain('assertSafeMetadata')
        ->toContain('hassas veri içeremez.')
        ->toContain('65535');
});

test('CH-202 webhook girişlerinde platforma özgü authentication doğrulaması vardır', function () {
    expect(AuditSource::read('app/Http/Controllers/TrendyolWebhookController.php'))->toContain('hash_equals');
    expect(AuditSource::read('app/Http/Controllers/HepsiburadaWebhookController.php'))->toContain('hash_equals');
    expect(AuditSource::read('app/Http/Controllers/WooCommerceWebhookController.php'))->toContain('hash_equals');
});

test('CH-203 WooCommerce webhook HMAC SHA256 doğrular', function () {
    expect(AuditSource::read('app/Http/Controllers/WooCommerceWebhookController.php'))
        ->toContain("hash_hmac(")
        ->toContain("'sha256'");
});

test('CH-204 invalid webhook authentication 401 ile reddedilir', function () {
    foreach ([
        'app/Http/Controllers/TrendyolWebhookController.php',
        'app/Http/Controllers/HepsiburadaWebhookController.php',
        'app/Http/Controllers/WooCommerceWebhookController.php',
    ] as $path) {
        expect(AuditSource::read($path))->toContain('401');
    }
});

test('CH-205 replay inbound event external registry unique claim ile tekilleştirilir', function () {
    $migration = AuditSource::read('database/migrations/master/0001_10_01_000010_create_channel_master_tables.php');
    $processor = AuditSource::read('app/Actions/Channels/ProcessChannelInboundEvent.php');

    expect($migration)->toContain('channel_external_event_unique');
    expect($processor)->toContain('ChannelExternalEventRegistryService');
});

test('CH-206 channel sync account active company ile eşleşmek zorundadır', function () {
    expect(AuditSource::read('app/Support/Channels/ChannelSyncRecorder.php'))
        ->toContain('PeriodContext::companyId()')
        ->toContain('aktif şirkete ait değil');
});

test('CH-207 channel clients fake placeholder endpoint içermez', function () {
    expect(AuditSource::grep(
        '#https?://(?:example\.com|localhost|127\.0\.0\.1)#i',
        [
            'app/Support/Channels/Trendyol',
            'app/Support/Channels/Hepsiburada',
            'app/Support/Channels/N11',
            'app/Support/Channels/WooCommerce',
        ],
    ))->toBe([]);
});

test('CH-208 channel endpoint ve adapter seçimi platform configuration veya account settings üzerinden yapılır', function () {
    $source = AuditSource::read('config/channels.php');

    foreach (['trendyol', 'hepsiburada', 'n11', 'woocommerce'] as $platform) {
        expect($source)->toContain("'{$platform}'");
    }
});

test('CH-209 outbound channel HTTP clients explicit timeout tanımlar', function () {
    foreach ([
        'app/Support/Channels/Trendyol/TrendyolClient.php',
        'app/Support/Channels/Hepsiburada/HepsiburadaClient.php',
        'app/Support/Channels/N11/N11Client.php',
        'app/Support/Channels/WooCommerce/WooCommerceClient.php',
    ] as $path) {
        expect(AuditSource::read($path))->toMatch('/timeout\s*\(|connectTimeout\s*\(/');
    }
});

test('CH-210 channel retry state machine bounded retry delay ve terminal error kaydı taşır', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSyncRecorder.php');

    expect($source)
        ->toContain("config('channels.retry_delays'")
        ->toContain('ChannelSyncError::query()->firstOrCreate');
});
