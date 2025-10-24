<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    protected string $chunkRoot = 'uploads/chunks';
    protected string $manifestRoot = 'uploads/manifests';
    protected string $finalRoot = 'uploads';
    protected string $finalDisk = 'public';

    public function start(string $filename, int $totalSize, int $chunkSize, ?string $mime = null): array
    {
        $uploadId = (string) Str::ulid();
        $manifest = [
            'id' => $uploadId,
            'filename' => basename($filename),
            'mime' => $mime,
            'total_size' => $totalSize,
            'chunk_size' => max(1, $chunkSize),
            'created_at' => now()->toIso8601String(),
            'received' => [],
            'completed' => false,
        ];
        Storage::put($this->manifestPath($uploadId), json_encode($manifest, JSON_UNESCAPED_UNICODE));
        Storage::makeDirectory($this->chunkDir($uploadId));
        return $manifest;
    }

    public function uploadChunk(string $uploadId, int $index, UploadedFile $chunk): array
    {
        $manifest = $this->getManifest($uploadId);
        if (!$manifest) {
            throw new \InvalidArgumentException('Invalid upload ID');
        }
        $path = $this->chunkPath($uploadId, $index);
        // Avoid trusting user filename; store as fixed part name
        $chunk->storeAs($this->chunkDir($uploadId), basename($path));

        $manifest['received'] = array_values(array_unique(array_merge($manifest['received'], [$index])));
        Storage::put($this->manifestPath($uploadId), json_encode($manifest, JSON_UNESCAPED_UNICODE));
        return [
            'id' => $uploadId,
            'index' => $index,
            'received' => $manifest['received'],
        ];
    }

    public function complete(string $uploadId): array
    {
        $manifest = $this->getManifest($uploadId);
        if (!$manifest) {
            throw new \InvalidArgumentException('Invalid upload ID');
        }
        $totalSize = (int) $manifest['total_size'];
        $chunkSize = (int) $manifest['chunk_size'];
        $numChunks = (int) ceil($totalSize / max(1, $chunkSize));
        $received = collect($manifest['received'])->unique()->sort()->values()->all();

        if (count($received) !== $numChunks) {
            return [
                'status' => 'incomplete',
                'expected' => $numChunks,
                'received' => $received,
            ];
        }

        $tmpPath = 'tmp/uploads/' . $manifest['id'] . '.part';
        Storage::put($tmpPath, '');
        $stream = Storage::readStream($tmpPath);
        if ($stream) {
            fclose($stream);
        }

        $finalTmp = Storage::path($tmpPath);
        $out = fopen($finalTmp, 'w');
        try {
            for ($i = 0; $i < $numChunks; $i++) {
                $chunkFile = Storage::path($this->chunkPath($uploadId, $i));
                $in = fopen($chunkFile, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $safeName = $this->safeFilename($manifest['filename']);
        $finalRel = $this->finalRoot . '/' . $manifest['id'] . '-' . $safeName;
        // store on public disk
        Storage::disk($this->finalDisk)->put($finalRel, file_get_contents($finalTmp));
        Storage::delete($tmpPath);

        // Cleanup chunks and mark completed
        Storage::deleteDirectory($this->chunkDir($uploadId));
        $manifest['completed'] = true;
        $manifest['stored_at'] = now()->toIso8601String();
        $manifest['path'] = $finalRel;
        $manifest['disk'] = $this->finalDisk;
        $manifest['url'] = Storage::disk($this->finalDisk)->url($finalRel);
        Storage::put($this->manifestPath($uploadId), json_encode($manifest, JSON_UNESCAPED_UNICODE));

        return [
            'status' => 'completed',
            'id' => $manifest['id'],
            'filename' => $manifest['filename'],
            'path' => $finalRel,
            'url' => $manifest['url'],
        ];
    }

    public function cancel(string $uploadId): bool
    {
        Storage::deleteDirectory($this->chunkDir($uploadId));
        Storage::delete($this->manifestPath($uploadId));
        return true;
    }

    public function directStore(UploadedFile $file): array
    {
        $path = $file->store($this->finalRoot, $this->finalDisk);
        return [
            'path' => $path,
            'url' => Storage::disk($this->finalDisk)->url($path),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'name' => $file->getClientOriginalName(),
        ];
    }

    protected function chunkDir(string $uploadId): string
    {
        return $this->chunkRoot . '/' . $uploadId;
    }

    protected function chunkPath(string $uploadId, int $index): string
    {
        return $this->chunkDir($uploadId) . '/part_' . $index;
    }

    protected function manifestPath(string $uploadId): string
    {
        return $this->manifestRoot . '/' . $uploadId . '.json';
    }

    protected function getManifest(string $uploadId): ?array
    {
        if (!Storage::exists($this->manifestPath($uploadId))) return null;
        return json_decode(Storage::get($this->manifestPath($uploadId)), true);
    }

    protected function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_\-.]+/', '-', $name);
        return trim($name, '-');
    }
}

