<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use Illuminate\Http\Request;

interface HandlesWebhookValidation
{
    /**
     * Return the provider-required handshake body, or null for a normal webhook.
     *
     * @return array<string, mixed>|null
     */
    public function webhookValidationResponse(Request $request): ?array;
}
