<?php

namespace App\Http\Controllers;

use App\Services\IngestPipelineService;
use App\Services\LawIngestService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IngestController extends Controller
{
    public function __construct(protected IngestPipelineService $pipeline, protected LawIngestService $laws)
    {
    }

    public function ingestText(Request $request)
    {
        $data = $request->validate([
            'agent' => ['required', 'string'],
            'namespace' => ['sometimes', 'string'],
            'text' => ['required', 'string'],
            'chunk_chars' => ['sometimes', 'integer', 'min:200'],
            'overlap' => ['sometimes', 'integer', 'min:0'],
            'model' => ['sometimes', 'string'],
        ]);
        $namespace = $data['namespace'] ?? 'default';
        $res = $this->pipeline->ingestText($data['agent'], $namespace, $data['text'], $data);
        return response()->json($res);
    }

    public function ingestFile(Request $request)
    {
        $data = $request->validate([
            'agent' => ['required', 'string'],
            'namespace' => ['sometimes', 'string'],
            'file' => ['required', 'file'],
            'chunk_chars' => ['sometimes', 'integer', 'min:200'],
            'overlap' => ['sometimes', 'integer', 'min:0'],
            'model' => ['sometimes', 'string'],
        ]);
        /** @var UploadedFile $file */
        $file = $data['file'];
        $namespace = $data['namespace'] ?? 'default';

        $path = $file->store('tmp/ingest');
        $full = Storage::path($path);
        try {
            $res = $this->pipeline->ingestFile($data['agent'], $namespace, $full, $file->getClientMimeType(), $data);
        } finally {
            Storage::delete($path);
        }
        return response()->json($res);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'agent' => ['required', 'string'],
            'namespace' => ['sometimes', 'string', 'nullable'],
            'query' => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $res = $this->pipeline->search($data['agent'], $data['namespace'] ?? null, $data['query'], $data['limit'] ?? 5);
        return response()->json($res);
    }

    public function ingestLaws(Request $request)
    {
        $data = $request->validate([
            'since_year' => ['sometimes', 'integer'],
            'max_acts' => ['sometimes', 'integer', 'min:1'],
            'agent' => ['sometimes', 'string'],
            'namespace' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'chunk_chars' => ['sometimes', 'integer', 'min:200'],
            'overlap' => ['sometimes', 'integer', 'min:0'],
        ]);
        $res = $this->laws->ingest($data);
        return response()->json($res);
    }
}
