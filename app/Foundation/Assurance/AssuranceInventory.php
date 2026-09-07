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

    /** @return array<string, mixed> */
    public function build(): array
    {
        $tests = $this->testCorpus();
        $http = [];

        foreach (Route::getRoutes() as $route) {
            $http[] = $this->httpSurface($route, $tests);
        }

        usort($http, static fn (array $left, array $right): int => [$left['uri'], $left['methods']] <=> [$right['uri'], $right['methods']]);

        $cli = $this->cliSurfaces($tests);
        $async = $this->asyncSurfaces($tests);
        $data = $this->dataSurfaces($tests);
        $external = $this->externalSurfaces($tests);
        $operations = $this->operationalSurfaces($tests);

        $coverageMap = array_values(array_merge(
            $this->coverageRows($http),
            $this->coverageRows($cli),
            $this->coverageRows($async),
            $this->coverageRows($data),
            $this->coverageRows($external),
            $this->coverageRows($operations),
        ));

        $criticalGaps = array_values(array_filter(
            $coverageMap,
            static fn (array $row): bool => $row['risk_level'] === 'critical' && $row['coverage_status'] === 'uncovered',
        ));

        $trustGaps = array_values(array_filter(
            $http,
            static fn (array $row): bool => $row['mutates_state'] === true && $row['trust_boundary'] === 'missing',
        ));

        $criticalGaps = array_values(array_merge(
            $criticalGaps,
            array_map(
                static fn (array $route): array => [
                    'component' => $route['name'] ?: $route['uri'],
                    'type' => 'http-trust-gap',
                    'path/class' => $route['action'],
                    'mutates_state' => true,
                    'financial_effect' => $route['financial_effect'],
                    'tenant_scoped' => $route['tenant_scoped'],
                    'requires_auth' => false,
                    'required_permission' => $route['required_permission'],
                    'unit_test' => $route['unit_test'],
                    'feature_test' => $route['feature_test'],
                    'integration_test' => $route['integration_test'],
                    'browser_test' => $route['browser_test'],
                    'concurrency_test' => $route['concurrency_test'],
                    'recovery_test' => $route['recovery_test'],
                    'risk_level' => 'critical',
                    'coverage_status' => 'trust-boundary-missing',
                ],
                $trustGaps,
            ),
        ));

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
            'route_authorization_map' => array_map(
                static fn (array $row): array => [
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
                ],
                $http,
            ),
            'critical_gaps' => $criticalGaps,
            'summary' => [
                'http_routes' => count($http),
                'mutating_routes' => count(array_filter($http, static fn (array $row): bool => $row['mutates_state'])),
                'tenant_scoped_routes' => count(array_filter($http, static fn (array $row): bool => $row['tenant_scoped'])),
                'cli_commands' => count($cli),
                'async_components' => count($async),
                'data_components' => count($data),
                'external_boundaries' => count($external),
                'operational_primitives' => count($operations),
                'critical_surfaces' => count(array_filter($coverageMap, static fn (array $row): bool => $row['risk_level'] === 'critical')),
                'critical_gaps' => count($criticalGaps),
            ],
        ];
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @return array<string, mixed>
     */
    private function httpSurface(LaravelRoute $route, array $tests): array
    {
        $methods = array_values(array_intersect($route->methods(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']));
        $middleware = array_values($route->gatherMiddleware());
        $name = $route->getName();
        $uri = $route->uri();
        $action = $route->getActionName();
        $mutates = array_intersect($methods, self::MUTATING_METHODS) !== [];
        $permission = $this->permissionFromMiddleware($middleware);
        $requiresAuth = $this->requiresAuthentication($middleware);
        $trustBoundary = $this->trustBoundary($middleware, $name, $uri);
        $tenantScoped = $this->hasTenantBoundary($middleware);
        $financial = $this->matchesRiskTerm($uri.' '.$action, [
            'invoice', 'payment', 'treasury', 'cash', 'bank', 'account', 'stock', 'inventory',
            'dispatch', 'receipt', 'purchase', 'sales-order', 'salesorder', 'return', 'production',
        ]);
        $criticalOperation = $this->matchesRiskTerm($uri.' '.$action, [
            'backup', 'restore', 'recovery', 'update', 'platform-admin', 'security', 'webhook',
        ]);
        $risk = $mutates && ($financial || $criticalOperation || $tenantScoped) ? 'critical' : ($mutates ? 'high' : 'medium');
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
            'requires_auth' => $requiresAuth,
            'required_permission' => $permission,
            'trust_boundary' => $mutates ? $trustBoundary : ($trustBoundary === 'missing' ? 'public-read' : $trustBoundary),
            'risk_level' => $risk,
            ...$coverage,
        ];
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @return list<array<string, mixed>>
     */
    private function cliSurfaces(array $tests): array
    {
        $surfaces = [];

        foreach (Artisan::all() as $name => $command) {
            $description = $command->getDescription();
            $mutates = $this->matchesRiskTerm($name, [
                'migrate', 'seed', 'prune', 'clear', 'flush', 'delete', 'restore', 'backup',
                'recover', 'recovery-mode', 'platform-admin', 'queue:work', 'schedule:run',
            ]);
            $critical = str_starts_with($name, 'mars:') && $this->matchesRiskTerm($name, [
                'restore', 'backup', 'recover', 'recovery-mode', 'platform-admin', 'production',
            ]);
            $coverage = $this->coverageFor($tests, [$name]);

            $surfaces[] = [
                'component' => $name,
                'type' => 'cli',
                'path/class' => $command::class,
                'description' => $description,
                'mutates_state' => $mutates,
                'financial_effect' => false,
                'tenant_scoped' => false,
                'requires_auth' => false,
                'required_permission' => null,
                'risk_level' => $critical ? 'critical' : ($mutates ? 'high' : 'low'),
                ...$coverage,
            ];
        }

        usort($surfaces, static fn (array $left, array $right): int => $left['component'] <=> $right['component']);

        return $surfaces;
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @return list<array<string, mixed>>
     */
    private function asyncSurfaces(array $tests): array
    {
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
            $coverage = $this->coverageFor($tests, [$component, $relative]);
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
                'retry_policy' => $this->retryPolicy($content),
                'risk_level' => $financial ? 'critical' : 'high',
                ...$coverage,
            ];
        }

        return $surfaces;
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @return list<array<string, mixed>>
     */
    private function dataSurfaces(array $tests): array
    {
        $surfaces = [];

        foreach ($this->phpFiles(base_path('database/migrations')) as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                continue;
            }

            preg_match_all("/Schema::create\\('([^']+)'/", $content, $matches);
            $tables = $matches[1] ?? [];
            if ($tables === []) {
                $tables = [pathinfo($file, PATHINFO_FILENAME)];
            }

            foreach ($tables as $table) {
                $coverage = $this->coverageFor($tests, [$table, pathinfo($file, PATHINFO_FILENAME)]);

                $surfaces[] = [
                    'component' => $table,
                    'type' => 'table',
                    'path/class' => $this->relativePath($file),
                    'mutates_state' => true,
                    'financial_effect' => $this->matchesRiskTerm($table, ['invoice', 'payment', 'account', 'stock', 'inventory', 'ledger', 'treasury']),
                    'tenant_scoped' => str_contains($content, 'company_id'),
                    'requires_auth' => false,
                    'required_permission' => null,
                    'constraints' => [
                        'foreign_keys' => substr_count($content, 'foreignId(') + substr_count($content, 'foreign('),
                        'unique' => substr_count($content, 'unique('),
                        'checks' => substr_count($content, 'check('),
                        'indexes' => substr_count($content, 'index('),
                    ],
                    'risk_level' => $this->matchesRiskTerm($table, ['invoice', 'payment', 'account', 'stock', 'inventory', 'ledger', 'treasury']) ? 'critical' : 'medium',
                    ...$coverage,
                ];
            }
        }

        return $surfaces;
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @return list<array<string, mixed>>
     */
    private function externalSurfaces(array $tests): array
    {
        $surfaces = [];

        foreach ($this->phpFiles(base_path('app')) as $file) {
            $content = file_get_contents($file);
            if (! is_string($content)) {
                continue;
            }

            $matched = [];
            foreach ([
                'http' => ['Http::', 'Illuminate\\Http\\Client', 'GuzzleHttp'],
                'storage' => ['Storage::', 'Filesystem'],
                'email' => ['Mail::', 'Mailer'],
                'webhook' => ['webhook', 'Webhook'],
                'provider' => ['Provider', 'Marketplace', 'Channel'],
            ] as $kind => $needles) {
                if ($this->containsAny($content, $needles)) {
                    $matched[] = $kind;
                }
            }

            if ($matched === []) {
                continue;
            }

            $relative = $this->relativePath($file);
            $component = pathinfo($file, PATHINFO_FILENAME);
            $coverage = $this->coverageFor($tests, [$component, $relative]);

            $surfaces[] = [
                'component' => $component,
                'type' => 'external:'.implode('+', array_unique($matched)),
                'path/class' => $relative,
                'mutates_state' => $this->containsAny($content, ['post(', 'put(', 'patch(', 'delete(', 'write', 'send(', 'dispatch(']),
                'financial_effect' => $this->matchesRiskTerm($relative, ['payment', 'bank', 'invoice', 'marketplace', 'channel']),
                'tenant_scoped' => str_contains($content, 'company_id') || str_contains($content, 'companyId'),
                'requires_auth' => false,
                'required_permission' => null,
                'risk_level' => $this->matchesRiskTerm($relative, ['payment', 'bank', 'invoice', 'marketplace', 'update']) ? 'critical' : 'high',
                ...$coverage,
            ];
        }

        return $surfaces;
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @return list<array<string, mixed>>
     */
    private function operationalSurfaces(array $tests): array
    {
        $surfaces = [];
        $names = [
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
        ];

        foreach ($names as $name) {
            $matches = glob(base_path('app').'/**/'.$name.'.php', GLOB_NOSORT) ?: [];
            if ($matches === []) {
                $matches = $this->findNamedPhpFile(base_path('app'), $name.'.php');
            }

            foreach ($matches as $file) {
                $coverage = $this->coverageFor($tests, [$name, $this->relativePath($file)]);
                $surfaces[] = [
                    'component' => $name,
                    'type' => 'operational-primitive',
                    'path/class' => $this->relativePath($file),
                    'mutates_state' => true,
                    'financial_effect' => false,
                    'tenant_scoped' => false,
                    'requires_auth' => false,
                    'required_permission' => null,
                    'risk_level' => 'critical',
                    ...$coverage,
                ];
            }
        }

        return $surfaces;
    }

    /** @param list<array<string, mixed>> $surfaces
     *  @return list<array<string, mixed>>
     */
    private function coverageRows(array $surfaces): array
    {
        return array_map(static fn (array $surface): array => [
            'component' => $surface['component'],
            'type' => $surface['type'],
            'path/class' => $surface['path/class'],
            'mutates_state' => $surface['mutates_state'],
            'financial_effect' => $surface['financial_effect'],
            'tenant_scoped' => $surface['tenant_scoped'],
            'requires_auth' => $surface['requires_auth'],
            'required_permission' => $surface['required_permission'],
            'unit_test' => $surface['unit_test'],
            'feature_test' => $surface['feature_test'],
            'integration_test' => $surface['integration_test'],
            'browser_test' => $surface['browser_test'],
            'concurrency_test' => $surface['concurrency_test'],
            'recovery_test' => $surface['recovery_test'],
            'risk_level' => $surface['risk_level'],
            'coverage_status' => $surface['coverage_status'],
        ], $surfaces);
    }

    /** @param list<string> $middleware */
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

    /** @param list<string> $middleware */
    private function requiresAuthentication(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if ($entry === 'auth' || $entry === 'b2b.auth' || $entry === 'api.token' || $entry === 'scanner.auth' || str_contains($entry, 'RequirePlatformAdmin')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $middleware */
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
        if (str_starts_with($uri, 'scanner/enroll')) {
            return 'scanner-enrollment';
        }

        return 'missing';
    }

    /** @param list<string> $middleware */
    private function hasTenantBoundary(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if ($entry === 'company.context' || $entry === 'b2b.auth' || $entry === 'api.token' || $entry === 'scanner.auth') {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function httpNeedles(?string $name, string $uri, string $action): array
    {
        $needles = [];
        if (is_string($name) && $name !== '') {
            $needles[] = $name;
        }

        $actionParts = explode('@', $action);
        $class = $actionParts[0] ?? '';
        $classParts = explode('\\', $class);
        $basename = end($classParts);
        if (is_string($basename) && $basename !== '' && $basename !== 'Closure') {
            $needles[] = $basename;
        }

        $module = $this->moduleFromAction($action);
        if ($module !== null) {
            $needles[] = '/'.$module.'/';
        }

        $segments = explode('/', $uri);
        if (($segments[0] ?? '') !== '' && ! str_contains($segments[0], '{')) {
            $needles[] = $segments[0];
        }

        return array_values(array_unique($needles));
    }

    private function moduleFromAction(string $action): ?string
    {
        if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)/', $action, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /** @param list<array{path:string,content:string}> $tests
     *  @param list<string> $needles
     *  @return array{unit_test:bool,feature_test:bool,integration_test:bool,browser_test:bool,concurrency_test:bool,recovery_test:bool,coverage_status:string}
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
            $matches = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && (str_contains($test['content'], $needle) || str_contains($test['path'], $needle))) {
                    $matches = true;
                    break;
                }
            }

            if (! $matches) {
                continue;
            }

            if (str_contains($test['path'], '/Unit/')) {
                $covered['unit_test'] = true;
            }
            if (str_contains($test['path'], '/Feature/')) {
                $covered['feature_test'] = true;
            }
            if (str_contains($test['path'], '/Integration/')) {
                $covered['integration_test'] = true;
            }
            if (str_contains($test['path'], '/Browser/')) {
                $covered['browser_test'] = true;
            }
            if (preg_match('/concurr|race|lock|idempoten/i', $test['path'].' '.$test['content']) === 1) {
                $covered['concurrency_test'] = true;
            }
            if (preg_match('/backup|restore|recovery|rollback/i', $test['path'].' '.$test['content']) === 1) {
                $covered['recovery_test'] = true;
            }
        }

        $covered['coverage_status'] = in_array(true, $covered, true) ? 'covered' : 'uncovered';

        return $covered;
    }

    /** @return list<array{path:string,content:string}> */
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

    /** @return list<string> */
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

    /** @return list<string> */
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

    /** @param list<string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $terms */
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

    /** @return array{tries:?int,backoff:?int} */
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
