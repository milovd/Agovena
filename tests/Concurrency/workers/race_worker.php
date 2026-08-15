<?php

declare(strict_types=1);

/**
 * Multi-process race worker. Args: action payloadJson envJsonPath
 */

use Agovena\Modules\Events\EventService;
use Agovena\Modules\Provisioning\Jobs\ProvisionServiceInstance;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Invoices\IssueCreditNote;
use App\Agovena\Payments\RecordRefund;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

$envPath = $argv[3] ?? null;
if (is_string($envPath) && is_file($envPath)) {
    foreach (json_decode((string) file_get_contents($envPath), true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
        $string = is_scalar($value) || $value === null ? (string) $value : '';
        putenv($key.'='.$string);
        $_ENV[$key] = $string;
        $_SERVER[$key] = $string;
    }
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
$payload = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

$result = ['ok' => false, 'error' => null];

try {
    switch ($action) {
        case 'refund':
            $staff = User::query()->findOrFail((int) $payload['staff_id']);
            $payment = Payment::query()->findOrFail((int) $payload['payment_id']);
            app(RecordRefund::class)->handle($payment, $staff, (int) $payload['amount'], (string) $payload['reason']);
            $result['ok'] = true;
            break;
        case 'credit':
            $staff = User::query()->findOrFail((int) $payload['staff_id']);
            $invoice = Invoice::query()->with('items')->findOrFail((int) $payload['invoice_id']);
            app(IssueCreditNote::class)->handle(
                $invoice,
                $staff,
                (string) $payload['reason'],
                $payload['quantities'] ?? null,
            );
            $result['ok'] = true;
            break;
        case 'renewal':
            $now = CarbonImmutable::parse((string) $payload['now']);
            $result['processed'] = app(SubscriptionService::class)->processDue($now);
            $result['ok'] = true;
            break;
        case 'checkin':
            $staff = isset($payload['staff_id']) ? User::query()->find((int) $payload['staff_id']) : null;
            $out = app(EventService::class)->checkIn((string) $payload['code'], $staff);
            $result['ok'] = true;
            $result['already'] = $out['already'];
            break;
        case 'provision-dispatch':
            ProvisionServiceInstance::dispatch((int) $payload['instance_id']);
            $result['ok'] = true;
            break;
        default:
            throw new RuntimeException('unknown action: '.$action);
    }
} catch (ValidationException $e) {
    $result['error'] = 'validation';
    $result['messages'] = $e->errors();
} catch (Throwable $e) {
    $result['error'] = $e::class.': '.$e->getMessage();
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL);
exit($result['ok'] ? 0 : 2);
