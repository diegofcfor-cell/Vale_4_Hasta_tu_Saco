# Manual del usuario

**Vale 4 Hasta tu Saco** es el truco, juego de cartas españolas típico de Argentina, para dos jugadores, cada uno desde su PC.

Este manual tiene dos partes: la del **anfitrión**, que levanta el juego en su computadora, y la del **jugador**, que sirve para los dos (el invitado solo necesita esta parte).

---

## Parte 1. Para el anfitrión: preparar la mesa

### 1.1 Qué necesitás

- Una PC con Linux (probado en Linux Mint) con PHP 8.2 o más nuevo, Composer y Node.js.
- El proyecto descargado:

  ```bash
  git clone https://github.com/diegofcfor-cell/vale_4_hasta_tu_saco.git
  cd vale_4_hasta_tu_saco
  ```

### 1.2 Instalar (una sola vez)

```bash
composer run setup
```

Este comando instala las dependencias de PHP y de JavaScript, crea el archivo `.env` con la configuración, arma la base de datos SQLite y compila la parte visual.

### 1.3 Levantar el juego (cada vez que quieras jugar)

```bash
composer run mesa
```

Se levantan tres programas juntos, cada uno con su color en la terminal:

| Nombre | Qué es | Puerto |
| --- | --- | --- |
| `web` | El servidor de Laravel (`php artisan serve`) | 8000 |
| `reverb` | El servidor de WebSockets, el que avisa las jugadas al instante | 8080 |
| `mesa` | Un proxy que junta los dos anteriores en una sola dirección | 8888 |

Entrá siempre por **http://localhost:8888**. No cierres esa terminal mientras jueguen.

### 1.4 Jugar con alguien de otra casa

Tu PC no es visible desde internet. Para que tu rival entre, se usa un **túnel**: un servicio gratis de Cloudflare que le da a tu PC una dirección pública por un rato, sin crear cuenta ni configurar el router.

1. Instalá `cloudflared` (una sola vez):

   ```bash
   wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
   sudo dpkg -i cloudflared-linux-amd64.deb
   ```

2. Con `composer run mesa` funcionando, abrí **otra terminal** y corré:

   ```bash
   cloudflared tunnel --url http://localhost:8888
   ```

3. Buscá en lo que muestra una dirección parecida a `https://palabras-raras.trycloudflare.com`.
4. **Abrí vos esa dirección** en tu navegador (no `localhost`) y creá la mesa desde ahí. Así el link de invitación sale con la dirección pública.

La dirección cambia cada vez que abrís el túnel. Cuando terminen, cerrá el túnel con **Ctrl+C**: mientras esté abierto, cualquiera con la dirección puede ver la portada.

### 1.5 Probar solo

Para probar sin otra persona, abrí el link de invitación en una **ventana de incógnito** (Ctrl+Shift+N en Chrome, Ctrl+Shift+P en Firefox). Cada ventana cuenta como un jugador distinto.

---

## Parte 2. Para los dos jugadores: jugar

### 2.1 Crear la mesa (anfitrión)

1. En la portada escribí **tu nombre** (hasta 20 letras).
2. Elegí **Partida a** 15 o 30 puntos.
3. Dejá marcado **Jugar con flor** si quieren jugar con flor, o desmarcalo.
4. Tocá **Crear mesa**.
5. Arriba de la mesa aparece "Mandale este link a tu rival". Tocá **Copiar** y mandáselo a tu rival por WhatsApp, mail o donde quieras. La partida empieza cuando entre.

### 2.2 Entrar a la mesa (invitado)

1. Abrí el link que te mandaron.
2. Vas a ver "*Fulano* te invitó a jugar un truco". Escribí **tu nombre** y tocá **Sentarme a jugar**.

Si aparece "Esta mesa ya tiene dos jugadores", el link ya se usó: pedile al anfitrión que cree otra mesa.

> **Importante:** el juego te recuerda por el navegador. Si cambiás de navegador o borrás las cookies, no vas a poder volver a sentarte en esa mesa.

### 2.3 La pantalla de la mesa

De arriba hacia abajo:

