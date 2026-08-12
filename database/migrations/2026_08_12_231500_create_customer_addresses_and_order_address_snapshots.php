<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code');
            $table->string('country', 2);
            $table->string('phone')->nullable();
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default_billing']);
            $table->index(['customer_id', 'is_default_shipping']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('billing_name')->nullable()->after('customer_email');
            $table->string('billing_company')->nullable()->after('billing_name');
            $table->string('billing_line1')->nullable()->after('billing_company');
            $table->string('billing_line2')->nullable()->after('billing_line1');
            $table->string('billing_city')->nullable()->after('billing_line2');
            $table->string('billing_region')->nullable()->after('billing_city');
            $table->string('billing_postal_code')->nullable()->after('billing_region');
            $table->string('billing_country', 2)->nullable()->after('billing_postal_code');
            $table->string('billing_phone')->nullable()->after('billing_country');

            $table->string('shipping_name')->nullable()->after('billing_phone');
            $table->string('shipping_company')->nullable()->after('shipping_name');
            $table->string('shipping_line1')->nullable()->after('shipping_company');
            $table->string('shipping_line2')->nullable()->after('shipping_line1');
            $table->string('shipping_city')->nullable()->after('shipping_line2');
            $table->string('shipping_region')->nullable()->after('shipping_city');
            $table->string('shipping_postal_code')->nullable()->after('shipping_region');
            $table->string('shipping_country', 2)->nullable()->after('shipping_postal_code');
            $table->string('shipping_phone')->nullable()->after('shipping_country');
            $table->boolean('shipping_same_as_billing')->default(true)->after('shipping_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_name',
                'billing_company',
                'billing_line1',
                'billing_line2',
                'billing_city',
                'billing_region',
                'billing_postal_code',
                'billing_country',
                'billing_phone',
                'shipping_name',
                'shipping_company',
                'shipping_line1',
                'shipping_line2',
                'shipping_city',
                'shipping_region',
                'shipping_postal_code',
                'shipping_country',
                'shipping_phone',
                'shipping_same_as_billing',
            ]);
        });

        Schema::dropIfExists('customer_addresses');
    }
};
