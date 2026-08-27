<?php

declare(strict_types=1);

/**
 * Multi-process race worker. Args: action payloadJson envJsonPath
 */

use Agovena\Modules\Events\EventService;
use Agovena\Modules\Provisioning\Jobs\ProvisionServiceInstance;
use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Agovena\Invoices\IssueCreditNote;
use App\Agovena\Modules\ModuleManager;
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

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Feature tests skip package boot in AgovenaServiceProvider; workers need the
// same enabled modules/extensions visible in the shared MariaDB database.
$app->make(ModuleManager::class)->bootEnabled();
$app->make(ExtensionManager::class)->bootEnabled();

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
        case 'role-sync':
            $actor = User::query()->findOrFail((int) $payload['actor_id']);
            $target = User::query()->findOrFail((int) $payload['target_id']);
            app(AdminRoleAssignmentPolicy::class)->syncRoles(
                $actor,
                $target,
                array_values(array_filter($payload['roles'] ?? [], 'is_string')),
                'roles',
            );
            $result['ok'] = true;
            break;
        case 'import':
            $run = app(ImportExecutor::class)->run(
                (string) $payload['path'],
                app(ImportAdapterRegistry::class)->for((string) $payload['source'], (string) $payload['entity']),
                (string) $payload['source'],
            );
            $result['ok'] = true;
            $result['imported'] = $run->rows()->where('status', 'imported')->count();
            $result['duplicates'] = $run->rows()->where('status', 'duplicate')->count();
            $result['errors'] = $run->errors;
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
