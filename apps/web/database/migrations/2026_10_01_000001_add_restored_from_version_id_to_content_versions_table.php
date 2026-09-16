<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CMS-7d.3 (ADR 0118): `ContentPublishingService::restoreVersion()`
     * ersetzt die alte, rein buchfuehrende `rollback()` -- eine
     * Wiederherstellung erzeugt weiterhin eine neue, eigene Version (nie
     * eine rueckwirkende Aenderung einer bestehenden), traegt aber jetzt
     * die Herkunft: welche historische Version wiederhergestellt wurde.
     * Nullable, weil jede normal veroeffentlichte (nicht wiederhergestellte)
     * Version diese Spalte nie setzt.
     */
    public function up(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            $table->foreignId('restored_from_version_id')
                ->nullable()
                ->after('published_at')
                ->constrained('content_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restored_from_version_id');
        });
    }
};