| Parte | Qué muestra |
| --- | --- |
| **Tanteador** | Tu nombre a la izquierda y el del rival a la derecha, con los puntos en número y en palitos (cuadrados de cinco), separados en malas y buenas. "(mano)" indica quién es mano en esta vuelta. |
| **Centro del tanteador** | A cuántos puntos se juega, si es con o sin flor, el estado de la conexión y el botón de sonido. |
| **Cartas del rival** | Boca abajo. Solo ves cuántas le quedan. |
| **Mesa** | Tres lugares, uno por baza. Arriba la carta del rival, abajo la tuya, y quién ganó cada baza. |
| **Avisos** | Carteles que aparecen sobre la mesa cuando alguien canta ("Rival: ¡Truco!") o suma puntos ("Diego gana 2 puntos por el envido"). |
| **Estado** | Una línea que te dice qué pasa: "Te toca", "Juega *rival*", "Te cantaron: ¿qué respondés?", "Así terminó la mano". |
| **Tus cartas** | Tus tres cartas. Tocá una para tirarla cuando es tu turno. |
| **Botones de canto** | Solo aparecen los cantos que podés hacer en ese momento. |
| **Chat** | Mensajes de los dos, frases rápidas y una caja para escribir. |
| **En la mesa** | La lista de todo lo que pasó, de lo más nuevo a lo más viejo. |

### 2.4 El indicador de conexión

- **● en vivo**: la conexión WebSocket está activa. Las jugadas del rival aparecen al instante.
- **○ sin vivo**: no hay conexión en tiempo real. El juego sigue andando, pero la pantalla se actualiza cada 5 segundos. Avisale al anfitrión: probablemente se cortó Reverb.

### 2.5 Cómo jugar

**Tirar una carta.** Cuando dice "Te toca", tocá una de tus cartas. Si no es tu turno, las cartas aparecen apagadas.

**Cantar.** Usá los botones debajo de tus cartas. Solo aparecen los que valen en ese momento:

| Botón | Cuándo aparece |
| --- | --- |
| ¡Truco! / ¡Quiero retruco! / ¡Quiero vale cuatro! | Para subir la apuesta de la mano. Solo puede subir quien tiene el quiero. |
| Envido / Real envido / Falta envido | En la primera baza, antes de tirar tu carta. También como respuesta a un truco ("el envido está primero"). |
| ¡Flor! / Contraflor / Contraflor al resto / Con flor me achico | Si se juega con flor y tenés tres cartas del mismo palo. |
| ¡Quiero! / No quiero | Cuando el rival te cantó algo. |
| Me voy al mazo | Siempre que puedas jugar. El rival se lleva lo que vale la mano. |

Cuando cantás, el rival lo ve como aviso en su mesa y lo escucha con voz.

**Terminar la mano.** Gana la mano quien gana dos bazas. Al terminar, la mesa queda a la vista unos segundos con el cartel "Así terminó la mano" para que veas cómo salió y después se reparte de nuevo.

**Terminar la partida.** Cuando alguien llega al objetivo aparece "¡Ganaste! 🎉" o "Perdiste la partida". Con **Jugar otra** empiezan una revancha en la misma mesa, sin mandar otro link.

Las reglas completas están en el [README](../README.md#reglas-implementadas).

### 2.6 El chat

- **Frases rápidas:** tocá cualquiera de los botones ("¡Buenas!", "Son buenas", "¡Mentira!", "¿Tenés el ancho?", "Jugá, que se enfría el mate"…) y se manda sola.
- **Texto libre:** escribí en la caja "Escribí algo…" (hasta 200 letras) y tocá **Enviar** o apretá Enter.
- Tus mensajes aparecen como "Vos" y los del rival con su nombre.

### 2.7 El sonido

Los cantos (truco, envido, flor, quiero, no quiero, al mazo) se leen en voz alta en los dos navegadores, con la voz en español que tenga tu sistema. Tocá **🔊** en el tanteador para silenciarlos (pasa a **🔇**) y otra vez para volver a escucharlos.

---

## Parte 3. Problemas frecuentes

| Problema | Qué hacer |
| --- | --- |
| La página no carga en `localhost:8888`. | Revisá que `composer run mesa` siga corriendo y que no haya errores en rojo en la terminal. |
| Dice "○ sin vivo". | Reverb no está corriendo o el navegador no lo alcanza. Cortá `composer run mesa` con Ctrl+C y volvé a levantarlo. Mientras tanto se puede seguir jugando. |
| Mi rival no puede abrir el link. | Fijate que el link empiece con `https://...trycloudflare.com` y no con `localhost`. Si empieza con `localhost`, creá la mesa de nuevo entrando por la dirección del túnel. |
| El link del túnel dejó de andar. | El túnel se cerró o se cortó. Volvé a correr `cloudflared` y creá una mesa nueva con la dirección nueva. |
| "No estás sentado en esta mesa." | Estás en otro navegador o se borraron las cookies. Creá una mesa nueva. |
| "Esperá a que entre tu rival." | La partida arranca cuando el invitado se sienta. |
| No escucho los cantos. | Revisá que el botón diga 🔊 y que el navegador tenga permiso de sonido. Algunos navegadores no tienen voces en español instaladas. |
| Un botón de canto no aparece. | No está permitido en ese momento (por ejemplo, el envido después de la primera baza). |
