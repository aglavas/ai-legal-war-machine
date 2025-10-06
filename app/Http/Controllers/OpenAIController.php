<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpenAIController extends Controller
{
    public function __construct(protected OpenAIService $openai)
    {
    }

    public function responses(Request $request)
    {
        $data = $request->validate([
            'input' => ['required'],
            'model' => ['nullable', 'string'],
            'tools' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        $payload = $data;
        // Map 'input' for the Responses API: allow string or array
        $payload['input'] = $data['input'];
        $res = $this->openai->responses($payload);
        return response()->json($res);
    }

    public function chat(Request $request)
    {
        $data = $request->validate([
            'messages' => ['required', 'array'],
            'model' => ['nullable', 'string'],
        ]);
        $res = $this->openai->chat($data['messages'], $data['model'] ?? null, []);
        return response()->json($res);
    }

    public function embeddings(Request $request)
    {
        $data = $request->validate([
            'input' => ['required'],
            'model' => ['nullable', 'string'],
        ]);
        $res = $this->openai->embeddings($data['input'], $data['model'] ?? null);
        return response()->json($res);
    }

    public function image(Request $request)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string'],
            'n' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'size' => ['sometimes', 'string'],
            'response_format' => ['sometimes', 'string'],
        ]);
        $res = $this->openai->imageGenerate($data['prompt'], $data);
        return response()->json($res);
    }

    public function tts(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
            'voice' => ['sometimes', 'string'],
            'format' => ['sometimes', 'in:mp3,wav,flac,ogg'],
            'model' => ['sometimes', 'string'],
        ]);
        $audio = $this->openai->tts($data['text'], $data);

        $format = $data['format'] ?? 'mp3';
        $mime = match ($format) {
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            default => 'audio/mpeg',
        };

        return response($audio, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="speech.' . $format . '"',
        ]);
    }

    public function transcribe(Request $request)
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp3,audio/ogg,audio/webm'],
            'model' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string'],
            'prompt' => ['sometimes', 'string'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['audio'];
        $path = $file->store('tmp/openai-audio');
        $full = Storage::path($path);
        try {
            $res = $this->openai->transcribe($full, $data);
        } finally {
            // cleanup temp file
            Storage::delete($path);
        }
        return response()->json($res);
    }

    // Files API proxies
    public function filesUpload(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
            'purpose' => ['sometimes', 'string'],
        ]);
        /** @var UploadedFile $file */
        $file = $data['file'];
        $purpose = $data['purpose'] ?? 'assistants';

        $path = $file->store('tmp/openai-files');
        $full = Storage::path($path);
        try {
            $res = $this->openai->fileUpload($full, $purpose);
        } finally {
            Storage::delete($path);
        }
        return response()->json($res);
    }

    public function filesList()
    {
        return response()->json($this->openai->fileList());
    }

    public function filesDelete(string $fileId)
    {
        return response()->json($this->openai->fileDelete($fileId));
    }

    // Assistants
    public function assistantsCreate(Request $request)
    {
        $data = $request->validate([
            'model' => ['sometimes', 'string'],
            'name' => ['sometimes', 'string'],
            'instructions' => ['sometimes', 'string'],
            'tools' => ['sometimes', 'array'],
            'metadata' => ['sometimes', 'array'],
        ]);
        return response()->json($this->openai->assistantsCreate($data));
    }

    public function assistantsRetrieve(string $assistantId)
    {
        return response()->json($this->openai->assistantsRetrieve($assistantId));
    }

    public function assistantsList(Request $request)
    {
        $query = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        return response()->json($this->openai->assistantsList($query));
    }

    public function assistantsDelete(string $assistantId)
    {
        return response()->json($this->openai->assistantsDelete($assistantId));
    }

    // Vector Stores
    public function vectorStoreCreate(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string'],
        ]);
        return response()->json($this->openai->vectorStoreCreate($data));
    }

    public function vectorStoreRetrieve(string $storeId)
    {
        return response()->json($this->openai->vectorStoreRetrieve($storeId));
    }

    public function vectorStoreList(Request $request)
    {
        $query = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        return response()->json($this->openai->vectorStoreList($query));
    }

    public function vectorStoreDelete(string $storeId)
    {
        return response()->json($this->openai->vectorStoreDelete($storeId));
    }

    public function vectorStoreAddFile(Request $request, string $storeId)
    {
        $data = $request->validate([
            'fileId' => ['required', 'string'],
        ]);
        return response()->json($this->openai->vectorStoreAddFile($storeId, $data['fileId']));
    }

    public function vectorStoreListFiles(Request $request, string $storeId)
    {
        $query = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        return response()->json($this->openai->vectorStoreListFiles($storeId, $query));
    }

    public function vectorStoreDeleteFile(string $storeId, string $fileId)
    {
        return response()->json($this->openai->vectorStoreDeleteFile($storeId, $fileId));
    }
}
