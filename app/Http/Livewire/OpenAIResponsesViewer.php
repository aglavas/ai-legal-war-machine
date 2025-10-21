<?php

namespace App\Http\Livewire;

use App\Services\OpenAIService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class OpenAIResponsesViewer extends Component
{
    #[Url]
    public ?string $from = null; // Y-m-d

    #[Url]
    public ?string $to = null;   // Y-m-d

    #[Url]
    public string $search = '';

    #[Url]
    public int $limit = 20;

    #[Url]
    public string $order = 'desc'; // or 'asc'

    public array $items = [];
    public ?string $error = null;

    public function mount(): void
    {
        // Defaults: last 7 days
        if (!$this->to) {
            $this->to = now()->format('Y-m-d');
        }
        if (!$this->from) {
            $this->from = now()->subDays(7)->format('Y-m-d');
        }

        $this->loadResponses();
    }

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'limit', 'order', 'search'], true)) {
            $this->loadResponses();
        }
    }

    public function refreshNow(): void
    {
        $this->loadResponses();
    }

    protected function loadResponses(): void
    {
        $this->error = null;
        $this->items = [];

        // Validate and convert date filters
        $fromTs = $this->parseDateToTs($this->from);
        $toTs = $this->parseDateToTs($this->to, endOfDay: true);
        if ($fromTs && $toTs && $fromTs > $toTs) {
            $this->error = 'Invalid range: from date is after to date.';
            return;
        }

        try {
            /** @var OpenAIService $svc */
            $svc = app(OpenAIService::class);

            $query = [
                'created_after' => $fromTs,
                'created_before' => $toTs,
                'limit' => max(1, min(100, (int) $this->limit)),
                'order' => in_array(strtolower($this->order), ['asc', 'desc'], true) ? strtolower($this->order) : 'desc',
                'input_item_limit' => 1,
                'output_item_limit' => 1,
            ];

            $include = [
                'message.input_text',
                'message.input_image.image_url',
                'output_text',
                'computer_call_output.output.image_url',
                'file_search_call.results',
            ];

            $resp = $svc->getResponses($query, $include);
            $data = (array) ($resp['data'] ?? []);

            $items = [];
            foreach ($data as $row) {
                $items[] = $this->mapResponseRow((array) $row);
            }

            // Optional search filter on mapped content
            if ($this->search) {
                $q = mb_strtolower($this->search);
                $items = array_values(array_filter($items, function ($it) use ($q) {
                    $hay = mb_strtolower(json_encode([$it['id'], $it['input_text'], $it['output_text'], $it['model'] ?? '', $it['created_at'] ?? '']));
                    return str_contains($hay, $q);
                }));
            }

            $this->items = $items;
        } catch (Throwable $e) {
            // Surface a friendly message without leaking secrets
            $this->error = 'Failed to load OpenAI responses: ' . $e->getMessage();
            Log::warning('openai.responses.viewer.error', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        }
    }

    protected function parseDateToTs(?string $date, bool $endOfDay = false): ?int
    {
        if (!$date) return null;
        try {
            $dt = Carbon::parse($date);
            if ($endOfDay) {
                $dt = $dt->endOfDay();
            }
            return $dt->timestamp;
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function mapResponseRow(array $row): array
    {
        $id = (string) ($row['id'] ?? Arr::get($row, 'response.id', ''));
        $created = Arr::get($row, 'created') ?? Arr::get($row, 'created_at');
        $createdAt = is_numeric($created) ? (int) $created : (is_string($created) ? strtotime($created) : null);
        $model = (string) ($row['model'] ?? Arr::get($row, 'response.model', ''));

        // Input and output summary (best-effort)
        $inputText = Arr::get($row, 'message.input_text');
        if (!$inputText) {
            $inputText = Arr::get($row, 'input_text');
        }
        if (is_array($inputText)) {
            $inputText = trim((string) ($inputText['text'] ?? json_encode($inputText)));
        }

        $outputText = Arr::get($row, 'output_text');
        if (!$outputText) {
            // try to synthesize from output items
            $outputs = (array) ($row['output'] ?? []);
            $texts = [];
            foreach ($outputs as $o) {
                $t = Arr::get($o, 'content.0.text') ?? Arr::get($o, 'text');
                if ($t) $texts[] = is_array($t) ? json_encode($t) : (string) $t;
            }
            $outputText = $texts ? implode("\n\n", $texts) : null;
        }

        // Image URLs best-effort
        $images = [];
        $img1 = Arr::get($row, 'message.input_image.image_url');
        if ($img1) $images[] = $img1;
        $img2s = Arr::get($row, 'computer_call_output.output.image_url');
        if (is_array($img2s)) {
            foreach ($img2s as $u) { $images[] = (string) $u; }
        } elseif (is_string($img2s)) {
            $images[] = $img2s;
        }

        return [
            'id' => $id,
            'created_at' => $createdAt ? date('Y-m-d H:i:s', $createdAt) : null,
            'model' => $model,
            'input_text' => $this->clip($inputText, 2000),
            'output_text' => $this->clip($outputText, 4000),
            'images' => $images,
            'raw' => $row,
        ];
    }

    protected function clip($text, int $limit): ?string
    {
        if ($text === null) return null;
        $s = (string) $text;
        if (mb_strlen($s) > $limit) {
            return mb_substr($s, 0, $limit - 1) . '…';
        }
        return $s;
    }

    public function render()
    {
        return view('livewire.openai-responses-viewer');
    }
}

