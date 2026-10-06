<?php

namespace App\Support\Printing;

use App\Enums\PrintType;
use App\Models\DocumentTemplate;
use App\Models\PrintProfile;
use App\Support\Period\PeriodContext;
use DomainException;
use Throwable;

final class PrintManager
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     */
    public static function send(PrintType $type, array $payload, array $context = []): PrintResult
    {
        $profile = self::resolveProfile($type);
        $driverClass = (string) config('printing.driver_class');
        $tracker = app(PrintJobTracker::class);
        $contentHash = self::payloadContentHash($payload);
        $job = $tracker->start($type, null, $profile, $context, $contentHash);

        try {
            /** @var PrintDriver $driver */
            $driver = app($driverClass);
            $result = $driver->send($type, $payload, $profile);
            $tracker->complete($job, $result, null, $context);

            return $result;
        } catch (Throwable $exception) {
            $tracker->fail($job, $exception);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $context */
    public static function sendTemplate(
        PrintType $type,
        DocumentTemplate $template,
        string $content,
        ?string $filename = null,
        array $context = [],
    ): PrintResult {
        PeriodContext::ensure();

        if ((int) $template->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Print template aktif şirket ile aynı şirkete ait olmalıdır.');
        }

        $profile = self::resolveProfile($type);
        $driverKey = match ($template->render_type) {
            'html_pdf' => 'browser',
            'zpl' => 'zpl',
            'text' => 'text',
            default => throw new DomainException('Template print driver tipi desteklenmiyor.'),
        };
        $driverClass = config("printing.drivers.{$driverKey}");

        if (! is_string($driverClass) || $driverClass === '') {
            throw new DomainException("Print driver yapılandırılmamış: {$driverKey}.");
        }

        $payload = [
            'paper_code' => $template->paper_code,
            'width_mm' => $template->width_mm,
            'height_mm' => $template->height_mm,
            'filename' => $filename,
        ];

        match ($template->render_type) {
            'html_pdf' => $payload['html'] = $content,
            'zpl' => $payload['zpl'] = $content,
            'text' => $payload['text'] = $content,
        };

        $tracker = app(PrintJobTracker::class);
        $job = $tracker->start(
            $type,
            $template,
            $profile,
            $context,
            hash('sha256', $content),
        );

        try {
            /** @var PrintDriver $driver */
            $driver = app($driverClass);
            $result = $driver->send($type, $payload, $profile);
            $tracker->complete($job, $result, $template, $context);

            return $result;
        } catch (Throwable $exception) {
            $tracker->fail($job, $exception);

            throw $exception;
        }
    }

    public static function resolveProfile(PrintType $type): ?PrintProfile
    {
        $companyId = PeriodContext::companyId();

        if (! $companyId) {
            PeriodContext::ensure();
            $companyId = PeriodContext::companyId();
        }

        $userId = auth()->id();
        $machineKey = app()->bound('request')
            ? request()->cookie('machine_key')
            : null;

        $base = fn () => PrintProfile::query()
            ->where('company_id', $companyId)
            ->where('print_type', $type->value);

        if ($userId && $machineKey) {
            $profile = $base()
                ->where('user_id', $userId)
                ->where('machine_key', $machineKey)
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        if ($userId) {
            $profile = $base()
                ->where('user_id', $userId)
                ->whereNull('machine_key')
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        return $base()
            ->whereNull('user_id')
            ->whereNull('machine_key')
            ->first();
    }

    /** @param array<string, mixed> $payload */
    private static function payloadContentHash(array $payload): ?string
    {
        foreach (['html', 'zpl', 'text'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                return hash('sha256', $payload[$key]);
            }
        }

        return null;
    }
}
