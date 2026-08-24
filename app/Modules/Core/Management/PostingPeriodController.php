<?php

namespace App\Modules\Core\Management;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Models\PostingPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class PostingPeriodController
{
    public function __construct(
        private readonly ActiveCompanyContext $companyContext,
        private readonly AuditRecorder $audit,
        private readonly Clock $clock,
    ) {}

    public function index(): View
    {
        return view('settings.posting-periods.index', [
            'periods' => PostingPeriod::query()
                ->where('company_id', $this->companyId())
                ->orderByDesc('starts_on')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.posting-periods.form', ['period' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertCodeAvailable($data['code']);

        try {
            $period = DB::transaction(function () use ($data): PostingPeriod {
                $period = PostingPeriod::query()->create([
                    'company_id' => $this->companyId(),
                    ...$data,
                    'status' => PostingPeriodStatus::Open,
                    'closed_at' => null,
                ]);

                $this->audit->record(
                    AuditAction::PostingPeriodCreated,
                    AuditTargetType::PostingPeriod,
                    (int) $period->getKey(),
                    after: $this->snapshot($period),
                );

                return $period;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages(['starts_on' => 'Bu tarih aralığı mevcut bir dönemle çakışıyor.']);
            }

            throw $exception;
        }

        return redirect()->route('settings.posting-periods.show', $period)->with('status', 'Muhasebe dönemi oluşturuldu.');
    }

    public function show(int $period): View
    {
        return view('settings.posting-periods.show', ['period' => $this->period($period)]);
    }

    public function edit(int $period): View
    {
        $period = $this->period($period);
        abort_if($period->status === PostingPeriodStatus::Closed, 409, 'Kapalı dönem normal düzenleme akışıyla değiştirilemez.');

        return view('settings.posting-periods.form', ['period' => $period]);
    }

    public function update(Request $request, int $period): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $period = DB::transaction(function () use ($period, $data): PostingPeriod {
                $locked = PostingPeriod::query()
                    ->where('company_id', $this->companyId())
                    ->lockForUpdate()
                    ->findOrFail($period);

                abort_if($locked->status === PostingPeriodStatus::Closed, 409, 'Kapalı dönem normal düzenleme akışıyla değiştirilemez.');
                $this->assertCodeAvailable($data['code'], (int) $locked->getKey());
                $before = $this->snapshot($locked);

                $locked->update($data);

                $this->audit->record(
                    AuditAction::PostingPeriodUpdated,
                    AuditTargetType::PostingPeriod,
                    (int) $locked->getKey(),
                    before: $before,
                    after: $this->snapshot($locked),
                );

                return $locked;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages(['starts_on' => 'Bu tarih aralığı mevcut bir dönemle çakışıyor.']);
            }

            throw $exception;
        }

        return redirect()->route('settings.posting-periods.show', $period)->with('status', 'Muhasebe dönemi güncellendi.');
    }

    public function close(int $period): RedirectResponse
    {
        /** @var array{period:PostingPeriod,changed:bool} $result */
        $result = DB::transaction(function () use ($period): array {
            $locked = PostingPeriod::query()
                ->where('company_id', $this->companyId())
                ->lockForUpdate()
                ->findOrFail($period);

            if ($locked->status === PostingPeriodStatus::Closed) {
                return ['period' => $locked, 'changed' => false];
            }

            $before = $this->snapshot($locked);
            $locked->update([
                'status' => PostingPeriodStatus::Closed,
                'closed_at' => $this->clock->now(),
            ]);

            $this->audit->record(
                AuditAction::PostingPeriodClosed,
                AuditTargetType::PostingPeriod,
                (int) $locked->getKey(),
                before: $before,
                after: $this->snapshot($locked),
            );

            return ['period' => $locked, 'changed' => true];
        });

        $message = $result['changed'] ? 'Muhasebe dönemi kapatıldı.' : 'Muhasebe dönemi zaten kapalı.';

        return redirect()->route('settings.posting-periods.show', $result['period'])->with('status', $message);
    }

    /** @return array{code:string,name:string,starts_on:string,ends_on:string} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        return [
            'code' => mb_strtoupper(trim((string) $validated['code'])),
            'name' => trim((string) $validated['name']),
            'starts_on' => (string) $validated['starts_on'],
            'ends_on' => (string) $validated['ends_on'],
        ];
    }

    /** @return array{code:string,name:string,starts_on:string,ends_on:string,status:string,closed_at:string|null} */
    private function snapshot(PostingPeriod $period): array
    {
        return [
            'code' => (string) $period->code,
            'name' => (string) $period->name,
            'starts_on' => $period->starts_on?->format('Y-m-d') ?? '',
            'ends_on' => $period->ends_on?->format('Y-m-d') ?? '',
            'status' => $period->status->value,
            'closed_at' => $period->closed_at?->setTimezone('UTC')->format(DATE_ATOM),
        ];
    }

    private function assertCodeAvailable(string $code, ?int $exceptId = null): void
    {
        $query = PostingPeriod::query()
            ->where('company_id', $this->companyId())
            ->whereRaw('lower(code) = ?', [mb_strtolower($code)]);

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu dönem kodu zaten kullanılıyor.']);
        }
    }

    private function period(int $id): PostingPeriod
    {
        return PostingPeriod::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
