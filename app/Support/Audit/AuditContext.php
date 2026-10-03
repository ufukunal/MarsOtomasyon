<?php

namespace App\Support\Audit;

use App\Support\Period\PeriodContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

final class AuditContext
{
    private static string $connection = 'master';

    public static function connection(): string
    {
        return self::$connection;
    }

    /** @param array<string, mixed> $properties */
    public static function master(
        string $description,
        array $properties = [],
        ?Model $subject = null,
        ?string $event = null,
    ): ?ActivityContract {
        return self::runOn('master', fn (): ?ActivityContract => self::write(
            $description,
            $properties,
            $subject,
            $event,
        ));
    }

    /** @param array<string, mixed> $properties */
    public static function period(
        string $description,
        array $properties = [],
        ?Model $subject = null,
        ?string $event = null,
    ): ?ActivityContract {
        PeriodContext::ensure();

        return self::runOn('period', fn (): ?ActivityContract => self::write(
            $description,
            $properties,
            $subject,
            $event,
        ));
    }

    public static function runOn(string $connection, Closure $callback): mixed
    {
        $previous = self::$connection;
        self::$connection = $connection;

        try {
            return $callback();
        } finally {
            self::$connection = $previous;
        }
    }

    /** @param array<string, mixed> $properties */
    private static function write(
        string $description,
        array $properties,
        ?Model $subject,
        ?string $event,
    ): ?ActivityContract {
        $logger = activity()
            ->useLog(self::$connection)
            ->withProperties($properties);

        if ($subject) {
            $logger->performedOn($subject);
        }

        $actor = auth()->user();

        if ($actor) {
            $logger->causedBy($actor);
        }

        $logger->tap(function ($activity) use ($actor): void {
            if (self::$connection === 'period') {
                $activity->actor_user_id = $actor?->getAuthIdentifier();
                $activity->actor_user_name = $actor?->name;
                $activity->correlation_id = self::correlationId();

                return;
            }

            $activity->company_id = PeriodContext::companyId()
                ?? ($actor?->last_company_id ? (int) $actor->last_company_id : null);
        }, $event);

        return $logger->log($description);
    }

    private static function correlationId(): ?string
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request->attributes->get('correlation_id')
            ?? $request->headers->get('X-Correlation-ID');
    }
}
