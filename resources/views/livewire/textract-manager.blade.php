<div>
    {{-- Header --}}
    <h1>📄 Textract Pipeline Manager</h1>
    <div class="sub">Manage PDF processing through AWS Textract with OCR reconstruction and searchable PDF generation.</div>

    {{-- Storage Folders Preview --}}
    <details class="seg" style="margin-top:12px" open>
        <summary>🗂️ Storage Folders (local storage/app/textract)</summary>
        <div class="detail-content" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:12px">
            @php
                $folders = [
                    ['key' => 'source', 'label' => 'Source (downloads from Drive)'],
                    ['key' => 'json', 'label' => 'Textract JSON results'],
                    ['key' => 'output', 'label' => 'Reconstructed PDFs'],
                ];
            @endphp
            @foreach($folders as $f)
                <div class="seg" style="padding:12px">
                    <div class="head">
                        <strong>{{ $f['label'] }}</strong>
                        <span class="chip">{{ $storagePreview[$f['key']]['count'] ?? 0 }} files</span>
                    </div>
                    <div class="txt" style="font-size:12px; color:#94a3b8; margin-top:4px">Path: storage/app/{{ $storagePreview[$f['key']]['path'] ?? '' }}</div>
                    @if(isset($storagePreview[$f['key']]['error']))
                        <div class="txt" style="color:var(--error); font-size:12px">Error: {{ $storagePreview[$f['key']]['error'] }}</div>
                    @endif
                    <ul style="margin-top:8px; list-style:none; padding:0; max-height:240px; overflow:auto">
                        @foreach(($storagePreview[$f['key']]['entries'] ?? []) as $e)
                            <li style="display:flex; justify-content:space-between; gap:8px; font-size:12px; padding:4px 0; border-bottom:1px dashed rgba(148,163,184,0.2)">
                                <span title="{{ $e['path'] }}" style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ $e['name'] }}</span>
                                <span style="color:#94a3b8">{{ $e['mtime'] }}</span>
                                <span style="color:#94a3b8">{{ number_format(($e['size'] ?? 0)/1024, 1) }} KB</span>
                            </li>
                        @endforeach
                        @if(($storagePreview[$f['key']]['count'] ?? 0) === 0)
                            <li class="txt" style="font-size:12px; color:#94a3b8">No files yet.</li>
                        @endif
                    </ul>
                </div>
            @endforeach
        </div>
    </details>

    {{-- Stats Dashboard --}}
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Jobs</div>
            <div class="stat-value" style="color:var(--accent)">{{ $stats['total'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">⏳ Queued</div>
            <div class="stat-value" style="color:var(--warn)">{{ $stats['queued'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">⚙️ Processing</div>
            <div class="stat-value" style="color:var(--info)">{{ $stats['processing'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">✅ Succeeded</div>
            <div class="stat-value" style="color:var(--success)">{{ $stats['succeeded'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">❌ Failed</div>
            <div class="stat-value" style="color:var(--error)">{{ $stats['failed'] }}</div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="controls">
        <div class="ctrl">
            <label>Drive Folder ID</label>
            <input type="text" class="in" placeholder="Google Drive Folder ID" wire:model.blur="folderId" />
        </div>
        <div class="ctrl">
            <label>Search Files</label>
            <input type="text" class="in small" placeholder="Name or ID…" wire:model.debounce.400ms="search" />
        </div>

        <div class="ctrl" style="min-width:150px">
            <label>Filter by Status</label>
            <select class="in small" wire:model.live="statusFilter">
                <option value="all">All Statuses</option>
                <option value="queued">Queued</option>
                <option value="uploading">Uploading</option>
                <option value="started">Started</option>
                <option value="analyzing">Analyzing</option>
                <option value="reconstructing">Reconstructing</option>
                <option value="succeeded">Succeeded</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <label class="switch">
            <input type="checkbox" wire:model.live="autoRefresh" />
            <span>Auto-refresh</span>
        </label>

        <button type="button" class="btn" wire:click="refreshJobs">🔄 Refresh</button>
        <button type="button" class="btn info" wire:click="syncFromDrive">📥 Sync from Drive</button>
        @if($search)
            <button type="button" class="btn" wire:click="$set('search','')">Clear Search</button>
        @endif
    </div>

    {{-- Manual Processing --}}
    <details class="seg">
        <summary>➕ Process Single File Manually</summary>
        <div class="detail-content">
            <div class="controls" style="margin:0">
                <div class="ctrl">
                    <label>Drive File ID</label>
                    <input type="text" class="in" placeholder="1abc..." wire:model="manualDriveFileId" />
                </div>
                <div class="ctrl">
                    <label>File Name</label>
                    <input type="text" class="in" placeholder="document.pdf" wire:model="manualDriveFileName" />
                </div>
                <div class="ctrl" style="min-width:280px">
                    <label>Attach to Case</label>
                    <select class="in" wire:model="selectedCaseForManual">
                        <option value="">— Select a case —</option>
                        @foreach($caseOptions as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }} ({{ $opt['id'] }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn success" wire:click="processManual">🚀 Queue Job</button>
            </div>
        </div>
    </details>

    {{-- Jobs List --}}
    <div @if($autoRefresh) wire:poll.10s="refreshJobs" @endif style="margin-top:20px">
        @if($jobs->isEmpty())
            <div class="seg" style="text-align:center; padding:40px 20px">
                <div style="font-size:48px; margin-bottom:12px; opacity:0.5">📭</div>
                <div style="color:var(--muted); font-size:15px">No jobs found. Sync from Drive or add files manually.</div>
            </div>
        @else
            <ul class="seg-list">
                @foreach($jobs as $job)
                    <li class="seg">
                        <div class="head">
                            <span class="chip" style="flex:1; max-width:600px; overflow:hidden; text-overflow:ellipsis; font-size:14px"
                                  title="{{ $job->drive_file_name }}">
                                📄 {{ \Illuminate\Support\Str::limit($job->drive_file_name, 60) }}
                            </span>

                            @php
                                $statusStyles = [
                                    'queued' => ['class' => 'warn', 'icon' => '⏳'],
                                    'uploading' => ['class' => 'info', 'icon' => '📤'],
                                    'started' => ['class' => 'info', 'icon' => '🔄'],
                                    'analyzing' => ['class' => 'info', 'icon' => '🔍'],
                                    'reconstructing' => ['class' => 'info', 'icon' => '🔧'],
                                    'succeeded' => ['class' => 'success', 'icon' => '✅'],
                                    'failed' => ['class' => 'error', 'icon' => '❌'],
                                ];
                                $style = $statusStyles[$job->status] ?? ['class' => '', 'icon' => '•'];
                            @endphp

                            <span class="chip {{ $style['class'] }}">
                                {{ $style['icon'] }} {{ ucfirst($job->status) }}
                            </span>

                            <span class="chip" style="font-size:11px" title="{{ $job->updated_at }}">
                                🕒 {{ $job->updated_at->diffForHumans() }}
                            </span>
                        </div>

                        {{-- Case selection & current case --}}
                        <div class="head" style="margin-top:10px; gap:8px; align-items:center">
                            <div class="ctrl" style="min-width:320px; margin:0">
                                <label style="font-size:12px; color:#94a3b8">Attach to Case</label>
                                <select class="in small" wire:model="selectedCaseForJob.{{ $job->id }}">
                                    <option value="">— Select a case —</option>
                                    @foreach($caseOptions as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }} ({{ $opt['id'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="btn" wire:click="assignJobCase({{ $job->id }})"
                                    style="font-size:12px; padding:6px 12px">💾 Assign</button>

                            @if($job->case_id)
                                <span class="chip info" title="Case ID: {{ $job->case_id }}">📁 Case: {{ $job->case_id }}</span>
                            @else
                                <span class="chip warn">📁 No case assigned</span>
                            @endif
                        </div>

                        @if($job->error)
                            <div class="txt" style="color:var(--error); font-size:13px; background:rgba(239,68,68,0.1);
                                              padding:10px 12px; border-radius:8px; border:1px solid rgba(239,68,68,0.2)">
                                <strong>❌ Error:</strong> {{ \Illuminate\Support\Str::limit($job->error, 250) }}
                            </div>
                        @endif

                        <div class="head" style="margin-top:12px; gap:8px">
                            <button type="button" class="btn" wire:click="viewJobDetails({{ $job->id }})"
                                    style="font-size:12px; padding:6px 12px">
                                👁️ Details
                            </button>

                            @if(in_array($job->status, ['queued', 'failed']))
                                <button type="button" class="btn success" wire:click="processJob({{ $job->id }})"
                                        @disabled(!$job->case_id)
                                        style="font-size:12px; padding:6px 12px">
                                    🚀 Process (Async)
                                </button>
                                <button type="button" class="btn info" wire:click="processJob({{ $job->id }}, true)"
                                        @disabled(!$job->case_id)
                                        style="font-size:12px; padding:6px 12px">
                                    ⚡ Process (Sync)
                                </button>
                            @endif

                            @if($job->status === 'failed')
                                <button type="button" class="btn warn" wire:click="retryJob({{ $job->id }})"
                                        style="font-size:12px; padding:6px 12px">
                                    🔄 Retry
                                </button>
                            @endif

                            <button type="button" class="btn error"
                                    wire:click="deleteJob({{ $job->id }})"
                                    wire:confirm="Are you sure you want to delete this job?"
                                    style="font-size:12px; padding:6px 12px">
                                🗑️ Delete
                            </button>
                        </div>

                        <div style="font-size:11px; color:#64748b; margin-top:10px; line-height:1.5">
                            <strong style="color:#94a3b8">Drive ID:</strong> <span style="font-family:monospace">{{ $job->drive_file_id }}</span>
                            @if($job->job_id)
                                <br><strong style="color:#94a3b8">Textract Job:</strong>
                                <span style="font-family:monospace">{{ \Illuminate\Support\Str::limit($job->job_id, 70) }}</span>
                            @endif
                            @if($job->s3_key)
                                <br><strong style="color:#94a3b8">S3 Key:</strong>
                                <span style="font-family:monospace">{{ \Illuminate\Support\Str::limit($job->s3_key, 70) }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Pagination --}}
            <div class="pagination">
                {{ $jobs->links() }}
            </div>
        @endif
    </div>

    {{-- Job Details Modal --}}
    @if($selectedJobId && $selectedJobData)
        <div class="modal-backdrop" wire:click.self="closeJobDetails">
            <div class="modal" wire:click.stop>
                <div class="modal-header">
                    <h2>📋 Job Details</h2>
                    <button type="button" class="btn error" wire:click="closeJobDetails" style="padding:6px 12px">
                        ✕ Close
                    </button>
                </div>

                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-label">File Name</div>
                        <div class="modal-value">{{ $selectedJobData['drive_file_name'] }}</div>
                    </div>

                    <div class="modal-section">
                        <div class="modal-label">Drive File ID</div>
                        <div class="modal-value mono">{{ $selectedJobData['drive_file_id'] }}</div>
                    </div>

                    <div class="modal-section">
                        <div class="modal-label">Status</div>
                        <div>
                            @php
                                $statusStyles = [
                                    'queued' => 'warn', 'uploading' => 'info', 'started' => 'info',
                                    'analyzing' => 'info', 'reconstructing' => 'info',
                                    'succeeded' => 'success', 'failed' => 'error',
                                ];
                                $statusClass = $statusStyles[$selectedJobData['status']] ?? '';
                            @endphp
                            <span class="chip {{ $statusClass }}">{{ ucfirst($selectedJobData['status']) }}</span>
                        </div>
                    </div>

                    @if($selectedJobData['job_id'])
                        <div class="modal-section">
                            <div class="modal-label">AWS Textract Job ID</div>
                            <div class="modal-value mono">{{ $selectedJobData['job_id'] }}</div>
                        </div>
                    @endif

                    @if($selectedJobData['s3_key'])
                        <div class="modal-section">
                            <div class="modal-label">S3 Input Key</div>
                            <div class="modal-value mono">{{ $selectedJobData['s3_key'] }}</div>
                        </div>
                    @endif

                    @if($selectedJobData['error'])
                        <div class="modal-section">
                            <div class="modal-label" style="color:var(--error)">Error Message</div>
                            <div style="padding:12px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2);
                                       border-radius:8px; font-size:13px; color:#fca5a5; font-family:monospace; line-height:1.5">
                                {{ $selectedJobData['error'] }}
                            </div>
                        </div>
                    @endif

                    <div class="modal-section">
                        <div class="modal-label">Local Files</div>
                        <div style="display:flex; flex-direction:column; gap:8px">
                            @if($selectedJobData['has_textract_json'])
                                <div class="file-badge found">
                                    ✅ Textract JSON ({{ number_format($selectedJobData['textract_json_size'] / 1024, 2) }} KB)
                                </div>
                            @else
                                <div class="file-badge missing">❌ Textract JSON (not found)</div>
                            @endif

                            @if($selectedJobData['has_reconstructed_pdf'])
                                <div class="file-badge found">
                                    ✅ Reconstructed PDF ({{ number_format($selectedJobData['reconstructed_pdf_size'] / 1024, 2) }} KB)
                                </div>
                            @else
                                <div class="file-badge missing">❌ Reconstructed PDF (not found)</div>
                            @endif
                        </div>
                    </div>

                    @if(!isset($selectedJobData['s3_error']))
                        <div class="modal-section">
                            <div class="modal-label">S3 Files</div>
                            <div style="display:flex; flex-direction:column; gap:8px">
                                <div class="file-badge {{ ($selectedJobData['has_s3_input'] ?? false) ? 'found' : 'missing' }}">
                                    {{ ($selectedJobData['has_s3_input'] ?? false) ? '✅' : '❌' }} S3 Input PDF
                                </div>
                                <div class="file-badge {{ ($selectedJobData['has_s3_json'] ?? false) ? 'found' : 'missing' }}">
                                    {{ ($selectedJobData['has_s3_json'] ?? false) ? '✅' : '❌' }} S3 JSON Results
                                </div>
                                <div class="file-badge {{ ($selectedJobData['has_s3_output'] ?? false) ? 'found' : 'missing' }}">
                                    {{ ($selectedJobData['has_s3_output'] ?? false) ? '✅' : '❌' }} S3 Output PDF
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="modal-section">
                            <div class="modal-label" style="color:var(--error)">S3 Access Error</div>
                            <div class="modal-value" style="color:var(--error); font-size:13px">
                                {{ $selectedJobData['s3_error'] }}
                            </div>
                        </div>
                    @endif

                    <div class="modal-section">
                        <div class="modal-label">Case</div>
                        <div class="modal-value">
                            @if($selectedJobData['case_id'])
                                <span class="chip info">📁 {{ $selectedJobData['case_label'] ?? $selectedJobData['case_id'] }}</span>
                            @else
                                <span class="chip warn">No case assigned</span>
                            @endif
                        </div>
                    </div>

                    <div class="modal-section">
                        <div class="modal-label">Timestamps</div>
                        <div style="display:flex; flex-direction:column; gap:6px; font-size:13px; color:#94a3b8">
                            <div><strong style="color:#cbd5e1">Created:</strong> {{ $selectedJobData['created_at'] }}</div>
                            <div><strong style="color:#cbd5e1">Updated:</strong> {{ $selectedJobData['updated_at'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('success', (event) => {
            const msg = event.message || 'Success';
            console.log('✅', msg);
            // You can add a toast notification library here
            alert('✅ ' + msg);
        });
        Livewire.on('error', (event) => {
            const msg = event.message || 'Error occurred';
            console.error('❌', msg);
            alert('❌ ' + msg);
        });
    });
</script>
