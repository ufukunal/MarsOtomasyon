<?php

use Tests\ManualAudit\Support\AuditSource;

test('SEC-136 production kaynaklarında PEM private key bulunmaz', function () {
    expect(AuditSource::grep(
        '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
        ['app', 'config', 'routes', 'resources/views'],
    ))->toBe([]);
});

test('SEC-137 hardcoded password assignment bulunmaz', function () {
    expect(AuditSource::grep(
        '/[\'"](?:password|passwd)[\'"]\s*=>\s*[\'"][^\'"$][^\'"]{5,}[\'"]/i',
        ['app', 'config', 'routes'],
    ))->toBe([]);
});

test('SEC-138 hardcoded API token assignment bulunmaz', function () {
    expect(AuditSource::grep(
        '/[\'"](?:api[_-]?key|access[_-]?token|bearer[_-]?token)[\'"]\s*=>\s*[\'"][A-Za-z0-9_\-]{12,}[\'"]/i',
        ['app', 'config', 'routes'],
    ))->toBe([]);
});

test('SEC-139 hardcoded secret assignment bulunmaz', function () {
    expect(AuditSource::grep(
        '/[\'"](?:secret|consumer_secret|client_secret)[\'"]\s*=>\s*[\'"][A-Za-z0-9_\-]{8,}[\'"]/i',
        ['app', 'config', 'routes'],
    ))->toBe([]);
});

test('SEC-140 hardcoded database credential production kodunda bulunmaz', function () {
    expect(AuditSource::grep(
        '/(?:DB_PASSWORD|database_password)\s*[=:>]+\s*[\'"][^\'"]+[\'"]/i',
        ['app', 'config', 'routes'],
    ))->toBe([]);
});

test('SEC-141 hardcoded SMTP credential production kodunda bulunmaz', function () {
    expect(AuditSource::grep(
        '/(?:MAIL_PASSWORD|smtp_password)\s*[=:>]+\s*[\'"][^\'"]+[\'"]/i',
        ['app', 'config', 'routes'],
    ))->toBe([]);
});

test('SEC-142 marketplace credential literal üretim koduna gömülmez', function () {
    expect(AuditSource::grep(
        '/[\'"](?:seller_id|merchant_id|app_key|consumer_key)[\'"]\s*=>\s*[\'"][A-Za-z0-9_\-]{8,}[\'"]/i',
        ['app'],
    ))->toBe([]);
});

test('SEC-143 env doğrudan app katmanında kullanılmaz', function () {
    expect(AuditSource::grep(
        '/\benv\s*\(/',
        ['app', 'routes', 'resources/views'],
    ))->toBe([]);
});

test('SEC-144 raw process execution fonksiyonları app içinde kullanılmaz', function () {
    expect(AuditSource::grep(
        '/(?<!->)(?<!::)\b(?:shell_exec|exec|system|passthru|proc_open|popen)\s*\(/',
        ['app'],
    ))->toBe([]);
});

test('SEC-145 DB unprepared production kodunda kullanılmaz', function () {
    expect(AuditSource::grep(
        '/\bDB::unprepared\s*\(/',
        ['app'],
    ))->toBe([]);
});

test('SEC-146 raw SQL identifier interpolation allowlist dışına çıkmaz', function () {
    $offenders = AuditSource::grep(
        '/DB::connection\([^)]*\)->statement\(\s*(?:sprintf\(|["\'][^"\']*\{\$)/s',
        ['app'],
    );

    expect($offenders)->toBe([]);
});

test('SEC-147 Blade raw echo dinamik kullanıcı değişkenlerinde kullanılmaz', function () {
    expect(AuditSource::grep(
        '/\{!!\s*\$(?!slot\b)[^!]+\s*!!\}/',
        ['resources/views'],
    ))->toBe([]);
});

test('SEC-148 controller exception mesajı raw response body olarak dönülmez', function () {
    expect(AuditSource::grep(
        '/response\([^;]*(?:getMessage\(\)|\$exception|\$e)\b/s',
        ['app/Http/Controllers'],
    ))->toBe([]);
});

test('SEC-149 log context içine credential veya authorization yazılmaz', function () {
    expect(AuditSource::grep(
        '/Log::(?:debug|info|notice|warning|error|critical|alert|emergency)\([^;]*(?:credentials|authorization|password|secret|token)/is',
        ['app'],
    ))->toBe([]);
});

test('SEC-150 channel raw HTTP body hata kaydına doğrudan yazılmaz', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelSensitiveDataRedactor.php');

    expect($source)
        ->toContain('HTTP $2 request failed.')
        ->toContain('[REDACTED]');
});

test('SEC-151 backup ve private artifacts public disk varsayımına bağlanmaz', function () {
    expect(AuditSource::grep(
        '/Storage::disk\(\s*[\'"]public[\'"]\s*\)[^;]*(?:backup|dump|archive|private)/is',
        ['app'],
    ))->toBe([]);
});

test('SEC-152 env, sql dump veya private key public path ile yazılmaz', function () {
    expect(AuditSource::grep(
        '/public_path\([^;]*(?:\.env|\.sql|\.dump|private|\.pem|\.key)/is',
        ['app'],
    ))->toBe([]);
});
