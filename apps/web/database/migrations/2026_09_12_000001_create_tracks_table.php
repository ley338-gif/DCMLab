<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tracks ist ein Index UEBER content/tracks.yml (Abschnitt 7), nicht der
     * Speicherort dafuer. content:sync fuellt und aktualisiert diese Tabelle.
     */
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('order');
            $table->string('title_key');
            $table->string('level');
            $table->unsignedSmallInteger('hours');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
