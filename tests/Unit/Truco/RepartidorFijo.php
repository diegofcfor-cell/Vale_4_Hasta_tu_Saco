<?php

namespace Tests\Unit\Truco;

use App\Truco\Carta;
use App\Truco\MazoRepartidor;
use App\Truco\Repartidor;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Reparte las manos que le pasamos, en orden. Cuando se acaban, reparte al
 * azar con semilla fija para que los tests sean siempre iguales.
 */
final class RepartidorFijo implements Repartidor
{
    private MazoRepartidor $resto;

    /** @param list<array{0: list<string>, 1: list<string>}> $manos */
    public function __construct(private array $manos = [])
    {
        $this->resto = new MazoRepartidor(new Randomizer(new Mt19937(1)));
    }

    public function repartir(): array
    {
        $mano = array_shift($this->manos);

        if ($mano === null) {
            return $this->resto->repartir();
        }

        return [
            array_map(Carta::desdeId(...), $mano[0]),
            array_map(Carta::desdeId(...), $mano[1]),
        ];
    }
}
