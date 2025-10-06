<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chunked Uploader</title>
    @vite(['resources/css/app.css','resources/js/uploader.js'])
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .dropzone { border: 2px dashed #94a3b8; border-radius: .5rem; padding: 2rem; background: #f8fafc; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="text-2xl font-bold mb-4">Chunked File Uploader</h1>
    <p class="mb-4 text-slate-700">Drop files below to upload in 5MB chunks via the /api/uploads endpoints. On completion, a public URL will be shown.</p>

    <div id="dropzone" class="dropzone">Drop files here or click to select</div>
    <div id="upload-list" class="mt-4 space-y-4"></div>

    <hr class="my-8">
    <livewire:openai-vector-manager />
</div>
</body>
</html>
