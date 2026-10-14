<?php

namespace App\Models;

use App\Truco\Accion;
use App\Truco\Carta;
use App\Truco\Partida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Una mesa para dos jugadores. Quien la crea es el jugador 0 y quien entra
 * con el link es el jugador 1. La partida se guarda como JSON en "estado".
 */
class Sala extends Model
{
    protected $table = 'salas';

    protected $fillable = ['codigo', 'jugador0', 'jugador1', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'codigo';
    }

    public static function crear(string $nombre, int $objetivo, bool $conFlor): self
    {
        return self::create([
            'codigo' => Str::lower(Str::random(8)),
            'jugador0' => $nombre,
            'estado' => Partida::nueva($objetivo, $conFlor)->toArray(),
        ]);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    public function partida(): Partida
    {
        return Partida::desdeArray($this->estado);
    }

    public function guardarPartida(Partida $partida): void
    {
        $this->estado = $partida->toArray();
    }

    public function completa(): bool
    {
        return $this->jugador1 !== null;
    }

    public function nombreDe(int $jugador): ?string
    {
        return $jugador === 0 ? $this->jugador0 : $this->jugador1;
    }

    /**
     * Lo que ve un jugador: sus cartas, nunca las del rival, y todo
     * expresado desde su lado de la mesa ("yo" y "rival").
     */
    public function vistaPara(int $yo): array
    {
        $partida = $this->partida();
        $rival = 1 - $yo;
        $carta = fn (?Carta $c) => $c === null ? null : ['id' => $c->id(), 'numero' => $c->numero, 'palo' => $c->palo->value];
        $quien = fn (?int $j) => $j === null ? null : ($j === $yo ? 'yo' : 'rival');
        $pendiente = $partida->pendiente();

        return [
            'codigo' => $this->codigo,
            'link' => route('salas.ver', $this),
            'nombres' => ['yo' => $this->nombreDe($yo), 'rival' => $this->nombreDe($rival)],
            'esperandoRival' => ! $this->completa(),
            'objetivo' => $partida->objetivo(),
            'conFlor' => $partida->conFlor(),
            'puntos' => ['yo' => $partida->puntos()[$yo], 'rival' => $partida->puntos()[$rival]],
            'numeroMano' => $partida->numeroMano(),
            'soyMano' => $partida->mano() === $yo,
            'turno' => $quien($partida->turno()),
            'misCartas' => array_map($carta, $partida->cartasDe($yo)),
            'cartasRival' => count($partida->cartasDe($rival)),
            'mesa' => array_map(fn (array $baza) => [
                'yo' => $carta($baza[$yo] ?? null),
                'rival' => $carta($baza[$rival] ?? null),
            ], $partida->mesa()),
            'bazas' => array_map(fn (?int $g) => $quien($g) ?? 'parda', $partida->bazas()),
            'pendiente' => $pendiente === null ? null : [
                'tipo' => $pendiente['tipo'],
                'respondo' => $pendiente['responde'] === $yo,
            ],
            'acciones' => $this->completa()
                ? array_map(fn (Accion $a) => [
                    'accion' => $a->value,
                    // Contestar un truco con envido es "el envido está primero".
                    'etiqueta' => $a === Accion::Envido && $pendiente !== null && $pendiente['tipo'] === 'truco'
                        ? '¡El envido está primero!'
                        : $a->etiqueta(),
                ], $partida->accionesDisponibles($yo))
                : [],
            'eventos' => array_map(fn (array $e) => [
                'n' => $e['n'],
                'quien' => $e['jugador'] === null ? null : $this->nombreDe($e['jugador']),
                'texto' => $e['texto'],
                'canto' => $e['canto'],
            ], array_slice($partida->eventos(), -12)),
            'chat' => $this->mensajes()->latest('id')->limit(30)->get()->reverse()->values()
                ->map(fn (Mensaje $m) => [
                    'id' => $m->id,
                    'mio' => $m->jugador === $yo,
                    'nombre' => $this->nombreDe($m->jugador),
                    'texto' => $m->texto,
                ])->all(),
            'terminada' => $partida->terminada(),
            'gane' => $partida->terminada() ? $partida->ganador() === $yo : null,
        ];
    }
}
