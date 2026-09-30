<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('show_delivery_card')->nullable()->after('show_specifications');
            $table->string('delivery_title')->nullable()->after('show_delivery_card');
            $table->text('delivery_text')->nullable()->after('delivery_title');
            $table->boolean('show_returns_card')->nullable()->after('delivery_text');
            $table->string('returns_title')->nullable()->after('show_returns_card');
            $table->text('returns_text')->nullable()->after('returns_title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'show_delivery_card',
                'delivery_title',
                'delivery_text',
                'show_returns_card',
                'returns_title',
                'returns_text',
            ]);
        });
    }
};
