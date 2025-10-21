<div id="single-timeline" class="wrap" wire:keydown.escape.window="closeEvidence" data-lw-id="{{ rand() }}">
    <div class="card">
        <h1>Usporedni timeline: Stvarna vs. Službena kronologija</h1>
        <div class="sub">
            Dva sinkronizirana prikaza za jednostavnu vizualnu usporedbu. Pomiči/zoomiraj jedan — drugi prati. Klikni stavke za isticanje u oba.
        </div>

        <div class="controls">
            <button class="btn" id="fitBoth" aria-label="Prikaži sve">Prikaži sve</button>
            <button class="btn" id="zoomIn" aria-label="Zoom in">Zoom +</button>
            <button class="btn" id="zoomOut" aria-label="Zoom out">Zoom −</button>

            <span class="switch" title="Sinkroniziraj pomak i zum">
                <input type="checkbox" id="linkToggle" checked />
                <label for="linkToggle">Sinkroniziraj pomak/zoom</label>
            </span>

            <span class="switch" title="Uključi/isključi vodič (crosshair)">
                <input type="checkbox" id="crosshairToggle" checked />
                <label for="crosshairToggle">Vodič</label>
            </span>

            <button class="btn" id="exportJson" aria-label="Izvezi JSON">Izvezi JSON</button>
            <button class="btn" id="testOpen" aria-label="Testiraj modal">Test modal</button>

            <span class="readout">
                <span class="chip" id="diffChip">Δ trajanje: —</span>
                <span class="chip" id="hoverTimeChip" hidden>—</span>
            </span>
        </div>

        <div class="legend">
            <span><span class="dot real"></span>Stvarna kronologija</span>
            <span><span class="dot offi"></span>Službena kronologija</span>
            <span><span class="dot k9"></span>K‑9 / spec. tim</span>
            <span><span class="dot photo"></span>Foto/vizual</span>
        </div>

        <div class="timelines">
            <div class="lane">
                <div class="laneTitle">Stvarna kronologija <small>na temelju tvojih zapisa</small></div>
                <div id="timelineReal" aria-label="Stvarna kronologija" wire:ignore></div>
            </div>
            <div class="lane">
                <div class="laneTitle">Službena kronologija <small>prema spisu</small></div>
                <div id="timelineOfficial" aria-label="Službena kronologija" wire:ignore></div>
            </div>
        </div>

        <br>

        @if($showModal)
            <div id="evidenceModal" class="evidence-modal" aria-modal="true" role="dialog">
                <br>
                <div class="evidence-backdrop" wire:click="closeEvidence"></div>
                <br>
                <div class="evidence-window" role="document">
                    <br>
                    <div class="evidence-header">
                        <br>
                        <div class="evidence-title">
                            <br>
                            <strong id="evTitle">{{ ($currentItem['icon'] ?? '') . ' ' . ($currentItem['content'] ?? 'Stavka') }}</strong>
                            <br>
                            <small id="evTime" style="color:var(--muted);margin-left:8px">
                                @php
                                    $t1 = isset($currentItem['start']) ? \Carbon\Carbon::parse($currentItem['start'])->format('H:i:s') : null;
                                    $t2 = isset($currentItem['end']) ? \Carbon\Carbon::parse($currentItem['end'])->format('H:i:s') : null;
                                @endphp
                                {{ $t1 }}{{ $t2 ? ' — '.$t2 : '' }}
                            </small>
                            <br>
                        </div>
                        <br>
                        <button id="evClose" class="btn btn-close" aria-label="Zatvori" wire:click="closeEvidence">✕</button>
                        <br>
                    </div>
                    <br>
                    <div id="evBody" class="evidence-body">
                        @if(!empty($currentItem['detailsHtml']))
                            <div class="ev-text">{!! $currentItem['detailsHtml'] !!}</div>
                        @endif

                        @if($currentAsset)
                            @if(($currentAsset['kind'] ?? '') === 'html')
                                {!! $currentAsset['html'] ?? '' !!}
                            @elseif(($currentAsset['kind'] ?? '') === 'link')
                                <a href="{{ $currentAsset['href'] ?? '#' }}" target="_blank" rel="noopener">
                                    {{ $currentAsset['caption'] ?? ($currentItem['title'] ?? 'Otvori') }}
                                </a>
                            @elseif(str_starts_with($currentAsset['contentType'] ?? '', 'image/') || ($currentAsset['kind'] ?? '') === 'image')
                                    <img class="ev-img" src="{{ $currentAsset['url'] ?? $currentAsset['dataUri'] }}" alt="{{ $currentAsset['caption'] ?? '' }}">
                                    @php
                                        $exif = $currentAsset['exif'] ?? [];
                                        $get = function($keys) use ($exif) {
                                            foreach ((array)$keys as $key) {
                                                $exifKeys = ['FILE', 'COMPUTED', 'IFD0', 'EXIF', 'GPS', 'INTEROP', 'THUMBNAIL'];
                                                foreach ($exifKeys as $k) {
                                                    if (array_key_exists($k, $exif) && is_array($exif[$k]) && array_key_exists($key, $exif[$k])) {
                                                        return $exif[$k][$key];
                                                    }
                                                }
                                            }
                                            return null;
                                        };
                                        $toFloat = function($v) {
                                            if (is_numeric($v)) return (float)$v;
                                            if (is_string($v) && preg_match('/^\s*(\d+)\s*\/\s*(\d+)\s*$/', $v, $m)) {
                                                return (float)$m[1] / max(1.0, (float)$m[2]);
                                            }
                                            return null;
                                        };
                                        $fmt = function($v) {
                                            if (is_array($v)) return implode(', ', array_map(fn($x)=>is_scalar($x)?(string)$x:json_encode($x, JSON_UNESCAPED_UNICODE), $v));
                                            if (is_bool($v)) return $v ? 'true' : 'false';
                                            return $v !== null ? (string)$v : null;
                                        };

                                        $make  = $get(['Make']);
                                        $model = $get(['Model']);
                                        $lens  = $get(['LensModel','Lens']);

                                        $expRaw = $get(['ExposureTime','EXIF.ExposureTime','ShutterSpeedValue']);
                                        $expNum = $toFloat($expRaw);
                                        $expStr = $expNum && $expNum > 0
                                            ? ($expNum >= 1 ? number_format($expNum, 1) . ' s' : '1/' . max(1, (int)round(1/$expNum)) . ' s')
                                            : (is_string($expRaw) ? $expRaw : null);

                                        $fnumRaw = $get(['FNumber','EXIF.FNumber','ApertureValue']);
                                        $fnumNum = $toFloat($fnumRaw);
                                        $fnumStr = $fnumNum ? 'f/' . rtrim(rtrim(number_format($fnumNum, 1, '.', ''), '0'), '.') : (is_string($fnumRaw) ? $fnumRaw : null);

                                        $isoRaw = $get(['ISOSpeedRatings','ISO','PhotographicSensitivity']);
                                        $isoStr = $isoRaw ? 'ISO ' . $fmt($isoRaw) : null;

                                        $focalRaw = $get(['FocalLength']);
                                        $focalNum = $toFloat($focalRaw);
                                        $focalStr = $focalNum ? (int)round($focalNum) . ' mm' : (is_string($focalRaw) ? $focalRaw : null);

                                        $dateStr = $get(['DateTimeOriginal','CreateDate','DateTime']);

                                        $w = $get(['ImageWidth','ExifImageWidth','PixelXDimension']);
                                        $h = $get(['ImageHeight','ExifImageHeight','PixelYDimension']);
                                        $dimStr = ($w && $h) ? "{$w}×{$h}" : null;

                                        $lat = $get(['GPSLatitude','GPS.Latitude']);
                                        $lon = $get(['GPSLongitude','GPS.Longitude']);
                                        $latRef = $get(['GPSLatitudeRef']);
                                        $lonRef = $get(['GPSLongitudeRef']);
                                        $toDec = function($v) {
                                            if (is_array($v)) {
                                                $nums = array_values(array_filter($v, fn($x)=>is_numeric($x)));
                                                if (count($nums) >= 3) return $nums[0] + $nums[1]/60 + $nums[2]/3600;
                                                return null;
                                            }
                                            if (is_string($v) && preg_match('/([0-9.]+)[^\d]+([0-9.]+)[^\d]+([0-9.]+)/', $v, $m)) {
                                                return (float)$m[1] + (float)$m[2]/60 + (float)$m[3]/3600;
                                            }
                                            if (is_numeric($v)) return (float)$v;
                                            return null;
                                        };
                                        $latDec = $toDec($lat);
                                        $lonDec = $toDec($lon);
                                        if ($latDec !== null && strtoupper((string)$latRef) === 'S') $latDec = -$latDec;
                                        if ($lonDec !== null && strtoupper((string)$lonRef) === 'W') $lonDec = -$lonDec;
                                        $gpsLink = ($latDec !== null && $lonDec !== null)
                                            ? "https://www.openstreetmap.org/?mlat={$latDec}&mlon={$lonDec}#map=16/{$latDec}/{$lonDec}"
                                            : null;

                                        $rawJson = !empty($exif) ? json_encode($exif) : null;
                                        $dateStr = Carbon\Carbon::parse($dateStr, "UTC")->setTimezone("Europe/Zagreb")->format('Y-m-d H:i:s');
                                    @endphp

                                    @if(!empty($exif))
                                        <details class="ev-exif" style="margin:8px 0">
                                            <summary style="cursor:pointer">EXIF metadata</summary>
                                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px">
                                                @if($make || $model)
                                                    <div class="chip" title="Camera">📷 {{ trim(($make ?? '') . ' ' . ($model ?? '')) }}</div>
                                                @endif
                                                @if($lens)
                                                    <div class="chip" title="Lens">🔭 {{ $lens }}</div>
                                                @endif
                                                @if($expStr)
                                                    <div class="chip" title="Exposure">{{ $expStr }}</div>
                                                @endif
                                                @if($fnumStr)
                                                    <div class="chip" title="Aperture">{{ $fnumStr }}</div>
                                                @endif
                                                @if($isoStr)
                                                    <div class="chip" title="ISO">{{ $isoStr }}</div>
                                                @endif
                                                @if($focalStr)
                                                    <div class="chip" title="Focal length">{{ $focalStr }}</div>
                                                @endif
                                                @if($dimStr)
                                                    <div class="chip" title="Dimensions">🖼️ {{ $dimStr }}</div>
                                                @endif
                                                @if($dateStr)
                                                    <div class="chip" title="Captured at">🕒 {{ $dateStr }}</div>
                                                @endif
                                                @if($gpsLink)
                                                    <a class="chip" href="{{ $gpsLink }}" target="_blank" rel="noopener" title="Open location on map">📍 {{ number_format($latDec, 6) }}, {{ number_format($lonDec, 6) }}</a>
                                                @endif
                                            </div>

                                            <div style="margin-top:8px">
                                                <button type="button" class="btn" onclick="navigator.clipboard.writeText(this.nextElementSibling.textContent)">Copy EXIF JSON</button>
                                                <pre style="margin:6px 0; padding:8px; background:#0b1220; color:#cbd5e1; border:1px solid var(--border); border-radius:8px; overflow:auto; max-height:240px">{{ $rawJson }}</pre>
                                            </div>
                                        </details>
                                    @else
                                        <div class="chip">No EXIF metadata.</div>
                                    @endif

