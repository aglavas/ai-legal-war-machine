<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Services\OpenAIService;
class OpenAIVectorManager extends Component
{
    /**
     * @var array $stores
     */
    public array $stores = [];

    /**
     * @var string|null $selectedStore
     */
    public string|null $selectedStore = null;

    /**
     * @var array $files
     */
    public array $files = [];

    /**
     * @var string|null $selectedFile
     */
    public string|null $selectedFile = null;

    /**
     * @var array $metadata
     */
    public array $metadata = [];

    /**
     * @var array $fileAttributes
     */
    public array $fileAttributes = [];

    /**
     * @var string $newMetadata
     */
    public string $newMetadata = '';

    /**
     * @var string $newAttributes
     */
    public string $newAttributes = '';

    /**
     * @var string|null $error
     */
    public ?string $error = null;

    /**
     * @var array $rules
     */
    protected $rules = [
        'newMetadata' => 'nullable|string',
        'newAttributes' => 'nullable|string',
    ];

    /**
     * @return void
     */
    public function mount()
    {
        $this->fetchStores();
    }

    /**
     * @return void
     */
    public function fetchStores()
    {
        try {
            $service = app(OpenAIService::class);
            $this->stores = $service->vectorStoreList()['data'] ?? [];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function selectStore($storeId)
    {
        $this->selectedStore = $storeId;
        $this->fetchFiles();
    }

    /**
     *
     */
    public function fetchFiles()
    {
        $this->files = [];
        $this->selectedFile = null;
        $this->metadata = [];
        $this->fileAttributes = [];
        if (!$this->selectedStore) return;
        try {
            $service = app(OpenAIService::class);
            $this->files = $service->vectorStoreListFiles($this->selectedStore)['data'] ?? [];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    public function selectFile($fileId)
    {
        $this->selectedFile = $fileId;
        $this->fetchFileMeta();
    }

    public function fetchFileMeta()
    {
        $this->metadata = [];
        $this->fileAttributes = [];
        if (!$this->selectedFile) return;
        try {
            $service = app(OpenAIService::class);
            $file = $service->vectorStoreGetFile($this->selectedStore, $this->selectedFile);
            $this->metadata = $file ?? [];
            $this->fileAttributes = $file['attributes'] ?? [];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * @return void
     */
    public function saveMeta(): void
    {
        $this->validate();
        // Pretpostavljamo da postoji metoda za spremanje metapodataka/atributa
        try {
            $service = app(OpenAIService::class);
            $payload = [];

            if (!empty($this->newMetadata)) {
                $payload['metadata'] = json_decode($this->newMetadata, true);
            }

            if (!empty($this->newAttributes)) {
                $payload['attributes'] = json_decode($this->newAttributes, true);
            }

            $response = $service->vectorStoreFileMetadataUpdate($this->selectedStore, $this->selectedFile, $payload);
            $this->fetchFileMeta();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     *
     */
    public function render()
    {
        return view('livewire.openai-vector-manager');
    }
}
