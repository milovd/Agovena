<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'remember_token')) {
            Schema::table('customers', function (Blueprint $table): void {
                if (! Schema::hasColumn('customers', 'anonymized_at')) {
                    $table->timestamp('anonymized_at')->nullable();
                }
                if (! Schema::hasColumn('customers', 'deletion_requested_at')) {
                    $table->timestamp('deletion_requested_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'credit_amount')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('credit_amount')->default(0);
            });
        }

        $identityTable = $this->identityTable();

        Schema::create('tickets', function (Blueprint $table) use ($identityTable): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained($identityTable)->nullOnDelete();
            $table->string('subject');
            $table->string('status', 20)->default('open')->index();
            $table->string('priority', 20)->default('normal');
            $table->string('department')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('related');
            $table->timestamp('last_reply_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 20);
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['author_type', 'author_id']);
        });

        Schema::create('customer_credit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('balance_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_credit_entries', function (Blueprint $table) use ($identityTable): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 10);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason');
            $table->nullableMorphs('reference');
            $table->foreignId('staff_user_id')->nullable()->constrained($identityTable)->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('customer_credit_entries');
        Schema::dropIfExists('customer_credit_accounts');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'credit_amount')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('credit_amount');
            });
        }

        if (Schema::hasTable('customers')) {
            $drop = array_values(array_filter([
                Schema::hasColumn('customers', 'anonymized_at') ? 'anonymized_at' : null,
                Schema::hasColumn('customers', 'deletion_requested_at') ? 'deletion_requested_at' : null,
            ]));
            if ($drop !== []) {
                Schema::table('customers', function (Blueprint $table) use ($drop): void {
                    $table->dropColumn($drop);
                });
            }
        }
    }

    private function identityTable(): string
    {
        if (Schema::hasTable('users')) {
            return 'users';
        }

        if (Schema::hasTable('staff_users')) {
            return 'staff_users';
        }

        throw new RuntimeException('Neither users nor staff_users exists; cannot create staff_user_id foreign keys.');
    }
};
