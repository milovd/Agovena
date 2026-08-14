<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'shipping_carrier_id')) {
                $table->string('shipping_carrier_id', 64)->nullable()->after('shipping_method_label');
            }
            if (! Schema::hasColumn('orders', 'shipping_service_code')) {
                $table->string('shipping_service_code', 64)->nullable()->after('shipping_carrier_id');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasIndex('orders', 'orders_customer_status_created_index')) {
                $table->index(['customer_id', 'status', 'created_at'], 'orders_customer_status_created_index');
            }
            if (! Schema::hasIndex('orders', 'orders_status_created_index')) {
                $table->index(['status', 'created_at'], 'orders_status_created_index');
            }
        });
    }

    public function down(): void
    {
        $customerForeign = $this->customerForeignKey();

        if ($customerForeign !== null && $this->driverNeedsForeignKeyRelease()) {
            Schema::table('orders', function (Blueprint $table) use ($customerForeign): void {
                $table->dropForeign($customerForeign['name']);
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasIndex('orders', 'orders_customer_status_created_index')) {
                $table->dropIndex('orders_customer_status_created_index');
            }
            if (Schema::hasIndex('orders', 'orders_status_created_index')) {
                $table->dropIndex('orders_status_created_index');
            }
            foreach (['shipping_carrier_id', 'shipping_service_code'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if ($customerForeign !== null && $this->driverNeedsForeignKeyRelease()) {
            Schema::table('orders', function (Blueprint $table) use ($customerForeign): void {
                $fk = $table->foreign($customerForeign['columns'][0], $customerForeign['name'])
                    ->references($customerForeign['foreign_columns'][0])
                    ->on($customerForeign['foreign_table']);

                match (strtolower((string) ($customerForeign['on_delete'] ?? ''))) {
                    'cascade' => $fk->cascadeOnDelete(),
                    'restrict' => $fk->restrictOnDelete(),
                    default => $fk->nullOnDelete(),
                };
            });
        }
    }

    /**
     * @return array{name: string, columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null}|null
     */
    private function customerForeignKey(): ?array
    {
        foreach (Schema::getForeignKeys('orders') as $foreign) {
            if (($foreign['columns'] ?? []) === ['customer_id']) {
                return $foreign;
            }
        }

        return null;
    }

    private function driverNeedsForeignKeyRelease(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
