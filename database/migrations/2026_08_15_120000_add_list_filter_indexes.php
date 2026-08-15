<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes justified by admin/list filters exercised under large-data sanity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasIndex('payments', 'payments_status_created_index')) {
                $table->index(['status', 'created_at'], 'payments_status_created_index');
            }
            if (! Schema::hasIndex('payments', 'payments_order_status_index')) {
                $table->index(['order_id', 'status'], 'payments_order_status_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasIndex('invoices', 'invoices_status_issued_index')) {
                $table->index(['status', 'issued_at'], 'invoices_status_issued_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasIndex('payments', 'payments_status_created_index')) {
                $table->dropIndex('payments_status_created_index');
            }
            if (Schema::hasIndex('payments', 'payments_order_status_index')) {
                $table->dropIndex('payments_order_status_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasIndex('invoices', 'invoices_status_issued_index')) {
                $table->dropIndex('invoices_status_issued_index');
            }
        });
    }
};