{{--                                    <img class="ev-img" src="{{ $currentAsset['url'] ?? $currentAsset['dataUri'] }}" alt="{{ $currentAsset['caption'] ?? '' }}">--}}
{{--                                <div>--}}
{{--                                    @foreach($currentAsset['exif'] as $key => $value)--}}
{{--                                        <div class="chip" title="{{ $key }}">{{ $key }}: {{ json_encode($value) }}</div>--}}
{{--                                    @endforeach--}}
{{--                                </div>--}}
                            @elseif(($currentAsset['contentType'] ?? '') === 'application/pdf')
                                <iframe style="height: 100%" src="{{ $currentAsset['dataUri'] ?? '' }}" title="PDF"></iframe>
                            @elseif(($currentAsset['kind'] ?? '') === 'error')
                                <div class="chip">{{ $currentAsset['error'] ?? 'Greška pri učitavanju.' }}</div>
                            @else
                                <div class="chip">Nepodržan prikaz sadržaja.</div>
                            @endif
                        @elseif(empty($currentItem['detailsHtml']))
                            <div class="chip">Nema sadržaja.</div>
                        @endif
                    </div>
                    <br>
                    @php
                        $assetCount = (isset($currentItem['assets']) && is_array($currentItem['assets'])) ? count($currentItem['assets']) : 0;
                    @endphp
                    <div id="evPager" class="evidence-pager" @if($assetCount <= 1) hidden @endif>
                        <br>
                        <button id="evPrev" class="btn" aria-label="Prethodno" wire:click="prevAsset">◀</button>
                        <br>
                        <span id="evCounter" class="chip">{{ $currentAssetIndex + 1 }}/{{ max($assetCount, 1) }}</span>
                        <br>
                        <button id="evNext" class="btn" aria-label="Sljedeće" wire:click="nextAsset">▶</button>
                        <br>
                    </div>
                    <br>
                </div>
                <br>
            </div>
        @endif

        <div class="foot">
            Savjet: drži CTRL/Cmd dok scrollaš za brzi zoom. Povlači osi za pomicanje. Tooltip otkriva detalje.
        </div>
    </div>
</div>

