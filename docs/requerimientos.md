# Documento de requerimientos

**Proyecto:** Vale 4 Hasta tu Saco, el truco, juego de cartas españolas típico de Argentina, para dos jugadores en tiempo real
**Materia:** Programación IV, Tecnicatura Universitaria en Programación, UTN Facultad Regional Resistencia
**Unidad relacionada:** U4, WebSockets con Laravel (Reverb + Echo)
**Autor:** Diego
**Repositorio:** https://github.com/diegofcfor-cell/vale_4_hasta_tu_saco

---

## 1. Objetivo

Construir un juego de truco argentino para dos personas que juegan cada una desde su propia PC, con las jugadas y el chat llegando al instante a la otra pantalla. El trabajo aplica lo visto en la Unidad 4: emitir eventos desde Laravel, distribuirlos con Laravel Reverb y escucharlos en el navegador con Laravel Echo.

El ejemplo de la unidad (emisor y receptor) manda un texto de una pestaña a otra. Este TP lleva la misma idea a un caso real: dos jugadores, cada uno en su casa, que tienen que ver la misma partida sin poder espiar las cartas del otro.

## 2. Conceptos que usa el sistema

Antes de los requerimientos, un repaso corto de las ideas que aparecen en todo el documento.

- **HTTP (pedido y respuesta).** El navegador pregunta y el servidor contesta. El servidor no puede hablarle al navegador si el navegador no preguntó antes. Sirve para cargar páginas y para mandar una jugada.
- **WebSocket.** Una conexión que queda abierta entre el navegador y el servidor. Por ella el servidor puede avisar algo en cualquier momento, sin esperar a que le pregunten. Es lo que hace que la jugada del rival aparezca sola.
- **Evento de broadcasting.** Una clase de Laravel que implementa `ShouldBroadcast` (o `ShouldBroadcastNow`) y dice en qué canal se publica (`broadcastOn()`), con qué nombre (`broadcastAs()`) y con qué datos (`broadcastWith()`).
- **Canal.** El "tema" al que se suscribe el navegador. Un canal **público** (`Channel`) no pide autenticación; uno **privado** (`PrivateChannel`) o de **presencia** (`PresenceChannel`) sí, y se autoriza en `routes/channels.php`.
- **Laravel Reverb.** El servidor de WebSockets de Laravel. Es un proceso aparte (`php artisan reverb:start`, puerto 8080) que recibe los eventos de Laravel y los reparte a los navegadores suscritos.
- **Laravel Echo.** La librería JavaScript del navegador que se conecta a Reverb (usando `pusher-js` por debajo) y escucha eventos con `Echo.channel(...).listen(...)`.
- **Túnel.** Un servicio que le da a tu PC una dirección pública de internet sin tocar el router. Acá se usa Cloudflare Tunnel (`cloudflared`).

## 3. Alcance

**Incluye:**

- Crear una mesa e invitar a un rival con un link.
- Partida completa de truco a 15 o a 30 puntos, con o sin flor.
- Actualización en tiempo real de la mesa en las dos pantallas.
- Chat con frases típicas del truco y texto libre.
- Juego entre dos casas distintas mediante un túnel.

**No incluye:**

- Registro de usuarios ni contraseñas: cada navegador queda sentado en su asiento por la sesión de Laravel.
- Partidas de cuatro o seis jugadores.
- Ranking, historial de partidas ni publicación en un hosting permanente.

## 4. Actores

| Actor | Descripción |
| --- | --- |
| Anfitrión | Levanta el sistema en su PC, crea la mesa y comparte el link. Juega en el asiento 0. |
| Invitado | Abre el link desde su PC y se sienta en el asiento 1. No instala nada: solo usa el navegador. |
| Servidor Laravel | Valida cada jugada con las reglas del truco, guarda la partida y emite el aviso. |
| Reverb | Reparte el aviso por WebSocket a las pantallas suscritas al canal de la mesa. |

## 5. Requerimientos funcionales

