<?php

namespace Tests\Feature;

use App\Models\Sala;
use App\Truco\Partida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Truco\RepartidorFijo;

class SalaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_portada_muestra_el_formulario(): void
    {
        $this->get('/')->assertOk()->assertSee('Crear mesa');
    }

    public function test_crear_una_sala_te_sienta_como_jugador_cero(): void
    {
        $respuesta = $this->post('/salas', ['nombre' => 'Diego', 'objetivo' => '15', 'con_flor' => '1']);

        $sala = Sala::sole();
        $respuesta->assertRedirect(route('salas.ver', $sala));
        $respuesta->assertSessionHas("salas.{$sala->codigo}", 0);
        $this->assertSame('Diego', $sala->jugador0);
        $this->assertSame(15, $sala->partida()->objetivo());
        $this->assertTrue($sala->partida()->conFlor());
    }

    public function test_crear_una_sala_valida_los_datos(): void
    {
        $this->post('/salas', ['nombre' => '', 'objetivo' => '20'])
            ->assertSessionHasErrors(['nombre', 'objetivo']);
    }

    public function test_el_invitado_entra_con_el_link(): void
    {
        $sala = $this->sala(conRival: false);

        $this->get(route('salas.ver', $sala))->assertOk()->assertSee('te invitó a jugar');

        $this->post(route('salas.unirse', $sala), ['nombre' => 'Juan'])
            ->assertRedirect(route('salas.ver', $sala))
            ->assertSessionHas("salas.{$sala->codigo}", 1);

        $this->assertSame('Juan', $sala->fresh()->jugador1);
        $this->get(route('salas.ver', $sala))->assertOk()->assertSee('x-data="mesa(', false);
    }

    public function test_un_tercero_no_puede_entrar(): void
    {
        $sala = $this->sala();

        $this->get(route('salas.ver', $sala))->assertSee('ya tiene dos jugadores');
        $this->post(route('salas.unirse', $sala), ['nombre' => 'Pedro'])->assertSessionHasErrors('nombre');
        $this->assertSame('Juan', $sala->fresh()->jugador1);
    }

    public function test_cada_jugador_ve_solo_sus_cartas(): void
    {
        $sala = $this->sala();

        $vista = $this->comoJugador($sala, 0)->getJson(route('salas.estado', $sala))->assertOk()->json();

        $this->assertSame(['1-espada', '1-basto', '4-copa'], array_column($vista['misCartas'], 'id'));
        $this->assertSame(3, $vista['cartasRival']);
        $this->assertSame('yo', $vista['turno']);
        $this->assertStringNotContainsString('5-oro', json_encode($vista));
    }

    public function test_jugar_una_carta_y_responder_un_truco(): void
    {
        $sala = $this->sala();

        $this->comoJugador($sala, 0)
            ->postJson(route('salas.accion', $sala), ['accion' => 'jugar_carta', 'carta' => '1-espada'])
            ->assertOk()
            ->assertJsonPath('mesa.0.yo.id', '1-espada')
            ->assertJsonPath('turno', 'rival');

        $this->comoJugador($sala, 1)
            ->postJson(route('salas.accion', $sala), ['accion' => 'truco'])
            ->assertOk()
            ->assertJsonPath('pendiente', ['tipo' => 'truco', 'respondo' => false]);

        $this->comoJugador($sala, 0)
            ->getJson(route('salas.estado', $sala))
            ->assertJsonPath('pendiente.respondo', true)
            ->assertJsonPath('mesa.0.yo.id', '1-espada');
    }

    public function test_una_jugada_invalida_devuelve_el_motivo(): void
    {
        $sala = $this->sala();

        $this->comoJugador($sala, 1)
            ->postJson(route('salas.accion', $sala), ['accion' => 'jugar_carta', 'carta' => '5-oro'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'No podés decir «Jugar carta» ahora.');

        $this->comoJugador($sala, 0)
            ->postJson(route('salas.accion', $sala), ['accion' => 'cualquiera'])
            ->assertStatus(422);
    }

    public function test_no_se_juega_hasta_que_entre_el_rival(): void
    {
        $sala = $this->sala(conRival: false);

        $this->comoJugador($sala, 0)
            ->getJson(route('salas.estado', $sala))
            ->assertJsonPath('esperandoRival', true)
            ->assertJsonPath('acciones', []);

        $this->comoJugador($sala, 0)
            ->postJson(route('salas.accion', $sala), ['accion' => 'jugar_carta', 'carta' => '1-espada'])
            ->assertStatus(409);
    }

    public function test_sin_asiento_no_se_ve_ni_se_juega(): void
    {
        $sala = $this->sala();

        $this->getJson(route('salas.estado', $sala))->assertForbidden();
        $this->postJson(route('salas.accion', $sala), ['accion' => 'truco'])->assertForbidden();
    }

    public function test_revancha_solo_al_terminar(): void
    {
        $sala = $this->sala();

        $this->comoJugador($sala, 0)->postJson(route('salas.revancha', $sala))->assertStatus(409);

        $estado = $sala->estado;
        $estado['puntos'] = [15, 3];
        $estado['ganador'] = 0;
        $sala->update(['estado' => $estado]);

        $this->comoJugador($sala, 1)
            ->postJson(route('salas.revancha', $sala))
            ->assertOk()
            ->assertJsonPath('puntos', ['yo' => 0, 'rival' => 0])
            ->assertJsonPath('terminada', false);
    }

    private function sala(bool $conRival = true): Sala
    {
        $partida = Partida::nueva(15, true, new RepartidorFijo([
            [['1-espada', '1-basto', '4-copa'], ['5-oro', '6-basto', '4-espada']],
        ]));

        return Sala::create([
            'codigo' => 'abc12345',
            'jugador0' => 'Diego',
            'jugador1' => $conRival ? 'Juan' : null,
            'estado' => $partida->toArray(),
        ]);
    }

    private function comoJugador(Sala $sala, int $jugador): static
    {
        return $this->flushSession()->withSession(["salas.{$sala->codigo}" => $jugador]);
    }
}
