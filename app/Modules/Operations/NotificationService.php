<?php

namespace App\Modules\Operations;

use App\Modules\Operations\Jobs\DeliverNotification;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

final class NotificationService
{
    /** @param array<string,scalar|null> $variables */
    public function enqueueTemplate(int $companyId, string $templateKey, string $channel, string $recipient, array $variables, ?string $idempotencyKey = null): int
    {
        $channel = strtolower(trim($channel));
        $template = DB::table('notification_templates')
            ->where('company_id', $companyId)
            ->where('key', $templateKey)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->first();
        if ($template === null) {
            throw new DomainException('Active notification template not found.');
        }

        return $this->enqueueRaw(
            $companyId,
            (int) $template->id,
            $channel,
            $recipient,
            $template->subject === null ? null : $this->render((string) $template->subject, $variables),
            $this->render((string) $template->body, $variables),
            $variables,
            $idempotencyKey,
        );
    }

    /** @param array<string,mixed>|null $context */
    public function enqueueRaw(int $companyId, ?int $templateId, string $channel, string $recipient, ?string $subject, string $body, ?array $context = null, ?string $idempotencyKey = null): int
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

        return DB::transaction(function () use ($companyId, $templateId, $idempotencyKey, $channel, $recipient, $subject, $body, $context, $contextJson): int {
            $inserted = DB::table('notification_deliveries')->insertOrIgnore([
                'company_id' => $companyId,
                'template_id' => $templateId,
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
            $existingContext = $delivery->context === null
                ? null
                : json_decode((string) $delivery->context, true, flags: JSON_THROW_ON_ERROR);
            if (
                ($delivery->template_id === null ? null : (int) $delivery->template_id) !== $templateId
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
        $delivery = DB::transaction(function () use ($deliveryId): ?object {
            $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
            if ($delivery === null || in_array((string) $delivery->status, ['sending', 'sent', 'cancelled'], true)) {
                return null;
            }
            if (! in_array((string) $delivery->status, ['queued', 'failed'], true)) {
                throw new DomainException('Notification delivery cannot execute from current status.');
            }
            DB::table('notification_deliveries')->where('id', $deliveryId)->update([
                'status' => 'sending',
                'attempts' => (int) $delivery->attempts + 1,
                'last_error' => null,
                'updated_at' => now(),
            ]);

            return $delivery;
        });
        if ($delivery === null) {
            return;
        }

        try {
            $provider = match ((string) $delivery->channel) {
                'email' => $this->sendEmail($delivery),
                'sms' => $this->sendHttpChannel('sms', $delivery),
                'whatsapp' => $this->sendHttpChannel('whatsapp', $delivery),
                default => throw new RuntimeException('Unknown notification channel.'),
            };
            DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'sending')->update([
                'provider' => $provider['provider'],
                'provider_message_id' => $provider['message_id'],
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('notification_deliveries')->where('id', $deliveryId)->where('status', 'sending')->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                'updated_at' => now(),
            ]);
            throw $exception;
        }
    }

    /** @param array<string,scalar|null> $variables */
    private function render(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{'.$key.'}}'] = $value === null ? '' : (string) $value;
        }

        return strtr($template, $replace);
    }

    /** @return array{provider:string,message_id:?string} */
    private function sendEmail(object $delivery): array
    {
        Mail::raw((string) $delivery->body, function ($message) use ($delivery): void {
            $message->to((string) $delivery->recipient);
            if ($delivery->subject !== null && (string) $delivery->subject !== '') {
                $message->subject((string) $delivery->subject);
            }
        });

        return ['provider' => (string) config('mail.default', 'mail'), 'message_id' => null];
    }

    /** @return array{provider:string,message_id:?string} */
    private function sendHttpChannel(string $channel, object $delivery): array
    {
        $endpoint = config('m11.notifications.'.$channel.'.endpoint');
        $token = config('m11.notifications.'.$channel.'.token');
        if (! is_string($endpoint) || trim($endpoint) === '') {
            throw new RuntimeException(strtoupper($channel).' endpoint is not configured.');
        }
        $request = Http::acceptJson()
            ->withHeaders(['Idempotency-Key' => (string) $delivery->idempotency_key])
            ->timeout(20);
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }
        $response = $request->post($endpoint, ['to' => (string) $delivery->recipient, 'message' => (string) $delivery->body]);
        if (! $response->successful()) {
            throw new RuntimeException(strtoupper($channel).' provider returned HTTP '.$response->status().'.');
        }
        $data = $response->json();
        $messageId = is_array($data) ? ($data['id'] ?? $data['message_id'] ?? null) : null;

        return ['provider' => $channel, 'message_id' => $messageId === null ? null : (string) $messageId];
    }
}
