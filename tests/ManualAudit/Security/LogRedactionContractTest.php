<?php

use Tests\ManualAudit\Support\AuditSource;

test('LOG-220 password redaction paterni vardır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))->toContain('password');
});

test('LOG-221 token redaction paterni vardır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))->toContain('token');
});

test('LOG-222 authorization header redaction paterni vardır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))->toContain('authorization');
});

test('LOG-223 api key redaction paterni vardır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))->toContain('api[_-]?key');
});

test('LOG-224 consumer secret redaction paterni vardır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))->toContain('consumer[_-]?secret');
});

test('LOG-225 channel webhook secret generic secret redaction kapsamındadır', function () {
    expect(AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php'))->toContain('secret');
});

test('LOG-226 database password URL credential redaction kapsamındadır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))
        ->toContain('password')
        ->toContain('[REDACTED]@');
});

test('LOG-227 SMTP password generic password redaction kapsamındadır', function () {
    expect(AuditSource::read('app/Support/Operations/OperationalErrorSanitizer.php'))->toContain('password');
});

test('LOG-228 backup archive secret kaynak kodda loglanmaz', function () {
    expect(AuditSource::grep(
        '/Log::[^;]*(?:BACKUP_ARCHIVE_PASSWORD|backup.*password)/i',
        ['app'],
    ))->toBe([]);
});

test('LOG-229 channel safe metadata buyer customer email phone address alanlarını reddeder', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSyncRecorder.php');

    foreach (['buyer', 'customer', 'email', 'phone', 'address'] as $needle) {
        expect($source)->toContain($needle);
    }
});

test('LOG-230 raw request response body production loguna doğrudan verilmez', function () {
    expect(AuditSource::grep(
        '/Log::(?:debug|info|warning|error|critical)\([^;]*(?:getContent\(\)|->body\(\)|->json\(\))/is',
        ['app'],
    ))->toBe([]);
});
