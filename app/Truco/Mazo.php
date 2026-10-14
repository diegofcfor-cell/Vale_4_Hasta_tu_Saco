<?php

namespace App\Truco;

final class Mazo
{
    /** @return list<Carta> las 40 cartas ordenadas */
    public static function completo(): array
    {
        $cartas = [];

        foreach (Palo::cases() as $palo) {
            foreach (Carta::NUMEROS as $numero) {
                $cartas[] = new Carta($numero, $palo);
            }
        }

        return $cartas;
    }
}
