<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensaje extends Model
{
    protected $table = 'mensajes';

    protected $fillable = ['jugador', 'texto'];

    protected function casts(): array
    {
        return ['jugador' => 'integer'];
    }

    /** Frases típicas de la mesa, para mandar con un toque. */
    public const FRASES = [
        '¡Buenas!',
        'Son buenas',
        '¡Mentira!',
        '¿Tenés el ancho?',
        'Esta es mía',
        'Me matás',
        '¡Bien jugado!',
        'Qué suerte que tenés',
        'Jugá, que se enfría el mate',
        'Dale la revancha',
    ];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }
}
