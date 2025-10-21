<?php

namespace App\Http\Livewire;

use Illuminate\Support\Facades\File;
use Livewire\Component;
use Carbon\Carbon;

class TranscriptPreviewer extends Component
{
    public string $filePath; // absolute or relative to storage_path
    public string $search = '';
    public array $speakers = []; // ['S1' => true, 'S2' => true]
    public bool $showTimestamps = true;
    public bool $autoRefresh = false;

    // New: lingua forensic analysis
    public string $linguaPath;
    public bool $showLingua = true;

    /** Base start datetime for absolute timestamps (local Europe/Zagreb). */
    public string $baseStart = '2025-06-09 14:45:00';
    public string $timezone = 'Europe/Zagreb';

    /** @var array<int,array{time:string, seconds:int, speaker:string, text:string, has_time:bool, abs?:string, id?:string}> */
    public array $segments = [];

    /** @var array<int,array{time:string, seconds:int, title:string, excerpt:string}> */
    public array $linguaEvents = [];

    /** Raw lingua text (optional display) */
    public string $linguaRaw = '';

    /** First-section summary (Opća analiza) */
    public string $linguaSummary = '';

    /** Derived: duration in seconds from transcript max time */
    public int $durationSec = 0;

    public function mount(?string $path = null): void
    {
        // Default to provided sample transcript under storage/iznedjenaIzjava.txt
        $default = storage_path('iznedjenaIzjava.txt');
        $this->filePath = $path ? $this->resolvePath($path) : $default;

        // Default lingua path
        $this->linguaPath = storage_path('lingua.txt');

        $this->loadTranscript();
        $this->loadLingua();
    }

    public function updated($property): void
    {
        if ($property === 'filePath') {
            $this->filePath = $this->resolvePath($this->filePath);
            $this->loadTranscript();
        }
        if ($property === 'linguaPath') {
            $this->linguaPath = $this->resolvePathLingua($this->linguaPath);
            $this->loadLingua();
        }
    }

    public function refreshNow(): void
    {
        $this->loadTranscript();
        if ($this->showLingua) {
            $this->loadLingua();
        }
    }

    public function toggleSpeaker(string $speaker): void
    {
        $cur = $this->speakers[$speaker] ?? true;
        $this->speakers[$speaker] = !$cur;
    }

    public function allSpeakers(bool $on = true): void
    {
        foreach ($this->speakers as $k => $v) {
            $this->speakers[$k] = $on;
        }
    }

    protected function resolvePath(string $path): string
    {
        // Allow relative paths under storage/ or absolute paths
        $trim = trim($path);
        if ($trim === '') return storage_path('iznedjenaIzjava.txt');
        if (str_starts_with($trim, '/')) return $trim;
        // If user provided something like storage/xyz.txt, normalize to absolute
        if (str_starts_with($trim, 'storage/')) {
            return base_path($trim);
        }
        // treat as relative to storage_path
        return storage_path($trim);
    }

    protected function resolvePathLingua(string $path): string
    {
        $trim = trim($path);
        if ($trim === '') return storage_path('lingua.txt');
        if (str_starts_with($trim, '/')) return $trim;
        if (str_starts_with($trim, 'storage/')) {
            return base_path($trim);
        }
        return storage_path($trim);
    }

    protected function loadTranscript(): void
    {
        $this->segments = [];
        $this->speakers = [];
        $this->durationSec = 0;

        if (! File::exists($this->filePath)) {
            // Try fallback: base_path('storage/iznedjenaIzjava.txt') in case file is at project root storage dir
            $fallback = base_path('storage/iznedjenaIzjava.txt');
            if ($this->filePath !== $fallback && File::exists($fallback)) {
                $this->filePath = $fallback;
            } else {
                return;
            }
        }

        $lines = @file($this->filePath, FILE_IGNORE_NEW_LINES) ?: [];
        $current = [
            'time' => '00:00:00:00',
            'seconds' => 0,
            'speaker' => 'Unknown',
            'text' => '',
            'has_time' => false,
        ];
        $hasCurrent = false;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || str_starts_with($line, '// filepath:')) {
                continue;
            }

