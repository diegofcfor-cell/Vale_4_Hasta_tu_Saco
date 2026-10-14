<?php

namespace App\Truco;

use InvalidArgumentException;

/**
 * Una partida de truco argentino entre dos jugadores (0 y 1).
 *
 * Toda la lógica vive acá y no depende de Laravel: la sala, la base de datos
 * y Reverb solo llaman a ejecutar() y muestran el estado. Cualquier acción
 * que las reglas no permitan lanza AccionInvalida.
 */
final class Partida
{
    private const PUNTOS_FLOR = 3;

    private const PUNTOS_ACHICO = 4;

    private const PUNTOS_CONTRAFLOR = 6;

    private const MAX_EVENTOS = 50;

    /** @var array{0: int, 1: int} */
    private array $puntos = [0, 0];

    private int $mano = 0;

    private int $numeroMano = 0;

    private int $turno = 0;

    /** @var array{0: list<Carta>, 1: list<Carta>} */
    private array $cartasIniciales = [[], []];

    /** @var array{0: list<Carta>, 1: list<Carta>} */
    private array $enMano = [[], []];

    /** @var list<array<int, Carta>> cartas tiradas en cada baza, por jugador */
    private array $mesa = [];

    /** @var list<int|null> ganador de cada baza terminada (null = parda) */
    private array $bazas = [];

    /** Truco aceptado: 0 nada, 1 truco, 2 retruco, 3 vale cuatro. */
    private int $trucoNivel = 0;

    /** Quién tiene el quiero (puede subir el truco); null = cualquiera. */
    private ?int $trucoPuedeSubir = null;

    private ?int $trucoPropuesto = null;

    private ?int $trucoCantor = null;

    /** El truco quedó esperando respuesta porque "el envido está primero". */
    private bool $trucoSuspendido = false;

    /** @var list<Accion> */
    private array $envido = [];

    private ?int $envidoCantor = null;

    private bool $envidoCerrado = false;

    /** @var list<Accion> */
    private array $flor = [];

    private ?int $florCantor = null;

    private bool $florCerrado = false;

    /** @var array{tipo: 'truco'|'envido'|'flor', responde: int}|null */
    private ?array $pendiente = null;

    private ?int $ganador = null;

    /** @var list<array{mano: int, jugador: int|null, texto: string}> */
    private array $eventos = [];

    private function __construct(
        private readonly int $objetivo,
        private readonly bool $conFlor,
        private readonly Repartidor $repartidor,
    ) {
        if (! in_array($objetivo, [15, 30], true)) {
            throw new InvalidArgumentException('La partida se juega a 15 o a 30 puntos.');
        }
    }

    public static function nueva(int $objetivo = 30, bool $conFlor = true, ?Repartidor $repartidor = null): self
    {
        $partida = new self($objetivo, $conFlor, $repartidor ?? new MazoRepartidor);
        $partida->iniciarMano();

        return $partida;
    }

    // ---------------------------------------------------------------------
    // Qué puede hacer cada jugador
    // ---------------------------------------------------------------------

    /** @return list<Accion> */
    public function accionesDisponibles(int $jugador): array
    {
        $this->validarJugador($jugador);

        if ($this->terminada()) {
            return [];
        }

        if ($this->pendiente !== null) {
            return $this->pendiente['responde'] === $jugador ? $this->respuestasPosibles($jugador) : [];
        }

        if ($this->turno !== $jugador) {
            return [];
        }

        $acciones = [Accion::JugarCarta];

        if (($truco = $this->subidaDeTruco($jugador)) !== null) {
            $acciones[] = $truco;
        }

        if ($this->puedeIniciarEnvido($jugador)) {
            array_push($acciones, Accion::Envido, Accion::RealEnvido, Accion::FaltaEnvido);
        }

        if ($this->puedeCantarFlor($jugador)) {
            $acciones[] = Accion::Flor;
        }

        $acciones[] = Accion::IrseAlMazo;

        return $acciones;
    }

