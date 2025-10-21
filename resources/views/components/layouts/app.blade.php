<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MCP Tools' }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="bg-slate-50 min-h-screen">
    {{ $slot }}

    @livewireScripts
    @stack('scripts')
</body>
</html>

