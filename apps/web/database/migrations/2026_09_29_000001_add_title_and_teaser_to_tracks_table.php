<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track ist bisher der einzige DB-gefuehrte Inhaltstyp ohne echten
     * Textspeicher -- `title_key` verweist nur auf `lang/de.json`, eine
     * Datei, die ein Studio-Formular nicht mitschreiben kann (ADR 0100,
     * CMS-4a). `title`/`teaser` sind nullable und additiv: bestehende, per
     * `content:sync` verwaltete Tracks behalten `title_key` und bleiben
     * unveraendert funktionsfaehig (`TrackController` faellt auf
     * `title_key` zurueck, solange `title` null ist); nur ein neuer, in
     * Studio angelegter Track bekommt `title` direkt gesetzt.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->json('title')->nullable()->after('themenfeld_id');
            $table->json('teaser')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['title', 'teaser']);
        });
    }
};
