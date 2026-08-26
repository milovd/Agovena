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
            'paymenter' => [
                'external_id' => ['customer_id', 'id', 'external_id'],
                'email' => ['email', 'email_address'],
                'name' => ['name', 'full_name', 'customer_name'],
            ],
            'whmcs' => [
                'external_id' => ['userid', 'id', 'external_id'],
                'email' => ['email', 'email_address'],
                'name' => ['fullname', 'name', 'full_name'],
            ],
            'woocommerce' => [
                'external_id' => ['customer_id', 'id', 'external_id'],
                'email' => ['billing_email', 'email'],
                'name' => ['billing_name', 'name', 'full_name'],
            ],
            'shopify' => [
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
            ],
            'subscription' => [
                'external_id' => ['subscription_id', 'id', 'external_id'],
                'customer_external_id' => ['customer_id', 'userid', 'customer_external_id'],
                'product_external_id' => ['product_id', 'product_external_id'],
                'status' => ['status', 'state'],
            ],
            default => throw new InvalidArgumentException('Unsupported import entity.'),
        };
    }

    public function for(string $source, string $entity): ImportAdapter
    {
        return new SourceProfileImportAdapter($source, $entity, $this->aliases($source, $entity));
    }
}
