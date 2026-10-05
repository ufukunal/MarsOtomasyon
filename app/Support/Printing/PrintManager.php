<?php

namespace App\Support\Printing;

use App\Enums\PrintType;
use App\Models\DocumentTemplate;
use App\Models\PrintProfile;
use App\Support\Period\PeriodContext;
use DomainException;

final class PrintManager
{
    /** @param array<string, mixed> $payload */
    public static function send(PrintType $type, array $payload): PrintResult
    {
        $profile = self::resolveProfile($type);
        $driverClass = (string) config('printing.driver_class');

        /** @var PrintDriver $driver */
        $driver = app($driverClass);

        return $driver->send($type, $payload, $profile);
    }

    public static function sendTemplate(
        PrintType $type,
        DocumentTemplate $template,
        string $content,
        ?string $filename = null,
    ): PrintResult {
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

        /** @var PrintDriver $driver */
        $driver = app($driverClass);

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

        return $driver->send($type, $payload, $profile);
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
}
