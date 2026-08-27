<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\ImportIdentityReservation;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ImportRollback
{
    public function handle(ImportRun $run): ImportRun
    {
        if ($run->status !== 'completed') {
            throw new RuntimeException('Only completed import runs can be rolled back.');
        }

        DB::transaction(function () use ($run): void {
            foreach ($run->rows()->where('status', 'imported')->orderByDesc('id')->get() as $row) {
                $this->deleteImportedModel((string) $row->imported_model_type, (int) $row->imported_model_id);
                $row->update(['status' => 'rolled_back']);
            }

            ImportIdentityReservation::query()
                ->where('import_run_id', $run->id)
                ->delete();

            $run->update([
                'status' => 'rolled_back',
                'completed_at' => now(),
            ]);
        });

        return $run->fresh();
    }

    private function deleteImportedModel(string $type, int $id): void
    {
        if ($type === User::class) {
            $user = User::query()->find($id);
            if ($user === null) {
                return;
            }

            $customer = $user->customer;
            if ($customer instanceof Customer && (
                $customer->orders()->exists()
                || $customer->addresses()->exists()
                || $customer->propertyValues()->exists()
                || $customer->tickets()->exists()
                || $customer->creditEntries()->exists()
                || $customer->creditAccount()->exists()
                || $customer->invoices()->exists()
                || $customer->creditNotes()->exists()
                || $customer->referralCodes()->exists()
                || $customer->referralAttributions()->exists()
            )) {
                throw new RuntimeException('Imported customer has dependent records and cannot be rolled back safely.');
            }
            $customer?->delete();
            $user->delete();

            return;
        }

        if ($type === Product::class) {
            $product = Product::query()->find($id);
            if ($product !== null && (
                $product->isReferencedByOrders()
                || $product->images()->exists()
                || $product->capabilities()->exists()
                || $product->purchaseOptions()->exists()
                || $product->currencyPrices()->exists()
                || DB::table('product_plan_changes')
                    ->where('from_product_id', $product->id)
                    ->orWhere('to_product_id', $product->id)
                    ->exists()
                || DB::table('product_plan_change_requests')
                    ->where('from_product_id', $product->id)
                    ->orWhere('to_product_id', $product->id)
                    ->exists()
            )) {
                throw new RuntimeException('Imported product has dependent records and cannot be rolled back safely.');
            }
            $product?->delete();

            return;
        }

        if ($type === ProductImage::class) {
            ProductImage::query()->find($id)?->delete();

            return;
        }

        if ($type === DiscountCode::class) {
            $discount = DiscountCode::query()->find($id);
            if ($discount === null) {
                return;
            }
            if ($discount->redemptions()->exists()) {
                throw new RuntimeException('Imported discount code has redemptions and cannot be rolled back safely.');
            }
            $discount->delete();

            return;
        }

        if ($type === Invoice::class) {
            $invoice = Invoice::query()->lockForUpdate()->find($id);
            if ($invoice === null) {
                return;
            }
            if (! $invoice->canVoid() || $invoice->refunds()->exists()) {
                throw new RuntimeException('Imported invoice is financially active and cannot be rolled back safely.');
            }
            $invoice->status = InvoiceStatus::Void;
            $invoice->saveQuietly();

            return;
        }

        if ($type === Payment::class) {
            $payment = Payment::query()->lockForUpdate()->find($id);
            if ($payment === null) {
                return;
            }
            if (
                $payment->refunds()->exists()
                || $payment->attempts()->exists()
                || in_array($payment->status, [
                    PaymentStatus::Paid,
                    PaymentStatus::PartiallyRefunded,
                    PaymentStatus::Refunded,
                ], true)
            ) {
                throw new RuntimeException('Imported payment has financial history and cannot be rolled back safely.');
            }
            $payment->delete();

            return;
        }

        if ($type === Order::class) {
            throw new RuntimeException('Imported orders are not rollbackable because their numbered and fulfillment records are retained.');
        }

        $subscriptionClass = 'Agovena\\Modules\\Subscriptions\\Models\\Subscription';
        if ($type === $subscriptionClass) {
            throw new RuntimeException('Imported subscriptions are not rollbackable because their service history must be retained.');
        }

        $serviceClass = 'Agovena\\Modules\\Provisioning\\Models\\ServiceInstance';
        if ($type === $serviceClass && class_exists($serviceClass)) {
            $service = $serviceClass::query()->lockForUpdate()->find($id);
            if ($service === null) {
                return;
            }

            $status = $service->getAttribute('status');
            $statusValue = $status instanceof BackedEnum ? $status->value : (string) $status;
            if (
                $statusValue !== 'pending'
                || $service->getAttribute('order_id') !== null
                || $service->getAttribute('order_item_id') !== null
                || $service->getAttribute('subscription_id') !== null
                || $service->getAttribute('external_ref') !== null
                || $service->getAttribute('provisioning_server_id') !== null
                || $service->getAttribute('provisioning_at') !== null
                || $service->getAttribute('activated_at') !== null
                || $service->getAttribute('suspended_at') !== null
                || $service->getAttribute('terminated_at') !== null
                || $service->getAttribute('failed_at') !== null
                || $service->getAttribute('failure_message') !== null
                || ($service->getAttribute('customer_id') !== null && ! $service->customer()->exists())
                || ($service->getAttribute('product_id') !== null && ! $service->product()->exists())
            ) {
                throw new RuntimeException('Imported service instance has dependencies or lifecycle history and cannot be rolled back safely.');
            }

            $service->delete();

            return;
        }

        throw new RuntimeException('Import rollback encountered an unsupported model type.');
    }
}
