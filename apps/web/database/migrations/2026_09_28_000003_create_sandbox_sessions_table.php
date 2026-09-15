<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable Aufzeichnung einer Spielwiesen-Sitzung (ADR 0096, CMS-2b) --
     * der eigentliche Laufzeitzustand bleibt in services/sandbox (Redis,
     * Docker-Container); diese Tabelle ist die Laravel-seitige Historie und
     * loest das Problem, dass state()/exec()/destroy() bisher fest auf
     * "docker" auflosen mussten (siehe docs/offene-fragen.md): sie schlagen
     * `runtime_provider` jetzt anhand der `runtime_instance_id` nach, statt
     * ihn zu raten.
     *
     * `activity_id` ist nullable: eine Lektion mit `sandbox.dataset` bekommt
     * ihren `type=sandbox`-Activity-Eintrag erst durch den naechsten
     * `content:sync`-Lauf (ContentSync::syncLessons()) -- eine Sitzung darf
     * trotzdem entstehen, auch wenn dieser Eintrag (noch) fehlt.
     */
    public function up(): void
    {
        Schema::create('sandbox_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sandbox_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('runtime_provider');
            $table->string('runtime_instance_id')->nullable()->index();
            $table->string('status')->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_sessions');
    }
};
