<?php

namespace App\Modules\Operations;

use App\Modules\Communication\NotificationTemplateService;
use App\Modules\Operations\Jobs\DeliverNotification;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

final class NotificationService
{
    public function __construct(private readonly NotificationTemplateService $templates) {}

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

        return DB::transaction(function () use ($companyId, $templateId, $idempotencyKey, $channel, $recipient, $subject, $body, $context, $contextJson, $templateVersion): int {
            $inserted = DB::table('notification_deliveries')->insertOrIgnore([
                'company_id' => $companyId,
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
            $existingContext = $delivery->context === null ? null : json_decode((string) $delivery->context, true, flags: JSON_THROW_ON_ERROR);
            if (
                ($delivery->template_id === null ? null : (int) $delivery->template_id) !== $templateId
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
                DeliverNotification::dispatch((int) $delivery->id)->afterCommit();
            }

            return (int) $delivery->id;
        });
    }

    public function deliver(int $deliveryId): void
    {
        /** @var array{attempt_id:int,company_id:int,channel:string,recipient:string,subject:?string,body:string,idempotency_key:string}|null $claim */
        $claim = DB::transaction(function () use ($deliveryId): ?array {
            $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
            if ($delivery === null || in_array((string) $delivery->status, ['sending', 'sent', 'cancelled'], true)) {
                return null;
            }
            if (! in_array((string) $delivery->status, ['queued', 'failed'], true)) {
                throw new DomainException('Notification delivery cannot execute from current status.');
            }
            $attemptNo = (int) $delivery->attempts + 1;
            $channel = (string) $delivery->channel;
            $attemptId = (int) DB::table('notification_provider_attempts')->insertGetId([
                'company_id' => $delivery->company_id,
                'delivery_id' => $deliveryId,
                'attempt_no' => $attemptNo,
                'provider' => $this->providerForChannel($channel),
                'status' => 'sending',
                'request_meta' => json_encode([
                    'channel' => $channel,
                    'recipient_sha256' => hash('sha256', (string) $delivery->recipient),
                ], JSON_THROW_ON_ERROR),
                'started_at' => now(),
            ]);
            DB::table('notification_deliveries')->where('id', $deliveryId)->update([
                'status' => 'sending',
                'attempts' => $attemptNo,
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return [
                'attempt_id' => $attemptId,
                'company_id' => (int) $delivery->company_id,
                'channel' => $channel,
                'recipient' => (string) $delivery->recipient,
                'subject' => $delivery->subject === null ? null : (string) $delivery->subject,
                'body' => (string) $delivery->body,
                'idempotency_key' => (string) $delivery->idempotency_key,
            ];
        });
        if ($claim === null) {
            return;
        }

        try {
            $provider = match ($claim['channel']) {
                'email' => $this->sendEmail($claim['recipient'], $claim['subject'], $claim['body']),
                'sms' => $this->sendHttpChannel('sms', $claim['recipient'], $claim['body'], $claim['idempotency_key']),
                'whatsapp' => $this->sendHttpChannel('whatsapp', $claim['recipient'], $claim['body'], $claim['idempotency_key']),
                default => throw new RuntimeException('Unknown notification channel.'),
            };
            DB::transaction(function () use ($deliveryId, $claim, $provider): void {
                DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'sending')->update([
                    'provider' => $provider['provider'],
                    'provider_message_id' => $provider['message_id'],
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
                DB::table('notification_provider_attempts')->where('id', $claim['attempt_id'])->update([
                    'provider' => $provider['provider'],
                    'status' => 'succeeded',
                    'response_meta' => json_encode(['message_id' => $provider['message_id']], JSON_THROW_ON_ERROR),
                    'finished_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($deliveryId, $claim, $exception): void {
                DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'sending')->update([
                    'status' => 'failed',
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
                DB::table('notification_provider_attempts')->where('id', $claim['attempt_id'])->update([
                    'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 4000),
                    'finished_at' => now(),
                ]);
            });
            throw $exception;
        }
    }

    /** @return array{provider:string,message_id:?string} */
    private function sendEmail(string $recipient, ?string $subject, string $body): array
    {
        Mail::raw($body, function ($message) use ($recipient, $subject): void {
            $message->to($recipient);
            if ($subject !== null && $subject !== '') {
                $message->subject($subject);
            }
        });

        return ['provider' => (string) config('mail.default', 'mail'), 'message_id' => null];
    }

    /** @return array{provider:string,message_id:?string} */
    private function sendHttpChannel(string $channel, string $recipient, string $body, string $idempotencyKey): array
    {
        $endpoint = config('m11.notifications.'.$channel.'.endpoint');
        $token = config('m11.notifications.'.$channel.'.token');
        if (! is_string($endpoint) || trim($endpoint) === '') {
            throw new RuntimeException(strtoupper($channel).' endpoint is not configured.');
        }
        $request = Http::acceptJson()->withHeaders(['Idempotency-Key' => $idempotencyKey])->timeout(20);
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }
        $response = $request->post($endpoint, ['to' => $recipient, 'message' => $body]);
        if (! $response->successful()) {
            throw new RuntimeException(strtoupper($channel).' provider returned HTTP '.$response->status().'.');
        }
        $data = $response->json();
        $messageId = is_array($data) ? ($data['id'] ?? $data['message_id'] ?? null) : null;

        return ['provider' => $channel, 'message_id' => $messageId === null ? null : (string) $messageId];
    }

    private function providerForChannel(string $channel): string
    {
        return match ($channel) {
            'email' => (string) config('mail.default', 'mail'),
            'sms', 'whatsapp' => $channel,
            default => 'unknown',
        };
    }
}
