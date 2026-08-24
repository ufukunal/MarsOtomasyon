<?php

namespace App\Modules\Core\Audit;

use App\Foundation\Clock\Clock;
use App\Foundation\Correlation\CorrelationContext;
use App\Foundation\Logging\SensitiveDataRedactor;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditSource;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\AuditEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class AuditRecorder
{
    public function __construct(
        private Clock $clock,
        private CorrelationContext $correlation,
        private SensitiveDataRedactor $redactor,
        private ActiveCompanyContext $companyContext,
    ) {}

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed> $metadata
     */
    public function record(
        AuditAction $action,
        AuditTargetType $targetType,
        int|string|null $targetId,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
        AuditSource $source = AuditSource::Web,
    ): AuditEntry {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Audit recording requires an active business transaction.');
        }

        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Audit recording requires a persisted active company.');
        }

        $actorId = Auth::id();
        if ($source === AuditSource::Web && ! is_int($actorId)) {
            throw new LogicException('Web audit recording requires an authenticated actor.');
        }

        $target = $targetId === null ? null : (string) $targetId;
        if ($target !== null && strlen($target) > 64) {
            throw new InvalidArgumentException('Audit target ID is too long.');
        }

        $now = $this->clock->now();

        return AuditEntry::query()->create([
            'event_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'actor_user_id' => is_int($actorId) ? $actorId : null,
            'correlation_id' => $this->correlation->requireId(),
            'source' => $source->value,
            'action' => $action->value,
            'target_type' => $targetType->value,
            'target_id' => $target,
            'before_state' => $before === null ? null : $this->redactor->redact($before),
            'after_state' => $after === null ? null : $this->redactor->redact($after),
            'metadata' => $this->redactor->redact($metadata),
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }
}
