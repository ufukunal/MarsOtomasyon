<?php

namespace App\Http\Controllers;

use App\Enums\SalesChannelPlatform;
use App\Jobs\PollHepsiburadaWebhookJob;
use App\Models\Period;
use App\Models\SalesChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class HepsiburadaWebhookController extends Controller
{
    private const EVENTS = [
        'createOrder',
        'createPackages',
        'orderCancel',
        'unpack',
        'intransit',
        'deliver',
        'undeliver',
        'changeShippingAddressOrder',
        'awaitingAction',
        'awaitingPreApproval',
        'disputedClaimResult',
        'packageFromClaimResult',
    ];

    public function __invoke(
        Request $request,
        int $account,
        string $event,
    ): Response {
        abort_unless(in_array($event, self::EVENTS, true), 404);

        $channel = SalesChannelAccount::query()
            ->whereKey($account)
            ->where('platform', SalesChannelPlatform::Hepsiburada->value)
            ->where('is_active', true)
            ->firstOrFail();
        $credentials = $channel->credentials();
        $expectedUser = trim((string) ($credentials['webhook_username'] ?? ''));
        $expectedPassword = (string) ($credentials['webhook_password'] ?? '');
        $providedUser = (string) ($request->getUser() ?? '');
        $providedPassword = (string) ($request->getPassword() ?? '');

        abort_unless(
            $expectedUser !== ''
                && $expectedPassword !== ''
                && $providedUser !== ''
                && $providedPassword !== ''
                && hash_equals($expectedUser, $providedUser)
                && hash_equals($expectedPassword, $providedPassword),
            401,
        );

        $period = Period::query()
            ->where('company_id', $channel->company_id)
            ->where('status', 'active')
            ->orderByDesc('year')
            ->firstOrFail();

        PollHepsiburadaWebhookJob::dispatch(
            (int) $channel->company_id,
            (int) $period->id,
            (int) $channel->id,
            $event,
        );

        return response()->noContent();
    }
}
