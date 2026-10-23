# Cambios

Todas las versiones de Vale 4 Hasta tu Saco. Las versiones siguen [Versionado Semántico](https://semver.org/lang/es/): `MAYOR.MENOR.PARCHE`.

## [1.0.0] - 2026-10-23

Primera versión completa, presentada como Trabajo Práctico de Programación IV (UTN Facultad Regional Resistencia).

### Juego

- Truco para dos jugadores, a 15 o a 30 puntos, con o sin flor.
- Truco, retruco y vale cuatro; envido, real envido y falta envido, incluso "el envido está primero".
- Flor, contraflor, contraflor al resto y "con flor me achico".
- Irse al mazo, bazas pardas y mano que se alterna en cada vuelta.
- Revancha en la misma mesa al terminar la partida.

### Mesa

- Crear una mesa y compartir el link para que entre el rival, sin registrarse.
- Cada jugador ve solo sus cartas; las del rival aparecen boca abajo.
- Cartas reales de la baraja española (Naipes libres, CC BY-SA 3.0).
- Tanteador con palitos, malas y buenas.
- Avisos sobre la mesa cuando alguien canta o suma puntos, y la mesa de la mano anterior a la vista unos segundos.
- Voz al cantar en los dos navegadores, con botón para silenciar.

### Tiempo real

- Las jugadas, el chat y la entrada del rival llegan al instante con Laravel Reverb y Laravel Echo.
- El aviso por WebSocket va vacío: las cartas del rival nunca viajan por el canal.
- Si Reverb se cae, la pantalla se actualiza sola cada 5 segundos.
- `composer run mesa` levanta la web, Reverb y un proxy en un solo puerto (8888) para jugar desde otra casa con un túnel de Cloudflare.

### Chat

- Frases típicas del truco con un toque y texto libre de hasta 200 caracteres.

### Documentación y calidad

- Documento de requerimientos y manual del usuario en `docs/`.
- 59 tests automáticos (reglas del juego y flujo web) que corren en GitHub Actions con PHP 8.3 y 8.4.

[1.0.0]: https://github.com/diegofcfor-cell/vale_4_hasta_tu_saco/releases/tag/v1.0.0
