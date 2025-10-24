<div>
    {{-- Header --}}
    <h1>📚 Ingested Laws Manager</h1>
    <div class="sub">Manage ingested legal documents, law chunks, and related uploads.</div>

    {{-- Search and Create --}}
    <div class="controls">
        <div class="ctrl">
            <label>Search</label>
            <input type="text" wire:model.debounce.300ms="search" placeholder="Search by title, doc ID, law number..." class="in" style="min-width:320px" />
        </div>
        <button wire:click="openScraper" class="btn success">
            🌐 Scrape Laws from zakon.hr
        </button>
        <button wire:click="createIngested" class="btn primary">
            ➕ New Ingested Law
        </button>
        @if($search)
            <button wire:click="$set('search','')" class="btn">Clear Search</button>
        @endif
    </div>

    {{-- Main Grid --}}
    <div class="grid-2" style="margin-top:20px">
        <!-- Left: IngestedLaws List -->
        <div>
            <div class="seg">
                <div class="flex items-center justify-between mb-2">
                    <div class="label">Ingested Laws Listing</div>
                    <div class="text-xs text-muted">
                        Sort:
                        <button class="chip" wire:click="sortBy('ingested_at')" style="cursor:pointer; padding:4px 8px">
                            ingested_at {{ $sortField === 'ingested_at' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </button>
                        <button class="chip" wire:click="sortBy('title')" style="cursor:pointer; padding:4px 8px">
                            title {{ $sortField === 'title' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </button>
                        <button class="chip" wire:click="sortBy('law_number')" style="cursor:pointer; padding:4px 8px">
                            law_number {{ $sortField === 'law_number' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                        </button>
                    </div>
                </div>
            </div>

            <ul class="seg-list" style="margin-top:12px">
                @forelse($ingested as $row)
                    <li class="seg clickable {{ $selectedIngestedId === $row->id ? 'active' : '' }}">
                        <div class="head" style="justify-content:space-between">
                            <div style="flex:1; min-width:0">
                                <button wire:click="selectIngested('{{ $row->id }}')" style="text-align:left; width:100%; background:none; border:none; color:inherit; cursor:pointer; padding:0">
                                    <div style="font-weight:600; font-size:15px; color:#e2e8f0; margin-bottom:4px">
                                        {{ $row->title ?? 'Untitled' }}
                                    </div>
                                    <div class="text-xs text-muted">
                                        Doc: <span style="font-family:monospace">{{ $row->doc_id }}</span>
                                        @if($row->law_number)
                                            · #{{ $row->law_number }}
                                        @endif
                                        @if($row->jurisdiction)
                                            · {{ $row->jurisdiction }}
                                        @endif
                                    </div>
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="editIngested('{{ $row->id }}')" class="btn" style="font-size:11px; padding:6px 10px">
                                    ✏️ Edit
                                </button>
                                <button wire:click="deleteIngested('{{ $row->id }}')" wire:confirm="Delete this ingested law?" class="btn error" style="font-size:11px; padding:6px 10px">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="seg text-center" style="padding:40px 20px">
                        <div style="font-size:48px; margin-bottom:12px; opacity:0.5">📭</div>
                        <div class="text-muted">No ingested laws found.</div>
                    </li>
                @endforelse
            </ul>

            <div class="pagination mt-4">
                {{ $ingested->links() }}
            </div>
        </div>

        <!-- Right: Details and Children -->
        <div>
            @if($selected)
                <div class="seg">
                    <div class="head" style="justify-content:space-between; margin-bottom:16px">
                        <div style="flex:1; min-width:0">
                            <h2 style="font-size:18px; font-weight:600; margin:0 0 6px 0; color:#e2e8f0">
                                {{ $selected->title ?? 'Untitled' }}
                            </h2>
                            <div class="text-xs text-muted">
                                Doc: <span style="font-family:monospace">{{ $selected->doc_id }}</span>
                                @if($selected->law_number)
                                    · #{{ $selected->law_number }}
                                @endif
                                @if($selected->jurisdiction)
                                    · {{ $selected->jurisdiction }}
                                @endif
                                @if($selected->language)
                                    · {{ strtoupper($selected->language) }}
                                @endif
                            </div>
                        </div>
                        <button wire:click="editIngested('{{ $selected->id }}')" class="btn info" style="font-size:12px; padding:8px 14px">
                            ✏️ Edit
                        </button>
                    </div>

                    {{-- Tabs --}}
                    <div class="tabs">
                        <div wire:click="$set('tab','laws')" class="tab {{ $tab === 'laws' ? 'active' : '' }}">
                            📜 Law Chunks
                        </div>
                        <div wire:click="$set('tab','uploads')" class="tab {{ $tab === 'uploads' ? 'active' : '' }}">
                            📎 Uploads
                        </div>
                    </div>

                    {{-- Laws Tab --}}
                    @if($tab === 'laws')
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm text-muted">Chunked content from this ingested law</div>
                                <button wire:click="createLaw" class="btn success" style="font-size:12px; padding:7px 12px">
                                    ➕ New Chunk
                                </button>
                            </div>

                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Chunk</th>
                                            <th>Lang</th>
                                            <th>Updated</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($laws as $law)
                                            <tr>
                                                <td><span style="font-family:monospace; font-size:11px">{{ \Illuminate\Support\Str::limit($law->id, 8, '') }}</span></td>
                                                <td>{{ $law->title ?? '—' }}</td>
                                                <td><span class="chip" style="padding:4px 8px">#{{ $law->chunk_index }}</span></td>
                                                <td>{{ strtoupper($law->language ?? '—') }}</td>
                                                <td class="text-muted text-xs">{{ $law->updated_at?->diffForHumans() }}</td>
                                                <td class="actions">
                                                    <button wire:click="editLaw('{{ $law->id }}')" class="btn" style="font-size:11px; padding:5px 9px">
                                                        ✏️ Edit
                                                    </button>
                                                    <button wire:click="deleteLaw('{{ $law->id }}')" wire:confirm="Delete this law chunk?" class="btn error" style="font-size:11px; padding:5px 9px; margin-left:4px">
                                                        🗑️ Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted" style="padding:40px 20px">
                                                    <div style="font-size:36px; margin-bottom:8px; opacity:0.5">📄</div>
                                                    No law chunks yet.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="pagination mt-2">
                                {{ $laws->links(data: ['scrollTo' => false]) }}
                            </div>
                        </div>
                    @endif

                    {{-- Uploads Tab --}}
                    @if($tab === 'uploads')
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-sm text-muted">Source files and related uploads</div>
                                <button wire:click="createUpload" class="btn success" style="font-size:12px; padding:7px 12px">
                                    ➕ New Upload
                                </button>
                            </div>

                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Path</th>
                                            <th>Disk</th>
                                            <th>Size</th>
                                            <th>SHA256</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($uploads as $u)
                                            <tr>
                                                <td style="max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap" title="{{ $u->local_path }}">
                                                    {{ $u->local_path }}
                                                </td>
                                                <td><span class="chip" style="padding:4px 8px">{{ $u->disk }}</span></td>
                                                <td class="text-muted text-xs">{{ $u->file_size ? number_format($u->file_size / 1024, 1) . ' KB' : '—' }}</td>
                                                <td><span style="font-family:monospace; font-size:11px">{{ \Illuminate\Support\Str::limit($u->sha256 ?? '—', 12, '…') }}</span></td>
                                                <td>
                                                    @if($u->status === 'stored')
                                                        <span class="chip success" style="padding:4px 8px">✅ {{ ucfirst($u->status) }}</span>
                                                    @elseif($u->status === 'error')
                                                        <span class="chip error" style="padding:4px 8px">❌ {{ ucfirst($u->status) }}</span>
                                                    @else
                                                        <span class="chip" style="padding:4px 8px">{{ ucfirst($u->status) }}</span>
                                                    @endif
                                                </td>
                                                <td class="actions">
                                                    <button wire:click="editUpload('{{ $u->id }}')" class="btn" style="font-size:11px; padding:5px 9px">
                                                        ✏️ Edit
                                                    </button>
                                                    <button wire:click="deleteUpload('{{ $u->id }}')" wire:confirm="Delete this upload?" class="btn error" style="font-size:11px; padding:5px 9px; margin-left:4px">
                                                        🗑️ Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted" style="padding:40px 20px">
                                                    <div style="font-size:36px; margin-bottom:8px; opacity:0.5">📎</div>
                                                    No uploads yet.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="pagination mt-2">
                                {{ $uploads->links(data: ['scrollTo' => false]) }}
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="seg text-center" style="padding:60px 20px">
                    <div style="font-size:64px; margin-bottom:16px; opacity:0.4">👈</div>
                    <div class="text-muted" style="font-size:15px">
                        Select an ingested law from the list to view and manage its law chunks and uploads.
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal: IngestedLaw --}}
    <div x-data="{ open: @entangle('showIngestedModal') }" x-cloak x-show="open" class="modal-backdrop" @click.self="open=false">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <h2>{{ isset($editingIngested['id']) && $editingIngested['id'] ? '✏️ Edit Ingested Law' : '➕ New Ingested Law' }}</h2>
                <button @click="open=false" class="btn error" style="padding:6px 12px">✕ Close</button>
            </div>
            <form wire:submit.prevent="saveIngested" class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doc ID *</label>
                        <input type="text" wire:model.defer="editingIngested.doc_id" required />
                        @error('editingIngested.doc_id') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Law Number</label>
                        <input type="text" wire:model.defer="editingIngested.law_number" />
                    </div>
                    <div class="form-group span-2">
                        <label>Title</label>
                        <input type="text" wire:model.defer="editingIngested.title" />
                    </div>
                    <div class="form-group">
                        <label>Jurisdiction</label>
                        <input type="text" wire:model.defer="editingIngested.jurisdiction" />
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" wire:model.defer="editingIngested.country" />
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <input type="text" wire:model.defer="editingIngested.language" placeholder="e.g., en, hr" maxlength="16" />
                    </div>
                    <div class="form-group">
                        <label>Source URL</label>
                        <input type="url" wire:model.defer="editingIngested.source_url" />
                    </div>
                    <div class="form-group span-2">
                        <label>Keywords (comma-separated)</label>
                        <input type="text" wire:model.defer="editingIngested.keywords_text" placeholder="law, regulation, policy" />
                    </div>
                    <div class="form-group span-2">
                        <label>Metadata (JSON)</label>
                        <textarea wire:model.defer="editingIngested.metadata" rows="3" placeholder='{"key": "value"}'></textarea>
                        @error('editingIngested.metadata') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" @click="open=false" class="btn">Cancel</button>
                    <button type="submit" class="btn primary">💾 Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Law --}}
    <div x-data="{ open: @entangle('showLawModal') }" x-cloak x-show="open" class="modal-backdrop" @click.self="open=false">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <h2>{{ isset($editingLaw['id']) && $editingLaw['id'] ? '✏️ Edit Law Chunk' : '➕ New Law Chunk' }}</h2>
                <button @click="open=false" class="btn error" style="padding:6px 12px">✕ Close</button>
            </div>
            <form wire:submit.prevent="saveLaw" class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doc ID *</label>
                        <input type="text" wire:model.defer="editingLaw.doc_id" required />
                        @error('editingLaw.doc_id') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Chunk Index *</label>
                        <input type="number" wire:model.defer="editingLaw.chunk_index" min="0" required />
                        @error('editingLaw.chunk_index') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group span-2">
                        <label>Title</label>
                        <input type="text" wire:model.defer="editingLaw.title" />
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <input type="text" wire:model.defer="editingLaw.language" placeholder="e.g., en, hr" maxlength="16" />
                    </div>
                    <div class="form-group">
                        <label>Source URL</label>
                        <input type="url" wire:model.defer="editingLaw.source_url" />
                    </div>
                    <div class="form-group span-2">
                        <label>Content *</label>
                        <textarea wire:model.defer="editingLaw.content" rows="8" required></textarea>
                        @error('editingLaw.content') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group span-2">
                        <label>Metadata (JSON)</label>
                        <textarea wire:model.defer="editingLaw.metadata" rows="3" placeholder='{"key": "value"}'></textarea>
                        @error('editingLaw.metadata') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" @click="open=false" class="btn">Cancel</button>
                    <button type="submit" class="btn primary">💾 Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Upload --}}
    <div x-data="{ open: @entangle('showUploadModal') }" x-cloak x-show="open" class="modal-backdrop" @click.self="open=false">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <h2>{{ isset($editingUpload['id']) && $editingUpload['id'] ? '✏️ Edit Upload' : '➕ New Upload' }}</h2>
                <button @click="open=false" class="btn error" style="padding:6px 12px">✕ Close</button>
            </div>
            <form wire:submit.prevent="saveUpload" class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doc ID *</label>
                        <input type="text" wire:model.defer="editingUpload.doc_id" required />
                        @error('editingUpload.doc_id') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Disk *</label>
                        <input type="text" wire:model.defer="editingUpload.disk" required />
                    </div>
                    <div class="form-group span-2">
                        <label>Local Path *</label>
                        <input type="text" wire:model.defer="editingUpload.local_path" required />
                        @error('editingUpload.local_path') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Original Filename</label>
                        <input type="text" wire:model.defer="editingUpload.original_filename" />
                    </div>
                    <div class="form-group">
                        <label>MIME Type</label>
                        <input type="text" wire:model.defer="editingUpload.mime_type" placeholder="application/pdf" />
                    </div>
                    <div class="form-group">
                        <label>File Size (bytes)</label>
                        <input type="number" wire:model.defer="editingUpload.file_size" min="0" />
                    </div>
                    <div class="form-group">
                        <label>SHA256 Hash</label>
                        <input type="text" wire:model.defer="editingUpload.sha256" maxlength="64" placeholder="64-character hash" />
                    </div>
                    <div class="form-group span-2">
                        <label>Source URL</label>
                        <input type="url" wire:model.defer="editingUpload.source_url" />
                    </div>
                    <div class="form-group">
                        <label>Downloaded At</label>
                        <input type="datetime-local" wire:model.defer="editingUpload.downloaded_at" />
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select wire:model.defer="editingUpload.status" required>
                            <option value="stored">Stored</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label>Error Message</label>
                        <textarea wire:model.defer="editingUpload.error" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" @click="open=false" class="btn">Cancel</button>
                    <button type="submit" class="btn primary">💾 Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Law Scraper --}}
    <div x-data="{ open: @entangle('showScraperModal') }" x-cloak x-show="open" class="modal-backdrop" @click.self="open=false">
        <div class="modal" @click.stop style="max-width:1000px">
            <div class="modal-header">
                <h2>🌐 Scrape Laws from zakon.hr</h2>
                <button @click="open=false" class="btn error" style="padding:6px 12px">✕ Close</button>
            </div>
            <div class="modal-body">
                {{-- Scraper Instructions --}}
                @if(empty($scrapedLaws))
                    <div class="seg" style="margin-bottom:16px">
                        <div class="text-sm" style="line-height:1.6">
                            <strong>📋 Step 1: Fetch Available Laws</strong>
                            <ul style="margin:8px 0 0 20px; padding:0">
                                <li>Fetches list of laws from zakon.hr categories (98, 99, 100, 101)</li>
                                <li>Categories: Domovinski rat, Kazneno i prekršajno</li>
                                <li>Displays available laws with titles and metadata</li>
                            </ul>
                            <div style="margin-top:12px; padding:10px; background:rgba(14,165,233,0.1); border:1px solid rgba(14,165,233,0.2); border-radius:8px">
                                <strong style="color:#7dd3fc">ℹ️ Note:</strong>
                                <span style="color:var(--muted)">After fetching the list, you can select which laws to actually scrape and import the full content.</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center" style="padding:20px">
                        <button wire:click="startScraping"
                                wire:loading.attr="disabled"
                                class="btn success"
                                style="padding:12px 24px; font-size:15px">
                            <span wire:loading.remove wire:target="startScraping">🚀 Fetch Available Laws</span>
                            <span wire:loading wire:target="startScraping">⏳ Fetching list...</span>
                        </button>
                    </div>
                @else
                    {{-- Scraped Laws List --}}
                    <div>
                        <div class="seg" style="margin-bottom:16px">
                            <div class="text-sm" style="line-height:1.6">
                                <strong>✅ Step 2: Select Laws to Import</strong>
                                <div style="color:var(--muted); margin-top:4px">
                                    Found <strong style="color:var(--accent)">{{ count($scrapedLaws) }}</strong> unique laws.
                                    Select the laws you want to scrape and import the full content.
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm">
                                @if(!empty($selectedLawsToImport))
                                    <strong class="text-sm" style="color:var(--accent)">{{ count($selectedLawsToImport) }}</strong> law(s) selected for import
                                @else
                                    <span class="text-muted">No laws selected yet</span>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <div class="ctrl" style="margin:0">
                                    <input type="text"
                                           wire:model.live.debounce.300ms="scraperSearchFilter"
                                           placeholder="Filter laws..."
                                           class="in small"
                                           style="min-width:200px" />
                                </div>
                                <button wire:click="selectAllFilteredLaws" class="btn" style="padding:8px 12px; font-size:12px">
                                    ✓ Select All
                                </button>
                                <button wire:click="deselectAllLaws" class="btn" style="padding:8px 12px; font-size:12px">
                                    ✗ Deselect All
                                </button>
                            </div>
                        </div>

                        <div class="table-container" style="max-height:500px; overflow-y:auto">
                            <table>
                                <thead style="position:sticky; top:0; z-index:1">
                                    <tr>
                                        <th style="width:40px">
                                            <input type="checkbox"
                                                   @if(count($selectedLawsToImport) > 0 && count($selectedLawsToImport) === count($this->getFilteredScrapedLaws()))
                                                       checked
                                                   @endif
                                                   wire:click="selectAllFilteredLaws"
                                                   style="cursor:pointer" />
                                        </th>
                                        <th>Title</th>
                                        <th style="width:100px">Law #</th>
                                        <th style="width:150px">Categories</th>
                                        <th style="width:100px">URL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($this->getFilteredScrapedLaws() as $law)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox"
                                                       wire:click="toggleLawSelection('{{ $law['url'] }}')"
                                                       @if(in_array($law['url'], $selectedLawsToImport)) checked @endif
                                                       style="cursor:pointer" />
                                            </td>
                                            <td>{{ $law['title'] }}</td>
                                            <td class="text-center">
                                                @if($law['law_number'])
                                                    <span class="chip" style="padding:4px 8px">#{{ $law['law_number'] }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(isset($law['found_in_categories']) && is_array($law['found_in_categories']))
                                                    <span class="text-xs text-muted">{{ implode(', ', $law['found_in_categories']) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ $law['url'] }}"
                                                   target="_blank"
                                                   class="chip info"
                                                   style="padding:4px 8px; text-decoration:none; cursor:pointer">
                                                    🔗 View
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted" style="padding:40px 20px">
                                                No laws match your filter.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Import Progress --}}
                        @if($isImporting)
                            <div class="seg" style="margin-top:16px">
                                <div class="text-sm mb-2">
                                    <strong>Importing laws...</strong>
                                    <span class="text-muted">{{ $importProgress }} / {{ $importTotal }}</span>
                                </div>
                                <div style="background:#0b1220; border:1px solid var(--border); border-radius:8px; overflow:hidden; height:24px; margin-bottom:8px">
                                    <div style="background:linear-gradient(90deg, var(--success), #16a34a); height:100%; width:{{ $importTotal > 0 ? ($importProgress / $importTotal * 100) : 0 }}%; transition:width 0.3s ease"></div>
                                </div>
                                <div class="text-xs text-muted">
                                    Currently importing: <strong>{{ $currentlyImporting }}</strong>
                                </div>
                            </div>
                        @endif

                        <div class="form-actions" style="border-top:none; margin-top:16px; padding-top:0">
                            <button @click="open=false"
                                    class="btn"
                                    @if($isImporting) disabled @endif>
                                Cancel
                            </button>
                            <button wire:click="importSelectedLaws"
                                    class="btn success"
                                    @if(empty($selectedLawsToImport) || $isImporting) disabled @endif>
                                @if($isImporting)
                                    ⏳ Importing...
                                @else
                                    📥 Import & Scrape {{ count($selectedLawsToImport) }} Selected Law(s)
                                @endif
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Toast Notifications --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('scraping-complete', (event) => {
                alert('✅ ' + event.message);
            });
            Livewire.on('scraping-error', (event) => {
                alert('❌ ' + event.message);
            });
            Livewire.on('import-complete', (event) => {
                alert('✅ ' + event.message);
            });
            Livewire.on('import-error', (event) => {
                alert('❌ ' + event.message);
            });
        });
    </script>
</div>
