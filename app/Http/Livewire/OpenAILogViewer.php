<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\File;

class OpenAILogViewer extends Component
{
    public string $path;

    public int $limit = 200; // number of lines to tail
    public string $search = '';
    public array $eventTypes = ['openai.request' => true, 'openai.response' => true, 'openai.error' => true];
    public ?string $requestId = null;
    public bool $autoRefresh = true;

    public array $entries = [];

    public function mount()
    {
        $this->path = storage_path('logs/openai.log');
        $this->loadEntries();
    }

    public function updated($property)
    {
        if (in_array($property, ['limit', 'search', 'requestId'])) {
            $this->loadEntries();
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->requestId = null;
        $this->eventTypes = ['openai.request' => true, 'openai.response' => true, 'openai.error' => true];
        $this->loadEntries();
    }

    public function filterByRequest(string $reqId)
    {
        $this->requestId = $reqId;
        $this->loadEntries();
    }

    public function toggleEvent(string $event)
    {
        $current = $this->eventTypes[$event] ?? false;
        $this->eventTypes[$event] = !$current;
        $this->loadEntries();
    }

    public function refreshNow()
    {
        $this->loadEntries();
    }

    protected function loadEntries(): void
    {
        $this->entries = [];
        if (! File::exists($this->path)) {
            return;
        }
        $lines = $this->tail($this->path, max(10, (int) $this->limit));
        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $data = json_decode($line, true);
            if (!is_array($data)) continue;

            $message = $data['message'] ?? '';
            $context = $data['context'] ?? [];
            $level = $data['level_name'] ?? ($data['level'] ?? '');
            $channel = $data['channel'] ?? '';
            $datetime = $data['datetime'] ?? null; // Monolog JsonFormatter usually string
            $reqId = $context['request_id'] ?? null;

            // Filters
            if ($this->requestId && $reqId !== $this->requestId) continue;
            if (!(($this->eventTypes[$message] ?? false))) continue;
            if ($this->search) {
                $hay = strtolower(json_encode([$message, $context, $level, $channel, $datetime]));
                if (!str_contains($hay, strtolower($this->search))) continue;
            }

            $parsed[] = [
                'message' => $message,
                'context' => $context,
                'level' => $level,
                'channel' => $channel,
                'datetime' => $datetime,
                'request_id' => $reqId,
            ];
        }
        // Sort by latest first based on array order returned by tail (tail keeps original order); ensure latest last line shows first
        $this->entries = array_reverse($parsed);
    }

    /**
     * Read last N lines from a file without loading entire file in memory.
     * @return array<int,string>
     */
    protected function tail(string $filepath, int $lines = 200): array
    {
        $f = @fopen($filepath, 'rb');
        if ($f === false) return [];

        $buffer = '';
        $chunkSize = 4096;
        $pos = -1;
        $lineCount = 0;
        $result = [];
        fseek($f, 0, SEEK_END);
        $fileSize = ftell($f);
        if ($fileSize === 0) { fclose($f); return []; }

        $cursor = $fileSize;
        while ($cursor > 0 && $lineCount <= $lines) {
            $read = min($chunkSize, $cursor);
            $cursor -= $read;
            fseek($f, $cursor);
            $chunk = fread($f, $read);
            if ($chunk === false) break;
            $buffer = $chunk . $buffer;
            // Count lines
            $linesInBuffer = substr_count($buffer, "\n");
            if ($linesInBuffer >= $lines + 1) {
                break;
            }
        }
        fclose($f);

        $arr = preg_split("/[\r\n]+/", $buffer); // split into lines
        // Remove potential trailing empty line
        $arr = array_values(array_filter($arr, fn($l) => $l !== null));
        // Return last N lines keeping original order
        return array_slice($arr, -$lines);
    }

    public function render()
    {
        return view('livewire.openai-log-viewer');
    }
}

