<div class="eog-wrap">
    <style>
        .eog-wrap{ --bg:#0f172a; --card:#0b1220; --fg:#e5e7eb; --muted:#9ca3af; --accent:#22d3ee; --border:#1f2937; --chip:#334155; --shadow:0 10px 30px rgba(0,0,0,0.35); --info:#0ea5e9; }
        .eog{ background:var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        .eog .wrap{ margin:20px auto 28px; padding:0 16px; max-width: 1200px; }
        .eog .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow:var(--shadow); }
        .eog h1{ font-size:20px; margin:0 0 8px 0; letter-spacing:0.2px; }
        .eog .sub{ color:var(--muted); font-size:13px; margin-bottom:14px; }
        .eog .tabs{ display:flex; gap:8px; flex-wrap:wrap; }
        .eog .tab{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border); color:var(--fg); padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px; }
        .eog .tab.active{ background:var(--info); color:#0b1220; border-color:#0ea5e9; }
        .eog .controls{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:10px 0 12px; }
        .eog .ctrl{ display:flex; flex-direction:column; gap:6px; }
        .eog .ctrl label{ font-size:12px; color:var(--muted); }
        .eog .in{ background:#0b1220; color:var(--fg); border:1px solid var(--border); border-radius:10px; padding:9px 10px; min-width:280px; }
        .eog .btn{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border); color:var(--fg); padding:9px 12px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px; transition:transform .06s ease, filter .15s ease; }
        .eog .btn:hover{ filter:brightness(1.1); }
        .eog .btn:active{ transform:translateY(1px); }
        .eog .btn.primary{ background:var(--info); color:#0b1220; border-color:#0ea5e9; }
        .eog .table-wrap{ overflow:auto; border:1px solid var(--border); border-radius:12px; }
        .eog table{ width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .eog thead th{ text-align:left; background:#0c1426; color:#cbd5e1; position:sticky; top:0; z-index:1; border-bottom:1px solid var(--border); padding:10px 12px; }
        .eog tbody td{ border-top:1px solid var(--border); padding:10px 12px; color:#e2e8f0; }
        .eog tbody tr:hover{ background:#0e182d; }
        .eog a.link{ color:var(--info); text-decoration:none; }
        .eog a.link:hover{ text-decoration:underline; }
        .eog .empty{ color:#94a3b8; padding:18px; text-align:center; }
        .eog .badges{ display:flex; gap:8px; flex-wrap:wrap; }
        .eog .chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--chip); color:#d1d5db; border:1px solid var(--border); font-size:12px; }
    </style>

    <div class="eog">
        <div class="wrap">
            <div class="card">
                <h1>e‑Oglasna Monitoring</h1>
                <div class="sub">Dark themed dashboard for court and keyword tracking.</div>

                <div class="tabs" role="tablist">
                    <button wire:click="$set('tab','osijek')" class="tab {{ $tab==='osijek' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab==='osijek' ? 'true' : 'false' }}">Osijek (Court)</button>
                    <button wire:click="$set('tab','keywords')" class="tab {{ $tab==='keywords' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab==='keywords' ? 'true' : 'false' }}">Keywords</button>
                    <button wire:click="$set('tab','activity')" class="tab {{ $tab==='activity' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab==='activity' ? 'true' : 'false' }}">Keyword Activity</button>
                </div>

                <div style="margin-top:12px">
                    @if($tab==='osijek')
                        <div class="controls">
                            <div class="ctrl">
                                <label>Search title/case/court</label>
                                <input type="text" wire:model.defer="searchOsijek" placeholder="e.g. Ovr-3753/2025" class="in" />
                            </div>
                            <button wire:click="$refresh" class="btn">Search</button>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Published</th>
                                    <th>Subject</th>
                                    <th>Title</th>
                                    <th>Case</th>
                                    <th>Court</th>
                                    <th>Link</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($osijekItems as $item)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ optional($item->date_published)->format('Y-m-d H:i') }}</td>
                                        <td class="whitespace-nowrap">
                                            @php $nm = trim(($item->name ?? '').' '.($item->last_name ?? '')); @endphp
                                            {{ $nm !== '' ? $nm : '—' }}
                                            @if($item->oib)
                                                <span class="chip" title="OIB" style="margin-left:6px">{{ $item->oib }}</span>
                                            @endif
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit((string) $item->title, 140) }}</td>
                                        <td class="whitespace-nowrap">{{ $item->case_number }}</td>
                                        <td>{{ $item->court_name }}</td>
                                        <td>@if($item->public_url)<a href="{{ $item->public_url }}" target="_blank" class="link">Open</a>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="empty">No items found.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top:10px">{{ $osijekItems->links() }}</div>
                    @elseif($tab==='keywords')
                        <div class="controls" style="justify-content:space-between">
                            <div class="ctrl">
                                <label>Search keyword</label>
                                <input type="text" wire:model.defer="searchKeyword" placeholder="e.g. stečaj" class="in" />
                            </div>
                            <div style="display:flex; gap:8px; align-items:flex-end">
                                <button wire:click="$refresh" class="btn">Search</button>
                                <button wire:click="createKeyword" class="btn primary">New Keyword</button>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Enabled</th>
                                    <th>Query</th>
                                    <th>Scope</th>
                                    <th>Deep</th>
                                    <th>Last Published</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($keywords as $kw)
                                    <tr>
                                        <td>{{ $kw->enabled ? 'Yes' : 'No' }}</td>
                                        <td>{{ $kw->query }}</td>
                                        <td>{{ $kw->scope }}</td>
                                        <td>{{ $kw->deep_scan ? 'Yes' : 'No' }}</td>
                                        <td class="whitespace-nowrap">{{ optional($kw->last_date_published)->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <div style="display:flex; gap:8px">
                                                <button wire:click="editKeyword({{ $kw->id }})" class="btn">Edit</button>
                                                <button wire:click="deleteKeyword({{ $kw->id }})" class="btn" style="border-color:#7f1d1d; color:#fecaca">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="empty">No keywords.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top:10px">{{ $keywords->links() }}</div>

                        @if($showKeywordModal)
                            <div class="fixed inset-0" style="background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center;">
                                <div class="card" style="width:100%; max-width:640px;">
                                    <h2 style="font-size:16px; font-weight:600; margin-bottom:10px">{{ empty($editingKeyword['id']) ? 'Create Keyword' : 'Edit Keyword' }}</h2>
                                    <div style="display:flex; flex-direction:column; gap:10px;">
                                        <div class="ctrl">
                                            <label>Query</label>
                                            <input type="text" wire:model.defer="editingKeyword.query" class="in" />
                                            @error('editingKeyword.query')<div style="color:#fecaca; font-size:12px">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="ctrl">
                                            <label>Scope</label>
                                            <select wire:model.defer="editingKeyword.scope" class="in" style="min-width:unset">
                                                <option value="notice">All notices</option>
                                                <option value="court">Court notices</option>
                                                <option value="institution">Institution notices</option>
                                                <option value="court_legal_bankruptcy">Court - legal person bankruptcy</option>
                                                <option value="court_natural_bankruptcy">Court - natural person bankruptcy</option>
                                            </select>
                                            @error('editingKeyword.scope')<div style="color:#fecaca; font-size:12px">{{ $message }}</div>@enderror
                                        </div>
                                        <div style="display:flex; gap:16px; align-items:center;">
                                            <label class="chip" style="cursor:pointer"><input type="checkbox" style="accent-color: var(--accent)" wire:model.defer="editingKeyword.deep_scan"> <span>Deep scan</span></label>
                                            <label class="chip" style="cursor:pointer"><input type="checkbox" style="accent-color: var(--accent)" wire:model.defer="editingKeyword.enabled"> <span>Enabled</span></label>
                                        </div>
                                        <div class="ctrl">
                                            <label>Notes</label>
                                            <textarea wire:model.defer="editingKeyword.notes" class="in" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                                        <button wire:click="$set('showKeywordModal',false)" class="btn">Cancel</button>
                                        <button wire:click="saveKeyword" class="btn primary">Save</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @elseif($tab==='activity')
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Matched at</th>
                                    <th>Keyword</th>
                                    <th>Scope</th>
                                    <th>Notice Title</th>
                                    <th>Case</th>
                                    <th>Published</th>
                                    <th>Link</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($activity as $row)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ optional($row->matched_at)->format('Y-m-d H:i') }}</td>
                                        <td>{{ $row->keyword_query }}</td>
                                        <td>{{ $row->keyword_scope }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit((string) $row->notice_title, 120) }}</td>
                                        <td class="whitespace-nowrap">{{ $row->notice_case_number }}</td>
                                        <td class="whitespace-nowrap">{{ optional($row->notice_date_published)->format('Y-m-d H:i') }}</td>
                                        <td>@if($row->notice_public_url)<a href="{{ $row->notice_public_url }}" target="_blank" class="link">Open</a>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="empty">No recent keyword activity.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top:10px">{{ $activity->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
