<?php

namespace App\Support\Printing;

use App\Enums\PrintType;
use App\Models\PrintProfile;
use App\Support\Period\PeriodContext;

final class PrintManager
{
    public static function send(PrintType $type, array $payload): PrintResult
    {
        $profile = self::resolveProfile($type);

        /** @var PrintDriver $driver */
        $driver = app(config('printing.driver_class'));

        return $driver->send($type, $payload, $profile);
    }

    public static function resolveProfile(PrintType $type): ?PrintProfile
    {
        $companyId = PeriodContext::companyId();

        if (! $companyId) {
            PeriodContext::ensure();
        }

        $userId = auth()->id();
        $machineKey = app()->runningInConsole()
            ? null
            : request()->cookie('machine_key');

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