    /** @return list<Accion> */
    private function respuestasPosibles(int $jugador): array
    {
        if ($this->pendiente['tipo'] === 'truco') {
            $acciones = [Accion::Quiero, Accion::NoQuiero];

            if ($this->trucoPropuesto < 3) {
                $acciones[] = $this->trucoPropuesto === 1 ? Accion::Retruco : Accion::ValeCuatro;
            }

            // "El envido está primero": se puede contestar un truco con envido.
            if ($this->trucoPropuesto === 1 && $this->puedeIniciarEnvido($jugador)) {
                array_push($acciones, Accion::Envido, Accion::RealEnvido, Accion::FaltaEnvido);
            }

            if ($this->puedeCantarFlor($jugador)) {
                $acciones[] = Accion::Flor;
            }

            $acciones[] = Accion::IrseAlMazo;

            return $acciones;
        }

        if ($this->pendiente['tipo'] === 'envido') {
            $acciones = [Accion::Quiero, Accion::NoQuiero, ...$this->subidasDeEnvido()];

            if ($this->puedeCantarFlor($jugador)) {
                $acciones[] = Accion::Flor;
            }

            return $acciones;
        }

        return match (end($this->flor)) {
            Accion::Flor => [Accion::ConFlorMeAchico, Accion::Contraflor, Accion::ContraflorAlResto],
            Accion::Contraflor => [Accion::Quiero, Accion::NoQuiero, Accion::ContraflorAlResto],
            default => [Accion::Quiero, Accion::NoQuiero],
        };
    }

    private function subidaDeTruco(int $jugador): ?Accion
    {
        if ($this->trucoNivel >= 3 || ($this->trucoPuedeSubir !== null && $this->trucoPuedeSubir !== $jugador)) {
            return null;
        }

        return [Accion::Truco, Accion::Retruco, Accion::ValeCuatro][$this->trucoNivel];
    }

    /** @return list<Accion> */
    private function subidasDeEnvido(): array
    {
        $hayReal = in_array(Accion::RealEnvido, $this->envido, true);
        $hayFalta = in_array(Accion::FaltaEnvido, $this->envido, true);
        $envidos = count(array_filter($this->envido, fn (Accion $a) => $a === Accion::Envido));

        $acciones = [];

        if (! $hayReal && ! $hayFalta && $envidos < 2) {
            $acciones[] = Accion::Envido;
        }

        if (! $hayReal && ! $hayFalta) {
            $acciones[] = Accion::RealEnvido;
        }

        if (! $hayFalta) {
            $acciones[] = Accion::FaltaEnvido;
        }

        return $acciones;
    }

    /** El envido se canta en la primera baza, antes de tirar tu carta y antes de querer el truco. */
    private function puedeIniciarEnvido(int $jugador): bool
    {
        return ! $this->envidoCerrado
            && $this->envido === []
            && $this->flor === []
            && $this->trucoNivel === 0
            && $this->enPrimeraSinJugar($jugador)
            && ! ($this->conFlor && $this->tieneFlor($jugador)); // con flor hay que cantar flor
    }

    private function puedeCantarFlor(int $jugador): bool
    {
        return $this->conFlor
            && $this->tieneFlor($jugador)
            && $this->flor === []
            && ! $this->florCerrado
            && ! $this->envidoCerrado
            && $this->enPrimeraSinJugar($jugador);
    }

    private function enPrimeraSinJugar(int $jugador): bool
    {
        return $this->bazas === [] && ! isset($this->mesa[0][$jugador]);
    }

    // ---------------------------------------------------------------------
    // Ejecutar acciones
    // ---------------------------------------------------------------------

    /**
     * @param  string|null  $carta  id de la carta (ej. "7-oro"), solo para JugarCarta
     *
     * @throws AccionInvalida
     */
    public function ejecutar(int $jugador, Accion $accion, ?string $carta = null): void
    {
        if (! in_array($accion, $this->accionesDisponibles($jugador), true)) {
            throw new AccionInvalida("No podés decir «{$accion->etiqueta()}» ahora.");
        }

        match ($accion) {
            Accion::JugarCarta => $this->jugarCarta($jugador, $carta),
            Accion::Truco, Accion::Retruco, Accion::ValeCuatro => $this->cantarTruco($jugador, $accion),
            Accion::Envido, Accion::RealEnvido, Accion::FaltaEnvido => $this->cantarEnvido($jugador, $accion),
            Accion::Flor => $this->cantarFlor($jugador),
            Accion::ConFlorMeAchico, Accion::Contraflor, Accion::ContraflorAlResto => $this->responderFlor($jugador, $accion),
            Accion::Quiero => $this->quiero($jugador),
            Accion::NoQuiero => $this->noQuiero($jugador),
            Accion::IrseAlMazo => $this->irseAlMazo($jugador),
        };
    }

