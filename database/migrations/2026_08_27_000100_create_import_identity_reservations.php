<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_identity_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();
            $table->foreignId('import_row_id')->constrained('import_rows')->cascadeOnDelete();
            $table->string('source', 32);
            $table->string('entity', 32);
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['source', 'entity', 'external_id'], 'import_identity_source_entity_external_unique');
        });

        $rows = DB::table('import_rows as rows')
            ->join('import_runs as runs', 'runs.id', '=', 'rows.import_run_id')
            ->where('rows.status', 'imported')
            ->whereNotNull('rows.external_id')
            ->select([
                'runs.source',
                'rows.entity',
                'rows.external_id',
                'rows.import_run_id',
                'rows.id as import_row_id',
                'rows.created_at',
                'rows.updated_at',
            ])
            ->orderBy('rows.id')
            ->get();

        foreach ($rows as $row) {
            DB::table('import_identity_reservations')->insertOrIgnore([
                'source' => $row->source,
                'entity' => $row->entity,
                'external_id' => $row->external_id,
                'import_run_id' => $row->import_run_id,
                'import_row_id' => $row->import_row_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('import_identity_reservations');
    }
};
