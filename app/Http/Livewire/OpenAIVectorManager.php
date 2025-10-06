<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Services\OpenAIService;

class OpenAIVectorManager extends Component
{
    public $stores = [];
    public $selectedStore = null;
    public $files = [];
    public $selectedFile = null;
    public $metadata = [];
    public $fileAttributes = [];
    public $newMetadata = "";
    public $newAttributes = "";
    public $error = null;

    protected $rules = [
        'newMetadata' => 'nullable|string',
        'newAttributes' => 'nullable|string',
    ];

    public function mount()
    {
        $this->fetchStores();
    }

    public function fetchStores()
    {
        try {
            $service = app(OpenAIService::class);
            $this->stores = $service->vectorStoreList()['data'] ?? [];
        } catch (\Exception $e) {
            dd($e->getMessage());
            $this->error = $e->getMessage();
        }
    }

    public function selectStore($storeId)
    {
        $this->selectedStore = $storeId;
        $this->fetchFiles();
    }

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
            dd($e->getMessage());
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
            $file = $service->fileRetrieve($this->selectedFile);
            $this->metadata = $file['metadata'] ?? [];
            $this->fileAttributes = $file['attributes'] ?? [];
        } catch (\Exception $e) {
            dd($e->getMessage());
            $this->error = $e->getMessage();
        }
    }

    public function saveMeta()
    {
        $this->validate();
        // Pretpostavljamo da postoji metoda za spremanje metapodataka/atributa
        try {
            $service = app(OpenAIService::class);
            $payload = [];
            if ($this->newMetadata) {
                $payload['metadata'] = json_decode($this->newMetadata, true);
            }
            if ($this->newAttributes) {
                $payload['attributes'] = json_decode($this->newAttributes, true);
            }
            // Ovdje bi išao API call za update file-a, placeholder:
            // $service->fileUpdate($this->selectedFile, $payload);
            // Za sada samo simuliramo refresh
            $this->fetchFileMeta();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            die();
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.openai-vector-manager');
    }
}