    private function jugarCarta(int $jugador, ?string $id): void
    {
        $indice = array_search($id, array_map(fn (Carta $c) => $c->id(), $this->enMano[$jugador]), true);

        if ($id === null || $indice === false) {
            throw new AccionInvalida('Esa carta no está en tu mano.');
        }

        $carta = array_splice($this->enMano[$jugador], $indice, 1)[0];
        $baza = count($this->bazas);
        $this->mesa[$baza][$jugador] = $carta;
        $this->registrar($jugador, "tira el {$carta->nombre()}");

        if (count($this->mesa[$baza]) < 2) {
            $this->turno = 1 - $jugador;

            return;
        }

        $valor0 = $this->mesa[$baza][0]->valorTruco();
        $valor1 = $this->mesa[$baza][1]->valorTruco();
        $ganadorBaza = $valor0 === $valor1 ? null : ($valor0 > $valor1 ? 0 : 1);
        $this->bazas[] = $ganadorBaza;
        $this->registrar($ganadorBaza, $ganadorBaza === null ? 'Parda' : 'gana la baza');

        $ganadorMano = $this->ganadorDeLaMano();

        if ($ganadorMano !== null) {
            $this->terminarMano($ganadorMano, $this->valorTruco($this->trucoNivel));

            return;
        }

        // Después de una parda vuelve a empezar el mano.
        $this->turno = $ganadorBaza ?? $this->mano;
    }

    private function ganadorDeLaMano(): ?int
    {
        $b = $this->bazas;

        if (count($b) < 2) {
            return null;
        }

        $ganadas = [0, 0];
        foreach ($b as $ganador) {
            if ($ganador !== null) {
                $ganadas[$ganador]++;
            }
        }

        if (count($b) === 2) {
            return match (true) {
                $ganadas[0] === 2 => 0,
                $ganadas[1] === 2 => 1,
                $b[0] === null && $b[1] !== null => $b[1], // parda la primera: define la segunda
                $b[0] !== null && $b[1] === null => $b[0], // parda la segunda: gana el de la primera
                default => null,
            };
        }

        if ($ganadas[0] !== $ganadas[1]) {
            return $ganadas[0] > $ganadas[1] ? 0 : 1;
        }

        // Una y una con la tercera parda gana el de la primera; todo pardas gana el mano.
        return $b[0] ?? $this->mano;
    }

    private function cantarTruco(int $jugador, Accion $accion): void
    {
        $this->trucoPropuesto = ($this->pendiente === null ? $this->trucoNivel : $this->trucoPropuesto) + 1;
        $this->trucoCantor = $jugador;
        $this->pendiente = ['tipo' => 'truco', 'responde' => 1 - $jugador];
        $this->registrar($jugador, $accion->etiqueta());
    }

    private function cantarEnvido(int $jugador, Accion $accion): void
    {
        if ($this->pendiente !== null && $this->pendiente['tipo'] === 'truco') {
            $this->trucoSuspendido = true;
        }

        $this->envido[] = $accion;
        $this->envidoCantor = $jugador;
        $this->pendiente = ['tipo' => 'envido', 'responde' => 1 - $jugador];
        $this->registrar($jugador, $accion->etiqueta());
    }

    private function cantarFlor(int $jugador): void
    {
        $this->registrar($jugador, Accion::Flor->etiqueta());

        if ($this->pendiente !== null && $this->pendiente['tipo'] === 'envido') {
            $this->envido = [];
            $this->envidoCantor = null;
            $this->registrar(null, 'La flor anula el envido');
        }

        if ($this->pendiente !== null && $this->pendiente['tipo'] === 'truco') {
            $this->trucoSuspendido = true;
        }

        $this->flor = [Accion::Flor];
        $this->florCantor = $jugador;

        if ($this->tieneFlor(1 - $jugador)) {
            $this->pendiente = ['tipo' => 'flor', 'responde' => 1 - $jugador];

            return;
        }

        $this->sumar($jugador, self::PUNTOS_FLOR, 'por la flor');
        $this->cerrarFlor();
    }

