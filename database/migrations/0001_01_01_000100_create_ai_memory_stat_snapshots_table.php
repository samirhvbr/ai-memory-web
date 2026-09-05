<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DURABLE history of the ai-memory statistics, in THIS app's own database.
 * ai-memory's index can be reset or rebuilt (it is derived from the Markdown
 * wiki); this table keeps one snapshot per day so the usage history survives
 * that. Fed by `php artisan aimemory:snapshot` (scheduled daily).
 * See docs/durable-history.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_memory_stat_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('captured_on')->unique();   // one snapshot per day (idempotent)

            // Totals at the moment of the snapshot.
            $table->unsignedInteger('workspaces')->default(0);
            $table->unsignedInteger('projects')->default(0);
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('observations')->default(0);
            $table->unsignedInteger('embeddings')->default(0);
            $table->unsignedInteger('handoffs_open')->default(0);
            $table->unsignedInteger('proposals_pending')->default(0);

            // The whole snapshot, for forward compatibility (new metrics without a migration).
            $table->json('raw_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memory_stat_snapshots');
    }
};
