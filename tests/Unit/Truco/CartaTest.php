<?php

namespace Tests\Unit\Truco;

use App\Truco\Carta;
use App\Truco\Mazo;
use App\Truco\MazoRepartidor;
use App\Truco\Puntaje;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CartaTest extends TestCase
{
    public function test_el_mazo_tiene_40_cartas_distintas_sin_8_ni_9(): void
    {
        $ids = array_map(fn (Carta $c) => $c->id(), Mazo::completo());

        $this->assertCount(40, array_unique($ids));
        $this->assertNotContains('8-oro', $ids);
        $this->assertNotContains('9-copa', $ids);
    }

    public function test_no_se_puede_crear_un_8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Carta::desdeId('8-espada');
    }

    public function test_jerarquia_del_truco(): void
    {
        $ordenDeMayorAMenor = [
            '1-espada', '1-basto', '7-espada', '7-oro', '3-copa', '2-oro', '1-copa',
            '12-basto', '11-espada', '10-oro', '7-copa', '6-espada', '5-oro', '4-basto',
        ];

        $valores = array_map(fn ($id) => Carta::desdeId($id)->valorTruco(), $ordenDeMayorAMenor);
        $ordenados = $valores;
        rsort($ordenados);

        $this->assertSame($ordenados, $valores);
        $this->assertCount(14, array_unique($valores));
    }

    public function test_cartas_que_empatan(): void
    {
        $this->assertSame(Carta::desdeId('1-oro')->valorTruco(), Carta::desdeId('1-copa')->valorTruco());
        $this->assertSame(Carta::desdeId('7-basto')->valorTruco(), Carta::desdeId('7-copa')->valorTruco());
        $this->assertSame(Carta::desdeId('3-espada')->valorTruco(), Carta::desdeId('3-oro')->valorTruco());
    }

    public function test_envido(): void
    {
        $this->assertSame(33, $this->envido('7-oro', '6-oro', '1-espada'));
        $this->assertSame(20, $this->envido('10-oro', '11-oro', '4-copa'));
        $this->assertSame(27, $this->envido('12-copa', '7-copa', '6-basto'));
        $this->assertSame(7, $this->envido('12-espada', '7-basto', '3-copa'));
        $this->assertSame(0, $this->envido('10-espada', '11-basto', '12-copa'));
    }

    public function test_flor(): void
    {
        $flor = array_map(Carta::desdeId(...), ['7-oro', '6-oro', '12-oro']);

        $this->assertTrue(Puntaje::tieneFlor($flor));
        $this->assertSame(33, Puntaje::flor($flor));
        $this->assertSame(33, Puntaje::envido($flor));
        $this->assertFalse(Puntaje::tieneFlor(array_map(Carta::desdeId(...), ['7-oro', '6-oro', '12-copa'])));
    }

    public function test_el_repartidor_da_tres_cartas_distintas_a_cada_uno(): void
    {
        [$a, $b] = (new MazoRepartidor)->repartir();
        $ids = array_map(fn (Carta $c) => $c->id(), [...$a, ...$b]);

        $this->assertCount(3, $a);
        $this->assertCount(3, $b);
        $this->assertCount(6, array_unique($ids));
    }

    private function envido(string ...$ids): int
    {
        return Puntaje::envido(array_map(Carta::desdeId(...), $ids));
    }
}
