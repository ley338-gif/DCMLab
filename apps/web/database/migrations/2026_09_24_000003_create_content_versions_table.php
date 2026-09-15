<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unveraenderliche Versions-Snapshots einer Aktivitaet (ADR 0071, W3),
     * ersetzt die Git-Historie als Aenderungsverlauf. `payload` ist die
     * normalisierte Entwurfsform aus ActivityContract::deserialize().
     * `is_current` markiert je Aktivitaet hoechstens eine veroeffentlichte
     * Version als die aktuell gueltige -- eine fruehere veroeffentlichte
     * Version bleibt fuer Diff/Rollback erhalten, verliert bei einer neuen
     * Veroeffentlichung nur dieses Flag (siehe ContentVersioningService).
     */
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft | review | published
            $table->json('payload');
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
