<!-- resources/views/agent/run.blade.php -->
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Research Run #{{ $run->id }}</title>
    @vite(['resources/css/app.css'])
    <style>
        .glass { background: rgba(255,255,255,.65); backdrop-filter: saturate(140%) blur(8px); }
        .badge { padding: 2px 8px; border-radius: 9999px; font-size: .75rem; font-weight: 600; }
        .badge-running { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .prose { max-width: none; }
        .prose h1 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; }
        .prose h2 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; }
        .prose h3 { font-size: 1.125rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; }
        .prose p { margin-bottom: 0.75rem; }
        .prose ul, .prose ol { margin-left: 1.5rem; margin-bottom: 0.75rem; }
        .prose li { margin-bottom: 0.25rem; }
        .prose code { background: #f1f5f9; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-900">
<header class="relative">
    <div class="absolute inset-0 -z-10 bg-gradient-to-br from-purple-600 via-indigo-600 to-blue-600 opacity-90"></div>
    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="glass rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-extrabold tracking-tight">Research Run #{{ $run->id }}</h1>
                        <span class="badge badge-{{ $run->status }}">{{ ucfirst($run->status) }}</span>
                    </div>
                    <p class="mt-2 text-slate-600">{{ $run->objective }}</p>
                </div>
                <div>
                    <a href="{{ route('agent.dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:text-purple-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M3 12l6-6M3 12l6 6"/></svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-8">
    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
            <p class="text-sm text-slate-600">Score</p>
            <p class="text-3xl font-bold mt-1 {{ $run->score >= 0.75 ? 'text-green-600' : 'text-orange-600' }}">
                {{ $run->score ? number_format($run->score, 3) : 'N/A' }}
            </p>
            @if($run->threshold)
            <p class="text-xs text-slate-500 mt-1">Threshold: {{ $run->threshold }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
            <p class="text-sm text-slate-600">Iterations</p>
            <p class="text-3xl font-bold text-slate-900 mt-1">{{ $run->current_iteration }}</p>
            <p class="text-xs text-slate-500 mt-1">Max: {{ $run->max_iterations }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
            <p class="text-sm text-slate-600">Duration</p>
            <p class="text-3xl font-bold text-slate-900 mt-1">
                @if($run->elapsed_seconds)
                    {{ gmdate('i:s', $run->elapsed_seconds) }}
                @else
                    -
                @endif
            </p>
            @if($run->time_limit_seconds)
            <p class="text-xs text-slate-500 mt-1">Limit: {{ gmdate('i:s', $run->time_limit_seconds) }}</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
            <p class="text-sm text-slate-600">Tokens / Cost</p>
            <p class="text-2xl font-bold text-slate-900 mt-1">
                {{ $run->tokens_used ? number_format($run->tokens_used) : 'N/A' }}
            </p>
            <p class="text-xs text-slate-500 mt-1">
                Cost: {{ $run->cost_spent ? '$' . number_format($run->cost_spent, 4) : 'N/A' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Final Output -->
            @if($run->final_output)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="text-lg font-semibold text-slate-900">Final Output</h2>
                </div>
                <div class="p-6 prose prose-slate max-w-none">
                    {!! Str::markdown($run->final_output) !!}
                </div>
            </div>
            @endif

            <!-- Iterations -->
            @if($run->iterations && count($run->iterations) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="text-lg font-semibold text-slate-900">Research Iterations</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        @foreach($run->iterations as $iteration)
                        <div class="border border-slate-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-slate-900">Iteration {{ $iteration['number'] ?? '?' }}</h3>
                                <span class="text-xs text-slate-500">
                                    {{ isset($iteration['started_at']) ? \Carbon\Carbon::parse($iteration['started_at'])->format('H:i:s') : '' }}
                                </span>
                            </div>

                            @if(isset($iteration['plan']))
                            <div class="mb-3">
                                <p class="text-sm font-medium text-slate-700 mb-1">Plan:</p>
                                <p class="text-sm text-slate-600">{{ $iteration['plan']['reasoning'] ?? 'N/A' }}</p>
                            </div>
                            @endif

                            @if(isset($iteration['actions']) && count($iteration['actions']) > 0)
                            <div class="mb-3">
                                <p class="text-sm font-medium text-slate-700 mb-2">Actions:</p>
                                <div class="space-y-2">
                                    @foreach($iteration['actions'] as $action)
                                    <div class="bg-slate-50 rounded p-3 text-sm">
                                        <div class="flex items-center gap-2 mb-1">
                                            <code class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-xs">
                                                {{ $action['tool'] ?? 'unknown' }}
                                            </code>
                                            @if($action['success'] ?? false)
                                                <span class="text-green-600 text-xs">✓ Success</span>
                                            @else
                                                <span class="text-red-600 text-xs">✗ Failed</span>
                                            @endif
                                        </div>
                                        @if(isset($action['error']))
                                        <p class="text-xs text-red-600 mt-1">{{ $action['error'] }}</p>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(isset($iteration['evaluation']['insights']) && count($iteration['evaluation']['insights']) > 0)
                            <div>
                                <p class="text-sm font-medium text-slate-700 mb-1">Insights:</p>
                                <ul class="text-sm text-slate-600 list-disc list-inside space-y-1">
                                    @foreach($iteration['evaluation']['insights'] as $insight)
                                    <li>{{ $insight }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Error -->
            @if($run->error)
            <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                <h3 class="text-lg font-semibold text-red-900 mb-2">Error</h3>
                <p class="text-sm text-red-700">{{ $run->error }}</p>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Metadata -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="text-lg font-semibold text-slate-900">Metadata</h2>
                </div>
                <div class="p-6 space-y-3 text-sm">
                    <div>
                        <p class="text-slate-600">Agent</p>
                        <p class="font-medium text-slate-900">{{ $run->agent_name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-600">Started</p>
                        <p class="font-medium text-slate-900">{{ $run->started_at?->format('M d, Y H:i:s') ?? 'N/A' }}</p>
                    </div>
                    @if($run->completed_at)
                    <div>
                        <p class="text-slate-600">Completed</p>
                        <p class="font-medium text-slate-900">{{ $run->completed_at->format('M d, Y H:i:s') }}</p>
                    </div>
                    @endif
                    @if($run->topics && count($run->topics) > 0)
                    <div>
                        <p class="text-slate-600 mb-1">Topics</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($run->topics as $topic)
                            <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Evaluation -->
            @if($evaluation)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="text-lg font-semibold text-slate-900">Evaluation</h2>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-slate-600">Overall Score</span>
                            <span class="font-bold text-lg {{ $evaluation['passed'] ? 'text-green-600' : 'text-orange-600' }}">
                                {{ number_format($evaluation['score'], 3) }}
                            </span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-{{ $evaluation['passed'] ? 'green' : 'orange' }}-600 h-2 rounded-full" style="width: {{ $evaluation['score'] * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm">
                        @foreach($evaluation['checks'] as $category => $check)
                        <div class="flex items-center justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-700 capitalize">{{ $category }}</span>
                            <span class="font-medium {{ ($check['passed'] ?? false) ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($check['score'] ?? 0, 2) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</main>
</body>
</html>
