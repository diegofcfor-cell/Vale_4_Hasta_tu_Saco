# Vale 4 Hasta tu Saco

Juego de mesa, con cartas españolas, llamado "Truco" tipico de argentina, para dos jugadores, hecho con Laravel 12. Cada jugador juega desde su PC: uno crea la partida y le manda el link al otro.

## Plan

1. **Reglas del juego** ✅: mazo, bazas, truco, envido y flor, con tests.
2. **Sala con link para invitar** ✅: crear partida, compartir el link y que cada jugador vea solo sus cartas.
3. **Tiempo real con Reverb y túnel** ✅: las jugadas llegan al instante y el link funciona desde cualquier casa (Cloudflare Tunnel).
4. **Chat con frases del truco** ✅: frases típicas con un toque ("Son buenas", "¡Mentira!", "¿Tenés el ancho?"...) o texto libre.

## Documentación

- [Requerimientos](docs/requerimientos.md): objetivo, requerimientos funcionales y no funcionales, y cómo se aplica Reverb + Echo (Unidad 4 de Programación IV).
- [Manual del usuario](docs/manual-usuario.md): cómo levantar la mesa, invitar a tu rival y jugar.

## Cómo correrlo

La primera vez:

```bash
composer run setup
```

Eso instala todo, crea el `.env`, la base de datos y compila el JavaScript. Si ya tenías un `.env` de antes, copiá a tu `.env` las líneas de Reverb del final de `.env.example` y poné `BROADCAST_CONNECTION=reverb`.

Cada vez que quieras jugar:

```bash
composer run mesa
```

Eso levanta tres cosas juntas: la web (`php artisan serve`, puerto 8000), Reverb (puerto 8080) y un proxy que las junta en **http://localhost:8888**. Entrá siempre por el 8888: arriba al medio de la mesa vas a ver "● en vivo" cuando Reverb está conectado.

Para probar solo, abrí el link de invitación en una ventana de incógnito: cada ventana es un jugador distinto.

## Jugar con alguien de otra casa

Se usa un túnel gratis de Cloudflare, sin cuenta ni configurar el router.

1. Instalá `cloudflared` una sola vez:

   ```bash
   wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
   sudo dpkg -i cloudflared-linux-amd64.deb
   ```

2. Con `composer run mesa` corriendo, en otra terminal:

   ```bash
   cloudflared tunnel --url http://localhost:8888
   ```

   Va a mostrar una dirección tipo `https://algo-raro.trycloudflare.com`.

3. **Abrí vos esa dirección** (no localhost), creá la mesa y mandale el link a tu rival. El link sale con la dirección del túnel, así que funciona desde cualquier lado.

La dirección cambia cada vez que levantás el túnel, y mientras esté abierto cualquiera con el link puede ver la portada. Cerralo con Ctrl+C cuando terminen.

## Cartas y sonido

- **Cartas reales:** poné las 40 imágenes en `public/cartas/` como `1-espada.png`, `12-copa.png`, etc. (ver `public/cartas/LEEME.md`). Si están, la mesa las usa; si no, muestra las cartas simples.
- **Voz:** cuando alguien canta (truco, envido, flor, quiero, no quiero, al mazo) se escucha en los dos navegadores con la voz en español del sistema, preferentemente de Argentina y de hombre. Se apaga y prende con el 🔊 del marcador.

### Cómo funciona el tiempo real

Cuando alguien juega, el servidor manda por Reverb un aviso vacío al canal `sala.{codigo}` y cada pantalla pide su propia vista. Así las cartas del rival nunca viajan por el WebSocket. Si Reverb se cae, la pantalla igual se actualiza sola cada 5 segundos.

`tunel/proxy.mjs` existe porque un túnel gratis expone un solo puerto: manda las conexiones WebSocket a Reverb y todo lo demás a Laravel.

## Dónde está cada cosa

Toda la lógica del juego está en `app/Truco/` y no depende de Laravel:

| Archivo | Qué hace |
| --- | --- |
| `Carta.php`, `Palo.php`, `Mazo.php` | Mazo español de 40 cartas y la jerarquía del truco |
| `Puntaje.php` | Cuenta el envido y la flor de una mano |
| `MazoRepartidor.php` | Mezcla y reparte tres cartas a cada uno |
| `Accion.php` | Todo lo que un jugador puede hacer o cantar |
| `Partida.php` | La partida completa: turnos, bazas, cantos y puntos |

La parte web:

| Archivo | Qué hace |
| --- | --- |
| `app/Models/Sala.php` | Una mesa: los dos nombres y la partida guardada como JSON. `vistaPara()` arma lo que ve cada jugador, sin las cartas del rival |
| `app/Http/Controllers/SalaController.php` | Crear mesa, entrar con el link, ver el estado, jugar y pedir revancha |
| `resources/views/mesa.blade.php` | La mesa en pantalla, con Alpine.js. Escucha a Reverb y pide el estado cuando hay novedades |
| `app/Models/Mensaje.php` | Los mensajes del chat y la lista de frases predeterminadas (`Mensaje::FRASES`, cambialas a gusto) |
| `app/Events/MesaActualizada.php` | El aviso que se manda por Reverb después de cada jugada |
| `resources/js/echo.js` | Conecta Laravel Echo con Reverb por el mismo puerto de la página |
| `tunel/proxy.mjs` | Junta web y WebSocket en el puerto 8888 para usar un solo túnel |

Cada navegador recuerda en qué asiento está con la sesión de Laravel, así que no hace falta registrarse.

Ejemplo de uso de la lógica:

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
