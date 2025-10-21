<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenAI Responses</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
    </style>
</head>
<body class="bg-slate-50">
<div class="container">
    <h1 class="text-2xl font-semibold text-slate-800 mb-4">OpenAI Responses</h1>
    <livewire:openai-responses-viewer />
</div>
@livewireScripts
</body>
</html>