            // Match timestamp + speaker header
            if (preg_match('/^(?<t>\d{2}:\d{2}:\d{2}:\d{2})\s+Speaker\s+(?<sp>[\w\-]+)\s*$/u', $line, $m)) {
                if ($hasCurrent) { $this->segments[] = $current; }
                $timeStr = $m['t'];
                $speaker = $m['sp'];
                $seconds = $this->timeToSeconds($timeStr);
                $current = [
                    'time' => $timeStr,
                    'seconds' => $seconds,
                    'speaker' => $speaker,
                    'text' => '',
                    'has_time' => true,
                ];
                $hasCurrent = true;
                $this->speakers[$speaker] = $this->speakers[$speaker] ?? true;
                $this->durationSec = max($this->durationSec, $seconds);
                continue;
            }

            // Fallback match
            if (preg_match('/^(?<t>\d{2}:\d{2}:\d{2}:\d{2})\s+(?<label>Speaker\s+)?(?<sp>[\w\-]+)?$/u', $line, $m2) && ($m2['label'] ?? '') !== '') {
                if ($hasCurrent) { $this->segments[] = $current; }
                $timeStr = $m2['t'];
                $speaker = $m2['sp'] ?: 'Unknown';
                $seconds = $this->timeToSeconds($timeStr);
                $current = [
                    'time' => $timeStr,
                    'seconds' => $seconds,
                    'speaker' => $speaker,
                    'text' => '',
                    'has_time' => true,
                ];
                $hasCurrent = true;
                $this->speakers[$speaker] = $this->speakers[$speaker] ?? true;
                $this->durationSec = max($this->durationSec, $seconds);
                continue;
            }

