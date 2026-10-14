<?php

namespace App\Truco;

use Random\Randomizer;

/**
 * Mezcla el mazo y reparte tres cartas a cada jugador, de a una y alternando.
 */
final class MazoRepartidor implements Repartidor
{
    private Randomizer $azar;

    public function __construct(?Randomizer $azar = null)
    {
        $this->azar = $azar ?? new Randomizer;
    }

    public function repartir(): array
    {
        $mazo = $this->azar->shuffleArray(Mazo::completo());

        return [
            [$mazo[0], $mazo[2], $mazo[4]],
            [$mazo[1], $mazo[3], $mazo[5]],
        ];
    }
}
