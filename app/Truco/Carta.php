<?php

namespace App\Truco;

use InvalidArgumentException;

/**
 * Una carta del mazo español de 40 (sin 8 ni 9).
 */
final readonly class Carta
{
    public const NUMEROS = [1, 2, 3, 4, 5, 6, 7, 10, 11, 12];

    public function __construct(public int $numero, public Palo $palo)
    {
        if (! in_array($numero, self::NUMEROS, true)) {
            throw new InvalidArgumentException("El {$numero} no existe en el mazo de truco.");
        }
    }

    /** Crea una carta desde su id, por ejemplo "1-espada". */
    public static function desdeId(string $id): self
    {
        [$numero, $palo] = array_pad(explode('-', $id, 2), 2, '');
        $palo = Palo::tryFrom($palo);

        if (! ctype_digit($numero) || $palo === null) {
            throw new InvalidArgumentException("Carta inválida: {$id}");
        }

        return new self((int) $numero, $palo);
    }

    public function id(): string
    {
        return "{$this->numero}-{$this->palo->value}";
    }

    public function nombre(): string
    {
        return "{$this->numero} de {$this->palo->value}";
    }

    /**
     * Valor para ganar una baza: más alto le gana a más bajo, igual es parda.
     */
    public function valorTruco(): int
    {
        return match (true) {
            $this->numero === 1 && $this->palo === Palo::Espada => 14,
            $this->numero === 1 && $this->palo === Palo::Basto => 13,
            $this->numero === 7 && $this->palo === Palo::Espada => 12,
            $this->numero === 7 && $this->palo === Palo::Oro => 11,
            $this->numero === 3 => 10,
            $this->numero === 2 => 9,
            $this->numero === 1 => 8,
            $this->numero === 12 => 7,
            $this->numero === 11 => 6,
            $this->numero === 10 => 5,
            $this->numero === 7 => 4,
            $this->numero === 6 => 3,
            $this->numero === 5 => 2,
            default => 1, // los 4
        };
    }

    /** Las figuras (10, 11 y 12) valen 0 para el envido. */
    public function valorEnvido(): int
    {
        return $this->numero >= 10 ? 0 : $this->numero;
    }
}
