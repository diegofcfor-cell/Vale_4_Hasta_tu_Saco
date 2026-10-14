<?php

namespace App\Http\Controllers;

use App\Events\MesaActualizada;
use App\Models\Sala;
use App\Truco\Accion;
use App\Truco\AccionInvalida;
use App\Truco\Partida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SalaController extends Controller
{
    public function inicio(): View
    {
        return view('inicio');
    }

    public function crear(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:20'],
            'objetivo' => ['required', Rule::in(['15', '30'])],
            'con_flor' => ['nullable', 'boolean'],
        ]);

        $sala = Sala::crear($datos['nombre'], (int) $datos['objetivo'], $request->boolean('con_flor'));
        $request->session()->put($this->claveAsiento($sala), 0);

        return redirect()->route('salas.ver', $sala);
    }

    public function ver(Request $request, Sala $sala): View
    {
        if ($this->asiento($request, $sala) !== null) {
            return view('mesa', ['sala' => $sala]);
        }

        return view('unirse', ['sala' => $sala]);
    }

    public function unirse(Request $request, Sala $sala): RedirectResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:20']]);

        $sentado = DB::transaction(function () use ($sala, $datos) {
            $sala = Sala::whereKey($sala->id)->lockForUpdate()->firstOrFail();

            if ($sala->completa()) {
                return false;
            }

            $sala->update(['jugador1' => $datos['nombre']]);

            return true;
        });

        if (! $sentado) {
            return redirect()->route('salas.ver', $sala)->withErrors(['nombre' => 'La sala ya tiene dos jugadores.']);
        }

        $request->session()->put($this->claveAsiento($sala), 1);
        $this->avisar($sala);

        return redirect()->route('salas.ver', $sala);
    }

    public function estado(Request $request, Sala $sala): JsonResponse
    {
        return response()->json($sala->vistaPara($this->asientoObligatorio($request, $sala)));
    }

    public function accion(Request $request, Sala $sala): JsonResponse
    {
        $jugador = $this->asientoObligatorio($request, $sala);
        $datos = $request->validate([
            'accion' => ['required', Rule::enum(Accion::class)],
            'carta' => ['nullable', 'string', 'max:12'],
        ]);

        $respuesta = DB::transaction(function () use ($sala, $jugador, $datos) {
            $sala = Sala::whereKey($sala->id)->lockForUpdate()->firstOrFail();

            if (! $sala->completa()) {
                return response()->json(['error' => 'Esperá a que entre tu rival.'], 409);
            }

            $partida = $sala->partida();

            try {
                $partida->ejecutar($jugador, Accion::from($datos['accion']), $datos['carta'] ?? null);
            } catch (AccionInvalida $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            $sala->guardarPartida($partida);
            $sala->save();

            return response()->json($sala->vistaPara($jugador));
        });

        return $this->avisarSiSalioBien($respuesta, $sala);
    }

    public function revancha(Request $request, Sala $sala): JsonResponse
    {
        $jugador = $this->asientoObligatorio($request, $sala);

        $respuesta = DB::transaction(function () use ($sala, $jugador) {
            $sala = Sala::whereKey($sala->id)->lockForUpdate()->firstOrFail();
            $anterior = $sala->partida();

            if (! $anterior->terminada()) {
                return response()->json(['error' => 'La partida todavía no terminó.'], 409);
            }

            $sala->guardarPartida(Partida::nueva($anterior->objetivo(), $anterior->conFlor()));
            $sala->save();

            return response()->json($sala->vistaPara($jugador));
        });

        return $this->avisarSiSalioBien($respuesta, $sala);
    }

    private function avisarSiSalioBien(JsonResponse $respuesta, Sala $sala): JsonResponse
    {
        if ($respuesta->isSuccessful()) {
            $this->avisar($sala);
        }

        return $respuesta;
    }

    /**
     * Le avisa al rival por Reverb. Si Reverb no está corriendo la jugada
     * igual vale: la pantalla también pregunta el estado cada tanto.
     */
    private function avisar(Sala $sala): void
    {
        try {
            MesaActualizada::dispatch($sala->codigo);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function claveAsiento(Sala $sala): string
    {
        return "salas.{$sala->codigo}";
    }

    private function asiento(Request $request, Sala $sala): ?int
    {
        return $request->session()->get($this->claveAsiento($sala));
    }

    private function asientoObligatorio(Request $request, Sala $sala): int
    {
        return $this->asiento($request, $sala) ?? abort(403, 'No estás sentado en esta mesa.');
    }
}
