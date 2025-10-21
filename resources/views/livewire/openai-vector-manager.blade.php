<div>
    <h2 class="text-xl font-bold mb-2">OpenAI Vector Stores</h2>
    @if($error)
        <div class="bg-red-100 text-red-700 p-2 mb-2">{{ $error }}</div>
    @endif
    <div class="mb-4">
        <ul class="space-y-1">
            @foreach($stores as $store)
                <li>
                    <button wire:click="selectStore('{{ $store['id'] }}')"
                        class="px-2 py-1 rounded {{ $selectedStore === $store['id'] ? 'bg-blue-200' : 'bg-slate-100' }}">
                        {{ $store['name'] ?? $store['id'] }}
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    @if($selectedStore)
        <h3 class="text-lg font-semibold mb-1">Files in Store</h3>
        <ul class="mb-4 space-y-1">
            @foreach($files as $file)
                <li>
                    <button wire:click="selectFile('{{ $file['id'] }}')"
                        class="px-2 py-1 rounded {{ $selectedFile === $file['id'] ? 'bg-green-200' : 'bg-slate-100' }}">
                        {{ $file['filename'] ?? $file['id'] }}
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if($selectedFile)
        <div class="mb-2">
            <h4 class="font-semibold">Metadata</h4>
            <pre style="overflow: auto" class="bg-slate-100 p-2 rounded text-xs ">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
        <div class="mb-2">
            <h4 class="font-semibold">Attributes</h4>
            <pre style="overflow: auto" class="bg-slate-100 p-2 rounded text-xs">{{ json_encode($fileAttributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
        <form wire:submit="saveMeta" class="space-y-2">
            <div>
                <label class="block font-medium">Nova metadata (JSON):</label>
                <textarea wire:model.defer="newMetadata" class="w-full border rounded p-1 text-xs" rows="2"></textarea>
            </div>
            <div>
                <label class="block font-medium">Novi atributi (JSON):</label>
                <textarea wire:model.defer="newAttributes" class="w-full border rounded p-1 text-xs" rows="2"></textarea>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded">Spremi</button>
        </form>
    @endif
</div>

