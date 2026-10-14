<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sala_id')->constrained('salas')->cascadeOnDelete();
            $table->unsignedTinyInteger('jugador');
            $table->string('texto', 200);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
