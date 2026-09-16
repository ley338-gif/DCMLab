<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-7d.1 (ADR 0115): Persistenz-Grundlage fuer die Rich-Content-
     * Migration (ADR 0111ff) -- rein additiv, `body` bleibt unveraendert
     * und ist weiterhin die einzige Quelle, aus der gerendert wird. Erst
     * CMS-7d.2 befuellt diese Spalte (Backfill), erst CMS-7d.3 schreibt sie
     * aus dem Editor/Publisher heraus. Nullable, weil vor dem Backfill jede
     * Lektion hier `null` hat.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->jsonb('rich_content')->nullable()->after('quiz');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('rich_content');
        });
    }
};
