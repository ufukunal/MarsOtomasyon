<?php

namespace App\Modules\Core\Shell;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use LogicException;

final readonly class GlobalSearchController
{
    private const int LIMIT_PER_TYPE = 8;

    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $results = [];

        if (mb_strlen($query) >= 2) {
            if (Gate::allows(PermissionKey::BranchView->value)) {
                $results = [...$results, ...$this->branches($query)];
            }

            if (Gate::allows(PermissionKey::UserView->value)) {
                $results = [...$results, ...$this->users($query)];
            }

            if (Gate::allows(PermissionKey::RoleView->value)) {
                $results = [...$results, ...$this->roles($query)];
            }

            usort(
                $results,
                static fn (array $left, array $right): int => $right['score'] <=> $left['score'],
            );
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    /** @return list<array{type:string,title:string,subtitle:string,url:string,score:float}> */
    private function branches(string $query): array
    {
        $rows = Branch::query()
            ->where('company_id', $this->companyId())
            ->selectRaw(
                "ts_rank(to_tsvector('simple', coalesce(code, '') || ' ' || coalesce(name, '')), plainto_tsquery('simple', ?)) + similarity(lower(name), lower(?)) as search_score",
                [$query, $query],
            )
            ->addSelect(['branches.id', 'branches.code', 'branches.name'])
            ->whereRaw(
                "(to_tsvector('simple', coalesce(code, '') || ' ' || coalesce(name, '')) @@ plainto_tsquery('simple', ?) OR similarity(lower(name), lower(?)) >= 0.15 OR lower(name) LIKE ?)",
                [$query, $query, '%'.mb_strtolower($query).'%'],
            )
            ->orderByDesc('search_score')
            ->limit(self::LIMIT_PER_TYPE)
            ->get();

        return array_values($rows->map(static fn (Branch $branch): array => [
            'type' => 'Şube',
            'title' => (string) $branch->name,
            'subtitle' => (string) $branch->code,
            'url' => route('settings.branches.show', $branch->getKey()),
            'score' => (float) $branch->getAttribute('search_score'),
        ])->all());
    }

    /** @return list<array{type:string,title:string,subtitle:string,url:string,score:float}> */
    private function users(string $query): array
    {
        $rows = CompanyMembership::query()
            ->where('company_memberships.company_id', $this->companyId())
            ->where('company_memberships.is_active', true)
            ->join('users', 'users.id', '=', 'company_memberships.user_id')
            ->where('users.status', 'active')
            ->selectRaw(
                "ts_rank(to_tsvector('simple', coalesce(users.name, '') || ' ' || coalesce(users.email, '')), plainto_tsquery('simple', ?)) + similarity(lower(users.name), lower(?)) as search_score",
                [$query, $query],
            )
            ->addSelect([
                'company_memberships.id',
                'users.name as user_name',
            ])
            ->whereRaw(
                "(to_tsvector('simple', coalesce(users.name, '') || ' ' || coalesce(users.email, '')) @@ plainto_tsquery('simple', ?) OR similarity(lower(users.name), lower(?)) >= 0.15 OR lower(users.name) LIKE ?)",
                [$query, $query, '%'.mb_strtolower($query).'%'],
            )
            ->orderByDesc('search_score')
            ->limit(self::LIMIT_PER_TYPE)
            ->get();

        return array_values($rows->map(static fn (CompanyMembership $membership): array => [
            'type' => 'Kullanıcı',
            'title' => (string) $membership->getAttribute('user_name'),
            'subtitle' => 'Firma kullanıcısı',
            'url' => route('settings.users.show', $membership->getKey()),
            'score' => (float) $membership->getAttribute('search_score'),
        ])->all());
    }

    /** @return list<array{type:string,title:string,subtitle:string,url:string,score:float}> */
    private function roles(string $query): array
    {
        $rows = Role::query()
            ->where('company_id', $this->companyId())
            ->selectRaw(
                "ts_rank(to_tsvector('simple', coalesce(code, '') || ' ' || coalesce(name, '')), plainto_tsquery('simple', ?)) + similarity(lower(name), lower(?)) as search_score",
                [$query, $query],
            )
            ->addSelect(['roles.id', 'roles.code', 'roles.name'])
            ->whereRaw(
                "(to_tsvector('simple', coalesce(code, '') || ' ' || coalesce(name, '')) @@ plainto_tsquery('simple', ?) OR similarity(lower(name), lower(?)) >= 0.15 OR lower(name) LIKE ?)",
                [$query, $query, '%'.mb_strtolower($query).'%'],
            )
            ->orderByDesc('search_score')
            ->limit(self::LIMIT_PER_TYPE)
            ->get();

        return array_values($rows->map(static fn (Role $role): array => [
            'type' => 'Rol',
            'title' => (string) $role->name,
            'subtitle' => (string) $role->code,
            'url' => route('settings.roles.show', $role->getKey()),
            'score' => (float) $role->getAttribute('search_score'),
        ])->all());
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId) ? $companyId : throw new LogicException('Global search requires a persisted active company.');
    }
}
