@extends('layouts.app')

@section('contenido')
    <h1>Vale 4 Hasta tu Saco</h1>

    <form class="panel" method="POST" action="{{ route('salas.crear') }}">
        @csrf
        <p style="margin-top:0">Creá una mesa y mandale el link a tu rival.</p>

        <label for="nombre">Tu nombre</label>
        <input id="nombre" type="text" name="nombre" maxlength="20" required value="{{ old('nombre') }}" autofocus>

        <label for="objetivo">Partida a</label>
        <select id="objetivo" name="objetivo">
            <option value="30">30 puntos</option>
            <option value="15">15 puntos</option>
        </select>

        <label class="check">
            <input type="hidden" name="con_flor" value="0">
            <input type="checkbox" name="con_flor" value="1" checked> Jugar con flor
        </label>

        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif

        <p><button type="submit">Crear mesa</button></p>
    </form>
@endsection
