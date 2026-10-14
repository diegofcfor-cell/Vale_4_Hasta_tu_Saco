<?php

namespace App\Truco;

enum Accion: string
{
    case JugarCarta = 'jugar_carta';
    case Truco = 'truco';
    case Retruco = 'retruco';
    case ValeCuatro = 'vale_cuatro';
    case Envido = 'envido';
    case RealEnvido = 'real_envido';
    case FaltaEnvido = 'falta_envido';
    case Flor = 'flor';
    case ConFlorMeAchico = 'con_flor_me_achico';
    case Contraflor = 'contraflor';
    case ContraflorAlResto = 'contraflor_al_resto';
    case Quiero = 'quiero';
    case NoQuiero = 'no_quiero';
    case IrseAlMazo = 'irse_al_mazo';

    /** Lo que se dice en la mesa (sirve para mostrar en pantalla y en el chat). */
    public function etiqueta(): string
    {
        return match ($this) {
            self::JugarCarta => 'Jugar carta',
            self::Truco => '¡Truco!',
            self::Retruco => '¡Quiero retruco!',
            self::ValeCuatro => '¡Quiero vale cuatro!',
            self::Envido => 'Envido',
            self::RealEnvido => 'Real envido',
            self::FaltaEnvido => 'Falta envido',
            self::Flor => '¡Flor!',
            self::ConFlorMeAchico => 'Con flor me achico',
            self::Contraflor => 'Contraflor',
            self::ContraflorAlResto => 'Contraflor al resto',
            self::Quiero => '¡Quiero!',
            self::NoQuiero => 'No quiero',
            self::IrseAlMazo => 'Me voy al mazo',
        };
    }
}
