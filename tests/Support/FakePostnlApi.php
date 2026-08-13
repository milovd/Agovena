<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Postnl\PostnlApi;
use Agovena\Extensions\Postnl\PostnlProviderException;

final class FakePostnlApi implements PostnlApi
{
    /** @var array<string, array<string, mixed>> */
    public array $shipments = [];

    /** @var array<string, string> */
    public array $idempotency = [];

    public int $barcodeCalls = 0;

    public int $createCalls = 0;

    public int $statusCalls = 0;

    public int $checkoutCalls = 0;

    public bool $timeout = false;

    public bool $unauthorized = false;

    public bool $invalidAddress = false;

    public bool $unsupportedDestination = false;

    public bool $failCreate = false;

    public bool $failLabel = false;

    public bool $failTracking = false;

    public bool $rateUnavailable = false;

    public string $nextStatus = '1';

    public function barcode(array $query): array
    {
        $this->guard();
        $this->barcodeCalls++;

        return ['Barcode' => '3SDEVC'.str_pad((string) $this->barcodeCalls, 9, '0', STR_PAD_LEFT)];
    }

    public function createShipment(array $payload, ?string $idempotencyKey = null): array
    {
        $this->guard();
        if ($this->invalidAddress) {
            throw PostnlProviderException::failed('postnl::messages.errors.invalid_address', 400);
        }
        if ($this->unsupportedDestination) {
            throw PostnlProviderException::failed('postnl::messages.errors.unsupported_destination', 422);
        }
        if ($this->failCreate) {
            throw PostnlProviderException::failed('postnl::messages.errors.create_failed');
        }
        if (is_string($idempotencyKey) && $idempotencyKey !== '' && isset($this->idempotency[$idempotencyKey])) {
            return $this->shipments[$this->idempotency[$idempotencyKey]];
        }

        $this->createCalls++;
        $barcode = (string) ($payload['Shipments'][0]['Barcode'] ?? '3SDEVCFAKE');
        $label = $this->failLabel ? '' : base64_encode("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $created = [
            'ResponseShipments' => [[
                'Barcode' => $barcode,
                'Labels' => [['Content' => $label, 'Labeltype' => 'Label', 'OutputType' => 'PDF']],
            ]],
        ];
        $this->shipments[$barcode] = $created;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $this->idempotency[$idempotencyKey] = $barcode;
        }

        return $created;
    }

    public function status(string $barcode): array
    {
        $this->guard();
        if ($this->failTracking) {
            throw PostnlProviderException::failed('postnl::messages.errors.tracking_failed');
        }
        $this->statusCalls++;

        return [
            'Barcode' => $barcode,
            'Status' => ['PhaseCode' => $this->nextStatus, 'StatusCode' => $this->nextStatus],
        ];
    }

    public function checkout(array $payload): array
    {
        $this->guard();
        if ($this->rateUnavailable) {
            throw PostnlProviderException::failed('postnl::messages.errors.rate_unavailable');
        }
        if ($this->unsupportedDestination) {
            throw PostnlProviderException::failed('postnl::messages.errors.unsupported_destination', 422);
        }
        $this->checkoutCalls++;

        return [[
            'ProductCode' => '3085',
            'Description' => 'Standard parcel NL',
            'Price' => 6.95,
            'DeliveryDays' => 1,
            'DeliveryDate' => '2026-08-15',
        ]];
    }

    private function guard(): void
    {
        if ($this->timeout) {
            throw PostnlProviderException::failed('postnl::messages.errors.timeout');
        }
        if ($this->unauthorized) {
            throw PostnlProviderException::failed('postnl::messages.errors.not_configured', 401);
        }
    }
}
