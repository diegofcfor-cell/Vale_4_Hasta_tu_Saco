@extends('layouts.app')

@section('contenido')
    <h1>Vale 4 Hasta tu Saco</h1>

    <div class="panel">
        @if ($sala->completa())
            <p style="margin-top:0">Esta mesa ya tiene dos jugadores.</p>
            <a class="boton" href="{{ route('inicio') }}">Crear otra mesa</a>
        @else
            <form method="POST" action="{{ route('salas.unirse', $sala) }}">
                @csrf
                <p style="margin-top:0"><strong>{{ $sala->jugador0 }}</strong> te invitó a jugar un truco.</p>

                <label for="nombre">Tu nombre</label>
                <input id="nombre" type="text" name="nombre" maxlength="20" required value="{{ old('nombre') }}" autofocus>

                @if ($errors->any())
                    <p class="error">{{ $errors->first() }}</p>
                @endif

                <p><button type="submit">Sentarme a jugar</button></p>
            </form>
        @endif
    </div>
@endsection
