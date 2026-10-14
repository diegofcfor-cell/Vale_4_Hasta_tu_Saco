<?php

namespace Tests\Unit\Truco;

use App\Truco\Accion;
use App\Truco\AccionInvalida;
use App\Truco\Carta;
use App\Truco\Partida;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class PartidaTest extends TestCase
{
    /** El jugador 0 gana las dos primeras bazas. Envido: 4 contra 6. */
    private const GANA_EL_CERO = [['1-espada', '1-basto', '4-copa'], ['5-oro', '6-basto', '4-espada']];

    /** Envido: 33 el jugador 0, 29 el jugador 1. */
    private const ENVIDO_33_A_29 = [['7-oro', '6-oro', '1-espada'], ['4-copa', '5-copa', '12-basto']];

    /** Solo el jugador 0 tiene flor (33). */
    private const FLOR_DEL_CERO = [['7-oro', '6-oro', '12-oro'], ['4-copa', '5-basto', '1-espada']];

    /** Los dos tienen flor: 33 el jugador 0, 26 el jugador 1. */
    private const DOS_FLORES = [['7-oro', '6-oro', '12-oro'], ['1-copa', '2-copa', '3-copa']];

    private Partida $p;

    // --- Bazas y manos ------------------------------------------------------

    public function test_una_mano_sin_cantos_vale_un_punto_y_pasa_la_mano(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->jugar(0, '1-espada');
        $this->jugar(1, '5-oro');
        $this->assertSame(0, $this->p->turno(), 'el que gana la baza sale primero');
        $this->jugar(0, '1-basto');
        $this->jugar(1, '6-basto');

        $this->assertSame([1, 0], $this->p->puntos());
        $this->assertSame(2, $this->p->numeroMano());
        $this->assertSame(1, $this->p->mano());
        $this->assertSame(1, $this->p->turno());
        $this->assertCount(3, $this->p->cartasDe(0));
    }

    public function test_parda_en_primera_define_la_segunda(): void
    {
        $this->partida([[['3-espada', '4-copa', '5-oro'], ['3-oro', '1-espada', '6-basto']]]);

        $this->jugar(0, '3-espada');
        $this->jugar(1, '3-oro');
        $this->assertSame([null], $this->p->bazas());
        $this->assertSame(0, $this->p->turno(), 'después de una parda sale el mano');
        $this->jugar(0, '4-copa');
        $this->jugar(1, '1-espada');

        $this->assertSame([0, 1], $this->p->puntos());
    }

    public function test_parda_en_segunda_gana_el_de_la_primera(): void
    {
        $this->partida([[['1-espada', '4-copa', '5-oro'], ['4-basto', '4-oro', '6-copa']]]);

        $this->jugar(0, '1-espada');
        $this->jugar(1, '4-basto');
        $this->jugar(0, '4-copa');
        $this->jugar(1, '4-oro');

        $this->assertSame([1, 0], $this->p->puntos());
    }

    public function test_todas_pardas_gana_el_mano(): void
    {
        $this->partida([[['3-espada', '2-oro', '4-copa'], ['3-basto', '2-copa', '4-oro']]]);

        $this->jugar(0, '3-espada');
        $this->jugar(1, '3-basto');
        $this->jugar(0, '2-oro');
        $this->jugar(1, '2-copa');
        $this->jugar(0, '4-copa');
        $this->jugar(1, '4-oro');

        $this->assertSame([1, 0], $this->p->puntos());
    }

    public function test_no_se_puede_jugar_fuera_de_turno_ni_una_carta_ajena(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->assertSame([], $this->p->accionesDisponibles(1));
        $this->assertLanza(fn () => $this->jugar(1, '5-oro'));
        $this->assertLanza(fn () => $this->jugar(0, '5-oro'));
        $this->assertLanza(fn () => $this->jugar(0, null));
    }

    // --- Truco --------------------------------------------------------------

    public function test_truco_querido_vale_dos(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::Truco);
        $this->assertSame(['tipo' => 'truco', 'responde' => 1], $this->p->pendiente());
        $this->assertContains(Accion::Retruco, $this->p->accionesDisponibles(1));
        $this->assertLanza(fn () => $this->jugar(0, '1-espada'), 'no se juega con un canto pendiente');
        $this->cantar(1, Accion::Quiero);
        $this->ganarManoConElCero();

        $this->assertSame([2, 0], $this->p->puntos());
    }

    public function test_truco_no_querido_vale_uno(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::NoQuiero);

        $this->assertSame([1, 0], $this->p->puntos());
        $this->assertSame(2, $this->p->numeroMano());
    }

    public function test_retruco_no_querido_le_da_dos_al_que_retruco(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::Retruco);
        $this->cantar(0, Accion::NoQuiero);

        $this->assertSame([0, 2], $this->p->puntos());
    }

    public function test_solo_sube_el_que_tiene_el_quiero_y_vale_cuatro_vale_cuatro(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::Quiero);
        $this->assertNotContains(Accion::Retruco, $this->p->accionesDisponibles(0), 'no podés subir tu propio truco');

        $this->jugar(0, '1-espada');
        $this->assertContains(Accion::Retruco, $this->p->accionesDisponibles(1));
        $this->cantar(1, Accion::Retruco);
        $this->cantar(0, Accion::ValeCuatro);
        $this->assertNotContains(Accion::ValeCuatro, $this->p->accionesDisponibles(1));
        $this->cantar(1, Accion::Quiero);
        $this->jugar(1, '5-oro');
        $this->jugar(0, '1-basto');
        $this->jugar(1, '6-basto');

        $this->assertSame([4, 0], $this->p->puntos());
    }

    // --- Envido -------------------------------------------------------------

    public function test_envido_querido_vale_dos_y_ya_no_se_puede_cantar(): void
    {
        $this->partida([self::ENVIDO_33_A_29]);

        $this->cantar(0, Accion::Envido);
        $this->cantar(1, Accion::Quiero);

        $this->assertSame([2, 0], $this->p->puntos());
        $this->assertNull($this->p->pendiente());
        $this->assertSame(0, $this->p->turno());
        $this->assertNotContains(Accion::Envido, $this->p->accionesDisponibles(0));
    }

    public function test_envido_no_querido_vale_uno(): void
    {
        $this->partida([self::ENVIDO_33_A_29]);

        $this->cantar(0, Accion::Envido);
        $this->cantar(1, Accion::NoQuiero);

        $this->assertSame([1, 0], $this->p->puntos());
    }

    public function test_en_el_empate_de_envido_gana_el_mano(): void
    {
        $this->partida([[['7-oro', '6-oro', '12-espada'], ['7-copa', '6-copa', '1-basto']]]);

        $this->jugar(0, '12-espada');
        $this->cantar(1, Accion::Envido);
        $this->cantar(0, Accion::Quiero);

        $this->assertSame([2, 0], $this->p->puntos());
    }

    public function test_envido_envido_real_envido_no_querido_vale_cuatro(): void
    {
        $this->partida([self::ENVIDO_33_A_29]);

        $this->cantar(0, Accion::Envido);
        $this->cantar(1, Accion::Envido);
        $this->assertSame(
            [Accion::Quiero, Accion::NoQuiero, Accion::RealEnvido, Accion::FaltaEnvido],
            $this->p->accionesDisponibles(0),
        );
        $this->cantar(0, Accion::RealEnvido);
        $this->cantar(1, Accion::NoQuiero);

        $this->assertSame([4, 0], $this->p->puntos());
    }

    public function test_envido_real_envido_querido_vale_cinco(): void
    {
        $this->partida([self::ENVIDO_33_A_29]);

        $this->cantar(0, Accion::Envido);
        $this->cantar(1, Accion::RealEnvido);
        $this->cantar(0, Accion::Quiero);

        $this->assertSame([5, 0], $this->p->puntos());
    }

    public function test_falta_envido_querida_puede_terminar_la_partida(): void
    {
        $this->partida([self::ENVIDO_33_A_29], objetivo: 15);

        $this->cantar(0, Accion::FaltaEnvido);
        $this->cantar(1, Accion::Quiero);

        $this->assertSame([15, 0], $this->p->puntos());
        $this->assertTrue($this->p->terminada());
        $this->assertSame(0, $this->p->ganador());
        $this->assertSame([], $this->p->accionesDisponibles(0));
        $this->assertLanza(fn () => $this->jugar(0, '1-espada'));
    }

    public function test_el_envido_esta_primero(): void
    {
        $this->partida([self::ENVIDO_33_A_29]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::Envido);
        $this->cantar(0, Accion::Quiero);
        $this->assertSame([2, 0], $this->p->puntos());
        $this->assertSame(['tipo' => 'truco', 'responde' => 1], $this->p->pendiente(), 'vuelve el truco');

        $this->cantar(1, Accion::Quiero);
        $this->jugar(0, '1-espada');
        $this->jugar(1, '4-copa');
        $this->jugar(0, '7-oro');
        $this->jugar(1, '5-copa');

        $this->assertSame([4, 0], $this->p->puntos());
    }

    public function test_el_envido_solo_se_canta_en_primera_y_antes_de_querer_el_truco(): void
    {
        $this->partida([self::GANA_EL_CERO, self::GANA_EL_CERO]);

        $this->jugar(0, '1-espada');
        $this->assertContains(Accion::Envido, $this->p->accionesDisponibles(1), 'el pie todavía no jugó');
        $this->jugar(1, '5-oro');
        $this->assertNotContains(Accion::Envido, $this->p->accionesDisponibles(0), 'ya pasó la primera');

        $this->partida([self::GANA_EL_CERO]);
        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::Quiero);
        $this->assertNotContains(Accion::Envido, $this->p->accionesDisponibles(0));
    }

    // --- Flor ---------------------------------------------------------------

    public function test_flor_sin_flor_del_rival_vale_tres_y_cierra_el_envido(): void
    {
        $this->partida([self::FLOR_DEL_CERO]);

        $acciones = $this->p->accionesDisponibles(0);
        $this->assertContains(Accion::Flor, $acciones);
        $this->assertNotContains(Accion::Envido, $acciones, 'con flor no se canta envido');

        $this->cantar(0, Accion::Flor);
        $this->assertSame([3, 0], $this->p->puntos());
        $this->jugar(0, '12-oro');
        $this->assertNotContains(Accion::Envido, $this->p->accionesDisponibles(1));
    }

    public function test_la_flor_anula_el_envido(): void
    {
        $this->partida([array_reverse(self::FLOR_DEL_CERO)]);

        $this->cantar(0, Accion::Envido);
        $this->assertContains(Accion::Flor, $this->p->accionesDisponibles(1));
        $this->cantar(1, Accion::Flor);

        $this->assertSame([0, 3], $this->p->puntos());
        $this->assertNull($this->p->pendiente());
        $this->assertSame(0, $this->p->turno());
    }

    public function test_se_puede_cantar_flor_al_responder_un_truco(): void
    {
        $this->partida([array_reverse(self::FLOR_DEL_CERO)]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::Flor);

        $this->assertSame([0, 3], $this->p->puntos());
        $this->assertSame(['tipo' => 'truco', 'responde' => 1], $this->p->pendiente());
    }

    public function test_con_flor_me_achico_vale_cuatro(): void
    {
        $this->partida([self::DOS_FLORES]);

        $this->cantar(0, Accion::Flor);
        $this->assertSame(
            [Accion::ConFlorMeAchico, Accion::Contraflor, Accion::ContraflorAlResto],
            $this->p->accionesDisponibles(1),
        );
        $this->cantar(1, Accion::ConFlorMeAchico);

        $this->assertSame([4, 0], $this->p->puntos());
    }

    public function test_contraflor_querida_vale_seis_para_la_mejor_flor(): void
    {
        $this->partida([self::DOS_FLORES]);

        $this->cantar(0, Accion::Flor);
        $this->cantar(1, Accion::Contraflor);
        $this->cantar(0, Accion::Quiero);

        $this->assertSame([6, 0], $this->p->puntos());
    }

    public function test_contraflor_no_querida_vale_cuatro(): void
    {
        $this->partida([self::DOS_FLORES]);

        $this->cantar(0, Accion::Flor);
        $this->cantar(1, Accion::Contraflor);
        $this->cantar(0, Accion::NoQuiero);

        $this->assertSame([0, 4], $this->p->puntos());
    }

    public function test_contraflor_al_resto_querida_gana_la_partida(): void
    {
        $this->partida([self::DOS_FLORES], objetivo: 15);

        $this->cantar(0, Accion::Flor);
        $this->cantar(1, Accion::ContraflorAlResto);
        $this->cantar(0, Accion::Quiero);

        $this->assertSame(0, $this->p->ganador());
    }

    public function test_jugando_sin_flor_se_canta_envido(): void
    {
        $this->partida([self::FLOR_DEL_CERO], conFlor: false);

        $acciones = $this->p->accionesDisponibles(0);
        $this->assertContains(Accion::Envido, $acciones);
        $this->assertNotContains(Accion::Flor, $acciones);
    }

    // --- Irse al mazo -------------------------------------------------------

    public function test_irse_al_mazo_en_primera_sin_envido_da_dos(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::IrseAlMazo);

        $this->assertSame([0, 2], $this->p->puntos());
    }

    public function test_irse_al_mazo_con_truco_querido_da_el_truco(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::Quiero);
        $this->jugar(0, '1-espada');
        $this->jugar(1, '5-oro');
        $this->cantar(0, Accion::IrseAlMazo);

        $this->assertSame([0, 2], $this->p->puntos());
    }

    public function test_irse_al_mazo_cuando_te_cantan_truco_es_no_quererlo(): void
    {
        $this->partida([self::GANA_EL_CERO]);

        $this->cantar(0, Accion::Truco);
        $this->cantar(1, Accion::IrseAlMazo);

        $this->assertSame([1, 0], $this->p->puntos());
    }

    // --- Guardar y seguir ---------------------------------------------------

    public function test_se_puede_guardar_y_seguir_la_partida(): void
    {
        $this->partida([self::GANA_EL_CERO]);
        $this->jugar(0, '1-espada');
        $this->cantar(1, Accion::Truco);

        $json = json_encode($this->p->toArray());
        $this->p = Partida::desdeArray(json_decode($json, true), new RepartidorFijo);

        $this->assertSame($json, json_encode($this->p->toArray()));
        $this->cantar(0, Accion::Quiero);
        $this->jugar(1, '5-oro');
        $this->jugar(0, '1-basto');
        $this->jugar(1, '6-basto');
        $this->assertSame([2, 0], $this->p->puntos());
    }

    // --- Partidas completas al azar ----------------------------------------

    public function test_partidas_al_azar_siempre_terminan_sin_errores(): void
    {
        foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $semilla) {
            $azar = new Randomizer(new Mt19937($semilla));
            $this->p = Partida::nueva($semilla % 2 ? 30 : 15, $semilla % 3 !== 0, new RepartidorFijo);

            for ($pasos = 0; $pasos < 5000 && ! $this->p->terminada(); $pasos++) {
                $jugador = $this->p->accionesDisponibles(0) !== [] ? 0 : 1;
                $acciones = $this->p->accionesDisponibles($jugador);
                $this->assertNotEmpty($acciones, 'siempre alguien tiene que poder hacer algo');

                // Que jugar carta sea lo más común, para que las manos avancen.
                $accion = $azar->getInt(0, 2) === 0 ? $acciones[$azar->getInt(0, count($acciones) - 1)] : $acciones[0];
                $carta = null;
                if ($accion === Accion::JugarCarta) {
                    $cartas = $this->p->cartasDe($jugador);
                    $carta = $cartas[$azar->getInt(0, count($cartas) - 1)]->id();
                }
                $this->p->ejecutar($jugador, $accion, $carta);

                $this->assertSame($this->p->toArray(), Partida::desdeArray(json_decode(json_encode($this->p->toArray()), true))->toArray());
            }

            $this->assertTrue($this->p->terminada(), "la partida {$semilla} no terminó");
            $this->assertGreaterThanOrEqual($this->p->objetivo(), $this->p->puntos()[$this->p->ganador()]);
        }
    }

    // --- Ayudas -------------------------------------------------------------

    private function partida(array $manos, int $objetivo = 30, bool $conFlor = true): void
    {
        $this->p = Partida::nueva($objetivo, $conFlor, new RepartidorFijo($manos));
    }

    private function jugar(int $jugador, ?string $carta): void
    {
        $this->p->ejecutar($jugador, Accion::JugarCarta, $carta);
    }

    private function cantar(int $jugador, Accion $accion): void
    {
        $this->p->ejecutar($jugador, $accion);
    }

    private function ganarManoConElCero(): void
    {
        $this->jugar(0, '1-espada');
        $this->jugar(1, '5-oro');
        $this->jugar(0, '1-basto');
        $this->jugar(1, '6-basto');
    }

    private function assertLanza(callable $accion, string $mensaje = ''): void
    {
        try {
            $accion();
            $this->fail($mensaje ?: 'Se esperaba AccionInvalida');
        } catch (AccionInvalida) {
            $this->addToAssertionCount(1);
        }
    }
}
