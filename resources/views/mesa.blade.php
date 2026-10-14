@extends('layouts.app')

@push('estilos')
<style>
    .marcador {
        display: flex; justify-content: space-between; align-items: center; gap: 12px;
        background: var(--pano-oscuro); border-radius: 12px; padding: 12px 16px; margin-bottom: 12px;
    }
    .marcador .puntos { font-size: 1.8rem; font-weight: 700; }
    .marcador .nombre { font-size: .9rem; opacity: .85; }
    .estado { text-align: center; font-weight: 600; margin: 8px 0 12px; min-height: 1.5em; }
    .fila { display: flex; justify-content: center; gap: 8px; min-height: 104px; align-items: center; }
    .carta {
        width: 68px; height: 100px; border-radius: 8px; background: var(--papel); color: var(--tinta);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, .35); border: 3px solid transparent; user-select: none;
    }
    .carta .numero { font-size: 1.7rem; font-weight: 700; line-height: 1; }
    .carta .palo { font-size: .75rem; margin-top: 6px; text-transform: capitalize; font-weight: 600; }
    .carta.espada { border-color: var(--espada); } .carta.espada .numero { color: var(--espada); }
    .carta.basto { border-color: var(--basto); } .carta.basto .numero { color: var(--basto); }
    .carta.oro { border-color: var(--oro); } .carta.oro .numero { color: var(--oro); }
    .carta.copa { border-color: var(--copa); } .carta.copa .numero { color: var(--copa); }
    .carta.dorso { background: repeating-linear-gradient(45deg, #7a2e2e, #7a2e2e 6px, #8e3a3a 6px, #8e3a3a 12px); }
    .carta.vacia { background: transparent; box-shadow: none; border: 2px dashed rgba(255, 255, 255, .25); }
    button.carta { padding: 0; font: inherit; cursor: pointer; }
    button.carta:disabled { opacity: .8; }
    button.carta:not(:disabled):hover { transform: translateY(-6px); }
    .mesa { display: flex; justify-content: center; gap: 16px; margin: 12px 0; }
    .baza { display: flex; flex-direction: column; gap: 6px; align-items: center; }
    .baza .resultado { font-size: .75rem; opacity: .85; min-height: 1em; }
    .acciones { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin: 16px 0; }
    .acciones button.secundaria { background: var(--papel); }
    .invitar { margin-bottom: 16px; }
    .invitar input { font-family: ui-monospace, monospace; }
    .invitar .copiar { display: flex; gap: 8px; margin-top: 8px; }
    .eventos { background: var(--pano-oscuro); border-radius: 12px; padding: 12px 16px; font-size: .9rem; }
    .eventos ul { margin: 0; padding-left: 18px; }
    .aviso { background: var(--peligro); color: white; border-radius: 8px; padding: 8px 12px; text-align: center; margin-bottom: 12px; }
    .chat { background: var(--papel); color: var(--tinta); border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; }
    .chat .mensajes { max-height: 220px; overflow-y: auto; margin: 8px 0; display: flex; flex-direction: column; gap: 6px; }
    .chat .mensaje { align-self: flex-start; background: #ece6d6; border-radius: 10px; padding: 6px 10px; max-width: 85%; overflow-wrap: anywhere; }
    .chat .mensaje.mio { align-self: flex-end; background: #f3dc9b; }
    .chat .frases { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .chat .frases button { font-size: .85rem; padding: 6px 10px; background: #ece6d6; font-weight: 500; }
    .chat .escribir { display: flex; gap: 8px; }
    .final { text-align: center; }
    .final h2 { margin-top: 0; }
    @media (max-width: 420px) {
        .carta { width: 56px; height: 84px; }
        .carta .numero { font-size: 1.4rem; }
        .mesa { gap: 8px; }
    }
</style>
@endpush

@section('contenido')
<div x-data="mesa(@js($sala->codigo), @js(route('salas.estado', $sala)), @js(route('salas.accion', $sala)), @js(route('salas.revancha', $sala)), @js(route('salas.mensaje', $sala)), @js(\App\Models\Mensaje::FRASES))" x-init="iniciar()" x-cloak>
    <template x-if="v">
        <div>
            <div class="panel invitar" x-show="v.esperandoRival">
                <strong>Mandale este link a tu rival</strong>
                <div class="copiar">
                    <input type="text" readonly :value="v.link" @focus="$event.target.select()">
                    <button type="button" @click="copiar()" x-text="copiado ? '¡Copiado!' : 'Copiar'"></button>
                </div>
                <p class="ayuda">La partida empieza cuando entre.</p>
            </div>

            <div class="marcador">
                <div>
                    <div class="nombre" x-text="v.nombres.yo + (v.soyMano ? ' (mano)' : '')"></div>
                    <div class="puntos" x-text="v.puntos.yo"></div>
                </div>
                <div class="ayuda" style="color:inherit;opacity:.7;text-align:center">
                    a <span x-text="v.objetivo"></span><br>
                    <span x-text="v.conFlor ? 'con flor' : 'sin flor'"></span><br>
                    <span :title="enVivo ? 'Conectado en vivo' : 'Sin conexión en vivo: se actualiza cada 5 segundos'"
                          x-text="enVivo ? '● en vivo' : '○ sin vivo'"></span>
                </div>
                <div style="text-align:right">
                    <div class="nombre" x-text="(v.nombres.rival ?? 'Esperando…') + (!v.soyMano && v.nombres.rival ? ' (mano)' : '')"></div>
                    <div class="puntos" x-text="v.puntos.rival"></div>
                </div>
            </div>

            <div class="aviso" x-show="error" x-text="error"></div>

            <div class="panel final" x-show="v.terminada">
                <h2 x-text="v.gane ? '¡Ganaste! 🎉' : 'Perdiste la partida'"></h2>
                <button type="button" @click="revancha()">Jugar otra</button>
            </div>

            <div x-show="!v.terminada">
                <div class="fila">
                    <template x-for="i in v.cartasRival" :key="'r' + i">
                        <div class="carta dorso"></div>
                    </template>
                </div>

                <div class="mesa">
                    <template x-for="i in [0, 1, 2]" :key="'b' + i">
                        <div class="baza">
                            <div x-html="dibujar(v.mesa[i]?.rival)"></div>
                            <div x-html="dibujar(v.mesa[i]?.yo)"></div>
                            <div class="resultado" x-text="resultado(v.bazas[i])"></div>
                        </div>
                    </template>
                </div>

                <div class="estado" x-text="mensaje()"></div>

                <div class="fila">
                    <template x-for="c in v.misCartas" :key="c.id">
                        <button type="button" class="carta" :class="c.palo" :disabled="!puede('jugar_carta') || enviando"
                                @click="hacer('jugar_carta', c.id)" :title="c.numero + ' de ' + c.palo">
                            <span class="numero" x-text="c.numero"></span>
                            <span class="palo" x-text="c.palo"></span>
                        </button>
                    </template>
                </div>

                <div class="acciones">
                    <template x-for="a in v.acciones.filter(a => a.accion !== 'jugar_carta')" :key="a.accion">
                        <button type="button" :class="{ secundaria: a.accion === 'irse_al_mazo' || a.accion === 'no_quiero' }"
                                :disabled="enviando" @click="hacer(a.accion)" x-text="a.etiqueta"></button>
                    </template>
                </div>
            </div>

            <div class="chat">
                <strong>Chat</strong>
                <div class="mensajes" x-ref="mensajes">
                    <template x-if="v.chat.length === 0">
                        <p class="ayuda" style="margin:0">Todavía no hay mensajes.</p>
                    </template>
                    <template x-for="m in v.chat" :key="m.id">
                        <div class="mensaje" :class="{ mio: m.mio }">
                            <strong x-text="m.mio ? 'Vos' : m.nombre"></strong>
                            <span x-text="m.texto"></span>
                        </div>
                    </template>
                </div>
                <div class="frases">
                    <template x-for="f in frases" :key="f">
                        <button type="button" :disabled="enviando" @click="decir(f)" x-text="f"></button>
                    </template>
                </div>
                <form class="escribir" @submit.prevent="decir(texto)">
                    <input type="text" x-model="texto" maxlength="200" placeholder="Escribí algo…" aria-label="Mensaje">
                    <button type="submit" :disabled="enviando || !texto.trim()">Enviar</button>
                </form>
            </div>

            <div class="eventos">
                <strong>En la mesa</strong>
                <ul>
                    <template x-for="(e, i) in v.eventos.slice().reverse()" :key="i">
                        <li><span x-show="e.quien"><strong x-text="e.quien"></strong> </span><span x-text="e.texto"></span></li>
                    </template>
                </ul>
            </div>
        </div>
    </template>
</div>

<script>
    function mesa(codigo, urlEstado, urlAccion, urlRevancha, urlMensaje, frases) {
        const csrf = document.querySelector('meta[name=csrf-token]').content;

        return {
            v: null,
            error: '',
            enviando: false,
            copiado: false,
            enVivo: false,
            frases,
            texto: '',
            ultimoMensaje: null,

            iniciar() {
                this.actualizar();
                this.escuchar();
                // Por las dudas: si Reverb no anda, igual se actualiza cada tanto.
                setInterval(() => { if (!this.enviando) this.actualizar(); }, 5000);
            },

            escuchar() {
                if (!window.Echo) return;
                window.Echo.channel(`sala.${codigo}`).listen('.mesa.actualizada', () => this.actualizar());
                const conexion = window.Echo.connector.pusher.connection;
                this.enVivo = conexion.state === 'connected';
                conexion.bind('state_change', ({ current }) => {
                    const volvio = current === 'connected' && !this.enVivo;
                    this.enVivo = current === 'connected';
                    if (volvio) this.actualizar();
                });
            },

            async actualizar() {
                const r = await fetch(urlEstado, { headers: { Accept: 'application/json' } });
                if (r.ok) this.poner(await r.json());
            },

            poner(v) {
                this.v = v;
                const ultimo = v.chat.at(-1)?.id ?? null;
                if (ultimo !== this.ultimoMensaje) {
                    this.ultimoMensaje = ultimo;
                    this.$nextTick(() => {
                        const caja = this.$refs.mensajes;
                        if (caja) caja.scrollTop = caja.scrollHeight;
                    });
                }
            },

            async enviar(url, cuerpo) {
                this.enviando = true;
                this.error = '';
                try {
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: JSON.stringify(cuerpo),
                    });
                    const datos = await r.json();
                    if (r.ok) this.poner(datos);
                    else this.error = datos.error ?? datos.message ?? 'Algo salió mal.';
                    return r.ok;
                } catch {
                    this.error = 'No hay conexión con la mesa.';
                } finally {
                    this.enviando = false;
                }
            },

            hacer(accion, carta = null) {
                this.enviar(urlAccion, { accion, carta });
            },

            async decir(texto) {
                if (!texto.trim()) return;
                if (await this.enviar(urlMensaje, { texto })) this.texto = '';
            },

            revancha() {
                this.enviar(urlRevancha, {});
            },

            puede(accion) {
                return this.v.acciones.some(a => a.accion === accion);
            },

            async copiar() {
                try {
                    await navigator.clipboard.writeText(this.v.link);
                    this.copiado = true;
                    setTimeout(() => this.copiado = false, 2000);
                } catch {
                    // Sin permiso de portapapeles: el input queda seleccionable a mano.
                }
            },

            dibujar(c) {
                if (!c) return '<div class="carta vacia"></div>';
                return `<div class="carta ${c.palo}"><span class="numero">${c.numero}</span><span class="palo">${c.palo}</span></div>`;
            },

            resultado(r) {
                return { yo: 'Ganaste', rival: 'Perdiste', parda: 'Parda' }[r] ?? '';
            },

            mensaje() {
                const v = this.v;
                if (v.esperandoRival) return 'Esperando a tu rival…';
                if (v.pendiente?.respondo) return 'Te cantaron: ¿qué respondés?';
                if (v.pendiente) return `Esperando la respuesta de ${v.nombres.rival}…`;
                if (v.turno === 'yo') return 'Te toca';
                return `Juega ${v.nombres.rival}`;
            },
        };
    }
</script>
@endsection
