<?php

declare(strict_types=1);

// Strictly read-only verification of dedicated TEST resources forwarded over SSH.
// This script never creates databases, changes privileges or writes Valkey keys.

function v4Require(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "BLOCKED: {$message}\n");
        exit(77);
    }
}

v4Require(getenv('DB_HOST') === '127.0.0.1' && getenv('DB_PORT') === '5432', 'unexpected PostgreSQL endpoint');
v4Require(getenv('DB_MASTER_DATABASE') === 'mars_test_master', 'unexpected master test database');
v4Require(getenv('DB_USERNAME') === 'mars_test', 'unexpected PostgreSQL test role');
v4Require(getenv('REDIS_HOST') === '127.0.0.1' && getenv('REDIS_PORT') === '6379', 'unexpected Valkey endpoint');
v4Require(getenv('REDIS_PREFIX') === 'mars:test:', 'unexpected Valkey test prefix');
v4Require(getenv('REDIS_USERNAME') === 'mars_test', 'dedicated Valkey ACL identity required');

$dbPassword = getenv('DB_PASSWORD');
$redisPassword = getenv('REDIS_PASSWORD');
v4Require(is_string($dbPassword) && $dbPassword !== '', 'missing dedicated PostgreSQL test password');
v4Require(is_string($redisPassword) && $redisPassword !== '', 'missing dedicated Valkey ACL password');
v4Require(extension_loaded('pdo_pgsql') && extension_loaded('redis'), 'missing PostgreSQL/Valkey PHP client extensions');

try {
    foreach (['mars_test_master', 'mars_test_period'] as $database) {
        $pdo = new PDO(
            "pgsql:host=127.0.0.1;port=5432;dbname={$database};connect_timeout=5",
            'mars_test',
            $dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
        $identity = $pdo->query('SELECT current_database() AS database, current_user AS username')->fetch(PDO::FETCH_ASSOC);
        v4Require($identity['database'] === $database && $identity['username'] === 'mars_test', 'test PostgreSQL identity mismatch');

        $role = $pdo->query(
            'SELECT rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls
             FROM pg_roles WHERE rolname = current_user',
        )->fetch(PDO::FETCH_ASSOC);
        v4Require(is_array($role), 'test role not found');
        foreach ($role as $privilege) {
            v4Require(! in_array($privilege, [true, 1, '1', 't', 'true'], true), 'test PostgreSQL role has elevated privileges');
        }

        $databases = $pdo->query(
            "SELECT datname, has_database_privilege(current_user, oid, 'CONNECT') AS can_connect
             FROM pg_database WHERE NOT datistemplate",
        )->fetchAll(PDO::FETCH_ASSOC);
        $found = [];
        foreach ($databases as $entry) {
            $name = $entry['datname'];
            $canConnect = in_array($entry['can_connect'], [true, 1, '1', 't', 'true'], true);
            if (in_array($name, ['mars_test_master', 'mars_test_period'], true)) {
                v4Require($canConnect, 'missing CONNECT grant on dedicated test database');
                $found[$name] = true;
            } elseif ($name !== 'postgres') {
                v4Require(! $canConnect, 'test role can CONNECT to a non-test database');
            }
        }
        v4Require(isset($found['mars_test_master'], $found['mars_test_period']), 'dedicated test databases missing');
        $pdo = null;
    }

    $valkey = new Redis();
    v4Require($valkey->connect('127.0.0.1', 6379, 5.0), 'Valkey connection failed');
    v4Require($valkey->auth(['mars_test', $redisPassword]), 'Valkey ACL authentication failed');
    v4Require($valkey->rawCommand('ACL', 'WHOAMI') === 'mars_test', 'Valkey ACL identity mismatch');
    v4Require($valkey->select(1), 'Valkey test cache logical DB is inaccessible');
    $valkey->close();
} catch (Throwable $exception) {
    // Do not log exception connection strings, SQL, or credential-bearing errors.
    fwrite(STDERR, 'BLOCKED: isolated service or credential verification failed (details withheld)'.PHP_EOL);
    exit(77);
}

echo "Isolated PostgreSQL roles/databases and Valkey ACL identity verified (read-only).\n";
