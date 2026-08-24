<?php

namespace App\Modules\Core\Shell;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Eloquent\Builder;
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
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    /** @return list<array{type:string,title:string,subtitle:string,url:string,score:float}> */
    private function branches(string $query): array
    {
        $rows = $this->ranked(
            Branch::query()->where('company_id', $this->companyId()),
            "coalesce(code, '') || ' ' || coalesce(name, '')",
            'name',
            $query,
        )
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'code', 'name']);

        return $rows->map(static fn (Branch $branch): array => [
            'type' => 'Şube',
            'title' => (string) $branch->name,
            'subtitle' => (string) $branch->code,
            'url' => route('settings.branches.show', $branch->getKey()),
            'score' => (float) $branch->getAttribute('search_score'),
        ])->values()->all();
    }

    /** @return list<array{type:string,title:string,subtitle:string,url:string,score:float}> */
    private function users(string $query): array
    {
        $builder = CompanyMembership::query()
            ->where('company_memberships.company_id', $this->companyId())
            ->where('company_memberships.is_active', true)
            ->join('users', 'users.id', '=', 'company_memberships.user_id')
            ->where('users.status', 'active');

        $rows = $this->ranked(
            $builder,
            "coalesce(users.name, '') || ' ' || coalesce(users.email, '')",
            'users.name',
            $query,
        )
            ->limit(self::LIMIT_PER_TYPE)
            ->get([
                'company_memberships.id',
                'users.name as user_name',
            ]);

        return $rows->map(static fn (CompanyMembership $membership): array => [
            'type' => 'Kullanıcı',
            'title' => (string) $membership->getAttribute('user_name'),
            'subtitle' => 'Firma kullanıcısı',
            'url' => route('settings.users.show', $membership->getKey()),
            'score' => (float) $membership->getAttribute('search_score'),
        ])->values()->all();
    }

    /** @return list<array{type:string,title:string,subtitle:string,url:string,score:float}> */
    private function roles(string $query): array
    {
        $rows = $this->ranked(
            Role::query()->where('company_id', $this->companyId()),
            "coalesce(code, '') || ' ' || coalesce(name, '')",
            'name',
            $query,
        )
            ->limit(self::LIMIT_PER_TYPE)
            ->get(['id', 'code', 'name']);

        return $rows->map(static fn (Role $role): array => [
            'type' => 'Rol',
            'title' => (string) $role->name,
            'subtitle' => (string) $role->code,
            'url' => route('settings.roles.show', $role->getKey()),
            'score' => (float) $role->getAttribute('search_score'),
        ])->values()->all();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $builder
     * @return Builder<TModel>
     */
    private function ranked(Builder $builder, string $documentSql, string $similarityColumn, string $query): Builder
    {
        $rankSql = "ts_rank(to_tsvector('simple', {$documentSql}), plainto_tsquery('simple', ?)) + similarity(lower({$similarityColumn}), lower(?))";

        return $builder
            ->selectRaw("{$rankSql} as search_score", [$query, $query])
            ->where(function (Builder $search) use ($documentSql, $similarityColumn, $query): void {
                $search
                    ->whereRaw("to_tsvector('simple', {$documentSql}) @@ plainto_tsquery('simple', ?)", [$query])
                    ->orWhereRaw("similarity(lower({$similarityColumn}), lower(?)) >= 0.15", [$query])
                    ->orWhereRaw("lower({$similarityColumn}) LIKE ?", ['%'.mb_strtolower($query).'%']);
            })
            ->orderByDesc('search_score');
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId) ? $companyId : throw new LogicException('Global search requires a persisted active company.');
    }
}