            // If we have a current block, append text
            if ($hasCurrent) {
                if ($current['text'] !== '') { $current['text'] .= "\n"; }
                $current['text'] .= $raw; // keep original spacing/punctuation
            } else {
                // If transcript starts with text before first timestamp, attach to Unknown at 0
                $current['text'] .= ($current['text'] ? "\n" : '') . $raw;
                $hasCurrent = true;
            }
        }
        if ($hasCurrent) {
            $this->segments[] = $current;
            $this->durationSec = max($this->durationSec, (int)($current['seconds'] ?? 0));
        }

        // Normalize speakers: ensure boolean flags
        foreach ($this->speakers as $k => $v) {
            $this->speakers[$k] = (bool) $v;
        }

        // Sort segments by seconds in case input is unordered
        usort($this->segments, fn($a, $b) => $a['seconds'] <=> $b['seconds']);

        // Attach stable ids to segments for anchor links
        foreach ($this->segments as $i => &$seg) {
            $seg['id'] = 'seg-' . str_pad((string)($seg['seconds'] ?? 0), 6, '0', STR_PAD_LEFT);
        }
        unset($seg);

        // Compute absolute datetimes for segments with known time
        try {
            $base = Carbon::parse($this->baseStart, $this->timezone);
        } catch (\Throwable $e) {
            $base = Carbon::parse('2025-06-09 14:45:00', $this->timezone);
        }
        foreach ($this->segments as &$seg) {
            if (!empty($seg['has_time'])) {
                $dt = (clone $base)->addSeconds((int)($seg['seconds'] ?? 0));
                $seg['abs'] = $dt->format('Y-m-d H:i:s');
            }
        }
        unset($seg);
    }

    protected function loadLingua(): void
    {
        $this->linguaEvents = [];
        $this->linguaRaw = '';
        $this->linguaSummary = '';

        $path = $this->resolvePathLingua($this->linguaPath);
        if (! File::exists($path)) {
            // Try fallback at project root
            $fallback = base_path('storage/lingua.txt');
            if (File::exists($fallback)) {
                $path = $fallback;
            } else {
                return;
            }
        }

        $raw = @file_get_contents($path) ?: '';
        $this->linguaRaw = $raw;

        // Extract first section after '# Opća analiza' as summary
        if ($raw !== '') {
            if (preg_match('/^#\s*Op\s*ća\s+analiza\s*\n+(.+?)(?=\n#|\z)/imsu', $raw, $m)) {
                $summary = trim($m[1]);
                // Collapse whitespace and limit length
                $summary = preg_replace('/\n{2,}/', "\n\n", $summary);
                $summary = trim($summary ?? '');
                if (mb_strlen($summary) > 2500) {
                    $summary = mb_substr($summary, 0, 2500) . '…';
                }
                $this->linguaSummary = $summary;
            }

            // Extract timeline bullets: - **[HH:MM:SS] Title:** Excerpt… up to next bullet or header
            if (preg_match_all('/^\-\s*\*\*\[(\d{2}:\d{2}:\d{2})]\s*(.*?):\*\*\s*(.*?)(?=\n\-\s*\*\*\[|\n#|\z)/imsu', $raw, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $hit) {
                    $t = $hit[1];
                    $title = trim($hit[2]);
                    $excerpt = trim($hit[3]);
                    // Keep only the first 240 chars of excerpt without newlines
                    $excerpt = preg_replace('/\s+/', ' ', $excerpt);
                    if (mb_strlen($excerpt) > 1240) { $excerpt = mb_substr($excerpt, 0, 1240) . '…'; }
                    $seconds = $this->timeToSeconds($t . ':00'); // normalize to HH:MM:SS:FF parser
                    $this->linguaEvents[] = [
                        'time' => $t,
                        'seconds' => $seconds,
                        'title' => $title,
                        'excerpt' => $excerpt,
                    ];
                }
            } else {
                // Fallback: any [HH:MM:SS] in text with a nearby sentence
                if (preg_match_all('/\[(\d{2}:\d{2}:\d{2})]/imu', $raw, $mt)) {
                    foreach ($mt[1] as $t) {
                        $seconds = $this->timeToSeconds($t . ':00');
                        // Create a lightweight title
                        $this->linguaEvents[] = [
                            'time' => $t,
                            'seconds' => $seconds,
                            'title' => 'Bilješka za ' . $t,
                            'excerpt' => 'Dogadjaj označen u forenzičkoj analizi.',
                        ];
                    }
                }
            }
        }

        // Sort and unique by seconds/title
        if (!empty($this->linguaEvents)) {
            usort($this->linguaEvents, fn($a,$b) => $a['seconds'] <=> $b['seconds']);
            $dedup = [];
            $seen = [];
            foreach ($this->linguaEvents as $ev) {
                $key = $ev['seconds'] . '|' . mb_strtolower($ev['title']);
                if (!isset($seen[$key])) { $seen[$key] = true; $dedup[] = $ev; }
            }
            $this->linguaEvents = $dedup;
        }
    }

    protected function timeToSeconds(string $time): int
    {
        // Accept HH:MM:SS:FF, ignoring frames (approximate)
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $h = (int) $m[1]; $mi = (int) $m[2]; $s = (int) $m[3];
            return $h * 3600 + $mi * 60 + $s; // ignoring frames
        }
        return 0;
    }

    /**
     * @return array<int,array{time:string, seconds:int, speaker:string, text:string}>
     */
    public function getFilteredProperty(): array
    {
        $needle = trim($this->search);
        $hasSearch = $needle !== '';
        $speakersOn = array_keys(array_filter($this->speakers, fn($v) => $v));

        return array_values(array_filter($this->segments, function ($seg) use ($hasSearch, $needle, $speakersOn) {
            if (!in_array($seg['speaker'], $speakersOn, true)) return false;
            if ($hasSearch) {
                $hay = mb_strtolower($seg['speaker'] . ' ' . ($seg['text'] ?? ''));
                if (!str_contains($hay, mb_strtolower($needle))) return false;
            }
            return true;
        }));
    }

    /**
     * Provide lingua events near a given segment time (±15s window)
     * @param int $sec
     * @return array<int,array{time:string, seconds:int, title:string, excerpt:string}>
     */
    public function eventsNear(int $sec): array
    {
        if (empty($this->linguaEvents)) return [];
        $win = 15;
        $out = [];
        foreach ($this->linguaEvents as $ev) {
            $d = abs(($ev['seconds'] ?? 0) - $sec);
            if ($d <= $win) { $out[] = $ev; }
        }
        return $out;
    }

    public function render()
    {
        return view('livewire.transcript-previewer');
    }

    /**
     * Find the nearest segment id to a given seconds timestamp (prefers <= sec)
     */
    public function segmentIdForSeconds(int $sec): string
    {
        if (empty($this->segments)) return 'seg-000000';
        // Exact or floor match
        $best = null; $bestDelta = PHP_INT_MAX;
        $floor = null; $floorTime = -1;
        foreach ($this->segments as $seg) {
            $s = (int)($seg['seconds'] ?? 0);
            if ($s <= $sec && $s > $floorTime) { $floor = $seg; $floorTime = $s; }
            $d = abs($s - $sec);
            if ($d < $bestDelta) { $bestDelta = $d; $best = $seg; }
        }
        $target = $floor ?: ($best ?: $this->segments[0]);
        return (string)($target['id'] ?? ('seg-' . str_pad((string)($target['seconds'] ?? 0), 6, '0', STR_PAD_LEFT)));
    }
}
