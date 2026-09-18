<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>OsonPOS</title>
        @if (isset($posSetup))
            <script>window.__OSONPOS_SETUP__ = @json($posSetup);</script>
        @endif
        <script>window.__OSONPOS_FLASH__ = @json(['logoutError' => session('pos_logout_error')]);</script>
        @vite('resources/js/pos/app.ts')
    </head>
    <body class="bg-slate-950 text-slate-100 antialiased">
        <div id="pos-app"></div>
    </body>
</html>
