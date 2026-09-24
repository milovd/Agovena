<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Agovena\Payments\Contracts\HandlesWebhookValidation;
use App\Agovena\Payments\HandlePaymentWebhook;
use App\Agovena\Payments\PaymentGatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

final class PaymentWebhookController
{
    public function __invoke(string $gateway, Request $request, HandlePaymentWebhook $handler, PaymentGatewayRegistry $gateways): JsonResponse
    {
        try {
            $registeredGateway = $gateways->get($gateway);
            if ($registeredGateway instanceof HandlesWebhookValidation) {
                $validationResponse = $registeredGateway->webhookValidationResponse($request);
                if ($validationResponse !== null) {
                    return response()->json($validationResponse);
                }
            }

            $result = $handler->handle($gateway, $request);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => 'Webhook processing failed.'], 500);
        }

        return response()->json([
            'ok' => true,
            'id' => $result->event->id,
            'duplicate' => $result->duplicate,
        ]);
    }
}
