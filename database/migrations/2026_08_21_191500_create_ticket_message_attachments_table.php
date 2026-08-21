<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_message_id')->constrained('ticket_messages')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime', 127);
            $table->string('extension', 16);
            $table->unsignedInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_message_attachments');
    }
};
