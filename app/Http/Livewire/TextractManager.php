<?php

namespace App\Http\Livewire;

use App\Actions\Textract\ListDrivePdfs;
use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractJob;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class TextractManager extends Component
{
    use WithPagination;

    public string $folderId = '';
    public string $search = '';
    public string $statusFilter = 'all'; // all, queued, started, analyzing, reconstructing, succeeded, failed
    public bool $autoRefresh = false;
    public int $perPage = 20;

    // For manual single file processing
    public string $manualDriveFileId = '';
    public string $manualDriveFileName = '';
    public ?string $selectedCaseForManual = null;

    // Case selection for jobs
    public array $selectedCaseForJob = []; // [jobId => caseId]
    public array $caseOptions = []; // [['id'=>..., 'label'=>...], ...]

    // Job detail view
    public ?int $selectedJobId = null;
    public ?array $selectedJobData = null;

    // Expandable cards
    public array $expandedJobs = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->folderId = (string) env('GOOGLE_DRIVE_FOLDER_ID', '');
        $this->loadCaseOptions();
    }

    private function loadCaseOptions(): void
    {
        $cases = LegalCase::query()
            ->select(['id', 'case_number', 'title'])
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();
        $this->caseOptions = $cases->map(fn($c) => [
            'id' => (string) $c->id,
            'label' => (string) ($c->title ?: $c->case_number ?: $c->id),
        ])->all();
    }

    public function updated($property): void
    {
        if ($property === 'search' || $property === 'statusFilter') {
            $this->resetPage();
        }
    }

    public function assignJobCase(int $jobId): void
    {
        try {
            $caseId = (string) ($this->selectedCaseForJob[$jobId] ?? '');
            if ($caseId === '') {
                $this->dispatch('error', message: 'Select a case first.');
                return;
            }
            $exists = LegalCase::query()->where('id', $caseId)->exists();
            if (!$exists) {
                $this->dispatch('error', message: 'Selected case not found.');
                return;
            }
            $job = TextractJob::findOrFail($jobId);
            $job->update(['case_id' => $caseId]);
            $this->dispatch('success', message: 'Case assigned to job.');
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to assign case: ' . $e->getMessage());
        }
    }

    public function refreshJobs(): void
    {
        $this->resetPage();
        $this->dispatch('jobs-refreshed');
    }

    public function syncFromDrive(): void
    {
        try {
            $folderId = $this->folderId ?: env('GOOGLE_DRIVE_FOLDER_ID');
            if (!$folderId) {
                $this->dispatch('error', message: 'Folder ID not configured');
                return;
            }

            $files = ListDrivePdfs::run($folderId);
            $count = 0;

            foreach ($files as $f) {
                $driveId = (string) ($f['id'] ?? '');
                $name = (string) ($f['name'] ?? 'unknown.pdf');

                if ($driveId === '') continue;

                TextractJob::firstOrCreate(
                    ['drive_file_id' => $driveId],
                    ['drive_file_name' => $name, 'status' => 'queued']
                );
                $count++;
            }

            $this->dispatch('success', message: "Synced {$count} files from Drive");
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Sync failed: ' . $e->getMessage());
        }
    }

    public function processJob(int $jobId, bool $sync = false): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);

            // Ensure a case is selected for this job
            $caseId = (string) ($job->case_id ?: ($this->selectedCaseForJob[$jobId] ?? ''));
            if (!$caseId) {
                $this->dispatch('error', message: 'Please select a case for this job before processing.');
                return;
            }
            // Validate case exists and persist on job
            if (!LegalCase::query()->where('id', $caseId)->exists()) {
                $this->dispatch('error', message: 'Selected case not found.');
                return;
            }
            if ($job->case_id !== $caseId) {
                $job->update(['case_id' => $caseId]);
            }

            if ($sync) {
                ProcessDrivePdf::run($job->drive_file_id, $job->drive_file_name);
                $this->dispatch('success', message: "Job processed synchronously: {$job->drive_file_name}");
            } else {
                ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name);
                $this->dispatch('success', message: "Job queued: {$job->drive_file_name}");
            }

            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to process job: ' . $e->getMessage());
        }
    }

    public function processManual(): void
    {
        if (!$this->manualDriveFileId || !$this->manualDriveFileName) {
            $this->dispatch('error', message: 'Both Drive File ID and Name are required');
            return;
        }
        if (!$this->selectedCaseForManual) {
            $this->dispatch('error', message: 'Please select a case for manual processing.');
            return;
        }

        try {
            // Ensure case exists
            $caseId = (string) $this->selectedCaseForManual;
            if (!LegalCase::query()->where('id', $caseId)->exists()) {
                $this->dispatch('error', message: 'Selected case not found.');
                return;
            }

            // Create or update the job with case_id
            $job = TextractJob::firstOrCreate(
                ['drive_file_id' => $this->manualDriveFileId],
                ['drive_file_name' => $this->manualDriveFileName, 'status' => 'queued']
            );
            if ($job->case_id !== $caseId) {
                $job->update(['case_id' => $caseId]);
            }

            ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name);
            $this->dispatch('success', message: "Job queued: {$job->drive_file_name}");
            $this->manualDriveFileId = '';
            $this->manualDriveFileName = '';
            $this->selectedCaseForManual = null;
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to queue job: ' . $e->getMessage());
        }
    }

    public function retryJob(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            if (!$job->case_id) {
                $this->dispatch('error', message: 'Please select a case for this job before retrying.');
                return;
            }
            $job->update(['status' => 'queued', 'error' => null]);
            ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name);
            $this->dispatch('success', message: "Job retried: {$job->drive_file_name}");
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to retry job: ' . $e->getMessage());
        }
    }

    public function deleteJob(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            $job->delete();
            $this->dispatch('success', message: 'Job deleted');
            $this->refreshJobs();
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to delete job: ' . $e->getMessage());
        }
    }

    public function viewJobDetails(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            $this->selectedJobId = $jobId;

            // Load additional data
            $data = $job->toArray();

            // Case info
            $data['case_id'] = $job->case_id;
            $data['case_label'] = null;
            if ($job->case_id) {
                $case = LegalCase::query()->select(['id','title','case_number'])->find($job->case_id);
                if ($case) {
                    $data['case_label'] = (string) ($case->title ?: $case->case_number ?: $case->id);
                }
            }

            // Check for local files (under the 'local' disk root)
            $textractJsonPath = Storage::disk('local')->path('textract/json/' . $job->drive_file_id . '.json');
            $reconstructedPdfPath = Storage::disk('local')->path('textract/output/' . $job->drive_file_id . '-searchable.pdf');

            $data['has_textract_json'] = is_file($textractJsonPath);
            $data['has_reconstructed_pdf'] = is_file($reconstructedPdfPath);
            $data['textract_json_size'] = $data['has_textract_json'] ? filesize($textractJsonPath) : 0;
            $data['reconstructed_pdf_size'] = $data['has_reconstructed_pdf'] ? filesize($reconstructedPdfPath) : 0;

            // Check S3 files
            try {
                $data['has_s3_input'] = $job->s3_key ? Storage::disk('s3')->exists($job->s3_key) : false;
                $data['has_s3_json'] = Storage::disk('s3')->exists('textract/json/' . $job->drive_file_id . '.json');
                $data['has_s3_output'] = Storage::disk('s3')->exists('textract/output/' . $job->drive_file_id . '-searchable.pdf');
            } catch (\Throwable $e) {
                $data['s3_error'] = $e->getMessage();
            }

            $this->selectedJobData = $data;
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to load job details: ' . $e->getMessage());
        }
    }

    public function closeJobDetails(): void
    {
        $this->selectedJobId = null;
        $this->selectedJobData = null;
    }

    public function toggleJobCard(int $jobId): void
    {
        if (isset($this->expandedJobs[$jobId])) {
            unset($this->expandedJobs[$jobId]);
        } else {
            $this->expandedJobs[$jobId] = true;
        }
    }

    public function downloadTextractJson(int $jobId): void
    {
        try {
            $job = TextractJob::findOrFail($jobId);
            $path = Storage::disk('local')->path('textract/json/' . $job->drive_file_id . '.json');

            if (!is_file($path)) {
                $this->dispatch('error', message: 'Textract JSON not found locally');
                return;
            }

            // For Livewire, we need to redirect to a download route
            $this->dispatch('download-file', path: $path, filename: $job->drive_file_id . '.json');
        } catch (\Throwable $e) {
            $this->dispatch('error', message: 'Failed to download: ' . $e->getMessage());
        }
    }

    public function getJobsProperty()
    {
        $query = TextractJob::query()->orderBy('updated_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('drive_file_name', 'like', '%' . $this->search . '%')
                  ->orWhere('drive_file_id', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate($this->perPage);
    }

    public function getStatsProperty()
    {
        return [
            'total' => TextractJob::count(),
            'queued' => TextractJob::where('status', 'queued')->count(),
            'processing' => TextractJob::whereIn('status', ['started', 'uploading', 'analyzing', 'reconstructing'])->count(),
            'succeeded' => TextractJob::where('status', 'succeeded')->count(),
            'failed' => TextractJob::where('status', 'failed')->count(),
        ];
    }

    public function getStoragePreviewProperty(): array
    {
        $disk = Storage::disk('local');
        $folders = [
            'source' => 'textract/source',
            'json' => 'textract/json',
            'output' => 'textract/output',
        ];

        $preview = [];
        foreach ($folders as $key => $path) {
            try {
                $files = $disk->files($path);
                $entries = [];
                foreach ($files as $f) {
                    $full = $disk->path($f);
                    $entries[] = [
                        'name' => basename($f),
                        'path' => $f,
                        'size' => is_file($full) ? filesize($full) : 0,
                        'mtime' => is_file($full) ? date('Y-m-d H:i:s', filemtime($full)) : null,
                    ];
                }
                // sort by mtime desc
                usort($entries, fn($a, $b) => strcmp((string)($b['mtime'] ?? ''), (string)($a['mtime'] ?? '')));
                $preview[$key] = [
                    'path' => $path,
                    'count' => count($entries),
                    'entries' => array_slice($entries, 0, 50), // limit preview
                ];
            } catch (\Throwable $e) {
                $preview[$key] = [
                    'path' => $path,
                    'count' => 0,
                    'entries' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $preview;
    }

    public function render()
    {
        return view('livewire.textract-manager', [
            'jobs' => $this->jobs,
            'stats' => $this->stats,
            'storagePreview' => $this->storagePreview,
        ]);
    }
}

