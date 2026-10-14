<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Vale 4 Hasta tu Saco')</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        :root {
            --pano: #1f5f3a;
            --pano-oscuro: #164a2c;
            --papel: #fdfaf2;
            --tinta: #1d1d1b;
            --suave: #6b6b63;
            --acento: #d9a521;
            --peligro: #b3261e;
            --espada: #2f4f8f;
            --basto: #2f6b2f;
            --oro: #b8860b;
            --copa: #a52a2a;
        }
        * { box-sizing: border-box; }
        [x-cloak] { display: none !important; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--pano);
            color: var(--papel);
        }
        main { max-width: 760px; margin: 0 auto; padding: 16px; }
        h1 { margin: 8px 0 16px; font-size: 1.6rem; }
        a { color: var(--acento); }
        .panel {
            background: var(--papel);
            color: var(--tinta);
            border-radius: 12px;
            padding: 20px;
        }
        label { display: block; font-weight: 600; margin: 12px 0 6px; }
        input[type=text], select {
            width: 100%; padding: 10px; font-size: 1rem;
            border: 1px solid #ccc; border-radius: 8px;
        }
        .check { display: flex; gap: 8px; align-items: center; font-weight: 400; }
        button, .boton {
            display: inline-block; padding: 10px 16px; font-size: 1rem; font-weight: 600;
            border: 0; border-radius: 8px; cursor: pointer;
            background: var(--acento); color: var(--tinta); text-decoration: none;
        }
        button:disabled { opacity: .5; cursor: default; }
        .error { color: var(--peligro); margin-top: 8px; }
        .ayuda { color: var(--suave); font-size: .9rem; }
    </style>
    @stack('estilos')
</head>
<body>
    <main>
        @yield('contenido')
    </main>
</body>
</html>
