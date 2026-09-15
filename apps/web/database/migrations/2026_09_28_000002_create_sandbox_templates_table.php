<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Katalog freigegebener Sandbox-Laufzeitumgebungen (ADR 0094/0096,
     * CMS-2): das Laufzeit-"Was" (Container-Paar, Ressourcenprofil,
     * Sicherheitsgrenzen -- alles heute fest in services/sandbox/docker_ops.py)
     * getrennt vom Lern-"Womit" (der Datensatz, weiterhin
     * `lessons.sandbox.dataset`). Nur Reviewer (spaeter: administrator,
     * ADR 0094 CMS-3) duerfen Zeilen anlegen/freigeben -- Autoren waehlen nur
     * unter `status = published` aus (SandboxTemplatePolicy).
     *
     * Absichtlich schlank: `runtime_provider` ist der einzige Hinweis
     * darauf, welche RuntimeProviderContract-Implementierung eine Instanz
     * bedient (heute ausschliesslich "docker") -- keine Spalten fuer
     * Ressourcenlimits o. Ae., solange services/sandbox diese nicht
     * tatsaechlich pro Template variieren kann (siehe
     * docs/studio-architecture-plan.md Abschnitt 1.3/6).
     */
    public function up(): void
    {
        Schema::create('sandbox_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('runtime_provider')->default('docker');
            $table->string('status')->default('draft'); // draft | published
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_templates');
    }
};
