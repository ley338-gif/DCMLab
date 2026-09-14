<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * themenfelder ist ein Index UEBER content/themenfelder.yml (Abschnitt
     * 13), nicht der Speicherort dafuer -- analog zu tracks. content:sync
     * fuellt und aktualisiert diese Tabelle vor den Tracks.
     */
    public function up(): void
    {
        Schema::create('themenfelder', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('order');
            $table->string('title_key');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themenfelder');
    }
};
