<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P10.65: haelt fest, ob genau dieser Versuch das TrackBadge neu vergeben
 * hat -- ein Wiederholungsversuch auf einem bereits bestandenen Track soll
 * auf der Ergebnisseite nichts mehr versprechen (siehe ExamAttemptService::complete()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->boolean('badge_awarded')->default(false)->after('passed');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('badge_awarded');
        });
    }
};
