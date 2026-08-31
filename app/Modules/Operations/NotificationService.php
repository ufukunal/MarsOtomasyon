<?php

namespace App\Modules\Operations;

use App\Foundation\Outbox\OutboxEventCatalog;
use App\Foundation\Outbox\OutboxMessageDraft;
use App\Foundation\Outbox\OutboxStore;
use App\Modules\Communication\NotificationTemplateService;
use App\Modules\Communication\SystemIntegrationRuntime;
use App\Modules\Communication\SystemIntegrationService;
use App\Modules\Operations\Jobs\RelayOutbox;
use Carbon\Carbon;
use DateTimeInterface;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class NotificationService
{
    private const MAX_PROVIDER_ATTEMPTS = 5;

    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly SystemIntegrationService $integrations,
        private readonly OutboxStore $outbox,
    ) {}

    /** @param array<string, scalar|null> $variables */
    public function enqueueTemplate(int $companyId, string $templateKey, string $channel, string $recipient, array $variables, ?string $idempotencyKey = null): int
    {
        $channel = strtolower(trim($channel));
        $template = $this->templates->activeVersion($companyId, $templateKey, $channel);

        return $this->enqueueRaw(
            $companyId,
            $template->templateId,
            $channel,
            $recipient,
            $template->subject === null ? null : $this->templates->render($template->subject, $variables),
            $this->templates->render($template->body, $variables),
            $variables,
            $idempotencyKey,
            $template->version,
        );
    }

    /** @param array<string, mixed>|null $context */
    public function enqueueRaw(int $companyId, ?int $templateId, string $channel, string $recipient, ?string $subject, string $body, ?array $context = null, ?string $idempotencyKey = null, ?int $templateVersion = null): int
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            throw new DomainException('Unsupported notification channel.');
        }
        $recipient = trim($recipient);
        if ($recipient === '' || $body === '') {
            throw new DomainException('Notification recipient and body are required.');
        }
        $idempotencyKey ??= (string) Str::uuid();
        if (! Str::isUuid($idempotencyKey)) {
            throw new DomainException('Notification idempotency key must be a UUID.');
        }
        $contextJson = $context === null ? null : json_encode($context, JSON_THROW_ON_ERROR);

        /** @var array{id:int,is_new:bool} $result */
        $result = DB::transaction(function () use ($companyId, $templateId, $templateVersion, $idempotencyKey, $channel, $recipient, $subject, $body, $context, $contextJson): array {
            DB::table('notifications')->insertOrIgnore([
                'company_id' => $companyId,
                'idempotency_key' => $idempotencyKey,
                'template_id' => $templateId,
                'template_version' => $templateVersion,
                'subject' => $subject,
                'body' => $body,
                'context' => $contextJson,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $notification = DB::table('notifications')
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($notification === null) {
                throw new RuntimeException('Notification could not be persisted.');
            }
            $this->assertSnapshotMatches($notification, $templateId, $templateVersion, $subject, $body, $context);

            $inserted = DB::table('notification_deliveries')->insertOrIgnore([
                'company_id' => $companyId,
                'notification_id' => $notification->id,
                'template_id' => $templateId,
                'template_version' => $templateVersion,
                'idempotency_key' => $idempotencyKey,
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'context' => $contextJson,
                'status' => 'queued',
                'attempts' => 0,
                'dispatch_attempts' => 0,
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $delivery = DB::table('notification_deliveries')
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($delivery === null) {
                throw new RuntimeException('Notification delivery could not be persisted.');
            }
            $existingContext = $this->decodeContext($delivery->context);
            if (
                (int) $delivery->notification_id !== (int) $notification->id
                || ($delivery->template_id === null ? null : (int) $delivery->template_id) !== $templateId
                || ($delivery->template_version === null ? null : (int) $delivery->template_version) !== $templateVersion
                || (string) $delivery->channel !== $channel
                || (string) $delivery->recipient !== $recipient
                || ($delivery->subject === null ? null : (string) $delivery->subject) !== $subject
                || (string) $delivery->body !== $body
                || $existingContext !== $context
            ) {
                throw new DomainException('Notification idempotency payload drift detected.');
            }

            if ($inserted > 0) {
                $this->scheduleDispatch($companyId, (int) $delivery->id, $idempotencyKey, 1, now());
            }

            return ['id' => (int) $delivery->id, 'is_new' => $inserted > 0];
        });

        if ($result['is_new']) {
            RelayOutbox::dispatch();
        }

        return $result['id'];
    }

    public function manualRetry(int $companyId, int $deliveryId, bool $confirmAmbiguous = false): void
    {
        $scheduled = DB::transaction(function () use ($companyId, $deliveryId, $confirmAmbiguous): bool {
            $delivery = DB::table('notification_deliveries')
                ->where('company_id', $companyId)
                ->where('id', $deliveryId)
                ->lockForUpdate()
                ->first();
            if ($delivery === null) {
                throw new DomainException('Notification delivery not found.');
            }
            $status = (string) $delivery->status;
            if (! in_array($status, ['failed', 'ambiguous'], true)) {
                throw new DomainException('Only failed or ambiguous notification deliveries can be retried manually.');
            }
            if ($status === 'ambiguous' && ! $confirmAmbiguous) {
                throw new DomainException('Ambiguous delivery requires explicit duplicate-risk confirmation before retry.');
            }
            if ($status === 'failed' && ! (bool) $delivery->manual_retry_required) {
                throw new DomainException('This delivery already has an automatic retry policy.');
            }

            $sequence = (int) $delivery->dispatch_attempts + 1;
            DB::table('notification_deliveries')->where('id', $deliveryId)->update([
                'status' => 'queued',
                'failure_class' => null,
                'manual_retry_required' => false,
                'last_error' => null,
                'available_at' => now(),
                'updated_at' => now(),
            ]);
            $this->scheduleDispatch($companyId, $deliveryId, (string) $delivery->idempotency_key, $sequence, now());

            return true;
        });

        if ($scheduled) {
            RelayOutbox::dispatch();
        }
    }

    public function deliver(int $deliveryId): void
    {
        /** @var array{attempt_id:int,attempt_no:int,dispatch_sequence:int,company_id:int,channel:string,recipient:string,subject:?string,body:string,idempotency_key:string,provider:string,endpoint:?string,credentials:array<string,mixed>}|null $claim */
        $claim = DB::transaction(function () use ($deliveryId): ?array {
            $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
            if ($delivery === null || in_array((string) $delivery->status, ['sending', 'sent', 'ambiguous', 'cancelled'], true)) {
                return null;
            }
            if (! in_array((string) $delivery->status, ['queued', 'failed'], true)) {
                throw new DomainException('Notification delivery cannot execute from current status.');
            }
            if ($delivery->available_at !== null && now()->lt(Carbon::parse((string) $delivery->available_at))) {
                return null;
            }

            $channel = (string) $delivery->channel;
            $dispatchSequence = (int) $delivery->dispatch_attempts + 1;
            $runtime = $this->runtimePolicy((int) $delivery->company_id, $channel);
            if ($runtime instanceof NotificationDeliveryException) {
                DB::table('notification_deliveries')->where('id', $deliveryId)->update([
                    'status' => 'failed',
                    'dispatch_attempts' => $dispatchSequence,
                    'failure_class' => $runtime->failureClass,
                    'manual_retry_required' => true,
                    'last_attempt_at' => now(),
                    'last_error' => mb_substr($runtime->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);

                return null;
            }

            $limit = $runtime['rate_limit'];
            if ($limit !== null && $this->isRateLimited((int) $delivery->company_id, $channel, $runtime['provider'], $limit)) {
                $availableAt = now()->addMinute();
                DB::table('notification_deliveries')->where('id', $deliveryId)->update([
                    'status' => 'failed',
                    'dispatch_attempts' => $dispatchSequence,
                    'failure_class' => 'rate_limited',
                    'manual_retry_required' => false,
                    'available_at' => $availableAt,
                    'last_attempt_at' => now(),
                    'last_error' => 'Provider rate limit deferred this notification delivery.',
                    'updated_at' => now(),
                ]);
                $this->scheduleDispatch((int) $delivery->company_id, $deliveryId, (string) $delivery->idempotency_key, $dispatchSequence + 1, $availableAt);

                return null;
            }

            $attemptNo = (int) $delivery->attempts + 1;
            $attemptId = (int) DB::table('notification_provider_attempts')->insertGetId([
                'company_id' => $delivery->company_id,
                'delivery_id' => $deliveryId,
                'attempt_no' => $attemptNo,
                'provider' => $runtime['provider'],
                'status' => 'sending',
                'request_meta' => json_encode([
                    'channel' => $channel,
                    'recipient_sha256' => hash('sha256', (string) $delivery->recipient),
                    'idempotency_key_sha256' => hash('sha256', (string) $delivery->idempotency_key),
                ], JSON_THROW_ON_ERROR),
                'retryable' => false,
                'started_at' => now(),
            ]);
            DB::table('notification_deliveries')->where('id', $deliveryId)->update([
                'status' => 'sending',
                'attempts' => $attemptNo,
                'dispatch_attempts' => $dispatchSequence,
                'failure_class' => null,
                'manual_retry_required' => false,
                'last_attempt_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return [
                'attempt_id' => $attemptId,
                'attempt_no' => $attemptNo,
                'dispatch_sequence' => $dispatchSequence,
                'company_id' => (int) $delivery->company_id,
                'channel' => $channel,
                'recipient' => (string) $delivery->recipient,
                'subject' => $delivery->subject === null ? null : (string) $delivery->subject,
                'body' => (string) $delivery->body,
                'idempotency_key' => (string) $delivery->idempotency_key,
                'provider' => $runtime['provider'],
                'endpoint' => $runtime['endpoint'],
                'credentials' => $runtime['credentials'],
            ];
        });
        if ($claim === null) {
            return;
        }

        try {
            $providerResult = match ($claim['channel']) {
                'email' => $this->sendEmail($claim['recipient'], $claim['subject'], $claim['body']),
                'sms', 'whatsapp' => $this->sendHttpChannel(
                    $claim['channel'],
                    $claim['provider'],
                    $claim['endpoint'],
                    $claim['credentials'],
                    $claim['recipient'],
                    $claim['body'],
                    $claim['idempotency_key'],
                ),
                default => throw new RuntimeException('Unknown notification channel.'),
            };
            DB::transaction(function () use ($deliveryId, $claim, $providerResult): void {
                DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'sending')->update([
                    'provider' => $claim['provider'],
                    'provider_message_id' => $providerResult['message_id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'failure_class' => null,
                    'manual_retry_required' => false,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
                DB::table('notification_provider_attempts')->where('id', $claim['attempt_id'])->where('status', 'sending')->update([
                    'status' => 'succeeded',
                    'response_meta' => json_encode(['message_id' => $providerResult['message_id']], JSON_THROW_ON_ERROR),
                    'retryable' => false,
                    'failure_class' => null,
                    'finished_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->recordFailure($deliveryId, $claim, $exception);
        }
    }

    /** @param object $snapshot */
    private function assertSnapshotMatches(object $snapshot, ?int $templateId, ?int $templateVersion, ?string $subject, string $body, ?array $context): void
    {
        if (
            ($snapshot->template_id === null ? null : (int) $snapshot->template_id) !== $templateId
            || ($snapshot->template_version === null ? null : (int) $snapshot->template_version) !== $templateVersion
            || ($snapshot->subject === null ? null : (string) $snapshot->subject) !== $subject
            || (string) $snapshot->body !== $body
            || $this->decodeContext($snapshot->context) !== $context
        ) {
            throw new DomainException('Notification idempotency payload drift detected.');
        }
    }

    /** @return array<string, mixed>|null */
    private function decodeContext(mixed $context): ?array
    {
        if ($context === null) {
            return null;
        }
        $decoded = json_decode((string) $context, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array{provider:string,endpoint:?string,credentials:array<string,mixed>,rate_limit:?int}|NotificationDeliveryException */
    private function runtimePolicy(int $companyId, string $channel): array|NotificationDeliveryException
    {
        $runtime = $this->integrations->runtime($companyId, $channel);
        if ($channel === 'email' && $runtime === null) {
            return [
                'provider' => (string) config('mail.default', 'mail'),
                'endpoint' => null,
                'credentials' => [],
                'rate_limit' => null,
            ];
        }
        if (! $runtime instanceof SystemIntegrationRuntime) {
            return new NotificationDeliveryException('Notification provider is not configured.', 'provider_unconfigured', false, manualRetryRequired: true);
        }
        if (! $runtime->isEnabled) {
            return new NotificationDeliveryException('Notification provider is disabled by kill-switch.', 'provider_disabled', false, manualRetryRequired: true);
        }
        if (! $runtime->isConfigurationValidated()) {
            return new NotificationDeliveryException('Notification provider configuration has not been validated.', 'provider_unvalidated', false, manualRetryRequired: true);
        }

        if ($channel === 'email') {
            return [
                'provider' => (string) config('mail.default', 'mail'),
                'endpoint' => null,
                'credentials' => $runtime->credentials,
                'rate_limit' => $runtime->rateLimitPerMinute(),
            ];
        }
        if ($runtime->providerKey === null || $runtime->endpointUrl === null) {
            return new NotificationDeliveryException('Notification provider endpoint or key is missing.', 'provider_unconfigured', false, manualRetryRequired: true);
        }

        return [
            'provider' => $runtime->providerKey,
            'endpoint' => $runtime->endpointUrl,
            'credentials' => $runtime->credentials,
            'rate_limit' => $runtime->rateLimitPerMinute(),
        ];
    }

    private function isRateLimited(int $companyId, string $channel, string $provider, int $limit): bool
    {
        $lockKey = crc32('m20-notification-rate:'.$companyId.':'.$channel.':'.$provider);
        DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
        $attempts = DB::table('notification_provider_attempts as attempt')
            ->join('notification_deliveries as delivery', 'delivery.id', '=', 'attempt.delivery_id')
            ->where('attempt.company_id', $companyId)
            ->where('attempt.provider', $provider)
            ->where('delivery.channel', $channel)
            ->where('attempt.started_at', '>=', now()->subMinute())
            ->count();

        return $attempts >= $limit;
    }

    /**
     * @param array{attempt_id:int,attempt_no:int,dispatch_sequence:int,company_id:int,channel:string,recipient:string,subject:?string,body:string,idempotency_key:string,provider:string,endpoint:?string,credentials:array<string,mixed>} $claim
     */
    private function recordFailure(int $deliveryId, array $claim, Throwable $exception): void
    {
        $failure = $exception instanceof NotificationDeliveryException
            ? $exception
            : new NotificationDeliveryException(
                'Provider outcome is ambiguous; automatic retry is blocked.',
                'ambiguous_transport',
                false,
                ambiguous: true,
                manualRetryRequired: true,
            );
        $error = $this->redactError($failure->getMessage(), $claim['credentials']);

        DB::transaction(function () use ($deliveryId, $claim, $failure, $error): void {
            $canRetry = $failure->retryable && ! $failure->ambiguous && $claim['attempt_no'] < self::MAX_PROVIDER_ATTEMPTS;
            $status = $failure->ambiguous ? 'ambiguous' : 'failed';
            $manual = $failure->manualRetryRequired || $failure->ambiguous || ($failure->retryable && ! $canRetry);
            $availableAt = $canRetry
                ? now()->addSeconds($failure->retryAfterSeconds ?? $this->backoffSeconds($claim['attempt_no']))
                : now();

            DB::table('notification_provider_attempts')->where('id', $claim['attempt_id'])->where('status', 'sending')->update([
                'status' => $failure->ambiguous ? 'ambiguous' : 'failed',
                'retryable' => $failure->retryable,
                'failure_class' => $failure->failureClass,
                'error' => mb_substr($error, 0, 4000),
                'finished_at' => now(),
            ]);
            DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'sending')->update([
                'status' => $status,
                'failure_class' => $failure->failureClass,
                'manual_retry_required' => $manual,
                'available_at' => $availableAt,
                'last_error' => mb_substr($error, 0, 4000),
                'updated_at' => now(),
            ]);

            if ($canRetry) {
                $this->scheduleDispatch(
                    $claim['company_id'],
                    $deliveryId,
                    $claim['idempotency_key'],
                    $claim['dispatch_sequence'] + 1,
                    $availableAt,
                );
            }
        });
    }

    private function scheduleDispatch(int $companyId, int $deliveryId, string $idempotencyKey, int $sequence, DateTimeInterface $availableAt): void
    {
        $append = $this->outbox->append(new OutboxMessageDraft(
            effectKey: 'notification.delivery:'.$deliveryId.':dispatch:'.$sequence,
            eventName: OutboxEventCatalog::NOTIFICATION_DELIVERY_REQUESTED_V1,
            payload: ['delivery_id' => $deliveryId],
            correlationId: $idempotencyKey,
            companyId: $companyId,
            sourceType: 'notification.delivery',
            sourceId: (string) $deliveryId,
            sourceVersion: $sequence,
        ));
        DB::table('outbox_messages')
            ->where('id', $append->recordId)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['available_at' => $availableAt, 'updated_at' => now()]);
    }

    /** @return array{message_id:?string} */
    private function sendEmail(string $recipient, ?string $subject, string $body): array
    {
        Mail::raw($body, function ($message) use ($recipient, $subject): void {
            $message->to($recipient);
            if ($subject !== null && $subject !== '') {
                $message->subject($subject);
            }
        });

        return ['message_id' => null];
    }

    /**
     * @param array<string, mixed> $credentials
     * @return array{message_id:?string}
     */
    private function sendHttpChannel(string $channel, string $provider, ?string $endpoint, array $credentials, string $recipient, string $body, string $idempotencyKey): array
    {
        if ($endpoint === null || $endpoint === '') {
            throw new NotificationDeliveryException('Notification provider endpoint is missing.', 'provider_unconfigured', false, manualRetryRequired: true);
        }

        $request = Http::acceptJson()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->connectTimeout(5)
            ->timeout(20);
        $token = $credentials['token'] ?? $credentials['api_token'] ?? null;
        if (is_scalar($token) && (string) $token !== '') {
            $request = $request->withToken((string) $token);
        }
        $apiKey = $credentials['api_key'] ?? null;
        if (is_scalar($apiKey) && (string) $apiKey !== '') {
            $request = $request->withHeaders(['X-API-Key' => (string) $apiKey]);
        }

        try {
            $response = $request->post($endpoint, ['to' => $recipient, 'message' => $body]);
        } catch (ConnectionException $exception) {
            throw new NotificationDeliveryException(
                strtoupper($channel).' provider transport result is ambiguous.',
                'ambiguous_transport',
                false,
                ambiguous: true,
                manualRetryRequired: true,
                previous: $exception,
            );
        }

        if ($response->status() === 429) {
            throw new NotificationDeliveryException(
                strtoupper($channel).' provider rate-limited the request.',
                'provider_http_429',
                true,
                retryAfterSeconds: 60,
            );
        }
        if ($response->serverError()) {
            throw new NotificationDeliveryException(
                strtoupper($channel).' provider rejected the request with a server error.',
                'provider_http_5xx',
                true,
            );
        }
        if (! $response->successful()) {
            throw new NotificationDeliveryException(
                strtoupper($channel).' provider rejected the request.',
                'provider_http_4xx',
                false,
                manualRetryRequired: true,
            );
        }
        $data = $response->json();
        $messageId = is_array($data) ? ($data['id'] ?? $data['message_id'] ?? null) : null;

        return ['message_id' => $messageId === null ? null : (string) $messageId];
    }

    /** @param array<string, mixed> $credentials */
    private function redactError(string $message, array $credentials): string
    {
        foreach ($credentials as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $message = str_replace((string) $value, '[redacted]', $message);
            }
        }

        return $message;
    }

    private function backoffSeconds(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 60,
            $attempt === 2 => 300,
            $attempt === 3 => 900,
            default => 3600,
        };
    }
}