| Código | Requerimiento | Dónde está |
| --- | --- | --- |
| RF-01 | El anfitrión crea una mesa con su nombre (hasta 20 caracteres), el objetivo (15 o 30) y si se juega con flor. | `SalaController@crear`, `inicio.blade.php` |
| RF-02 | Al crear la mesa se genera un link único para invitar. | `Sala::crear`, ruta `/sala/{sala}` |
| RF-03 | El invitado entra con el link, escribe su nombre y se sienta. Si la mesa ya tiene dos jugadores, se le avisa. | `SalaController@unirse` |
| RF-04 | La partida no arranca hasta que estén los dos jugadores. | `SalaController@accion` (responde 409) |
| RF-05 | Cada jugador ve sus tres cartas y solo el dorso de las del rival. | `Sala::vistaPara()` |
| RF-06 | El jugador puede tirar una carta cuando es su turno. | `Accion::JugarCarta` |
| RF-07 | El jugador puede cantar truco, retruco y vale cuatro, y responder quiero o no quiero. | `Partida`, `Accion` |
| RF-08 | El jugador puede cantar envido, real envido y falta envido en la primera baza, incluso como respuesta a un truco ("el envido está primero"). | `Partida` |
| RF-09 | Si la partida es con flor, el jugador con flor puede cantar flor, contraflor, contraflor al resto o "con flor me achico". | `Partida`, `Puntaje` |
| RF-10 | El jugador puede irse al mazo y el rival suma lo que corresponde. | `Accion::IrseAlMazo` |
| RF-11 | Solo se muestran los botones de las acciones permitidas en ese momento; si llega una acción inválida, el servidor la rechaza con el motivo. | `Partida::accionesDisponibles()`, `AccionInvalida` |
| RF-12 | El tanteador muestra los puntos de cada uno con palitos, separando malas y buenas. | `mesa.blade.php` |
| RF-13 | Los cantos y los puntos ganados aparecen como avisos sobre la mesa y en la lista "En la mesa". | `mesa.blade.php` |
| RF-14 | Al terminar una mano, la mesa sigue mostrando unos segundos cómo quedaron las cartas antes de repartir de nuevo. | `mesa.blade.php` |
| RF-15 | Al terminar la partida se muestra quién ganó y se puede jugar otra (revancha). | `SalaController@revancha` |
| RF-16 | El chat permite mandar frases predeterminadas del truco con un toque o escribir texto libre (hasta 200 caracteres). | `Mensaje::FRASES`, `SalaController@mensaje` |
| RF-17 | Cada jugada, mensaje o entrada del rival se ve en la otra pantalla sin recargar la página. | `MesaActualizada`, Echo en `mesa.blade.php` |
| RF-18 | Los cantos se escuchan con voz en los dos navegadores y se pueden silenciar. | `speechSynthesis` en `mesa.blade.php` |
| RF-19 | Se puede jugar desde dos casas distintas con un link público del túnel. | `tunel/proxy.mjs`, `cloudflared` |

## 6. Requerimientos no funcionales

| Código | Requerimiento | Cómo se cumple |
| --- | --- | --- |
| RNF-01 | **Tiempo real.** La jugada del rival se ve en menos de un segundo en condiciones normales. | Evento `ShouldBroadcastNow`: se emite en el momento, sin pasar por la cola. |
| RNF-02 | **Privacidad de las cartas.** Las cartas del rival nunca viajan al navegador del otro jugador. | El evento va vacío; cada pantalla pide su propia vista por HTTP. |
| RNF-03 | **Tolerancia a fallas.** Si Reverb se cae, el juego sigue funcionando. | La pantalla consulta el estado cada 5 segundos y muestra "○ sin vivo". |
| RNF-04 | **Consistencia.** Dos jugadas al mismo tiempo no pisan la partida. | Transacción con `lockForUpdate()` al ejecutar cada acción. |
| RNF-05 | **Validación.** Toda entrada se valida en el servidor. | `$request->validate()` con reglas por campo y `Rule::enum(Accion::class)`. |
| RNF-06 | **Protección del chat.** Un jugador no puede inundar el chat. | Middleware `throttle:30,1` (30 mensajes por minuto). |
| RNF-07 | **Instalación simple.** Se levanta con un solo comando. | `composer run setup` la primera vez y `composer run mesa` para jugar. |
| RNF-08 | **Sin costo.** No requiere servicios pagos. | Reverb corre en la misma PC y el túnel rápido de Cloudflare es gratis. |
| RNF-09 | **Calidad.** Las reglas y el flujo web están cubiertos por tests automáticos. | 59 tests con PHPUnit (reglas en `tests/Unit/Truco`, flujo web en `tests/Feature/SalaTest.php`) que corren en GitHub Actions. |
| RNF-10 | **Usabilidad.** Se puede jugar desde el celular. | Mesa adaptable al ancho de pantalla. |

## 7. Arquitectura de tiempo real

### 7.1 Flujo de una jugada

```
Jugador A (navegador)                Laravel                 Reverb            Jugador B (navegador)
        │  POST /sala/{codigo}/accion   │                       │                        │
        │──────────────────────────────>│ valida y guarda       │                        │
        │                               │ MesaActualizada ─────>│                        │
        │<── vista de A (JSON) ─────────│                       │── "mesa.actualizada" ─>│
        │                               │                       │   (sin datos)          │
        │                               │<──────── GET /sala/{codigo}/estado ────────────│
        │                               │──────── vista de B (JSON) ────────────────────>│
```

1. A toca una carta o un canto. El navegador manda un `POST` normal.
2. Laravel valida la jugada con las reglas del truco, la guarda en la base de datos y emite el evento `MesaActualizada`.
3. Reverb recibe el evento y lo reparte a todos los que escuchan el canal `sala.{codigo}`.
4. Echo, en la pantalla de B, recibe `mesa.actualizada` y pide `GET /sala/{codigo}/estado`.
5. Laravel arma la vista de B (sus cartas, el dorso de las de A, puntos, chat) y se la devuelve.

### 7.2 Relación con el ejemplo de la unidad

