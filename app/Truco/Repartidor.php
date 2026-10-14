<?php

namespace App\Truco;

interface Repartidor
{
    /** @return array{0: list<Carta>, 1: list<Carta>} tres cartas para cada jugador */
    public function repartir(): array;
}
