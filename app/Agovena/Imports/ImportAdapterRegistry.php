<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Agovena\Imports\Contracts\ImportAdapter;
use InvalidArgumentException;

final class ImportAdapterRegistry
{
    /** @return array<string, list<string>> */
    private function aliases(string $source, string $entity): array
    {
        $sourceAliases = match ($source) {
            'hosting_billing' => [
                'external_id' => ['customer_id', 'id', 'external_id'],
                'email' => ['email', 'email_address'],
                'name' => ['name', 'full_name', 'customer_name'],
            ],
            'billing_platform' => [
                'external_id' => ['userid', 'id', 'external_id'],
                'email' => ['email', 'email_address'],
                'name' => ['fullname', 'name', 'full_name'],
            ],
            'shop_platform' => [
                'external_id' => ['customer_id', 'id', 'external_id'],
                'email' => ['billing_email', 'email'],
                'name' => ['billing_name', 'name', 'full_name'],
            ],
            'commerce_platform' => [
                'external_id' => ['customer_id', 'id', 'external_id'],
                'email' => ['email', 'contact_email'],
                'name' => ['name', 'display_name', 'full_name'],
            ],
            'csv' => [
                'external_id' => ['external_id', 'id', 'customer_id'],
                'email' => ['email', 'email_address', 'contact_email'],
                'name' => ['name', 'full_name', 'display_name'],
            ],
            default => throw new InvalidArgumentException('Unsupported import source.'),
        };

        if ($entity === 'customer') {
            return $sourceAliases;
        }

        return match ($entity) {
            'product' => [
                'external_id' => ['product_id', 'id', 'external_id'],
                'name' => ['product_name', 'name', 'title'],
                'price_amount' => ['price_amount', 'price', 'amount'],
            ],
            'order' => [
                'external_id' => ['order_id', 'id', 'external_id'],
                'customer_external_id' => ['customer_id', 'userid', 'customer_external_id'],
                'total_amount' => ['total_amount', 'total', 'amount'],
                'currency' => ['currency', 'currency_code'],
                'number' => ['order_number', 'number', 'order_no'],
                'items_json' => ['items_json', 'items', 'line_items'],
            ],
            'subscription' => [
                'external_id' => ['subscription_id', 'id', 'external_id'],
                'customer_external_id' => ['customer_id', 'userid', 'customer_external_id'],
                'product_external_id' => ['product_id', 'product_external_id'],
                'status' => ['status', 'state'],
                'interval' => ['interval', 'billing_interval'],
                'interval_count' => ['interval_count', 'billing_interval_count'],
                'price_amount' => ['price_amount', 'price', 'amount'],
                'currency' => ['currency', 'currency_code'],
                'quantity' => ['quantity', 'qty'],
                'number' => ['subscription_number', 'number'],
            ],
            'invoice' => [
                'external_id' => ['invoice_id', 'id', 'external_id'],
                'customer_external_id' => ['customer_id', 'userid', 'customer_external_id'],
                'order_external_id' => ['order_id', 'order_external_id'],
                'number' => ['invoice_number', 'number'],
                'status' => ['status', 'state'],
                'subtotal_amount' => ['subtotal_amount', 'subtotal'],
                'discount_amount' => ['discount_amount', 'discount'],
                'credit_amount' => ['credit_amount', 'credit'],
                'tax_amount' => ['tax_amount', 'tax'],
                'payment_fee_amount' => ['payment_fee_amount', 'payment_fee'],
                'total_amount' => ['total_amount', 'total', 'amount'],
                'currency' => ['currency', 'currency_code'],
                'issued_at' => ['issued_at', 'issue_date', 'created_at'],
                'due_at' => ['due_at', 'due_date'],
                'paid_at' => ['paid_at', 'payment_date'],
                'items_json' => ['items_json', 'items', 'line_items'],
            ],
            'payment', 'transaction' => [
                'external_id' => ['payment_id', 'transaction_id', 'id', 'external_id'],
                'order_external_id' => ['order_id', 'order_external_id'],
                'amount' => ['amount', 'total_amount'],
                'currency' => ['currency', 'currency_code'],
                'method' => ['method', 'gateway', 'payment_method'],
                'status' => ['status', 'state'],
                'reference' => ['reference', 'transaction_reference'],
                'paid_at' => ['paid_at', 'payment_date'],
                'refunded_amount' => ['refunded_amount', 'refunded_total', 'refund_amount'],
                'refunded_at' => ['refunded_at', 'refund_date'],
            ],
            'discount', 'discount_code' => [
                'external_id' => ['discount_id', 'coupon_id', 'id', 'external_id'],
                'code' => ['code', 'coupon_code'],
                'type' => ['type', 'discount_type'],
                'value' => ['value', 'amount', 'discount_value'],
                'currency' => ['currency', 'currency_code'],
                'starts_at' => ['starts_at', 'start_date'],
                'ends_at' => ['ends_at', 'end_date'],
                'max_uses' => ['max_uses', 'usage_limit'],
                'max_uses_per_customer' => ['max_uses_per_customer', 'customer_usage_limit'],
                'min_subtotal_amount' => ['min_subtotal_amount', 'minimum_amount'],
                'is_active' => ['is_active', 'active'],
            ],
            'media', 'product_image' => [
                'external_id' => ['media_id', 'image_id', 'id', 'external_id'],
                'product_external_id' => ['product_id', 'product_external_id'],
                'path' => ['path', 'storage_path', 'file_path'],
                'sort' => ['sort', 'position', 'sort_order'],
            ],
            'service_instance', 'service' => [
                'external_id' => ['service_id', 'service_instance_id', 'id', 'external_id'],
                'customer_external_id' => ['customer_id', 'userid', 'customer_external_id'],
                'order_external_id' => ['order_id', 'order_external_id'],
                'product_external_id' => ['product_id', 'product_external_id'],
                'subscription_external_id' => ['subscription_id', 'subscription_external_id'],
                'number' => ['service_number', 'number'],
                'status' => ['status', 'state'],
                'provider_key' => ['provider_key', 'provider'],
                'external_ref' => ['external_ref', 'provider_id', 'remote_id'],
                'meta_json' => ['meta_json', 'metadata'],
            ],
            default => throw new InvalidArgumentException('Unsupported import entity.'),
        };
    }

    public function for(string $source, string $entity): ImportAdapter
    {
        return new SourceProfileImportAdapter($source, $entity, $this->aliases($source, $entity));
    }
}
