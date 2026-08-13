<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_property_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->string('type', 32);
            $table->boolean('is_required')->default(false);
            $table->json('constraints')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_registration')->default(false);
            $table->boolean('show_on_checkout')->default(false);
            $table->boolean('show_on_account')->default(true);
            $table->boolean('show_on_invoice')->default(false);
            $table->boolean('customer_editable')->default(true);
            $table->boolean('staff_editable')->default(true);
            $table->boolean('internal_only')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_property_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('customer_property_definitions')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'definition_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->json('custom_properties_snapshot')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->json('custom_properties_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('custom_properties_snapshot');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('custom_properties_snapshot');
        });
        Schema::dropIfExists('customer_property_values');
        Schema::dropIfExists('customer_property_definitions');
    }
};
