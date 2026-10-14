# Vale 4 Hasta tu Saco

Truco argentino para dos jugadores, hecho con Laravel 12. Cada jugador juega desde su PC: uno crea la partida y le manda el link al otro.

## Plan

1. **Reglas del juego** (este paso): mazo, bazas, truco, envido y flor, con tests.
2. **Sala con link para invitar**: crear partida, compartir el link y que cada jugador vea solo sus cartas.
3. **Tiempo real con Reverb y túnel**: las jugadas llegan al instante y el link funciona desde cualquier casa (Cloudflare Tunnel).
4. **Chat con frases del truco**: "¡Quiero!", "Son buenas", "Mazo"...

## Cómo correrlo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```

## Dónde está cada cosa

Toda la lógica del juego está en `app/Truco/` y no depende de Laravel:

| Archivo | Qué hace |
| --- | --- |
| `Carta.php`, `Palo.php`, `Mazo.php` | Mazo español de 40 cartas y la jerarquía del truco |
| `Puntaje.php` | Cuenta el envido y la flor de una mano |
| `MazoRepartidor.php` | Mezcla y reparte tres cartas a cada uno |
| `Accion.php` | Todo lo que un jugador puede hacer o cantar |
| `Partida.php` | La partida completa: turnos, bazas, cantos y puntos |

Ejemplo de uso:

```php
use App\Truco\Accion;
use App\Truco\Partida;

$partida = Partida::nueva(objetivo: 30, conFlor: true);

$partida->accionesDisponibles(0);       // [JugarCarta, Truco, Envido, ...]
$partida->ejecutar(0, Accion::Truco);
$partida->ejecutar(1, Accion::Quiero);
$partida->ejecutar(0, Accion::JugarCarta, '1-espada');

$partida->toArray();                    // para guardar en la base de datos
Partida::desdeArray($datos);            // para seguir donde quedó
```

Si una acción no está permitida en ese momento, `ejecutar()` lanza `AccionInvalida` con el motivo.

## Reglas implementadas

**Bazas.** Gana la mano quien gana dos bazas. Si la primera es parda, define la segunda. Si una baza es parda después de que alguien ganó la primera, gana el de la primera. Si son todas pardas, gana el mano. Después de una parda sale el mano.

**Jerarquía.** 1 de espada, 1 de basto, 7 de espada, 7 de oro, 3, 2, 1 de copa y oro, 12, 11, 10, 7 de copa y basto, 6, 5, 4.

**Truco.** Truco 2, retruco 3, vale cuatro 4. No querido vale lo anterior (truco no querido, 1 punto). Solo puede subir quien tiene el quiero. Una mano sin truco vale 1.

**Envido.** Se canta en la primera baza, antes de tirar tu carta. Envido 2, real envido 3, falta envido lo que le falta al que va ganando. Se puede encadenar envido, envido, real envido y falta envido. No querido vale lo que estaba aceptado antes (o 1 si era el primer canto). En el empate gana el mano. Si te cantan truco podés contestar con envido ("el envido está primero") y después se vuelve al truco.

**Flor.** Tres cartas del mismo palo: 20 más la suma. Quien tiene flor no puede cantar envido, tiene que cantar flor, y la flor anula el envido. Si el rival no tiene flor son 3 puntos. Si los dos tienen: con flor me achico (4 para el que cantó), contraflor (querida 6 para la mejor flor, no querida 4) o contraflor al resto (querida gana lo que falta). Se puede jugar sin flor creando la partida con `conFlor: false`.

**Irse al mazo.** El rival se lleva lo que vale la mano. Si te vas en la primera baza y todavía no se jugó el envido, le das un punto más. Irse cuando te cantan truco es lo mismo que no quererlo.

**Partida.** A 15 o a 30 puntos; el mano se alterna en cada mano.
