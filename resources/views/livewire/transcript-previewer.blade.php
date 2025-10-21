<div class="container mx-auto max-w-5xl p-4">
    <div class="controls">
        <div class="ctrl">
            <label>Transcript path</label>
            <input type="text" class="in" placeholder="storage/iznedjenaIzjava.txt" wire:model.lazy="filePath" />
        </div>
        <div class="ctrl">
            <label>Search</label>
            <input type="text" class="in small" placeholder="speaker, phrase…" wire:model.debounce.400ms="search" />
        </div>

        <span class="switch" title="Show/hide timestamps">
            <input type="checkbox" id="tsToggle" wire:model="showTimestamps" />
            <label for="tsToggle">Timestamps</label>
        </span>
        <span class="switch" title="Auto refresh every 5s">
            <input type="checkbox" id="arToggle" wire:model="autoRefresh" />
            <label for="arToggle">Auto refresh</label>
        </span>

        <button type="button" class="btn" wire:click="refreshNow">Refresh</button>
        <button type="button" class="btn" wire:click="$set('search','')">Clear</button>

        <span class="chip" title="Base start datetime">🕒 Base: {{ $baseStart }}</span>
        <span class="chip" title="Timezone">🌍 {{ $timezone }}</span>
        <span class="chip" title="Source path">📄 {{ \Illuminate\Support\Str::limit($filePath, 48) }}</span>
    </div>

    <div class="controls" style="margin-top:6px">
        <div class="ctrl">
            <label>Lingua analysis path</label>
            <input type="text" class="in small" placeholder="storage/lingua.txt" wire:model.lazy="linguaPath" />
        </div>
        <span class="switch" title="Show/hide forensics panel">
            <input type="checkbox" id="lgToggle" wire:model="showLingua" />
            <label for="lgToggle">Forensics</label>
        </span>
        @if(!empty($linguaEvents))
            <span class="chip" title="Detected events">🔎 {{ count($linguaEvents) }} events</span>
        @endif
    </div>

    @if($showLingua)
        <div class="seg" style="margin:10px 0 14px;">
            <div class="head" style="margin-bottom:10px">
                <span class="chip">🧠 Forensic summary</span>
                @if(!empty($durationSec))
                    <span class="chip" title="Transcript duration">⏱️ {{ gmdate('H:i:s', max(0,$durationSec)) }}</span>
                @endif
            </div>
            @if(!empty($linguaSummary))
                <div class="txt" style="margin-bottom:10px; white-space:pre-line;">{{ $linguaSummary }}</div>
            @endif

            @if(!empty($linguaEvents))
                <div class="txt" style="margin:6px 0 8px; font-size:13px; color:#cbd5e1">Timeline</div>
                <div style="position:relative; height:26px; background:#0b1220; border:1px solid var(--border); border-radius:999px; display:flex; align-items:center; padding:0 8px; overflow:hidden">
                    <div style="position:absolute; left:0; right:0; height:2px; background:#334155; top:50%; transform:translateY(-50%)"></div>
                    @php $dur = max(1, $durationSec); @endphp
                    @foreach($linguaEvents as $ev)
                        @php $pct = min(100, max(0, round(($ev['seconds'] / $dur) * 100, 2))); @endphp
                        @php
                            $segId = method_exists($this, 'segmentIdForSeconds') ? $this->segmentIdForSeconds((int)$ev['seconds']) : ('seg-' . str_pad((string)$ev['seconds'], 6, '0', STR_PAD_LEFT));
                        @endphp
                        <a href="#{{ $segId }}" title="{{ $ev['time'] }} • {{ $ev['title'] }}" style="position:absolute; left:{{ $pct }}%; transform:translateX(-50%); text-decoration:none;">
                            <span class="chip" style="padding:2px 6px; font-size:11px; background:var(--info); color:#0b1220; border-color:#0ea5e9">{{ $ev['time'] }}</span>
                        </a>
                    @endforeach
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px">
                    @foreach($linguaEvents as $ev)
                        @php
                            $segId = method_exists($this, 'segmentIdForSeconds') ? $this->segmentIdForSeconds((int)$ev['seconds']) : ('seg-' . str_pad((string)$ev['seconds'], 6, '0', STR_PAD_LEFT));
                        @endphp
                        <a href="#{{ $segId }}" class="chip" style="text-decoration:none" title="Jump to {{ $ev['time'] }}">
                            <strong style="margin-right:6px">{{ $ev['time'] }}</strong>
                            <span>{{ $ev['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="chip">No timestamped events found in lingua.txt</div>
            @endif
        </div>
    @endif

    @if(!empty($speakers))
        <div class="controls" style="margin-top: 6px">
            <div class="ctrl" style="gap:8px">
                <label>Speakers</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px">
                    @foreach(array_keys($speakers) as $sp)
                        <label class="chip" style="cursor:pointer">
                            <input type="checkbox" class="accent-sky-500" wire:model.live="speakers.{{ $sp }}" />
                            <span class="font-medium">{{ $sp }}</span>
                        </label>
                    @endforeach
                    <button type="button" class="btn" wire:click="allSpeakers(true)">All</button>
                    <button type="button" class="btn" wire:click="allSpeakers(false)">None</button>
                </div>
            </div>
        </div>
    @endif

    <div @if($autoRefresh) wire:poll.5s="refreshNow" @endif>
        @php $items = $this->filtered; @endphp
        @if(empty($items))
            <div class="chip">No transcript segments to display. Adjust the path, or check that the file exists.</div>
        @else
            <ul class="seg-list">
                @foreach($items as $seg)
                    <li class="seg" id="{{ $seg['id'] ?? '' }}">
                        <div class="head">
                            @if($showTimestamps)
                                <span class="chip" title="Segment timecode">⏱️ {{ $seg['time'] }}</span>
                            @endif
                            <span class="chip" title="Speaker">🎤 {{ $seg['speaker'] }}</span>
                            @if(($seg['has_time'] ?? false) && !empty($seg['abs']))
                                <span class="chip" title="Absolute date-time based on base start">🗓️ {{ $seg['abs'] }}</span>
                            @endif
                        </div>
                        <div class="txt">
                            @php
                                $text = $seg['text'] ?? '';
                                $html = e($text);
                                $q = trim($this->search);
                                if ($q !== '') {
                                    $pattern = '/(' . preg_quote($q, '/') . ')/iu';
                                    $replaced = @preg_replace($pattern, '<mark>$1</mark>', $html);
                                    if ($replaced !== null) { $html = $replaced; }
                                }
                            @endphp
                            {!! nl2br($html) !!}
                        </div>

                        @if($showLingua && ($seg['has_time'] ?? false))
                            @php $near = $this->eventsNear((int)($seg['seconds'] ?? 0)); @endphp
                            @if(!empty($near))
                                <div class="head" style="margin-top:8px">
                                    @foreach($near as $ev)
                                        <a href="#{{ $seg['id'] ?? '' }}" class="chip" title="{{ $ev['time'] }}: {{ $ev['title'] }}" style="background:var(--chip); text-decoration:none">
                                            <span style="background:var(--warn); width:8px; height:8px; border-radius:999px; display:inline-block"></span>
                                            <strong>{{ $ev['time'] }}</strong>
                                            <span>{{ $ev['title'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                                <details class="txt" style="font-size:13px; color:#cbd5e1">
                                    <summary style="cursor:pointer; color:#e2e8f0; font-weight:500">
                                        Detalji ({{ count($near) }})
                                    </summary>
                                    <div style="margin-top:6px">
                                        @foreach($near as $ev)
                                            <div style="margin-top:4px"><em>{{ $ev['title'] }}:</em> {{ $ev['excerpt'] }}</div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
