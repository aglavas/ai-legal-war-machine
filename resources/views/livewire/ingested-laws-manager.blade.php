<div class="px-6 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Ingested Laws</h1>
        <div class="flex gap-2">
            <input type="text" wire:model.debounce.300ms="search" placeholder="Search..."
                   class="px-3 py-2 border rounded-lg text-sm w-64" />
            <button wire:click="createIngested"
                    class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                New Ingested Law
            </button>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <!-- Left: IngestedLaws list -->
        <div class="col-span-12 lg:col-span-5">
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-4 py-2 border-b flex items-center justify-between">
                    <div class="text-sm text-slate-600">Listing</div>
                    <div class="text-xs text-slate-400">Sort:
                        <button class="underline" wire:click="sortBy('ingested_at')">ingested_at</button> ·
                        <button class="underline" wire:click="sortBy('title')">title</button> ·
                        <button class="underline" wire:click="sortBy('law_number')">law_number</button>
                    </div>
                </div>
                <div class="divide-y">
                    @forelse($ingested as $row)
                        <div class="px-4 py-3 flex items-start justify-between hover:bg-slate-50">
                            <div class="flex-1">
                                <button wire:click="selectIngested('{{ $row->id }}')"
                                        class="text-left">
                                    <div class="font-medium text-slate-800">{{ $row->title ?? 'Untitled' }}</div>
                                    <div class="text-xs text-slate-500">
                                        doc: {{ $row->doc_id }} · #{{ $row->law_number ?? '—' }} · {{ $row->jurisdiction ?? '—' }}
                                    </div>
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="editIngested('{{ $row->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-slate-100 hover:bg-slate-200">Edit</button>
                                <button wire:click="deleteIngested('{{ $row->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-rose-50 text-rose-700 hover:bg-rose-100">Delete</button>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-sm text-slate-500">No results.</div>
                    @endforelse
                </div>
                <div class="px-4 py-3">
                    {{ $ingested->links() }}
                </div>
            </div>
        </div>

        <!-- Right: Details and children -->
        <div class="col-span-12 lg:col-span-7">
            @if($selected)
                <div class="bg-white rounded-xl shadow-sm border">
                    <div class="px-4 py-3 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-semibold text-slate-800">{{ $selected->title ?? 'Untitled' }}</div>
                                <div class="text-xs text-slate-500">
                                    doc: {{ $selected->doc_id }} · #{{ $selected->law_number ?? '—' }} · {{ $selected->jurisdiction ?? '—' }} · {{ $selected->language ?? '—' }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="editIngested('{{ $selected->id }}')"
                                        class="text-xs px-3 py-2 rounded bg-slate-100 hover:bg-slate-200">Edit</button>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 pt-3">
                        <div class="flex gap-2 border-b">
                            <button wire:click="$set('tab','laws')"
                                    class="px-3 py-2 text-sm {{ $tab==='laws' ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-600' }}">
                                Laws
                            </button>
                            <button wire:click="$set('tab','uploads')"
                                    class="px-3 py-2 text-sm {{ $tab==='uploads' ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-600' }}">
                                Uploads
                            </button>
                        </div>

                        <!-- Laws tab -->
                        @if($tab==='laws')
                            <div class="py-4">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="text-sm text-slate-600">Chunked content</div>
                                    <button wire:click="createLaw"
                                            class="text-xs px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">New Law Chunk</button>
                                </div>
                                <div class="overflow-hidden border rounded-lg">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="px-3 py-2 text-left">#</th>
                                            <th class="px-3 py-2 text-left">Title</th>
                                            <th class="px-3 py-2 text-left">Chunk</th>
                                            <th class="px-3 py-2 text-left">Lang</th>
                                            <th class="px-3 py-2 text-left">Updated</th>
                                            <th class="px-3 py-2 text-right">Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                        @forelse($laws as $law)
                                            <tr>
                                                <td class="px-3 py-2 align-top">{{ \Illuminate\Support\Str::limit($law->id, 6, '') }}</td>
                                                <td class="px-3 py-2 align-top">{{ $law->title ?? '—' }}</td>
                                                <td class="px-3 py-2 align-top">#{{ $law->chunk_index }}</td>
                                                <td class="px-3 py-2 align-top">{{ $law->language ?? '—' }}</td>
                                                <td class="px-3 py-2 align-top">{{ $law->updated_at?->diffForHumans() }}</td>
                                                <td class="px-3 py-2 align-top text-right">
                                                    <button wire:click="editLaw('{{ $law->id }}')"
                                                            class="text-xs px-2 py-1 rounded bg-slate-100 hover:bg-slate-200">Edit</button>
                                                    <button wire:click="deleteLaw('{{ $law->id }}')"
                                                            class="text-xs px-2 py-1 rounded bg-rose-50 text-rose-700 hover:bg-rose-100">Delete</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No chunks.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    {{ $laws->links(data: ['scrollTo' => false]) }}
                                </div>
                            </div>
                        @endif

                        <!-- Uploads tab -->
                        @if($tab==='uploads')
                            <div class="py-4">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="text-sm text-slate-600">Files</div>
                                    <button wire:click="createUpload"
                                            class="text-xs px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">New Upload</button>
                                </div>
                                <div class="overflow-hidden border rounded-lg">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Path</th>
                                            <th class="px-3 py-2 text-left">Disk</th>
                                            <th class="px-3 py-2 text-left">Size</th>
                                            <th class="px-3 py-2 text-left">SHA256</th>
                                            <th class="px-3 py-2 text-left">Status</th>
                                            <th class="px-3 py-2 text-right">Actions</th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                        @forelse($uploads as $u)
                                            <tr>
                                                <td class="px-3 py-2">{{ $u->local_path }}</td>
                                                <td class="px-3 py-2">{{ $u->disk }}</td>
                                                <td class="px-3 py-2">{{ $u->file_size ? number_format($u->file_size) : '—' }}</td>
                                                <td class="px-3 py-2">{{ \Illuminate\Support\Str::limit($u->sha256 ?? '—', 10, '…') }}</td>
                                                <td class="px-3 py-2">{{ $u->status }}</td>
                                                <td class="px-3 py-2 text-right">
                                                    <button wire:click="editUpload('{{ $u->id }}')"
                                                            class="text-xs px-2 py-1 rounded bg-slate-100 hover:bg-slate-200">Edit</button>
                                                    <button wire:click="deleteUpload('{{ $u->id }}')"
                                                            class="text-xs px-2 py-1 rounded bg-rose-50 text-rose-700 hover:bg-rose-100">Delete</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No uploads.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    {{ $uploads->links(data: ['scrollTo' => false]) }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-slate-500 text-sm">Select an ingested law to manage its laws and uploads.</div>
            @endif
        </div>
    </div>

    <!-- Modal: IngestedLaw -->
    <div x-data="{ open: @entangle('showIngestedModal') }" x-cloak x-show="open"
         class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" @click="open=false"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white shadow-xl">
            <div class="p-4 border-b flex justify-between items-center">
                <div class="font-semibold">{{ isset($editingIngested['id']) ? 'Edit Ingested Law' : 'New Ingested Law' }}</div>
                <button @click="open=false" class="text-slate-500">✕</button>
            </div>
            <form wire:submit.prevent="saveIngested" class="p-4 space-y-4 overflow-y-auto h-[calc(100%-56px)]">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-slate-600">doc_id</label>
                        <input type="text" wire:model.defer="editingIngested.doc_id" class="w-full border rounded px-2 py-1.5 text-sm" />
                        @error('editingIngested.doc_id') <div class="text-xs text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">law_number</label>
                        <input type="text" wire:model.defer="editingIngested.law_number" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">title</label>
                        <input type="text" wire:model.defer="editingIngested.title" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">jurisdiction</label>
                        <input type="text" wire:model.defer="editingIngested.jurisdiction" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">country</label>
                        <input type="text" wire:model.defer="editingIngested.country" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">language</label>
                        <input type="text" wire:model.defer="editingIngested.language" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">source_url</label>
                        <input type="url" wire:model.defer="editingIngested.source_url" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">keywords_text</label>
                        <input type="text" wire:model.defer="editingIngested.keywords_text" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">metadata (JSON)</label>
                        <textarea wire:model.defer="editingIngested.metadata" class="w-full border rounded px-2 py-1.5 text-sm" rows="3"></textarea>
                    </div>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="open=false" class="px-3 py-2 rounded border text-sm">Cancel</button>
                    <button type="submit" class="px-3 py-2 rounded bg-blue-600 text-white text-sm">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Law -->
    <div x-data="{ open: @entangle('showLawModal') }" x-cloak x-show="open"
         class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" @click="open=false"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white shadow-xl">
            <div class="p-4 border-b flex justify-between items-center">
                <div class="font-semibold">{{ isset($editingLaw['id']) ? 'Edit Law Chunk' : 'New Law Chunk' }}</div>
                <button @click="open=false" class="text-slate-500">✕</button>
            </div>
            <form wire:submit.prevent="saveLaw" class="p-4 space-y-4 overflow-y-auto h-[calc(100%-56px)]">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-slate-600">doc_id</label>
                        <input type="text" wire:model.defer="editingLaw.doc_id" class="w-full border rounded px-2 py-1.5 text-sm" />
                        @error('editingLaw.doc_id') <div class="text-xs text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">chunk_index</label>
                        <input type="number" min="0" wire:model.defer="editingLaw.chunk_index" class="w-full border rounded px-2 py-1.5 text-sm" />
                        @error('editingLaw.chunk_index') <div class="text-xs text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">title</label>
                        <input type="text" wire:model.defer="editingLaw.title" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">language</label>
                        <input type="text" wire:model.defer="editingLaw.language" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">source_url</label>
                        <input type="url" wire:model.defer="editingLaw.source_url" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">content</label>
                        <textarea wire:model.defer="editingLaw.content" rows="6" class="w-full border rounded px-2 py-1.5 text-sm"></textarea>
                        @error('editingLaw.content') <div class="text-xs text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">metadata (JSON)</label>
                        <textarea wire:model.defer="editingLaw.metadata" rows="3" class="w-full border rounded px-2 py-1.5 text-sm"></textarea>
                    </div>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="open=false" class="px-3 py-2 rounded border text-sm">Cancel</button>
                    <button type="submit" class="px-3 py-2 rounded bg-blue-600 text-white text-sm">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Upload -->
    <div x-data="{ open: @entangle('showUploadModal') }" x-cloak x-show="open"
         class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" @click="open=false"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-white shadow-xl">
            <div class="p-4 border-b flex justify-between items-center">
                <div class="font-semibold">{{ isset($editingUpload['id']) ? 'Edit Upload' : 'New Upload' }}</div>
                <button @click="open=false" class="text-slate-500">✕</button>
            </div>
            <form wire:submit.prevent="saveUpload" class="p-4 space-y-4 overflow-y-auto h-[calc(100%-56px)]">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-slate-600">doc_id</label>
                        <input type="text" wire:model.defer="editingUpload.doc_id" class="w-full border rounded px-2 py-1.5 text-sm" />
                        @error('editingUpload.doc_id') <div class="text-xs text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">disk</label>
                        <input type="text" wire:model.defer="editingUpload.disk" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">local_path</label>
                        <input type="text" wire:model.defer="editingUpload.local_path" class="w-full border rounded px-2 py-1.5 text-sm" />
                        @error('editingUpload.local_path') <div class="text-xs text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">original_filename</label>
                        <input type="text" wire:model.defer="editingUpload.original_filename" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">mime_type</label>
                        <input type="text" wire:model.defer="editingUpload.mime_type" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">file_size</label>
                        <input type="number" min="0" wire:model.defer="editingUpload.file_size" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">sha256</label>
                        <input type="text" wire:model.defer="editingUpload.sha256" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">source_url</label>
                        <input type="url" wire:model.defer="editingUpload.source_url" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">downloaded_at</label>
                        <input type="datetime-local" wire:model.defer="editingUpload.downloaded_at" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">status</label>
                        <input type="text" wire:model.defer="editingUpload.status" class="w-full border rounded px-2 py-1.5 text-sm" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-slate-600">error</label>
                        <textarea wire:model.defer="editingUpload.error" rows="3" class="w-full border rounded px-2 py-1.5 text-sm"></textarea>
                    </div>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="open=false" class="px-3 py-2 rounded border text-sm">Cancel</button>
                    <button type="submit" class="px-3 py-2 rounded bg-blue-600 text-white text-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