| Ejemplo de la unidad (emisor/receptor) | Este TP | Por qué cambia |
| --- | --- | --- |
| `php artisan install:broadcasting --reverb` | Igual | Deja instalado Reverb, Echo y las variables del `.env`. |
| `RemoteStyleUpdate implements ShouldBroadcast` | `MesaActualizada implements ShouldBroadcastNow` | Se emite en el momento, sin depender de un queue worker. |
| `new Channel('style-channel')` | `new Channel("sala.{$codigo}")` | Un canal por mesa, así cada partida solo avisa a sus jugadores. |
| `broadcastAs(): 'RemoteStyleUpdate'` | `broadcastAs(): 'mesa.actualizada'` | En JS se escucha con punto adelante: `.listen('.mesa.actualizada', ...)`. |
| El evento lleva `newText` y `newStyle` | `broadcastWith()` devuelve `[]` | El canal es público: si llevara datos, cualquiera podría ver las cartas. |
| Echo configurado con `VITE_REVERB_HOST` y `VITE_REVERB_PORT` | Echo usa el host y el puerto de la página | Así funciona igual en `localhost` que por el túnel. |
| `php artisan serve` y `php artisan reverb:start` en dos terminales | `composer run mesa` levanta web, Reverb y proxy juntos | El proxy junta los dos puertos en el 8888 para usar un solo túnel. |

### 7.3 Decisiones de diseño

- **Canal público con aviso vacío en lugar de canal privado.** Un canal privado exige usuarios logueados y autorización en `routes/channels.php`. Como el juego no tiene registro, se eligió un canal público que solo dice "algo cambió"; la información privada viaja por HTTP, donde Laravel sabe qué asiento tiene cada navegador por la sesión.
- **Proxy en el puerto 8888.** El túnel gratis expone un solo puerto. `tunel/proxy.mjs` manda las conexiones WebSocket a Reverb (8080) y el resto a Laravel (8000).
- **Lógica del juego separada de Laravel.** Las reglas están en `app/Truco/` y no dependen del framework. Se pueden probar con tests unitarios sin base de datos ni servidor.

### 7.4 Variables de entorno

| Variable | Valor en `.env.example` | Para qué |
| --- | --- | --- |
| `BROADCAST_CONNECTION` | `reverb` | Laravel emite los eventos por Reverb. |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | `vale4`, `vale4-clave`, `vale4-secreto` | Identifican la app ante Reverb. La clave se pasa al navegador; el secreto no. |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | `localhost`, `8080`, `http` | Dónde Laravel encuentra a Reverb. |
| `DB_CONNECTION` | `sqlite` | Base de datos en un archivo, sin instalar MySQL. |

## 8. Modelo de datos

| Tabla | Campos principales | Descripción |
| --- | --- | --- |
| `salas` | `codigo`, `jugador0`, `jugador1`, `estado` (JSON) | Una mesa. La partida completa se guarda como JSON y se reconstruye con `Partida::desdeArray()`. |
| `mensajes` | `sala_id`, `jugador`, `texto` | Los mensajes del chat de cada mesa. |

## 9. Endpoints

| Método | Ruta | Qué hace |
| --- | --- | --- |
| GET | `/` | Portada para crear una mesa. |
| POST | `/salas` | Crea la mesa y sienta al anfitrión. |
| GET | `/sala/{codigo}` | Muestra la mesa o el formulario para sentarse. |
| POST | `/sala/{codigo}/unirse` | Sienta al invitado. |
| GET | `/sala/{codigo}/estado` | Devuelve la vista del jugador en JSON. |
| POST | `/sala/{codigo}/accion` | Ejecuta una jugada o un canto. |
| POST | `/sala/{codigo}/mensaje` | Manda un mensaje al chat. |
| POST | `/sala/{codigo}/revancha` | Empieza otra partida en la misma mesa. |

## 10. Criterios de aceptación

| Criterio | Cómo se verifica |
| --- | --- |
| Dos navegadores distintos juegan una partida completa. | Abrir la mesa en una ventana normal y el link en una de incógnito. |
| La jugada aparece en la otra pantalla sin recargar. | Indicador "● en vivo" encendido y la carta aparece sola. |
| El WebSocket no lleva las cartas. | En las herramientas del navegador (F12, Red, filtro WS) el mensaje `mesa.actualizada` llega con datos vacíos. |
| Un tercero no puede sentarse ni jugar. | Abrir el link en un tercer navegador: avisa que la mesa está completa. Test `test_un_tercero_no_puede_entrar`. |
| Funciona entre dos casas. | Con `cloudflared` corriendo, el rival entra con el link `https://...trycloudflare.com`. |
| Las reglas se respetan. | `composer run test` en verde. |

## 11. Tecnologías

| Categoría | Tecnología |
| --- | --- |
| Lenguaje y framework | PHP 8.2+, Laravel 12 |
| Tiempo real | Laravel Reverb (servidor) y Laravel Echo con pusher-js (cliente) |
| Interfaz | Blade y Alpine.js, compilado con Vite |
| Base de datos | SQLite |
| Túnel | Cloudflare Tunnel (`cloudflared`) y un proxy en Node.js |
| Tests | PHPUnit, GitHub Actions |
| Sistema operativo de desarrollo | Linux Mint |
