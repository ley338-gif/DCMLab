<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abzeichen pro Nutzer (Abschnitt 7), aktuell nur "first_blood"
     * (Abschnitt 5.3: Bonus fuer die erste Loesung einer neu
     * veroeffentlichten Node). unique(node_id, type) sorgt dafuer, dass
     * jede Node ihr First Blood nur einmal vergibt, egal wie oft
     * ProfileService das nach spaeteren Loesungen erneut prueft.
     */
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['node_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
