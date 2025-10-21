<div class="space-y-6">
    <div class="bg-white shadow-sm rounded-lg p-4 border border-slate-200">
        <div class="flex flex-col md:flex-row md:items-end md:space-x-4 space-y-3 md:space-y-0">
            <div>
                <label class="block text-sm font-medium text-slate-700">From</label>
                <input type="date" wire:model.debounce.500ms="from" class="mt-1 block w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">To</label>
                <input type="date" wire:model.debounce.500ms="to" class="mt-1 block w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500" />
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700">Search</label>
                <input type="text" placeholder="filter by text/model/id" wire:model.debounce.400ms="search" class="mt-1 block w-full rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Limit</label>
                <input type="number" min="1" max="100" wire:model.debounce.300ms="limit" class="mt-1 w-24 rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Order</label>
                <select wire:model="order" class="mt-1 w-28 rounded-md border-slate-300 focus:border-sky-500 focus:ring-sky-500">
                    <option value="desc">Newest</option>
                    <option value="asc">Oldest</option>
                </select>
            </div>
            <div class="md:ml-auto">
                <button wire:click="refreshNow" class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-md hover:bg-sky-700 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a1 1 0 011-1h3a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V9a1 1 0 11-2 0V5a1 1 0 011-1zm12 12a1 1 0 01-1 1h-3a1 1 0 110-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L14 13.586V11a1 1 0 112 0v4z" clip-rule="evenodd"/></svg>
                    Refresh
                </button>
            </div>
        </div>
        @if($error)
            <div class="mt-3 text-sm text-red-600">{{ $error }}</div>
        @endif
    </div>

    <div class="relative">
        <div class="absolute left-4 top-0 bottom-0 w-px bg-slate-200"></div>
        <div class="space-y-6">
            @forelse($items as $i)
                <div class="relative pl-12">
                    <div class="absolute left-0 top-2 h-3 w-3 rounded-full bg-sky-500 border-2 border-white shadow"></div>
                    <div class="bg-white border border-slate-200 rounded-lg shadow-sm p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-slate-500">{{ $i['created_at'] ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $i['model'] ?? '' }}</div>
                        </div>
                        <div class="mt-2">
                            <div class="text-xs font-semibold text-slate-600">User</div>
                            <div class="mt-1 whitespace-pre-wrap text-slate-800">{{ $i['input_text'] ?? '—' }}</div>
                        </div>
                        <div class="mt-3 border-t border-slate-100 pt-3">
                            <div class="text-xs font-semibold text-slate-600">Assistant</div>
                            <div class="mt-1 whitespace-pre-wrap text-slate-800">{{ $i['output_text'] ?? '—' }}</div>
                        </div>
                        @if(!empty($i['images']))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($i['images'] as $url)
                                    <a href="{{ $url }}" target="_blank" class="block"><img src="{{ $url }}" class="h-20 w-20 object-cover rounded-md border border-slate-200" alt="img" loading="lazy"/></a>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-3 text-xs text-slate-400">ID: {{ $i['id'] }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center text-slate-500">No responses in the selected range.</div>
            @endforelse
        </div>
    </div>

    <div wire:loading class="fixed bottom-4 right-4 bg-slate-800 text-white text-sm px-3 py-2 rounded-md shadow-lg">Loading…</div>
</div>