@push('head')
    <link href="https://unpkg.com/vis-timeline@7.7.4/styles/vis-timeline-graph2d.min.css" rel="stylesheet" type="text/css" />
    <style>
        :root{
            /* Base theme */
            --bg:#0f172a; --card:#111827; --fg:#e5e7eb; --muted:#9ca3af; --accent:#22d3ee;
            --real:#0ea5e9; --offi:#ef4444; --k9:#22c55e; --photo:#a855f7;
            --border:#1f2937; --chip:#334155; --axis:#cbd5e1; --axisGrid:#1f2937; --shadow:0 10px 30px rgba(0,0,0,0.35);

            /* Overlay tokens (tweak only these percentages to retune the whole look) */
            /* Runways */
            --rw-real-top:   color-mix(in oklab, var(--real) 46%, transparent);
            --rw-real-bot:   color-mix(in oklab, var(--real) 16%,  transparent);
            --rw-off-top:    color-mix(in oklab, var(--offi) 14%, transparent);
            --rw-off-bot:    color-mix(in oklab, var(--offi) 5%,  transparent);
            --rw-ghost-top:  color-mix(in oklab, var(--real) 7%,  transparent);
            --rw-ghost-bot:  color-mix(in oklab, var(--real) 3%,  transparent);

            /* Diff on official (wash + hatch) */
            --diff-wash-top: color-mix(in oklab, var(--offi) 7.5%, transparent);
            --diff-wash-bot: color-mix(in oklab, var(--offi) 2.5%, transparent);
            --diff-hatch:    color-mix(in oklab, var(--offi) 18%, transparent); /* stripe color */
            --diff-stripe-a: 14px;  /* hatch thickness */
            --diff-stripe-b: 28px;  /* hatch pitch (a to b) */

            /* Location band */
            --loc-band-top:  color-mix(in oklab, var(--real) 6%, transparent);   /* align toward "real" blue */
            --loc-band-bot:  color-mix(in oklab, var(--real) 2.5%, transparent);
            --loc-border:    color-mix(in oklab, #94a3b8 48%, transparent);
        }

        html,body{
            margin:0; padding:0; background:var(--bg); color:var(--fg);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        }
        .wrap{ margin:24px auto; padding:0 16px 40px; }
        .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow:var(--shadow); }
        h1{ font-size:20px; margin:0 0 8px 0; letter-spacing:0.2px; }
        .sub{ color:var(--muted); font-size:13px; margin-bottom:16px; }

        .controls{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:8px 0 12px; }
        .btn{
            background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border);
            color:var(--fg); padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
            transition:transform .06s ease, filter .15s ease;
        }
        .btn:hover{ filter:brightness(1.1); }
        .btn:active{ transform:translateY(1px); }
        .switch{ display:inline-flex; gap:8px; align-items:center; background:#0b1220; border:1px solid var(--border); border-radius:999px; padding:6px 10px; font-size:13px; }
        .switch input{ accent-color: var(--accent); }

        .legend{ display:flex; flex-wrap:wrap; gap:10px; margin:10px 0 6px; font-size:12px; color:var(--muted); }
        .legend .dot{ width:10px;height:10px;border-radius:3px;display:inline-block;margin-right:6px; border:1px solid var(--border); }
        .dot.real{ background:var(--real); }
        .dot.offi{ background:var(--offi); }
        .dot.k9{ background:var(--k9); }
        .dot.photo{ background:var(--photo); }

        .chip{ display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; background:var(--chip); color:#d1d5db; border:1px solid var(--border); }

        .timelines{ display:grid; grid-template-columns: 1fr; gap:10px; }
        .lane{ background:#0b1220; border:1px solid var(--border); border-radius:12px; padding:10px; position:relative; overflow:hidden; }
        .laneTitle{ display:flex;align-items:center;justify-content:space-between; font-weight:700;font-size:14px;color:#cbd5e1;margin:0 0 8px 4px; }
        .laneTitle small{ font-weight:500;color:var(--muted) }

        .vis-timeline{ border-color: var(--border); background:#0b1220; border-radius:10px; }

        /* Responsive item font from JS (10..14) */
        .vis-item{
            color:#0b1220; font-weight:700; border:none; border-radius:10px; padding:6px 10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.25); transition:filter .15s ease, transform .06s ease;
            font-size: clamp(10px, var(--itFont, 12px), 14px); line-height: 1.2;
        }
        .vis-item.vis-selected{ outline:2px solid var(--accent); color:#00111a; filter:brightness(1.05); }
        .vis-item.vis-box .vis-item-content{ padding:6px 10px; }
        .vis-item .vis-drag-center{ display:none; }

        .item-real{ background:var(--real); }
        .item-official{ background:var(--offi); }
        .item-k9{ background:var(--k9); color:#06230f; }
        .item-photo{ background:var(--photo); }
        .item-photo-off{ background:color-mix(in oklab, var(--photo) 82%, #ffffff 18%); }

        /* Backgrounds — only root node paints */
        .vis-item.vis-background.bg-runway-real,
        .vis-item.vis-background.bg-runway-off,
        .vis-item.vis-background.bg-runway-real-ghost,
        .vis-item.vis-background.bg-diff-minus,
        .vis-item.vis-background.bg-location{
            height:100%; border:0; pointer-events:none;
        }

        /* Runways (harmonized to --real/--offi) */
        .vis-item.vis-background.bg-runway-real{
            background: linear-gradient(180deg, var(--rw-real-top), var(--rw-real-bot));
        }
        .vis-item.vis-background.bg-runway-off{
            background: linear-gradient(180deg, var(--rw-off-top), var(--rw-off-bot));
        }
        /* Ghost of real on official (ultra light) */
        .vis-item.vis-background.bg-runway-real-ghost{
            background: linear-gradient(180deg, var(--rw-ghost-top), var(--rw-ghost-bot));
        }

        /* Diff (official only): soft wash + subtle hatch */
        .vis-item.vis-background.bg-diff-minus{
            background-image:
                linear-gradient(180deg, var(--diff-wash-top), var(--diff-wash-bot)),
                repeating-linear-gradient(
                    135deg,
                    var(--diff-hatch) 0 var(--diff-stripe-a),
                    transparent var(--diff-stripe-a) var(--diff-stripe-b)
                );
            background-blend-mode: multiply;
        }

        /* Safety: no inner background painting for bg items */
        .vis-item.vis-background.bg-runway-real .vis-item-content,
        .vis-item.vis-background.bg-runway-off .vis-item-content,
        .vis-item.vis-background.bg-runway-real-ghost .vis-item-content,
        .vis-item.vis-background.bg-diff-minus .vis-item-content{
            background: transparent !important;
        }

        /* Location tag — tracks item font (slightly smaller) */
        .it .locTag{
            display:inline-flex; align-items:center; gap:4px; margin-left:6px; padding:2px 6px;
            font-size: clamp(9px, calc(var(--itFont, 12px) - 1px), 13px);
            font-weight:700; border-radius:999px; color:#03141c;
            background: color-mix(in oklab, var(--bg) 65%, white 35%);
            border:1px solid var(--border); box-shadow:0 1px 2px rgba(0,0,0,0.18);
        }
        .it .locTag .pin{ opacity:.9; }

        /* Location overlay layer (now harmonized with "real" blue) */
        .loc-layer{ position:absolute; inset:0; z-index:9; pointer-events:none; }
        .loc-layer .loc-band{
            position:absolute; top:0; bottom:0;
            background: linear-gradient(180deg, var(--loc-band-top), var(--loc-band-bot));
            border-top: 1px dashed var(--loc-border);
            border-bottom: 1px dashed var(--loc-border);
        }
        .loc-layer .loc-band .label{
            position:absolute; top:2px; left:8px; padding:2px 6px;
            font-size: clamp(9px, calc(var(--itFont, 12px) - 1px), 13px);
            color:#d1d5db; border:1px solid var(--border); border-radius:6px; background:#0b1220;
            box-shadow:0 1px 4px rgba(0,0,0,.18); white-space:nowrap;
        }

        .vis-time-axis .vis-text{ color:var(--axis); }
        .vis-time-axis .vis-grid{ border-color:var(--axisGrid); }
        .vis-panel.vis-center, .vis-panel.vis-left, .vis-panel.vis-right{ background:#0b1220; }

        .foot{ margin-top:14px; color:var(--muted); font-size:12px; }

        /* Crosshair */
        .crosshair{ position:absolute; top:0; right:0; bottom:0; left:0; pointer-events:none; z-index:10; }
        .crosshair.hidden{ opacity:0; }
        .crosshair .vline{
            position:absolute; top:0; bottom:0; width:1px;
            background: linear-gradient(180deg, #22d3eeaa, #22d3ee33);
            box-shadow: 0 0 0 1px #22d3ee22; transform: translateX(-0.5px);
        }
        .crosshair .label{
            position:absolute; top:0; transform: translate(-50%, -100%);
            background:#0b1220; color:#cbd5e1; border:1px solid var(--border); border-radius:8px;
            font-size:11px; padding:2px 6px; white-space:nowrap; box-shadow:0 2px 8px rgba(0,0,0,.25);
        }

        .vis-item:hover{ transform: translateY(-1px) scale(1.01); }

        /* Evidence modal (unchanged) */
        .evidence-modal { position: fixed; inset: 0; z-index: 9999; display: grid; place-items: center; }
        .evidence-modal[hidden] { display: none; }
        .evidence-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .6); backdrop-filter: blur(2px); }
        .evidence-window { position: relative; width: min(960px, 96vw); height: min(80vh, 820px); background: var(--card); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow); display: flex; flex-direction: column; overflow: hidden; }
        .evidence-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--border); }
        .evidence-body { flex: 1; overflow: auto; background: #0b1220; display: block; }
        .evidence-body .ev-text{ width: 100%; max-width: 940px; padding: 16px 18px; margin: 0 auto; color: #cbd5e1; font-size: 14px; line-height: 1.55; }
        .evidence-body .ev-text h1, .evidence-body .ev-text h2, .evidence-body .ev-text h3{ margin: 0 0 8px; }
        .evidence-body .ev-text p{ margin: 0 0 8px; }
        .evidence-body .ev-text ul{ margin: 6px 0 10px 16px; }
        .evidence-body .ev-text details{ margin: 8px 0; }
        .evidence-body .ev-img, .evidence-body iframe { display: block; width: min(960px, 96%); margin: 8px auto 16px; background: #0b1220; border: 0; }
        .evidence-pager { display: flex; gap: 8px; align-items: center; justify-content: center; padding: 8px; border-top: 1px solid var(--border); }
        .btn-close { background: transparent; border: 1px solid var(--border); border-radius: 8px; padding: 6px 10px; cursor: pointer; color: var(--fg); }



        /* --- Tonal accent refresh (subtle, readable on dark) --- */
        :root{
            /* Keep your dark base; lift accents just a touch for clarity */
            --real:  #38bdf8;  /* cyan-400 */
            --offi:  #f87171;  /* rose-400 */
            --k9:    #34d399;  /* emerald-400 */
            --photo: #c084fc;  /* violet-400 */

            /* Runways (stronger but still elegant) */
            --rw-real-top:  color-mix(in oklab, var(--real) 26%, transparent);
            --rw-real-bot:  color-mix(in oklab, var(--real) 10%, transparent);
            --rw-off-top:   color-mix(in oklab, var(--offi) 18%, transparent);
            --rw-off-bot:   color-mix(in oklab, var(--offi) 7%,  transparent);
            /* Ghost of real on official (just enough to read overlap) */
            --rw-ghost-top: color-mix(in oklab, var(--real) 10%, transparent);
            --rw-ghost-bot: color-mix(in oklab, var(--real) 4%,  transparent);

            /* Diff wash + hatch (a bit more contrast on dark) */
            --diff-wash-top: color-mix(in oklab, var(--offi) 10%, transparent);
            --diff-wash-bot: color-mix(in oklab, var(--offi) 4%,  transparent);
            --diff-hatch:     color-mix(in oklab, var(--offi) 30%, transparent);
            --diff-stripe-a: 12px;  /* slightly tighter hatch */
            --diff-stripe-b: 24px;

            /* Location band aligns to “real”, nudged brighter for visibility */
            --loc-band-top: color-mix(in oklab, var(--real) 8%,  transparent);
            --loc-band-bot: color-mix(in oklab, var(--real) 3%,  transparent);
            --loc-border:   color-mix(in oklab, #94a3b8 60%, transparent);
        }

        /* --- Event surfaces: subtle borders + gentle tint --- */
        .vis-item{ border:none; } /* keep default neutral, then style variants below */

        /* Tonal border helper (mix accent into a darker edge) */
        .item-real{
            background: linear-gradient(180deg,
            color-mix(in oklab, var(--real) 86%, #ffffff 14%),
            color-mix(in oklab, var(--real) 68%, #000000 32%)
            );
            border: 1px solid color-mix(in oklab, var(--real) 35%, #091222 65%);
        }
        .item-official{
            background: linear-gradient(180deg,
            color-mix(in oklab, var(--offi) 86%, #ffffff 14%),
            color-mix(in oklab, var(--offi) 68%, #000000 32%)
            );
            border: 1px solid color-mix(in oklab, var(--offi) 35%, #1a0f13 65%);
        }
        .item-k9{
            background: linear-gradient(180deg,
            color-mix(in oklab, var(--k9) 84%, #ffffff 16%),
            color-mix(in oklab, var(--k9) 64%, #000000 36%)
            );
            border: 1px solid color-mix(in oklab, var(--k9) 32%, #0b1f16 68%);
            color:#052012; /* maintain dark-ink legibility */
        }
        .item-photo{
            background: linear-gradient(180deg,
            color-mix(in oklab, var(--photo) 88%, #ffffff 12%),
            color-mix(in oklab, var(--photo) 66%, #000000 34%)
            );
            border: 1px solid color-mix(in oklab, var(--photo) 34%, #161028 66%);
        }
        .item-photo-off{
            /* photo for official stream: slightly lighter cap to keep contrast with offi runway */
            background: linear-gradient(180deg,
            color-mix(in oklab, var(--photo) 78%, #ffffff 22%),
            color-mix(in oklab, var(--photo) 60%, #000000 40%)
            );
            border: 1px solid color-mix(in oklab, var(--photo) 28%, #161028 72%);
        }

        /* Elevate selected items a bit more on dark */
        .vis-item.vis-selected{
            outline: 2px solid var(--accent);
            box-shadow: 0 6px 24px rgba(0,0,0,0.45), 0 0 0 2px #0ea5e933 inset;
        }

        /* --- Runway bands (use the updated tokens above) --- */
        .vis-item.vis-background.bg-runway-real{
            background: linear-gradient(180deg, var(--rw-real-top), var(--rw-real-bot));
        }
        .vis-item.vis-background.bg-runway-off{
            background: linear-gradient(180deg, var(--rw-off-top), var(--rw-off-bot));
        }
        .vis-item.vis-background.bg-runway-real-ghost{
            background: linear-gradient(180deg, var(--rw-ghost-top), var(--rw-ghost-bot));
        }

        /* Diff remains “official only”, with slightly higher contrast hatch */
        .vis-item.vis-background.bg-diff-minus{
            background-image:
                linear-gradient(180deg, var(--diff-wash-top), var(--diff-wash-bot)),
                repeating-linear-gradient(135deg,
                var(--diff-hatch) 0 var(--diff-stripe-a),
                transparent var(--diff-stripe-a) var(--diff-stripe-b)
                );
            background-blend-mode: multiply;
        }

        /* --- Points (timeline “dot” items) --- */
        /* Base dot: small + glow on dark, then per-variant colors */
        .vis-item.vis-dot, .vis-item.vis-point{
            /*width: 10px; height: 10px;*/
            border-width: 2px;
            box-shadow: 0 0 0 3px rgba(0,0,0,.35), 0 0 14px rgba(0,0,0,.25);
        }

        /* Variant-tinted dots (match your item-* classes) */
        .vis-item.item-real.vis-dot, .vis-item.item-real.vis-point{
            background: color-mix(in oklab, var(--real) 88%, white 12%);
            border-color: color-mix(in oklab, var(--real) 55%, #04131d 45%);
            box-shadow: 0 0 0 3px rgba(14,165,233,.12), 0 0 22px rgba(56,189,248,.10);
        }
        .vis-item.item-official.vis-dot, .vis-item.item-official.vis-point{
            background: color-mix(in oklab, var(--offi) 86%, white 14%);
            border-color: color-mix(in oklab, var(--offi) 55%, #1a0f13 45%);
            box-shadow: 0 0 0 3px rgba(248,113,113,.12), 0 0 22px rgba(248,113,113,.10);
        }
        .vis-item.item-k9.vis-dot, .vis-item.item-k9.vis-point{
            background: color-mix(in oklab, var(--k9) 86%, white 14%);
            border-color: color-mix(in oklab, var(--k9) 55%, #0b1f16 45%);
            box-shadow: 0 0 0 3px rgba(52,211,153,.12), 0 0 22px rgba(52,211,153,.08);
        }
        .vis-item.item-photo.vis-dot, .vis-item.item-photo.vis-point,
        .vis-item.item-photo-off.vis-dot, .vis-item.item-photo-off.vis-point{
            background: color-mix(in oklab, var(--photo) 90%, white 10%);
            border-color: color-mix(in oklab, var(--photo) 55%, #1a122c 45%);
            box-shadow: 0 0 0 3px rgba(192,132,252,.12), 0 0 22px rgba(192,132,252,.10);
        }

        /* Keep labels near points readable on dark */
        .vis-item.vis-dot .vis-item-content, .vis-item.vis-point .vis-item-content{
            color: #e5e7eb;
        }

        /* --- Crosshair label: slightly clearer on dark lanes --- */
        .crosshair .label{
            color:#e2e8f0;
            border-color: var(--border);
            background: #0b1220;
        }

        /* Subtle inner-light for long items to keep text contrast */
        .item-real .vis-item-content,
        .item-official .vis-item-content,
        .item-k9 .vis-item-content,
        .item-photo .vis-item-content,
        .item-photo-off .vis-item-content{
            text-shadow: 0 1px 0 rgba(255,255,255,0.18); /* faint lift on dark text */
        }

        .item-real, .item-official, .item-k9, .item-photo, .item-photo-off{
            color:#051018; /* a hair lighter than #0b1220 for readability */
        }

        .loc-layer{ z-index: 5; } /* above vis background band but below items */
        .loc-layer .loc-band{
            backdrop-filter: saturate(105%);
        }
        .loc-layer .loc-band .label{
            color:#e2e8f0;
            border-color: color-mix(in oklab, var(--real) 24%, var(--border));
        }

        /* Soft fade at runway edges to blend into lane */
        .vis-item.vis-background.bg-runway-real,
        .vis-item.vis-background.bg-runway-off,
        .vis-item.vis-background.bg-runway-real-ghost{
            -webkit-mask-image: linear-gradient(to right, transparent, #000 18px, #000 calc(100% - 18px), transparent);
            mask-image: linear-gradient(to right, transparent, #000 18px, #000 calc(100% - 18px), transparent);
        }

        .vis-item:focus-visible{
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(34,211,238,.18);
        }

        @media (max-width: 1200px){
            .vis-item.vis-dot, .vis-item.vis-point{
                box-shadow: 0 0 0 2px rgba(0,0,0,.3), 0 0 12px rgba(0,0,0,.18);
            }
        }

        /* --- Hover/selection label overlay for point items --- */
        .ptx-layer{
            position:absolute; inset:0; pointer-events:none; z-index:30; /* above items & crosshair */
        }
        .ptx-label{
            position:absolute;
            max-width: 48vw;
            padding: 2px 6px;
            border-radius: 6px;
            background:#0b1220;
            color:#e2e8f0;
            border:1px solid var(--border);
            box-shadow: 0 1px 8px rgba(0,0,0,.25);
            white-space: nowrap;
            font-size: clamp(10px, var(--itFont, 12px), 14px);
        }
        .ptx-label .locTag{ transform: translateY(-1px); }

        /* Hide inline text for point items – we’ll show our overlay instead */
        .vis-item.vis-point .vis-item-content,
        .vis-item.vis-dot   .vis-item-content{
            display: grid;
        }

        /* Keep point marker visually clear and not glued to lane bottom */
        .vis-item .vis-dot{ transform: translateY(-5px); }

        /* Give the bottom-most group some breathing room so last row never clips */
        .lane .vis-panel.vis-center{ padding-bottom: 20px; }
    </style>
@endpush

@push('scripts')
    <script>
        window.GupTimeline = window.GupTimeline || {};
        (function () {
            function findLW() {
                try {
                    const host = document.getElementById('single-timeline');
                    const root = host ? host.closest('[wire\\:id]') : null;
                    const compId = root ? root.getAttribute('wire:id') : null;
                    if (window.Livewire) {
                        if (compId && typeof window.Livewire.find === 'function') return window.Livewire.find(compId);
                        if (typeof window.Livewire.all === 'function') {
                            const all = window.Livewire.all();
                            if (Array.isArray(all) && all.length) return all[0];
                        }
                    }
                } catch (_) {}
                return null;
            }
            function call(method, ...args) {
                try {
                    const comp = findLW();
                    if (comp && typeof comp.call === 'function') comp.call(method, ...args);
                    else console.warn('[GUP] Livewire component not found or call() missing for', method);
                } catch (e) { console.warn('[GUP] Livewire call failed', method, e); }
            }
            window.GupTimeline.openEvidence = (id) => call('openEvidence', id);
            window.GupTimeline.closeEvidence = () => call('closeEvidence');
            window.GupTimeline.prevAsset = () => call('prevAsset');
            window.GupTimeline.nextAsset = () => call('nextAsset');
        })();
    </script>

    <script src="https://unpkg.com/vis-timeline@7.7.4/standalone/umd/vis-timeline-graph2d.min.js"></script>
    <script>
        const defaultReal = @js($real);
        const defaultOfficial = @js($official);

        function initGupTimelines(){
            console.debug('[GUP] init timelines');

            const toDate = (s) => (s instanceof Date ? s : new Date(s));
            const toMs = (x) => { const d = toDate(x); const ms = +d; return Number.isFinite(ms) ? ms : null; };
            const fmtDiff = (ms) => {
                const sign = ms < 0 ? "-" : "+"; ms = Math.abs(ms);
                const h = Math.floor(ms/3600000), m = Math.floor((ms%3600000)/60000), s = Math.floor((ms%60000)/1000);
                const parts = []; if (h) parts.push(h+"h"); if (m || (!h && s)) parts.push(m+"m"); if (!h && s) parts.push(s+"s");
                return sign + (parts.join(" ") || "0m");
            };
            const fmtTime = (d) => new Intl.DateTimeFormat('hr-HR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(d);

            const groupFor = (it, lane) => {
                const txt = `${it.content||''} ${it.className||''}`;
                if (/item-k9|K\-?9|K‑9/i.test(txt)) return 'k9';
                if (/Svjedo/i.test(txt)) return 'witness';
                if (/Foto|Phot|item-photo/i.test(txt)) return 'photo';
                if (lane === 'official' && /(Pouka|Oduzimanje|item-interview-offi)/i.test(txt)) return 'interview';
                return 'core';
            };

            const withGrouping = (items, lane) => items.map(it => ({
                ...it,
                group: it.group ?? groupFor(it, lane),
                subgroup: it.subgroup ?? 'event',
                subOrder: it.subOrder ?? 1
            }));

            function computeInitialWindowFrom(itemsA, itemsB, hours = 2, padMs = 5 * 60 * 1000){
                const all = [...itemsA, ...itemsB];
                const starts = all.map(i=>i.start).map(toMs).filter(Number.isFinite);
                const ends = all.map(i=>i.end ?? i.start).map(toMs).filter(Number.isFinite);
                const minMs = Math.min(...starts);
                const maxMs = Math.max(...ends);
                const spanCap = minMs + hours * 3600000;
                const end = Math.min(spanCap, maxMs + padMs);
                const start = minMs - Math.min(padMs, 60 * 1000);
                return { start: new Date(start), end: new Date(Math.max(end, start + 5 * 60 * 1000)) };
            }
            const initial = computeInitialWindowFrom(defaultReal, defaultOfficial);

            const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
            const template = (item) => {
                const icon = item.icon || "";
                const txt = item.content || "";
                const loc = item.location ? `<span class="locTag"><span class="pin">📍</span>${escapeHtml(item.location)}</span>` : "";
                return `<div class="it">${icon} ${txt} ${loc}</div>`;
            };

            const baseOptions = {
                stack: true, stackSubgroups: true,
                groupOrder: (a,b) => (a.order||0) - (b.order||0),
                multiselect: false,
                zoomKey: 'ctrlKey',
                zoomMin: 60 * 1000,
                zoomMax: 36 * 60 * 60 * 1000,
                orientation: { axis: 'top' },
                margin: { item: 8, axis: 8 },
                timeAxis: { scale: 'minute' },
                showCurrentTime: false,
                selectable: true,
                editable: { updateTime: false, updateGroup: false, add: false, remove: false },
                tooltip: { followMouse: true },
                start: initial.start, end: initial.end,
                template
            };

            const realGroups = new vis.DataSet([
                { id: 'core', content: 'Glavni interval', order: 1, subgroupOrder: (a,b)=> (a.subOrder||0)-(b.subOrder||0), subgroupStack: true },
                { id: 'k9', content: 'K‑9 / spec. tim', order: 2 },
                { id: 'witness', content: 'Svjedoci', order: 3 },
                { id: 'photo', content: 'Foto', order: 4 },
            ]);
            const offiGroups = new vis.DataSet([
                { id: 'core', content: 'Glavni interval', order: 1, subgroupOrder: (a,b)=> (a.subOrder||0)-(b.subOrder||0), subgroupStack: true },
                { id: 'interview', content: 'Obrada/ispitivanje', order: 2 },
                { id: 'photo', content: 'Foto/POPOP', order: 3 },
            ]);

            const contReal = document.getElementById("timelineReal");
            const contOffi = document.getElementById("timelineOfficial");

            const realItemsDS = new vis.DataSet(); realItemsDS.add(withGrouping(defaultReal, 'real'));
            const offiItemsDS = new vis.DataSet(); offiItemsDS.add(withGrouping(defaultOfficial, 'official'));

            const tlReal = new vis.Timeline(contReal, realItemsDS, realGroups, { ...baseOptions });
            const tlOffi = new vis.Timeline(contOffi, offiItemsDS, offiGroups, { ...baseOptions });

            // buildPointHoverLabels(tlReal, contReal, realItemsDS);
            // buildPointHoverLabels(tlOffi,  contOffi, offiItemsDS);

            // Sync viewports + responsive font
            let linkingEnabled = true, syncing = false;
            function sync(from, to){
                if (!linkingEnabled || syncing) return;
                const r = from.getWindow();
                try{ syncing = true; to.setWindow(r.start, r.end, { animation: false }); }
                finally { syncing = false; }
            }
            function updateFontScale(tl, container) {
                const panel = container.querySelector('.vis-panel.vis-center') || container;
                const w = tl.getWindow();
                const spanMs = Math.max(1, +new Date(w.end) - +new Date(w.start));
                const pxPerMs = panel.clientWidth / spanMs;
                const pxPerMin = pxPerMs * 60000;
                const t = Math.max(0, Math.min(1, (pxPerMin - 2) / (22 - 2))); // map 2..22 px/min -> 0..1
                const fontPx = 10 + t * 4; // 10..14
                panel.style.setProperty('--itFont', `${fontPx.toFixed(2)}px`);
            }
            function updateAxisStepFor(tl, container){
                const panel = container.querySelector('.vis-panel.vis-center') || container;
                const w = tl.getWindow();
                const spanMs = Math.max(1, +new Date(w.end) - +new Date(w.start));
                const pxPerMs = panel.clientWidth / spanMs;
                const pxPerMin = pxPerMs * 60000;

                // Tune thresholds to taste; keep labels from truncating
                let step;
                if (pxPerMin < 7) step = 15;
                else if (pxPerMin < 12) step = 10;
                else step = 5;

                tl.setOptions({ timeAxis: { scale: 'minute', step } });
            }

// call this on init and on each rangechange
            tlReal.on("rangechange", () => {
                sync(tlReal, tlOffi);
                updateFontScale(tlReal, contReal);
                updateFontScale(tlOffi, contOffi);
                updateAxisStepFor(tlReal, contReal);
                updateAxisStepFor(tlOffi, contOffi);
            });
            tlOffi.on("rangechange", () => {
                sync(tlOffi, tlReal);
                updateFontScale(tlReal, contReal);
                updateFontScale(tlOffi, contOffi);
                updateAxisStepFor(tlReal, contReal);
                updateAxisStepFor(tlOffi, contOffi);
            });
// also once after initial setWindow:
            updateAxisStepFor(tlReal, contReal);
            updateAxisStepFor(tlOffi, contOffi);
            tlReal.on("rangechange", () => { sync(tlReal, tlOffi); updateFontScale(tlReal, contReal); updateFontScale(tlOffi, contOffi); });
            tlOffi.on("rangechange", () => { sync(tlOffi, tlReal); updateFontScale(tlReal, contReal); updateFontScale(tlOffi, contOffi); });

            // Initial window + font sizing
            tlReal.setWindow(initial.start, initial.end, { animation: false });
            tlOffi.setWindow(initial.start, initial.end, { animation: false });
            updateFontScale(tlReal, contReal);
            updateFontScale(tlOffi, contOffi);

            // Open modal on double click
            const openEvidenceForId = (id) => {
                if (!id) return;
                if (window.GupTimeline?.openEvidence) return window.GupTimeline.openEvidence(id);
            };
            tlReal.on('doubleClick', (ev) => { if (ev.item) openEvidenceForId(ev.item); });
            tlOffi.on('doubleClick', (ev) => { if (ev.item) openEvidenceForId(ev.item); });

            // Helpers
            function dsRemoveByFilter(ds, pred){
                const ids = (typeof ds.getIds === 'function') ? ds.getIds({ filter: pred }) : ds.get({ filter: pred }).map(i=>i.id);
                ids.forEach(id => ds.remove(id));
            }
            function runwayRangeFrom(ds, fallbackStartId, fallbackEndId = null) {
                const tagged = ds.get({ filter: it => it.type !== 'background' && it.runway === true });
                if (tagged.length) {
                    const starts = tagged.map(i => +new Date(i.start)).filter(Number.isFinite);
                    const ends   = tagged.map(i => +new Date(i.end ?? i.start)).filter(Number.isFinite);
                    return { start: new Date(Math.min(...starts)), end: new Date(Math.max(...ends)) };
                }
                const s = ds.get(fallbackStartId);
                const e = fallbackEndId ? ds.get(fallbackEndId) : s;
                if (s && (e?.end ?? e?.start)) return { start: new Date(s.start), end: new Date(e.end ?? e.start) };
                const items = ds.get({ filter: i => i.type !== 'background' });
                const starts = items.map(i => +new Date(i.start)).filter(Number.isFinite);
                const ends   = items.map(i => +new Date(i.end ?? i.start)).filter(Number.isFinite);
                return { start: new Date(Math.min(...starts)), end: new Date(Math.max(...ends)) };
            }
            function addRunwayLane(ds, start, end, className, id) {
                ds.add({ id, start, end, type: 'background', className, _runway: true });
            }

            // Location overlays
            function computeLocationClusters(items, { gapMs = 8 * 60 * 1000, pointWidthMs = 2 * 60 * 1000 } = {}) {
                const rows = items
                    .filter(i => i.type !== 'background' && i.location && String(i.location).trim() !== '')
                    .map(i => {
                        const s = +new Date(i.start);
                        const e = +new Date(i.end ?? (s + pointWidthMs));
                        return { s, e, loc: String(i.location).trim() };
                    })
                    .sort((a,b) => a.s - b.s);
                const out = [];
                for (const r of rows) {
                    const last = out[out.length - 1];
                    if (!last || last.loc !== r.loc || r.s - last.e > gapMs) out.push({ s: r.s, e: r.e, loc: r.loc });
                    else last.e = Math.max(last.e, r.e);
                }
                return out.map((r, idx) => ({ id: idx, start: new Date(r.s), end: new Date(r.e), location: r.loc }));
            }
            function buildLocationLayer(tl, container) {
                const panel = container.querySelector('.vis-panel.vis-center') || container;
                if (getComputedStyle(panel).position === 'static') panel.style.position = 'relative';
                const layer = document.createElement('div'); layer.className = 'loc-layer'; panel.appendChild(layer);
                const bands = [];
                const timeToPx = (time) => {
                    const w = tl.getWindow(); const start = +new Date(w.start), end = +new Date(w.end);
                    const span = Math.max(1, end - start); const ratio = (+new Date(time) - start) / span;
                    return Math.round(Math.max(0, Math.min(1, ratio)) * panel.clientWidth);
                };
                function clear(){ bands.length = 0; layer.innerHTML = ''; }
                function setClusters(clusters){
                    clear();
                    for (const c of clusters) {
                        const el = document.createElement('div'); el.className = 'loc-band';
                        const label = document.createElement('div'); label.className = 'label'; label.textContent = `📍 ${c.location}`;
                        el.appendChild(label); layer.appendChild(el);
                        bands.push({ el, start: c.start, end: c.end, location: c.location });
                    }
                    redraw();
                }
                function redraw(){
                    for (const b of bands) {
                        const left = timeToPx(b.start), right = timeToPx(b.end);
                        b.el.style.left = left + 'px';
                        b.el.style.width = Math.max(1, right - left) + 'px';
                    }
                }
                tl.on('rangechange', redraw);
                const ro = new ResizeObserver(redraw); ro.observe(panel);
                return { setClusters, clear, redraw, element: layer };
            }

            // Show a single floating label for point items on hover/selection.
// Keeps labels above everything and inside the item's group band.
//             function buildPointHoverLabels(tl, container, ds){
//                 const panel = container.querySelector('.vis-panel.vis-center') || container;
//                 if (getComputedStyle(panel).position === 'static') panel.style.position = 'relative';
//
//                 const layer = document.createElement('div');
//                 layer.className = 'ptx-layer';
//                 panel.appendChild(layer);
//
//                 const label = document.createElement('div');
//                 label.className = 'ptx-label';
//                 label.style.display = 'none';
//                 layer.appendChild(label);
//
//                 let activeId = null;
//                 let raf = 0;
//
//                 const isPoint = (it) => {
//                     const t = String(it?.type || '').toLowerCase();
//                     return t === 'point' || t === 'dot';
//                 };
//                 const htmlFor = (it) => {
//                     const icon = it.icon || '';
//                     const txt  = it.content || '';
//                     const loc  = it.location ? `<span class="locTag"><span class="pin">📍</span>${String(it.location)}</span>` : '';
//                     return `${icon} ${txt} ${loc}`;
//                 };
//
//                 function placeForItem(itNode){
//                     if (!itNode) return hide();
//                     const itRect = itNode.getBoundingClientRect();
//                     const panelRect = panel.getBoundingClientRect();
//                     const group = itNode.closest('.vis-group');
//                     const gRect = group ? group.getBoundingClientRect() : panelRect;
//
//                     // Prepare content before measuring
//                     const itData = ds.get(itNode.getAttribute('data-id')) || ds.get(activeId);
//                     label.innerHTML = htmlFor(itData);
//                     label.style.display = 'block';
//                     label.style.visibility = 'hidden';
//
//                     // Measure
//                     const w = Math.ceil(label.getBoundingClientRect().width);
//                     const x = Math.round(itRect.left - panelRect.left) + 8;
//                     // Prefer placing above the dot; clamp within the group band
//                     const desiredTop = Math.round(itRect.top - panelRect.top) - 16;
//                     const gTop = Math.round(gRect.top - panelRect.top) + 2;
//                     const gBot = Math.round(gRect.bottom - panelRect.top) - 2;
//                     const top = Math.max(gTop, Math.min(desiredTop, gBot - 18)); // keep inside band
//
//                     const left = Math.max(2, Math.min(panel.clientWidth - w - 2, x));
//                     label.style.left = left + 'px';
//                     label.style.top  = top + 'px';
//                     label.style.visibility = 'visible';
//                 }
//
//                 function hide(){
//                     label.style.display = 'none';
//                     label.style.visibility = 'hidden';
//                 }
//
//                 function schedulePlace(itNode){
//                     cancelAnimationFrame(raf);
//                     raf = requestAnimationFrame(() => placeForItem(itNode));
//                 }
//
//                 // Hover tracking
//                 const srcPanel = panel;
//                 srcPanel.addEventListener('pointermove', (ev) => {
//                     const props = tl.getEventProperties(ev);
//                     if (!props || !props.item) {
//                         hide(); activeId = null; return;
//                     }
//                     const it = ds.get(props.item);
//                     if (!isPoint(it)) { hide(); activeId = null; return; }
//                     activeId = it.id;
//                     // Find the item DOM node
//                     const node = srcPanel.querySelector(`.vis-item[data-id="${CSS.escape(String(it.id))}"]`);
//                     schedulePlace(node);
//                 }, { passive:true });
//
//                 srcPanel.addEventListener('pointerleave', () => { hide(); activeId = null; }, { passive:true });
//
//                 // Keep the label pinned if the user selects a point (keyboard nav/focus etc.)
//                 tl.on('select', (ev) => {
//                     const id = (ev.items || [])[0];
//                     const it = id ? ds.get(id) : null;
//                     if (!isPoint(it)) { hide(); return; }
//                     activeId = id;
//                     const node = srcPanel.querySelector(`.vis-item[data-id="${CSS.escape(String(id))}"]`);
//                     schedulePlace(node);
//                 });
//                 tl.on('rangechange', () => {
//                     if (!activeId) return;
//                     const node = srcPanel.querySelector(`.vis-item[data-id="${CSS.escape(String(activeId))}"]`);
//                     schedulePlace(node);
//                 });
//                 tl.on('redraw', () => {
//                     if (!activeId) return;
//                     const node = srcPanel.querySelector(`.vis-item[data-id="${CSS.escape(String(activeId))}"]`);
//                     schedulePlace(node);
//                 });
//
//                 // Public API (not used yet, but handy)
//                 return { hide };
//             }

            // Hot re-init cleanup
            dsRemoveByFilter(realItemsDS, it => it.type === 'background' && (it._runway || it._diff || it._loc));
            dsRemoveByFilter(offiItemsDS, it => it.type === 'background' && (it._runway || it._diff || it._loc));

            // Build runways: one per lane; ghost of real on official
            const realRange = runwayRangeFrom(realItemsDS, 'r2', 'r8');
            const offRange  = runwayRangeFrom(offiItemsDS, 'o2', 'o2');
            addRunwayLane(realItemsDS, realRange.start, realRange.end, 'bg-runway-real', 'runway-real');
            addRunwayLane(offiItemsDS, offRange.start,  offRange.end,  'bg-runway-off',  'runway-off');
            addRunwayLane(offiItemsDS, realRange.start, realRange.end, 'bg-runway-real-ghost', 'runway-real-ghost');

            // Location overlay bands
            const locLayerReal = buildLocationLayer(tlReal, contReal);
            const locLayerOffi = buildLocationLayer(tlOffi, contOffi);
            const setLocs = () => {
                locLayerReal.setClusters(computeLocationClusters(realItemsDS.get()));
                locLayerOffi.setClusters(computeLocationClusters(offiItemsDS.get()));
            };
            setLocs();
            ['add','update','remove'].forEach(evt => {
                realItemsDS.on(evt, setLocs);
                offiItemsDS.on(evt, setLocs);
            });

            // Mirror selection across timelines
            function mirrorSelect(fromDS, toTL, toDS, sel){
                const ids = (sel.items || []); if (!ids.length) return;
                const it = fromDS.get(ids[0]); if (it?.mirror){ const target = toDS.get(it.mirror); if (target) toTL.setSelection(target.id, { focus: true }); }
            }
            tlReal.on("select", (ev) => mirrorSelect(realItemsDS, tlOffi, offiItemsDS, ev));
            tlOffi.on("select", (ev) => mirrorSelect(offiItemsDS, tlReal, realItemsDS, ev));

            // Diff highlighting and duration readout: ONLY on OFFICIAL lane
            const diffChip = document.getElementById("diffChip");
            function clearDiffBackgrounds(){
                dsRemoveByFilter(realItemsDS, it => it.type==="background" && it._diff);
                dsRemoveByFilter(offiItemsDS, it => it.type==="background" && it._diff);
            }
            function addBg(set, id, start, end, className){
                if (+start >= +end) return;
                set.add({ id, start, end, type:"background", className, _diff:true });
            }
            function computeAndMarkDiff(){
                clearDiffBackgrounds();
                const r = realRange;
                const oItem = offiItemsDS.get('o2') || offiItemsDS.get('o1');
                if (!r || !oItem) return;
                const rs = toDate(r.start), re = toDate(r.end);
                const os = toDate(oItem.start), oe = toDate(oItem.end ?? oItem.start);
                const durR = re - rs, durO = oe - os, delta = durR - durO;

                // Paint diffs only on OFFICIAL, keep REAL runway perfectly uniform.
                if (rs < os){ addBg(offiItemsDS, 'o-bg-left', rs, new Date(Math.min(re, os)), 'bg-diff-minus'); }
                if (re > oe){ addBg(offiItemsDS, 'o-bg-right', new Date(Math.max(rs, oe)), re, 'bg-diff-minus'); }

                diffChip.textContent = `Δ trajanje: ${fmtDiff(delta)} (stvarna ${Math.round(durR/60000)} min vs. službena ${Math.round(durO/60000)} min)`;
            }
            computeAndMarkDiff();
            ["add","update","remove"].forEach(evt => { realItemsDS.on(evt, computeAndMarkDiff); offiItemsDS.on(evt, computeAndMarkDiff); });

            // Controls
            function safeSetWindow(tl, startMs, endMs, animate=true){
                if (!Number.isFinite(startMs) || !Number.isFinite(endMs) || startMs >= endMs) {
                    try { tl.fit({ animation: animate }); } catch(_) { tl.fit(); }
                    return;
                }
                const opts = animate ? { animation: true } : { animation: false };
                try { tl.setWindow(new Date(startMs), new Date(endMs), opts); }
                catch (e) { try { tl.fit({ animation: false }); } catch(_) { tl.fit(); } }
            }
            function getAllTimes(){
                const starts = [ ...realItemsDS.get().map(i=>i.start), ...offiItemsDS.get().map(i=>i.start) ].map(toMs).filter(Number.isFinite);
                const ends = [ ...realItemsDS.get().map(i=>i.end).filter(Boolean), ...offiItemsDS.get().map(i=>i.end).filter(Boolean) ].map(toMs).filter(Number.isFinite);
                return { starts, ends };
            }
            function fitBoth(){
                const { starts, ends } = getAllTimes(); if (!starts.length) return;
                const minMs = Math.min(...starts);
                const maxMs = (ends.length ? Math.max(...ends) : Math.max(...starts));
                const span = Math.max(1, maxMs - minMs);
                const pad = Math.max(5 * 60 * 1000, Math.round(span * 0.05));
                const startMs = minMs - pad, endMs = maxMs + pad;
                safeSetWindow(tlReal, startMs, endMs, true);
                safeSetWindow(tlOffi, startMs, endMs, true);
            }
            function zoom(tl, factor){
                const range = tl.getWindow();
                const startMs = toMs(range.start), endMs = toMs(range.end);
                if (!Number.isFinite(startMs) || !Number.isFinite(endMs)) return;
                const center = (startMs + endMs) / 2;
                const newStart = center + (startMs - center) * factor;
                const newEnd = center + (endMs - center) * factor;
                safeSetWindow(tl, newStart, newEnd, true);
            }
            document.getElementById("fitBoth").onclick = () => fitBoth();
            document.getElementById("zoomIn").onclick  = () => { zoom(tlReal, 0.7); if (linkingEnabled) sync(tlReal, tlOffi); };
            document.getElementById("zoomOut").onclick = () => { zoom(tlReal, 1.3); if (linkingEnabled) sync(tlReal, tlOffi); };
            document.getElementById("linkToggle").onchange = (e) => { linkingEnabled = e.target.checked; };

            document.getElementById("exportJson").onclick = () => {
                const data = { real: realItemsDS.get(), official: offiItemsDS.get() };
                const blob = new Blob([JSON.stringify(data,null,2)], {type:"application/json"});
                const a = document.createElement("a"); a.href = URL.createObjectURL(blob);
                a.download = "timeline-data.json"; a.click(); URL.revokeObjectURL(a.href);
            };

            const testBtn = document.getElementById('testOpen');
            if (testBtn) testBtn.onclick = () => {
                const first = (realItemsDS.getIds && realItemsDS.getIds()[0]) || (realItemsDS.get()[0]?.id);
                const id = first || 'r1';
                console.debug('[GUP] Test modal button clicked, trying id:', id);
                if (window.GupTimeline?.openEvidence) window.GupTimeline.openEvidence(id);
            };

            // Crosshair
            const crosshairToggle = document.getElementById("crosshairToggle");
            const hoverTimeChip = document.getElementById("hoverTimeChip");
            function crosshairEnabled(){ return !!crosshairToggle.checked; }
            function createCrosshairFor(tl, container){
                const panel = container.querySelector('.vis-panel.vis-center') || container;
                if (getComputedStyle(panel).position === 'static') panel.style.position = 'relative';
                const host = document.createElement('div'); host.className = 'crosshair hidden';
                const vline = document.createElement('div'); vline.className = 'vline';
                const label = document.createElement('div'); label.className = 'label'; label.textContent = '—';
                host.appendChild(vline); host.appendChild(label); panel.appendChild(host);
                let visible = false, raf = 0;
                function show(){ if(!visible){ visible = true; host.classList.remove('hidden'); } }
                function hide(){ visible = false; host.classList.add('hidden'); }
                function timeToPxInPanel(time){
                    const w = tl.getWindow();
                    const start = +new Date(w.start), end = +new Date(w.end);
                    const t = +new Date(time); const span = Math.max(1, end - start);
                    const ratio = (t - start) / span; const x = Math.max(0, Math.min(1, ratio)) * panel.clientWidth;
                    return Math.round(x);
                }
                function update(time){
                    cancelAnimationFrame(raf);
                    raf = requestAnimationFrame(() => {
                        const x = timeToPxInPanel(time);
                        vline.style.left = x + 'px'; label.style.left = x + 'px'; label.textContent = fmtTime(time);
                    });
                }
                function enable(on){ on ? show() : hide(); }
                return { element: host, update, enable, hide };
            }
            const chReal = createCrosshairFor(tlReal, contReal);
            const chOffi = createCrosshairFor(tlOffi, contOffi);

            function handlePointerMove(tlSrc, tlTgt, contSrc, chSrc, chTgt, ev){
                if (!crosshairEnabled()) { chSrc.hide(); chTgt.hide(); hoverTimeChip.hidden = true; return; }
                const props = tlSrc.getEventProperties(ev); if (!props || !props.time) return;
                const time = props.time;
                chSrc.enable(true); chTgt.enable(true); chSrc.update(time); chTgt.update(time);
                hoverTimeChip.hidden = false; hoverTimeChip.textContent = fmtTime(time);
            }
            function attachCrosshair(tlSrc, tlTgt, contSrc, chSrc, chTgt){
                const srcPanel = contSrc.querySelector('.vis-panel.vis-center') || contSrc;
                srcPanel.addEventListener('pointermove', (ev) => handlePointerMove(tlSrc, tlTgt, srcPanel, chSrc, chTgt, ev), { passive:true } );
                srcPanel.addEventListener('pointerleave', () => { chSrc.hide(); chTgt.hide(); hoverTimeChip.hidden = true; }, { passive:true });
                tlSrc.on('rangechange', () => { chSrc.hide(); chTgt.hide(); });
            }
            attachCrosshair(tlReal, tlOffi, contReal, chReal, chOffi);
            attachCrosshair(tlOffi, tlReal, contOffi, chOffi, chReal);

            // Responsive on window resize
            let resizeTO = 0;
            window.addEventListener("resize", () => {
                clearTimeout(resizeTO);
                resizeTO = setTimeout(() => {
                    tlReal.redraw(); tlOffi.redraw();
                    updateFontScale(tlReal, contReal);
                    updateFontScale(tlOffi, contOffi);
                }, 120);
            });

            // Debug: runway counts
            const dbgRealRunways = realItemsDS.get({ filter: it => it.type === 'background' && it._runway });
            const dbgOffRunways  = offiItemsDS.get({ filter: it => it.type === 'background' && it._runway });
            console.debug('[GUP] runway counts', { real: dbgRealRunways.length, official: dbgOffRunways.length });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGupTimelines, { once: true });
        } else {
            initGupTimelines();
        }
    </script>
@endpush
