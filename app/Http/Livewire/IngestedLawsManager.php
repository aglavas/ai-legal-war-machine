<?php

namespace App\Http\Livewire;

use App\Models\IngestedLaw;
use App\Models\Law;
use App\Models\LawUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class IngestedLawsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortField = 'ingested_at';
    public string $sortDirection = 'desc';
    public ?string $selectedIngestedId = null;
    public string $tab = 'laws'; // laws|uploads

    public bool $showIngestedModal = false;
    public bool $showLawModal = false;
    public bool $showUploadModal = false;

    public array $editingIngested = [];
    public array $editingLaw = [];
    public array $editingUpload = [];

    protected $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortField' => ['except' => 'ingested_at'],
        'sortDirection' => ['except' => 'desc'],
        'selectedIngestedId' => ['except' => null],
        'tab' => ['except' => 'laws'],
    ];

    // ----- Rules
    protected function ingestedRules(): array
    {
        $table = (new IngestedLaw())->getTable();
        $id = $this->editingIngested['id'] ?? null;

        return [
            'editingIngested.doc_id' => [
                'required',
                'string',
                Rule::unique($table, 'doc_id')->ignore($id, 'id'),
            ],
            'editingIngested.title' => ['nullable', 'string'],
            'editingIngested.law_number' => ['nullable', 'string'],
            'editingIngested.jurisdiction' => ['nullable', 'string'],
            'editingIngested.country' => ['nullable', 'string'],
            'editingIngested.language' => ['nullable', 'string', 'max:16'],
            'editingIngested.source_url' => ['nullable', 'url'],
            'editingIngested.aliases' => ['nullable', 'array'],
            'editingIngested.keywords' => ['nullable', 'array'],
            'editingIngested.keywords_text' => ['nullable', 'string'],
            'editingIngested.metadata' => ['nullable', 'array'],
        ];
    }

    protected function lawRules(): array
    {
        $table = (new Law())->getTable();
        $id = $this->editingLaw['id'] ?? null;

        return [
            'editingLaw.doc_id' => ['required', 'string'],
            'editingLaw.title' => ['nullable', 'string'],
            'editingLaw.law_number' => ['nullable', 'string'],
            'editingLaw.jurisdiction' => ['nullable', 'string'],
            'editingLaw.country' => ['nullable', 'string'],
            'editingLaw.language' => ['nullable', 'string', 'max:16'],
            'editingLaw.promulgation_date' => ['nullable', 'date'],
            'editingLaw.effective_date' => ['nullable', 'date'],
            'editingLaw.repeal_date' => ['nullable', 'date'],
            'editingLaw.version' => ['nullable', 'string'],
            'editingLaw.chapter' => ['nullable', 'string'],
            'editingLaw.section' => ['nullable', 'string'],
            'editingLaw.tags' => ['nullable', 'array'],
            'editingLaw.source_url' => ['nullable', 'url'],
            'editingLaw.chunk_index' => ['required', 'integer', 'min:0'],
            'editingLaw.content' => ['required', 'string'],
            'editingLaw.metadata' => ['nullable', 'array'],
        ];
    }

    protected function uploadRules(): array
    {
        return [
            'editingUpload.doc_id' => ['required', 'string'],
            'editingUpload.disk' => ['required', 'string'],
            'editingUpload.local_path' => ['required', 'string'],
            'editingUpload.original_filename' => ['nullable', 'string'],
            'editingUpload.mime_type' => ['nullable', 'string'],
            'editingUpload.file_size' => ['nullable', 'integer', 'min:0'],
            'editingUpload.sha256' => ['nullable', 'string', 'size:64'],
            'editingUpload.source_url' => ['nullable', 'url'],
            'editingUpload.downloaded_at' => ['nullable', 'date'],
            'editingUpload.status' => ['required', 'string'],
            'editingUpload.error' => ['nullable', 'string'],
        ];
    }

    // ----- UI actions: IngestedLaw
    public function createIngested(): void
    {
        $this->resetValidation();
        $this->editingIngested = [
            'id' => null,
            'doc_id' => '',
            'title' => '',
            'law_number' => '',
            'jurisdiction' => '',
            'country' => '',
            'language' => '',
            'source_url' => '',
            'aliases' => [],
            'keywords' => [],
            'keywords_text' => '',
            'metadata' => [],
        ];
        $this->showIngestedModal = true;
    }

    public function editIngested(string $id): void
    {
        $this->resetValidation();
        $model = IngestedLaw::findOrFail($id);
        $this->editingIngested = $model->toArray();
        $this->showIngestedModal = true;
    }

    public function saveIngested(): void
    {
        $this->validate($this->ingestedRules());

        if (empty($this->editingIngested['id'])) {
            $this->editingIngested['id'] = (string) Str::ulid();
            $this->editingIngested['ingested_at'] = now();
            IngestedLaw::create($this->editingIngested);
        } else {
            $model = IngestedLaw::findOrFail($this->editingIngested['id']);
            $model->update($this->editingIngested);
        }

        $this->showIngestedModal = false;
    }

    public function deleteIngested(string $id): void
    {
        IngestedLaw::whereKey($id)->delete();
        if ($this->selectedIngestedId === $id) {
            $this->selectedIngestedId = null;
        }
    }

    public function selectIngested(?string $id): void
    {
        $this->selectedIngestedId = $id;
        $this->tab = 'laws';
        $this->resetPage();
    }

    // ----- UI actions: Law (child)
    public function createLaw(): void
    {
        if (!$this->selectedIngestedId) return;

        $parent = IngestedLaw::findOrFail($this->selectedIngestedId);

        $this->resetValidation();
        $this->editingLaw = [
            'id' => null,
            'doc_id' => $parent->doc_id ?? '',
            'title' => '',
            'law_number' => '',
            'jurisdiction' => $parent->jurisdiction,
            'country' => $parent->country,
            'language' => $parent->language,
            'promulgation_date' => null,
            'effective_date' => null,
            'repeal_date' => null,
            'version' => '',
            'chapter' => '',
            'section' => '',
            'tags' => [],
            'source_url' => $parent->source_url,
            'chunk_index' => 0,
            'content' => '',
            'metadata' => [],
        ];
        $this->showLawModal = true;
    }

    public function editLaw(string $id): void
    {
        $this->resetValidation();
        $model = Law::findOrFail($id);
        $this->editingLaw = $model->toArray();
        $this->showLawModal = true;
    }

    public function saveLaw(): void
    {
        if (!$this->selectedIngestedId) return;

        $this->validate($this->lawRules());

        $isCreate = empty($this->editingLaw['id']);
        $driver = DB::connection()->getDriverName();

        if ($isCreate) {
            $law = new Law();
            $law->id = (string) Str::ulid();
            $law->ingested_law_id = $this->selectedIngestedId;
        } else {
            $law = Law::findOrFail($this->editingLaw['id']);
        }

        // Fill basic fields
        $law->fill(collect($this->editingLaw)->except([
            'id', 'ingested_law_id', 'embedding_provider', 'embedding_model', 'embedding_dimensions',
            'embedding_vector', 'embedding', 'embedding_norm', 'content_hash', 'token_count',
        ])->toArray());

        // Required embedding fields
        $law->embedding_provider = 'manual';
        $law->embedding_model = 'none';
        $law->embedding_dimensions = 1536;
        $law->embedding_norm = null;
        $law->token_count = mb_strlen((string) ($law->content ?? ''));

        // Content hash must be unique per doc_id
        $law->content_hash = hash('sha256', (string) $law->content);

        // Zero-vector as default embedding
        if ($driver === 'pgsql') {
            // pgvector accepts bracketed literal like [0, 0, ...]
            $law->setAttribute('embedding', $this->zeroVectorLiteral(1536));
        } else {
            $law->embedding_vector = array_fill(0, 1536, 0.0);
        }

        $law->save();

        $this->showLawModal = false;
    }

    public function deleteLaw(string $id): void
    {
        Law::whereKey($id)->delete();
    }

    // ----- UI actions: LawUpload (child)
    public function createUpload(): void
    {
        if (!$this->selectedIngestedId) return;

        $parent = IngestedLaw::findOrFail($this->selectedIngestedId);

        $this->resetValidation();
        $this->editingUpload = [
            'id' => null,
            'doc_id' => $parent->doc_id ?? '',
            'disk' => 'local',
            'local_path' => '',
            'original_filename' => '',
            'mime_type' => '',
            'file_size' => null,
            'sha256' => '',
            'source_url' => $parent->source_url,
            'downloaded_at' => null,
            'status' => 'stored',
            'error' => '',
        ];
        $this->showUploadModal = true;
    }

    public function editUpload(string $id): void
    {
        $this->resetValidation();
        $model = LawUpload::findOrFail($id);
        $this->editingUpload = $model->toArray();
        $this->showUploadModal = true;
    }

    public function saveUpload(): void
    {
        if (!$this->selectedIngestedId) return;

        $this->validate($this->uploadRules());

        if (empty($this->editingUpload['id'])) {
            $upload = new LawUpload();
            $upload->id = (string) Str::ulid();
            $upload->ingested_law_id = $this->selectedIngestedId;
        } else {
            $upload = LawUpload::findOrFail($this->editingUpload['id']);
        }

        $upload->fill(collect($this->editingUpload)->except(['id', 'ingested_law_id'])->toArray());
        $upload->save();

        $this->showUploadModal = false;
    }

    public function deleteUpload(string $id): void
    {
        LawUpload::whereKey($id)->delete();
    }

    // ----- Helpers
    private function zeroVectorLiteral(int $dims): string
    {
        // e.g. "[0, 0, 0, ...]" for pgvector
        return '[' . implode(', ', array_fill(0, $dims, '0')) . ']';
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ----- Render
    public function render()
    {
        $ingestedQuery = IngestedLaw::query()
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('doc_id', 'like', '%'.$this->search.'%')
                        ->orWhere('title', 'like', '%'.$this->search.'%')
                        ->orWhere('law_number', 'like', '%'.$this->search.'%')
                        ->orWhere('jurisdiction', 'like', '%'.$this->search.'%')
                        ->orWhere('keywords_text', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        $ingested = $ingestedQuery->paginate(10);

        $selected = $this->selectedIngestedId
            ? IngestedLaw::find($this->selectedIngestedId)
            : null;

        $laws = collect();
        $uploads = collect();

        if ($selected) {
            $laws = Law::query()
                ->where('ingested_law_id', $selected->id)
                ->orderBy('chunk_index')
                ->paginate(8, ['*'], pageName: 'lawsPage');

            $uploads = LawUpload::query()
                ->where('ingested_law_id', $selected->id)
                ->latest()
                ->paginate(8, ['*'], pageName: 'uploadsPage');
        }

        return view('livewire.ingested-laws-manager', [
            'ingested' => $ingested,
            'selected' => $selected,
            'laws' => $laws,
            'uploads' => $uploads,
        ]);
    }
}
