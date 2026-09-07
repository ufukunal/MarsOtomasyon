<?php

declare(strict_types=1);

namespace App\Foundation\Assurance;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AssuranceInventory
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $tests = $this->testCorpus();

        /** @var list<array<string, mixed>> $http */
        $http = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $http[] = $this->httpSurface($route, $tests);
        }

        usort($http, static fn (array $left, array $right): int => [(string) $left['uri'], (array) $left['methods']] <=> [(string) $right['uri'], (array) $right['methods']]);

        $cli = $this->cliSurfaces($tests);
        $async = $this->asyncSurfaces($tests);
        $data = $this->dataSurfaces($tests);
        $external = $this->externalSurfaces($tests);
        $operations = $this->operationalSurfaces($tests);
        $coverageMap = array_merge($http, $cli, $async, $data, $external, $operations);

        $criticalGaps = array_values(array_filter(
            $coverageMap,
            static fn (array $row): bool => (string) $row['risk_level'] === 'critical' && (string) $row['coverage_status'] === 'uncovered',
        ));

        foreach ($http as $route) {
            if ((bool) $route['mutates_state'] && (string) $route['trust_boundary'] === 'missing') {
                $criticalGaps[] = [
                    ...$route,
                    'type' => 'http-trust-gap',
                    'risk_level' => 'critical',
                    'coverage_status' => 'trust-boundary-missing',
                ];
            }
        }

        return [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'commit' => getenv('GITHUB_SHA') ?: null,
            'surfaces' => [
                'http' => $http,
                'cli' => $cli,
                'async' => $async,
                'data' => $data,
                'external' => $external,
                'operations' => $operations,
            ],
            'coverage_map' => $coverageMap,
            'route_authorization_map' => array_map($this->authorizationRow(...), $http),
            'critical_gaps' => $criticalGaps,
            'summary' => [
                'http_routes' => count($http),
                'mutating_routes' => count(array_filter($http, static fn (array $row): bool => (bool) $row['mutates_state'])),
                'tenant_scoped_routes' => count(array_filter($http, static fn (array $row): bool => (bool) $row['tenant_scoped'])),
                'cli_commands' => count($cli),
                'async_components' => count($async),
                'data_components' => count($data),
                'external_boundaries' => count($external),
                'operational_primitives' => count($operations),
                'critical_surfaces' => count(array_filter($coverageMap, static fn (array $row): bool => (string) $row['risk_level'] === 'critical')),
                'critical_gaps' => count($criticalGaps),
            ],
        ];
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @return array<string, mixed>
     */
    private function httpSurface(LaravelRoute $route, array $tests): array
    {
        $methods = array_values(array_intersect($route->methods(), array_merge(['GET'], self::MUTATING_METHODS)));
        $middleware = array_values(array_filter($route->gatherMiddleware(), 'is_string'));
        $name = $route->getName();
        $uri = $route->uri();
        $action = $route->getActionName();
        $mutates = array_intersect($methods, self::MUTATING_METHODS) !== [];
        $tenantScoped = $this->hasTenantBoundary($middleware);
        $financial = $this->matchesRiskTerm($uri.' '.$action, [
            'invoice', 'payment', 'treasury', 'cash', 'bank', 'account', 'stock', 'inventory',
            'dispatch', 'receipt', 'purchase', 'sales-order', 'salesorder', 'return', 'production',
        ]);
        $criticalOperation = $this->matchesRiskTerm($uri.' '.$action, [
            'backup', 'restore', 'recovery', 'update', 'platform-admin', 'security', 'webhook',
        ]);
        $coverage = $this->coverageFor($tests, $this->httpNeedles($name, $uri, $action));

        return [
            'component' => $name ?: $uri,
            'type' => 'http',
            'path/class' => $action,
            'methods' => $methods,
            'uri' => $uri,
            'name' => $name,
            'action' => $action,
            'middleware' => $middleware,
            'mutates_state' => $mutates,
            'financial_effect' => $financial,
            'tenant_scoped' => $tenantScoped,
            'requires_auth' => $this->requiresAuthentication($middleware),
            'required_permission' => $this->permissionFromMiddleware($middleware),
            'trust_boundary' => $mutates ? $this->trustBoundary($middleware, $name, $uri) : 'public-read',
            'risk_level' => $mutates && ($financial || $criticalOperation || $tenantScoped) ? 'critical' : ($mutates ? 'high' : 'medium'),
            ...$coverage,
        ];
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @return list<array<string, mixed>>
     */
    private function cliSurfaces(array $tests): array
    {
        /** @var list<array<string, mixed>> $surfaces */
        $surfaces = [];

        foreach (Artisan::all() as $name => $command) {
            $mutates = $this->matchesRiskTerm($name, [
                'migrate', 'seed', 'prune', 'clear', 'flush', 'delete', 'restore', 'backup',
                'recover', 'recovery-mode', 'platform-admin', 'queue:work', 'schedule:run',
            ]);
            $critical = str_starts_with($name, 'mars:') && $this->matchesRiskTerm($name, [
                'restore', 'backup', 'recover', 'recovery-mode', 'platform-admin', 'production',
            ]);

            $surfaces[] = [
                'component' => $name,
                'type' => 'cli',
                'path/class' => $command::class,
                'description' => $command->getDescription(),
                'mutates_state' => $mutates,
                'financial_effect' => false,
                'tenant_scoped' => false,
                'requires_auth' => false,
                'required_permission' => null,
                'trust_boundary' => 'local-cli',
                'risk_level' => $critical ? 'critical' : ($mutates ? 'high' : 'low'),
                ...$this->coverageFor($tests, [$name]),
            ];
        }

        return $surfaces;
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @return list<array<string, mixed>>
     */
    private function asyncSurfaces(array $tests): array
    {
        /** @var list<array<string, mixed>> $surfaces */
        $surfaces = [];

        foreach ($this->phpFiles(base_path('app')) as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                continue;
            }

            $relative = $this->relativePath($file);
            if (! str_contains($content, 'ShouldQueue') && ! str_contains($relative, '/Jobs/') && ! str_contains($relative, '/Listeners/')) {
                continue;
            }

            $component = pathinfo($file, PATHINFO_FILENAME);
            $financial = $this->matchesRiskTerm($relative.' '.$content, ['invoice', 'payment', 'stock', 'inventory', 'dispatch']);
            $surfaces[] = [
                'component' => $component,
                'type' => str_contains($relative, '/Listeners/') ? 'listener' : 'job',
                'path/class' => $relative,
                'mutates_state' => true,
                'financial_effect' => $financial,
                'tenant_scoped' => str_contains($content, 'company_id') || str_contains($content, 'companyId'),
                'requires_auth' => false,
                'required_permission' => null,
                'trust_boundary' => 'queue-runtime',
                'retry_policy' => $this->retryPolicy($content),
                'risk_level' => $financial ? 'critical' : 'high',
                ...$this->coverageFor($tests, [$component, $relative]),
            ];
        }

        return $surfaces;
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @return list<array<string, mixed>>
     */
    private function dataSurfaces(array $tests): array
    {
        /** @var list<array<string, mixed>> $surfaces */
        $surfaces = [];

        foreach ($this->phpFiles(base_path('database/migrations')) as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                continue;
            }

            preg_match_all("/Schema::create\\('([^']+)'/", $content, $matches);
            $tables = array_values(array_filter($matches[1], 'is_string'));
            if ($tables === []) {
                $tables = [pathinfo($file, PATHINFO_FILENAME)];
            }

            foreach ($tables as $table) {
                $financial = $this->matchesRiskTerm($table, ['invoice', 'payment', 'account', 'stock', 'inventory', 'ledger', 'treasury']);
                $surfaces[] = [
                    'component' => $table,
                    'type' => 'table',
                    'path/class' => $this->relativePath($file),
                    'mutates_state' => true,
                    'financial_effect' => $financial,
                    'tenant_scoped' => str_contains($content, 'company_id'),
                    'requires_auth' => false,
                    'required_permission' => null,
                    'trust_boundary' => 'database',
                    'constraints' => [
                        'foreign_keys' => substr_count($content, 'foreignId(') + substr_count($content, 'foreign('),
                        'unique' => substr_count($content, 'unique('),
                        'checks' => substr_count($content, 'check('),
                        'indexes' => substr_count($content, 'index('),
                    ],
                    'risk_level' => $financial ? 'critical' : 'medium',
                    ...$this->coverageFor($tests, [$table, pathinfo($file, PATHINFO_FILENAME)]),
                ];
            }
        }

        return $surfaces;
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @return list<array<string, mixed>>
     */
    private function externalSurfaces(array $tests): array
    {
        /** @var list<array<string, mixed>> $surfaces */
        $surfaces = [];

        foreach ($this->phpFiles(base_path('app')) as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                continue;
            }

            $kinds = [];
            foreach ([
                'http' => ['Http::', 'Illuminate\\Http\\Client', 'GuzzleHttp'],
                'storage' => ['Storage::', 'Filesystem'],
                'email' => ['Mail::', 'Mailer'],
                'webhook' => ['webhook', 'Webhook'],
                'provider' => ['Provider', 'Marketplace', 'Channel'],
            ] as $kind => $needles) {
                if ($this->containsAny($content, $needles)) {
                    $kinds[] = $kind;
                }
            }

            if ($kinds === []) {
                continue;
            }

            $relative = $this->relativePath($file);
            $component = pathinfo($file, PATHINFO_FILENAME);
            $financial = $this->matchesRiskTerm($relative, ['payment', 'bank', 'invoice', 'marketplace', 'channel']);
            $surfaces[] = [
                'component' => $component,
                'type' => 'external:'.implode('+', array_unique($kinds)),
                'path/class' => $relative,
                'mutates_state' => $this->containsAny($content, ['post(', 'put(', 'patch(', 'delete(', 'write', 'send(', 'dispatch(']),
                'financial_effect' => $financial,
                'tenant_scoped' => str_contains($content, 'company_id') || str_contains($content, 'companyId'),
                'requires_auth' => false,
                'required_permission' => null,
                'trust_boundary' => 'external-integration',
                'risk_level' => $financial || str_contains(strtolower($relative), 'update') ? 'critical' : 'high',
                ...$this->coverageFor($tests, [$component, $relative]),
            ];
        }

        return $surfaces;
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @return list<array<string, mixed>>
     */
    private function operationalSurfaces(array $tests): array
    {
        /** @var list<array<string, mixed>> $surfaces */
        $surfaces = [];

        foreach ([
            'BackupManager',
            'BackupDrillRunner',
            'ProductionSafetyState',
            'RecoveryGate',
            'RestoreDrillService',
            'UpdateCenterController',
            'UpdateManifestVerifier',
            'ProductionOperationalPrimitiveExecutor',
            'ProductionPromotionService',
            'ProductionStabilizationService',
        ] as $name) {
            foreach ($this->findNamedPhpFile(base_path('app'), $name.'.php') as $file) {
                $surfaces[] = [
                    'component' => $name,
                    'type' => 'operational-primitive',
                    'path/class' => $this->relativePath($file),
                    'mutates_state' => true,
                    'financial_effect' => false,
                    'tenant_scoped' => false,
                    'requires_auth' => false,
                    'required_permission' => null,
                    'trust_boundary' => 'operations-control-plane',
                    'risk_level' => 'critical',
                    ...$this->coverageFor($tests, [$name, $this->relativePath($file)]),
                ];
            }
        }

        return $surfaces;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function authorizationRow(array $row): array
    {
        return [
            'methods' => $row['methods'],
            'uri' => $row['uri'],
            'name' => $row['name'],
            'action' => $row['action'],
            'middleware' => $row['middleware'],
            'mutates_state' => $row['mutates_state'],
            'tenant_scoped' => $row['tenant_scoped'],
            'requires_auth' => $row['requires_auth'],
            'required_permission' => $row['required_permission'],
            'trust_boundary' => $row['trust_boundary'],
            'risk_level' => $row['risk_level'],
            'coverage_status' => $row['coverage_status'],
        ];
    }

    /**
     * @param  list<string>  $middleware
     */
    private function permissionFromMiddleware(array $middleware): ?string
    {
        foreach ($middleware as $entry) {
            if (str_starts_with($entry, 'can:') || str_starts_with($entry, 'api.permission:')) {
                return $entry;
            }
            if (str_contains($entry, 'RequirePlatformAdmin')) {
                return 'platform-admin';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function requiresAuthentication(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (in_array($entry, ['auth', 'b2b.auth', 'api.token', 'scanner.auth'], true) || str_contains($entry, 'RequirePlatformAdmin')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $middleware
     */
    private function trustBoundary(array $middleware, ?string $name, string $uri): string
    {
        foreach ($middleware as $entry) {
            if ($entry === 'auth') {
                return 'session-auth';
            }
            if ($entry === 'b2b.auth') {
                return 'b2b-auth';
            }
            if ($entry === 'api.token') {
                return 'api-token';
            }
            if ($entry === 'scanner.auth') {
                return 'scanner-auth';
            }
            if (str_contains($entry, 'RequirePlatformAdmin')) {
                return 'platform-admin';
            }
        }

        if (in_array($name, ['login.store', 'b2b.login.store', 'b2b.password.email', 'b2b.password.update'], true)) {
            return 'authentication-entrypoint';
        }
        if ($name === 'channels.webhook') {
            return 'external-webhook';
        }
        if (str_contains($uri, 'scanner/enroll')) {
            return 'scanner-enrollment';
        }

        return 'missing';
    }

    /**
     * @param  list<string>  $middleware
     */
    private function hasTenantBoundary(array $middleware): bool
    {
        return array_intersect($middleware, ['company.context', 'b2b.auth', 'api.token', 'scanner.auth']) !== [];
    }

    /**
     * @return list<string>
     */
    private function httpNeedles(?string $name, string $uri, string $action): array
    {
        $needles = [];
        if (is_string($name) && $name !== '') {
            $needles[] = $name;
        }

        $class = explode('@', $action)[0];
        $basename = basename(str_replace('\\', '/', $class));
        if ($basename !== '' && $basename !== 'Closure') {
            $needles[] = $basename;
        }

        if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)/', $action, $match) === 1) {
            $needles[] = '/'.$match[1].'/';
        }

        $firstSegment = explode('/', $uri)[0];
        if ($firstSegment !== '' && ! str_contains($firstSegment, '{')) {
            $needles[] = $firstSegment;
        }

        return array_values(array_unique($needles));
    }

    /**
     * @param  list<array{path:string,content:string}>  $tests
     * @param  list<string>  $needles
     * @return array{unit_test:bool,feature_test:bool,integration_test:bool,browser_test:bool,concurrency_test:bool,recovery_test:bool,coverage_status:string}
     */
    private function coverageFor(array $tests, array $needles): array
    {
        $covered = [
            'unit_test' => false,
            'feature_test' => false,
            'integration_test' => false,
            'browser_test' => false,
            'concurrency_test' => false,
            'recovery_test' => false,
        ];

        foreach ($tests as $test) {
            if (! $this->containsAny($test['content'].' '.$test['path'], $needles)) {
                continue;
            }

            $path = $test['path'];
            $covered['unit_test'] = $covered['unit_test'] || str_contains($path, '/Unit/');
            $covered['feature_test'] = $covered['feature_test'] || str_contains($path, '/Feature/');
            $covered['integration_test'] = $covered['integration_test'] || str_contains($path, '/Integration/');
            $covered['browser_test'] = $covered['browser_test'] || str_contains($path, '/Browser/');
            $covered['concurrency_test'] = $covered['concurrency_test'] || preg_match('/concurr|race|lock|idempoten/i', $path.' '.$test['content']) === 1;
            $covered['recovery_test'] = $covered['recovery_test'] || preg_match('/backup|restore|recovery|rollback/i', $path.' '.$test['content']) === 1;
        }

        return [
            ...$covered,
            'coverage_status' => in_array(true, $covered, true) ? 'covered' : 'uncovered',
        ];
    }

    /**
     * @return list<array{path:string,content:string}>
     */
    private function testCorpus(): array
    {
        $tests = [];
        foreach ($this->phpFiles(base_path('tests')) as $file) {
            $content = file_get_contents($file);
            if (is_string($content)) {
                $tests[] = ['path' => $this->relativePath($file), 'content' => $content];
            }
        }

        return $tests;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function findNamedPhpFile(string $directory, string $filename): array
    {
        return array_values(array_filter(
            $this->phpFiles($directory),
            static fn (string $path): bool => basename($path) === $filename,
        ));
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_replace(DIRECTORY_SEPARATOR, '/', str_starts_with($path, $base) ? substr($path, strlen($base)) : $path);
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $terms
     */
    private function matchesRiskTerm(string $value, array $terms): bool
    {
        $value = strtolower($value);
        foreach ($terms as $term) {
            if (str_contains($value, strtolower($term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{tries:?int,backoff:?int}
     */
    private function retryPolicy(string $content): array
    {
        $tries = null;
        $backoff = null;

        if (preg_match('/(?:public\\s+)?\\$tries\\s*=\\s*(\\d+)/', $content, $match) === 1) {
            $tries = (int) $match[1];
        }
        if (preg_match('/(?:public\\s+)?\\$backoff\\s*=\\s*(\\d+)/', $content, $match) === 1) {
            $backoff = (int) $match[1];
        }

        return ['tries' => $tries, 'backoff' => $backoff];
    }
}