    private function responderFlor(int $jugador, Accion $accion): void
    {
        $this->registrar($jugador, $accion->etiqueta());

        if ($accion === Accion::ConFlorMeAchico) {
            $this->sumar(1 - $jugador, self::PUNTOS_ACHICO, 'por la flor');
            $this->cerrarFlor();

            return;
        }

        $this->flor[] = $accion;
        $this->florCantor = $jugador;
        $this->pendiente = ['tipo' => 'flor', 'responde' => 1 - $jugador];
    }

    private function quiero(int $jugador): void
    {
        $this->registrar($jugador, Accion::Quiero->etiqueta());

        match ($this->pendiente['tipo']) {
            'truco' => $this->aceptarTruco($jugador),
            'envido' => $this->resolverEnvido(),
            'flor' => $this->resolverFlor(),
        };
    }

    private function noQuiero(int $jugador): void
    {
        $this->registrar($jugador, Accion::NoQuiero->etiqueta());

        if ($this->pendiente['tipo'] === 'truco') {
            $this->terminarMano($this->trucoCantor, $this->valorTruco($this->trucoPropuesto - 1));

            return;
        }

        if ($this->pendiente['tipo'] === 'envido') {
            $puntos = count($this->envido) === 1
                ? 1
                : $this->valorEnvidoQuerido(array_slice($this->envido, 0, -1));
            $this->sumar($this->envidoCantor, $puntos, 'por el envido no querido');
            $this->cerrarEnvido();

            return;
        }

        $puntos = end($this->flor) === Accion::ContraflorAlResto && in_array(Accion::Contraflor, $this->flor, true)
            ? self::PUNTOS_CONTRAFLOR
            : self::PUNTOS_ACHICO;
        $this->sumar($this->florCantor, $puntos, 'por la flor');
        $this->cerrarFlor();
    }

    private function aceptarTruco(int $jugador): void
    {
        $this->trucoNivel = $this->trucoPropuesto;
        $this->trucoPropuesto = null;
        $this->trucoCantor = null;
        $this->trucoPuedeSubir = $jugador;
        $this->pendiente = null;
    }

    private function resolverEnvido(): void
    {
        $tantos = [Puntaje::envido($this->cartasIniciales[0]), Puntaje::envido($this->cartasIniciales[1])];
        $this->registrar(null, "Envido: {$tantos[$this->mano]} el mano, {$tantos[1 - $this->mano]} el pie");
        $ganador = $this->mejorTanto($tantos);
        $this->sumar($ganador, $this->valorEnvidoQuerido($this->envido), 'por el envido');
        $this->cerrarEnvido();
    }

    private function resolverFlor(): void
    {
        $tantos = [Puntaje::flor($this->cartasIniciales[0]), Puntaje::flor($this->cartasIniciales[1])];
        $this->registrar(null, "Flor: {$tantos[$this->mano]} el mano, {$tantos[1 - $this->mano]} el pie");
        $puntos = end($this->flor) === Accion::Contraflor ? self::PUNTOS_CONTRAFLOR : $this->falta();
        $this->sumar($this->mejorTanto($tantos), $puntos, 'por la flor');
        $this->cerrarFlor();
    }

    /** @param array{0: int, 1: int} $tantos En caso de empate gana el mano. */
    private function mejorTanto(array $tantos): int
    {
        return $tantos[0] === $tantos[1] ? $this->mano : ($tantos[0] > $tantos[1] ? 0 : 1);
    }

    /** @param list<Accion> $cantos */
    private function valorEnvidoQuerido(array $cantos): int
    {
        if (in_array(Accion::FaltaEnvido, $cantos, true)) {
            return $this->falta();
        }

        return array_sum(array_map(fn (Accion $a) => $a === Accion::RealEnvido ? 3 : 2, $cantos));
    }

