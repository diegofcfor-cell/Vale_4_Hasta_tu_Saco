<?php

namespace App\Truco;

final class Puntaje
{
    /**
     * Envido de una mano: con dos cartas del mismo palo son 20 más la suma de
     * las dos más altas de ese palo; si no, vale la carta más alta.
     *
     * @param  list<Carta>  $cartas
     */
    public static function envido(array $cartas): int
    {
        $mejor = 0;
        $porPalo = [];

        foreach ($cartas as $carta) {
            $mejor = max($mejor, $carta->valorEnvido());
            $porPalo[$carta->palo->value][] = $carta->valorEnvido();
        }

        foreach ($porPalo as $valores) {
            if (count($valores) >= 2) {
                rsort($valores);
                $mejor = max($mejor, 20 + $valores[0] + $valores[1]);
            }
        }

        return $mejor;
    }

    /** @param  list<Carta>  $cartas */
    public static function tieneFlor(array $cartas): bool
    {
        return count($cartas) === 3
            && count(array_unique(array_map(fn (Carta $c) => $c->palo->value, $cartas))) === 1;
    }

    /**
     * Valor de la flor: 20 más la suma de las tres cartas.
     *
     * @param  list<Carta>  $cartas
     */
    public static function flor(array $cartas): int
    {
        return 20 + array_sum(array_map(fn (Carta $c) => $c->valorEnvido(), $cartas));
    }
}
