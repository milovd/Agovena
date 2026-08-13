<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status');
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('billing_name')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_line1')->nullable();
            $table->string('billing_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_region')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('merchant_name')->nullable();
            $table->text('merchant_address')->nullable();
            $table->date('issued_at');
            $table->text('reason');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('tax_rate_name')->nullable();
            $table->unsignedInteger('tax_rate_bps')->nullable();
            $table->string('currency', 3);
            $table->timestamps();

            $table->index(['invoice_id', 'issued_at']);
            $table->index(['customer_id', 'issued_at']);
        });

        Schema::create('credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->string('kind');
            $table->string('label');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('line_total_amount');
            $table->string('currency', 3);
            $table->timestamps();

            $table->index('invoice_item_id');
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->text('reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
    }
};