    /** Lo que le falta al que va ganando para llegar al final. */
    private function falta(): int
    {
        return $this->objetivo - max($this->puntos);
    }

    private function cerrarEnvido(): void
    {
        $this->envidoCerrado = true;
        $this->reanudarTruco();
    }

    private function cerrarFlor(): void
    {
        $this->florCerrado = true;
        $this->envidoCerrado = true;
        $this->reanudarTruco();
    }

    private function reanudarTruco(): void
    {
        $this->pendiente = $this->trucoSuspendido && ! $this->terminada()
            ? ['tipo' => 'truco', 'responde' => 1 - $this->trucoCantor]
            : null;
        $this->trucoSuspendido = false;
    }

    private function irseAlMazo(int $jugador): void
    {
        if ($this->pendiente !== null) {
            // Irse al mazo cuando te cantan truco es lo mismo que no quererlo.
            $this->noQuiero($jugador);

            return;
        }

        $puntos = $this->valorTruco($this->trucoNivel);

        // Irse en primera sin que se haya jugado el envido le regala ese punto al rival.
        if ($this->bazas === [] && ! $this->envidoCerrado && $this->envido === [] && $this->flor === []) {
            $puntos++;
        }

        $this->registrar($jugador, Accion::IrseAlMazo->etiqueta());
        $this->terminarMano(1 - $jugador, $puntos);
    }

    private function valorTruco(int $nivel): int
    {
        return $nivel === 0 ? 1 : $nivel + 1;
    }

    // ---------------------------------------------------------------------
    // Manos y puntos
    // ---------------------------------------------------------------------

    private function iniciarMano(): void
    {
        $this->numeroMano++;
        $this->cartasIniciales = $this->repartidor->repartir();
        $this->enMano = $this->cartasIniciales;
        $this->mesa = [];
        $this->bazas = [];
        $this->turno = $this->mano;
        $this->trucoNivel = 0;
        $this->trucoPuedeSubir = null;
        $this->trucoPropuesto = null;
        $this->trucoCantor = null;
        $this->trucoSuspendido = false;
        $this->envido = [];
        $this->envidoCantor = null;
        $this->envidoCerrado = false;
        $this->flor = [];
        $this->florCantor = null;
        $this->florCerrado = false;
        $this->pendiente = null;
        $this->registrar($this->mano, 'es mano');
    }

    private function terminarMano(int $ganador, int $puntos): void
    {
        $this->pendiente = null;
        $this->sumar($ganador, $puntos, 'por la mano');

        if (! $this->terminada()) {
            $this->mano = 1 - $this->mano;
            $this->iniciarMano();
        }
    }

    private function sumar(int $jugador, int $puntos, string $motivo): void
    {
        if ($this->terminada()) {
            return;
        }

        $this->puntos[$jugador] += $puntos;
        $this->registrar($jugador, "suma {$puntos} {$motivo}");

        if ($this->puntos[$jugador] >= $this->objetivo) {
            $this->ganador = $jugador;
            $this->pendiente = null;
            $this->registrar($jugador, 'gana la partida');
        }
    }

    private function tieneFlor(int $jugador): bool
    {
        return Puntaje::tieneFlor($this->cartasIniciales[$jugador]);
    }

    private function registrar(?int $jugador, string $texto): void
    {
        $this->eventos[] = ['mano' => $this->numeroMano, 'jugador' => $jugador, 'texto' => $texto];
        $this->eventos = array_slice($this->eventos, -self::MAX_EVENTOS);
    }

    private function validarJugador(int $jugador): void
    {
        if ($jugador !== 0 && $jugador !== 1) {
            throw new InvalidArgumentException('El jugador tiene que ser 0 o 1.');
        }
    }

    // ---------------------------------------------------------------------
    // Consultas
    // ---------------------------------------------------------------------

    /** @return array{0: int, 1: int} */
    public function puntos(): array
    {
        return $this->puntos;
    }

    public function objetivo(): int
    {
        return $this->objetivo;
    }

    public function conFlor(): bool
    {
        return $this->conFlor;
    }

    public function mano(): int
    {
        return $this->mano;
    }

    public function numeroMano(): int
    {
        return $this->numeroMano;
    }

