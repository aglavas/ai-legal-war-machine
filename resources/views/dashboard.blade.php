<!-- resources/views/dashboard.blade.php -->
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unified Dashboard</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <style>
        .glass { background: rgba(255,255,255,.65); backdrop-filter: saturate(140%) blur(8px); }
        .card:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(2,6,23,.10); }
        .badge { background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 9999px; font-size: .75rem; }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900">
<header class="relative">
    <div class="absolute inset-0 -z-10 bg-gradient-to-br from-sky-500 via-indigo-500 to-fuchsia-500 opacity-90"></div>
    <div class="max-w-7xl mx-auto px-4 py-10 sm:py-14">
        <div class="glass rounded-2xl p-6 sm:p-8 shadow-xl">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Unified Dashboard</h1>
                    <p class="mt-2 text-slate-600">Access timelines, file uploader, OpenAI logs, and ingested laws from one place.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:text-sky-600">
                        <!-- home icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7M4 10v10h6v-6h4v6h6V10"/></svg>
                        Home
                    </a>
                    <a href="/dashboard" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700">
                        <!-- dashboard icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-90" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z"/></svg>
                        Dashboard
                    </a>
                </div>
            </div>
            <div class="mt-6 flex flex-col sm:flex-row gap-4 sm:items-center">
                <div class="relative flex-1">
                    <input id="dash-search" type="search" placeholder="Search tiles (e.g. timeline, uploader, logs)..."
                           class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 pl-10 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M10 3.5a6.5 6.5 0 1 0 3.96 11.68l4.43 4.43a.75.75 0 1 0 1.06-1.06l-4.43-4.43A6.5 6.5 0 0 0 10 3.5Zm-5 6.5a5 5 0 1 1 10 0 5 5 0 0 1-10 0Z" clip-rule="evenodd"/></svg>
                </div>
                <div class="flex gap-2">
                    <a href="/uploader" class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white hover:text-sky-600">
                        <!-- upload icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2Zm7-16 5 5h-3v4h-4v-4H7l5-5Z"/></svg>
                        Quick upload
                    </a>
                    <a href="/timeline" class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white hover:text-sky-600">
                        <!-- clock icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 10.414V7h-2v6a1 1 0 0 0 .293.707l4 4 1.414-1.414L13 12.414Z"/></svg>
                        Open timeline
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-10">
    <!-- e‑Predmet widget placed close to the hero section -->
    <section class="mb-8">
        <livewire:epredmet-widget />
    </section>

    <section>
        <h2 class="sr-only">Tiles</h2>
        <div id="tiles" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

            <!-- Timeline (primary) -->
            <a href="/timeline" data-title="timeline events kp-do pp prz comparative"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-indigo-100 p-2 text-indigo-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 10.414V7h-2v6a1 1 0 0 0 .293.707l4 4 1.414-1.414L13 12.414Z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Timeline</h3>
                            <p class="text-sm text-slate-600">Interactive event view with filters and jump-to-date.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-indigo-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- GUP timeline -->
            <a href="/comparative-timeline" data-title="timeline gup"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-fuchsia-100 p-2 text-fuchsia-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l9 4-9 4-9-4 9-4Zm0 7 9 4-9 4-9-4 9-4Z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Comparative Timeline</h3>
                            <p class="text-sm text-slate-600">Side‑by‑side timeline comparison.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-fuchsia-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <a href="/transcript" data-title="transcript"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-violet-100 p-2 text-violet-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 5h6v14H4zM14 5h6v10h-6z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Transcript</h3>
                            <p class="text-sm text-slate-600">Transcript preview and analysis.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-violet-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- e‑Oglasna Monitoring -->
            <a href="/eoglasna" data-title="eoglasna court monitoring keywords notices osijek"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-sky-100 p-2 text-sky-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 6h6v2h-6V8ZM5 14h14v2H5v-2Zm0-6h6v2H5V8Z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">e‑Oglasna Monitoring</h3>
                            <p class="text-sm text-slate-600">Court notices (Osijek), keywords, and activity feed.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-sky-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <a href="/uploader" data-title="uploader files chunk upload file manager"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-sky-100 p-2 text-sky-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2Zm7-16 5 5h-3v4h-4v-4H7l5-5Z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Chunked Uploader</h3>
                            <p class="text-sm text-slate-600">Upload large files in 5MB chunks and get a public URL.</p>
                        </div>
                    </div>
                    <span class="badge">Tools</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-sky-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- OpenAI logs -->
            <a href="/openai/logs" data-title="openai logs requests responses"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-emerald-100 p-2 text-emerald-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h16v4H4zM4 10h10v4H4zM4 16h16v4H4z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">OpenAI Logs</h3>
                            <p class="text-sm text-slate-600">Inspect prompts, responses, and metadata.</p>
                        </div>
                    </div>
                    <span class="badge">Ops</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-emerald-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Ingested laws -->
            <a href="/ingested-laws" data-title="ingested laws vector store documents"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-amber-100 p-2 text-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 7h4l-4-4v4Z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Ingested Laws</h3>
                            <p class="text-sm text-slate-600">Browse and manage ingested legal documents.</p>
                        </div>
                    </div>
                    <span class="badge">Data</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-amber-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- Textract Pipeline Manager -->
            <a href="/textract" data-title="textract pipeline ocr pdf aws processing reconstruction"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-cyan-100 p-2 text-cyan-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6ZM6 20V4h7v5h5v11H6Zm2-8h8v2H8v-2Zm0 4h8v2H8v-2Z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Textract Pipeline</h3>
                            <p class="text-sm text-slate-600">Process PDFs with OCR and generate searchable documents.</p>
                        </div>
                    </div>
                    <span class="badge">Tools</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-cyan-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>

            <!-- AI Chatbot -->
            <a href="/chatbot" data-title="chatbot ai assistant legal research court decisions chat"
               class="card group block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-purple-100 p-2 text-purple-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 5.52 4.48 10 10 10s10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">AI Legal Assistant</h3>
                            <p class="text-sm text-slate-600">Chat with AI for legal research and case analysis.</p>
                        </div>
                    </div>
                    <span class="badge">AI</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-purple-600 group-hover:gap-3 transition-all">
                    <span>Open</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/></svg>
                </div>
            </a>
        </div>
    </section>

    <section class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Quick previews</h2>
            <p class="text-sm text-slate-500">Lightweight iframes; open full view from tiles above for best UX.</p>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <div class="font-semibold">Timeline</div>
                    <a href="/timeline" class="text-sky-600 hover:underline text-sm">Open</a>
                </div>
                <iframe src="/timeline" class="w-full h-[430px] rounded-b-2xl" loading="lazy"></iframe>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <div class="font-semibold">Uploader</div>
                    <a href="/uploader" class="text-sky-600 hover:underline text-sm">Open</a>
                </div>
                <iframe src="/uploader" class="w-full h-[430px] rounded-b-2xl" loading="lazy"></iframe>
            </div>
        </div>
    </section>
</main>

<footer class="max-w-7xl mx-auto px-4 pb-10 pt-6 text-sm text-slate-500">
    <div class="flex items-center justify-between">
        <span>© {{ date('Y') }} Dashboard</span>
        <a class="hover:text-sky-600" href="/dashboard">Back to top</a>
    </div>
</footer>

<script>
    // Simple search filter for tiles
    const q = document.getElementById('dash-search');
    const tiles = document.getElementById('tiles').children;
    q?.addEventListener('input', () => {
        const term = q.value.toLowerCase().trim();
        for (const el of tiles) {
            const hay = (el.getAttribute('data-title') || '').toLowerCase();
            const title = el.querySelector('h3')?.textContent?.toLowerCase() || '';
            el.style.display = (!term || hay.includes(term) || title.includes(term)) ? '' : 'none';
        }
    });
</script>
@livewireScripts
</body>
</html>
