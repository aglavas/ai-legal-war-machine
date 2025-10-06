<?php

namespace App\Http\Controllers;

use App\Services\UploadService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class UploadController extends Controller
{
    public function __construct(protected UploadService $uploads)
    {
    }

    // Direct upload
    public function direct(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
        ]);
        /** @var UploadedFile $file */
        $file = $data['file'];
        $res = $this->uploads->directStore($file);
        return response()->json($res);
    }

    // Chunked: start
    public function start(Request $request)
    {
        $data = $request->validate([
            'filename' => ['required', 'string'],
            'totalSize' => ['required', 'integer', 'min:1'],
            'chunkSize' => ['required', 'integer', 'min:1'],
            'mime' => ['sometimes', 'string'],
        ]);
        $res = $this->uploads->start($data['filename'], $data['totalSize'], $data['chunkSize'], $data['mime'] ?? null);
        return response()->json($res);
    }

    // Chunked: upload a part
    public function chunk(Request $request, string $uploadId, int $index)
    {
        $data = $request->validate([
            'chunk' => ['required', 'file'],
        ]);
        /** @var UploadedFile $part */
        $part = $data['chunk'];
        $res = $this->uploads->uploadChunk($uploadId, $index, $part);
        return response()->json($res);
    }

    // Chunked: complete
    public function complete(string $uploadId)
    {
        $res = $this->uploads->complete($uploadId);
        return response()->json($res);
    }

    // Chunked: cancel
    public function cancel(string $uploadId)
    {
        $ok = $this->uploads->cancel($uploadId);
        return response()->json(['ok' => (bool) $ok]);
    }
}