    public function turno(): int
    {
        return $this->turno;
    }

    /** @return list<Carta> */
    public function cartasDe(int $jugador): array
    {
        $this->validarJugador($jugador);

        return $this->enMano[$jugador];
    }

    /** @return list<array<int, Carta>> */
    public function mesa(): array
    {
        return $this->mesa;
    }

    /** @return list<int|null> */
    public function bazas(): array
    {
        return $this->bazas;
    }

    /** @return array{tipo: 'truco'|'envido'|'flor', responde: int}|null */
    public function pendiente(): ?array
    {
        return $this->pendiente;
    }

    public function terminada(): bool
    {
        return $this->ganador !== null;
    }

    public function ganador(): ?int
    {
        return $this->ganador;
    }

    /** @return list<array{mano: int, jugador: int|null, texto: string}> */
    public function eventos(): array
    {
        return $this->eventos;
    }

    // ---------------------------------------------------------------------
    // Guardar y recuperar (para la base de datos)
    // ---------------------------------------------------------------------

    public function toArray(): array
    {
        $ids = fn (array $cartas) => array_map(fn (Carta $c) => $c->id(), $cartas);
        $valores = fn (array $acciones) => array_map(fn (Accion $a) => $a->value, $acciones);

        return [
            'objetivo' => $this->objetivo,
            'con_flor' => $this->conFlor,
            'puntos' => $this->puntos,
            'mano' => $this->mano,
            'numero_mano' => $this->numeroMano,
            'turno' => $this->turno,
            'cartas_iniciales' => array_map($ids, $this->cartasIniciales),
            'en_mano' => array_map($ids, $this->enMano),
            'mesa' => array_map($ids, $this->mesa),
            'bazas' => $this->bazas,
            'truco_nivel' => $this->trucoNivel,
            'truco_puede_subir' => $this->trucoPuedeSubir,
            'truco_propuesto' => $this->trucoPropuesto,
            'truco_cantor' => $this->trucoCantor,
            'truco_suspendido' => $this->trucoSuspendido,
            'envido' => $valores($this->envido),
            'envido_cantor' => $this->envidoCantor,
            'envido_cerrado' => $this->envidoCerrado,
            'flor' => $valores($this->flor),
            'flor_cantor' => $this->florCantor,
            'flor_cerrado' => $this->florCerrado,
            'pendiente' => $this->pendiente,
            'ganador' => $this->ganador,
            'eventos' => $this->eventos,
        ];
    }

    public static function desdeArray(array $datos, ?Repartidor $repartidor = null): self
    {
        $cartas = fn (array $ids) => array_map(Carta::desdeId(...), $ids);
        $acciones = fn (array $valores) => array_map(Accion::from(...), $valores);

        $partida = new self($datos['objetivo'], $datos['con_flor'], $repartidor ?? new MazoRepartidor);
        $partida->puntos = $datos['puntos'];
        $partida->mano = $datos['mano'];
        $partida->numeroMano = $datos['numero_mano'];
        $partida->turno = $datos['turno'];
        $partida->cartasIniciales = array_map($cartas, $datos['cartas_iniciales']);
        $partida->enMano = array_map($cartas, $datos['en_mano']);
        $partida->mesa = array_map($cartas, $datos['mesa']);
        $partida->bazas = $datos['bazas'];
        $partida->trucoNivel = $datos['truco_nivel'];
        $partida->trucoPuedeSubir = $datos['truco_puede_subir'];
        $partida->trucoPropuesto = $datos['truco_propuesto'];
        $partida->trucoCantor = $datos['truco_cantor'];
        $partida->trucoSuspendido = $datos['truco_suspendido'];
        $partida->envido = $acciones($datos['envido']);
        $partida->envidoCantor = $datos['envido_cantor'];
        $partida->envidoCerrado = $datos['envido_cerrado'];
        $partida->flor = $acciones($datos['flor']);
        $partida->florCantor = $datos['flor_cantor'];
        $partida->florCerrado = $datos['flor_cerrado'];
        $partida->pendiente = $datos['pendiente'];
        $partida->ganador = $datos['ganador'];
        $partida->eventos = $datos['eventos'];

        return $partida;
    }
}
