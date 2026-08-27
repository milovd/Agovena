<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 1800, 7200];

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $claimed = WebhookDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereIn('status', ['queued', 'retrying'])
            ->update(['status' => 'in_progress']);
        if ($claimed !== 1) {
            return;
        }

        $delivery = WebhookDelivery::query()->with('endpoint')->findOrFail($this->deliveryId);
        $endpoint = $delivery->endpoint;

        if ($endpoint === null) {
            return;
        }

        if (! $endpoint->active) {
            $delivery->update(['status' => 'skipped']);

            return;
        }

        $delivery->increment('attempt_count');
        $delivery->refresh();

        if (! WebhookUrlValidator::isAllowed($endpoint->url)) {
            $this->recordFailure($delivery, 'Endpoint URL rejected by SSRF policy.', false, 'ssrf_rejected');

            return;
        }

        $body = json_encode(WebhookPayloadFormatter::format($endpoint->destination ?? 'http', $delivery->payload), JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $response = null;

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Agovena-Event' => $delivery->event_type,
                    'X-Agovena-Delivery' => $delivery->delivery_id,
                    'X-Agovena-Timestamp' => (string) $timestamp,
                    'X-Agovena-Signature' => WebhookSigner::sign($endpoint->secret, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (Throwable) {
            $this->recordFailure($delivery, 'Webhook request failed before a response was received.', true, 'transport_error');

            return;
        }

        if ($response->successful()) {
            $delivery->update([
                'status' => 'delivered',
                'response_status' => $response->status(),
                'response_body' => $this->truncate($response),
                'last_error' => null,
                'next_attempt_at' => null,
                'delivered_at' => now(),
            ]);
            $endpoint->update(['failure_count' => 0, 'last_delivered_at' => now()]);

            return;
        }

        $retryable = $response->status() === 429 || $response->serverError();
        $this->recordFailure(
            $delivery,
            'Webhook endpoint returned HTTP '.$response->status().'.',
            $retryable,
            'http_'.$response->status(),
            $response,
        );

        if ($retryable) {
            return;
        }

    }

    public function failed(Throwable $exception): void
    {
        WebhookDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'dead_letter',
            'failure_code' => 'retry_exhausted',
            'failed_at' => now(),
            'dead_lettered_at' => now(),
            'last_error' => 'Webhook delivery exhausted retries.',
        ]);
    }

    private function recordFailure(
        WebhookDelivery $delivery,
        string $error,
        bool $retryable,
        string $failureCode,
        ?Response $response = null,
    ): void {
        $endpoint = $delivery->endpoint;
        $attempt = (int) $delivery->attempt_count;
        $exhausted = ! $retryable || $attempt >= $this->tries;

        $delivery->update([
            'status' => $exhausted ? 'dead_letter' : 'retrying',
            'response_status' => $response?->status(),
            'response_body' => $response === null ? null : $this->truncate($response),
            'failure_code' => $failureCode,
            'last_error' => $error,
            'failed_at' => $exhausted ? now() : null,
            'dead_lettered_at' => $exhausted ? now() : null,
            'next_attempt_at' => $exhausted ? null : now()->addSeconds($this->backoff[min($attempt - 1, count($this->backoff) - 1)]),
        ]);

        $endpoint?->increment('failure_count');
        $endpoint?->update(['last_failure_at' => now()]);
    }

    private function truncate(Response $response): string
    {
        return mb_substr($response->body(), 0, 2000);
    }
}
