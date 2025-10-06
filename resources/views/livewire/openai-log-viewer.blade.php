<div class="container mx-auto max-w-6xl p-4">
    <h1 class="text-2xl font-bold mb-4">OpenAI API Logs</h1>

    <div class="bg-white border rounded-md p-3 mb-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm">Limit
                <select class="border rounded px-2 py-1 text-sm" wire:model.live="limit">
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="500">500</option>
                </select>
            </label>
            <label class="text-sm">Search
                <input type="text" class="border rounded px-2 py-1 text-sm" placeholder="text, id, status…" wire:model.debounce.500ms="search" />
            </label>
            <label class="text-sm">Request ID
                <input type="text" class="border rounded px-2 py-1 text-sm" placeholder="req-uuid" wire:model.debounce.500ms="requestId" />
            </label>

            <div class="flex items-center gap-2 text-sm">
                <button type="button" class="px-2 py-1 border rounded {{ $eventTypes['openai.request'] ? 'bg-green-50 border-green-300' : '' }}" wire:click="toggleEvent('openai.request')">Request</button>
                <button type="button" class="px-2 py-1 border rounded {{ $eventTypes['openai.response'] ? 'bg-blue-50 border-blue-300' : '' }}" wire:click="toggleEvent('openai.response')">Response</button>
                <button type="button" class="px-2 py-1 border rounded {{ $eventTypes['openai.error'] ? 'bg-red-50 border-red-300' : '' }}" wire:click="toggleEvent('openai.error')">Error</button>
            </div>

            <div class="flex-1"></div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="autoRefresh" /> Auto refresh
            </label>
            <button type="button" class="px-3 py-1.5 border rounded text-sm" wire:click="refreshNow">Refresh</button>
            <button type="button" class="px-3 py-1.5 border rounded text-sm" wire:click="clearFilters">Clear</button>
        </div>
    </div>

    <div @if($autoRefresh) wire:poll.5s="refreshNow" @endif>
        @if(empty($entries))
            <div class="text-slate-600 text-sm">No entries to display. Ensure requests are being made and logging channel 'openai' is configured.</div>
        @else
            <ul class="space-y-3">
                @foreach($entries as $i => $e)
                    <li class="bg-white border rounded-md p-3 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2 text-xs mb-2">
                            <span class="px-2 py-0.5 rounded {{ $e['message'] === 'openai.request' ? 'bg-green-100 text-green-800' : ($e['message'] === 'openai.response' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">{{ $e['message'] }}</span>
                            @if($e['request_id'])
                                <span class="cursor-pointer underline" wire:click="filterByRequest('{{ $e['request_id'] }}')">{{ $e['request_id'] }}</span>
                            @endif
                            @if($e['datetime'])
                                <span class="text-slate-500">{{ is_array($e['datetime']) ? ($e['datetime']['date'] ?? '') : $e['datetime'] }}</span>
                            @endif
                            <span class="text-slate-500">{{ $e['channel'] ?? '' }} {{ $e['level'] ? '· '.$e['level'] : '' }}</span>

                            @if(isset($e['context']['status']))
                                <span class="px-1.5 py-0.5 rounded bg-slate-100">status: {{ $e['context']['status'] }}</span>
                            @endif
                            @if(isset($e['context']['duration_ms']))
                                <span class="px-1.5 py-0.5 rounded bg-slate-100">{{ $e['context']['duration_ms'] }} ms</span>
                            @endif
                            @if(isset($e['context']['url']))
                                <span class="px-1.5 py-0.5 rounded bg-slate-100">{{ $e['context']['method'] ?? '' }} {{ $e['context']['url'] }}</span>
                            @endif
                        </div>

                        @php
                            $ctx = $e['context'] ?? [];
                            $payload = $ctx['payload'] ?? null;
                            $response = $ctx['response'] ?? null;
                            $error = $ctx['error'] ?? null;
                        @endphp

                        @if($payload)
                            <details class="mb-2" open>
                                <summary class="cursor-pointer text-sm font-medium">Payload</summary>
                                <pre class="mt-2 text-xs bg-slate-50 border rounded p-2 overflow-x-auto">{{ json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif

                        @if($response)
                            <details class="mb-2" @if($e['message']==='openai.response') open @endif>
                                <summary class="cursor-pointer text-sm font-medium">Response</summary>
                                <pre class="mt-2 text-xs bg-slate-50 border rounded p-2 overflow-x-auto">{{ json_encode($response, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif

                        @if($error)
                            <details class="mb-2" open>
                                <summary class="cursor-pointer text-sm font-medium text-red-700">Error</summary>
                                <pre class="mt-2 text-xs bg-red-50 border border-red-200 rounded p-2 overflow-x-auto">{{ json_encode($error, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

