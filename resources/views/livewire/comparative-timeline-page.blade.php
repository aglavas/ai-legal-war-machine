@push('head')
<link title="timeline-styles" rel="stylesheet" href="https://cdn.knightlab.com/libs/timeline3/latest/css/timeline.css">
{{--<script src="https://cdn.knightlab.com/libs/timeline3/latest/js/timeline.js"></script>--}}
<script src="../js/timeline.js"></script>
<style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
    .container { margin: 2rem auto; padding: 0 1rem; }
    /* Stack with tiny seam between timenavs */
    #tl-compare-stack { display: flex; flex-direction: column; gap: 4px; }
    .tl-vcol { position: relative; background: #bf8484; border: 1px solid #848ea1; }
    .tl-vcol.top { border-radius: 12px 12px 0 0; }
    .tl-vcol.bottom { border-radius: 0 0 12px 12px; }
    .tl-embed { width: 100%; height: 40vh; min-height: 320px; }
    #tl-compare-stack .tl-embed .tl-attribution { display: none !important; }
    #tl-compare-stack .tl-embed .vco-navigation { margin: 0 !important; }
    #tl-compare-stack .tl-embed .tl-credit { display: none !important; }
    #tl-compare-stack .tl-embed .tl-timenav { border: 0 !important; }
    .sync-overlay { pointer-events: none; position: absolute; inset: 0; }
    .sync-line { position: absolute; top: 0; bottom: 0; width: 2px; background: #0ea5e9; opacity: .9; transform: translateX(-1px); left: 50%; }
    .sync-tooltip { position: absolute; left: 50%; transform: translateX(-50%); top: 6px; background: #0ea5e9; color: #fff; font: 12px/1.2 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; padding: 3px 6px; border-radius: 4px; white-space: nowrap; box-shadow: 0 4px 10px rgba(2,6,23,.15); }
</style>
<style>
    /* Base layout (yours, unchanged except minor tweaks) */
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
    .container { margin: 2rem auto; padding: 0 1rem; }

    .sync-overlay { pointer-events: none; }
    /* Stack with tiny seam between timenavs */
    #tl-compare-stack { display: flex; flex-direction: column; gap: 4px; }
    .tl-vcol { position: relative; background: #fff; border: 1px solid #e5e7eb; }
    .tl-vcol.top { border-radius: 12px 12px 0 0; }
    .tl-vcol.bottom { border-radius: 0 0 12px 12px; }

    /* Timeline boxes */
    .tl-embed { width: 100%; height: 40vh; min-height: 320px; }

    /* Remove extra spacing/branding to bring them closer visually */
    #tl-compare-stack .tl-embed .tl-attribution { display: none !important; }
    #tl-compare-stack .tl-embed .vco-navigation { margin: 0 !important; }
    #tl-compare-stack .tl-embed .tl-credit { display: none !important; }
    #tl-compare-stack .tl-embed .tl-timenav { border: 0 !important; }

    /* Crosshair overlay (visual only) */
    .sync-overlay { pointer-events: none; position: absolute; inset: 0; }
    .sync-line { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--tl-accent, #0ea5e9); opacity: .9; transform: translateX(-1px); left: 50%; }
    .sync-tooltip {
        position: absolute; left: 50%; transform: translateX(-50%);
        top: 6px; background: var(--tl-accent, #0ea5e9); color: #fff;
        font: 12px/1.2 ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
        padding: 3px 6px; border-radius: 4px; white-space: nowrap; box-shadow: 0 4px 10px rgba(2,6,23,.15);
    }

    /* --------------------------- */
    /* Theming "CSS interface"     */
    /* --------------------------- */
    /* 1) Variables: change once, restyles both nav + slides inside that column. */
    .tl-vcol {
        --tl-bg: #284377;   /* site background behind timenav */
        --tl-surface:   #0f172a;   /* slide area background */
        --tl-card:      #111827;   /* text panel background */
        --tl-border:    #1f2937;
        --tl-text:      #e5e7eb;
        --tl-muted:     #94a3b8;
        --tl-accent:    #38bdf8;   /* crosshair + accents */
        --tl-active:    #f59e0b;   /* active flag bg */
        --tl-flag-bg:   #111827;   /* nav flag bg */
        --tl-flag-text: #dbeafe;   /* nav flag headline text */
        --tl-grid:      #334155;   /* axis grid lines */
        --tl-axis:      #64748b;   /* axis label */
        --tl-shadow:    rgba(3,7,18,.5);
    }

    /* 2) Skins: slap one of these classes on a column to swap palette quickly */
    .tl-skin-ink { /* defaults above already "ink" */ }
    .tl-skin-sand { --tl-bg:#191611; --tl-surface:#22201b; --tl-card:#29261f; --tl-text:#eee7dc; --tl-muted:#c8bda8; --tl-accent:#eab308; --tl-active:#f97316; --tl-flag-bg:#312e23; --tl-flag-text:#fff5e8; --tl-grid:#3a342a; --tl-axis:#a08f71; }
    .tl-skin-forest { --tl-bg:#0d1714; --tl-surface:#0f201b; --tl-card:#12261f; --tl-text:#d9f99d; --tl-muted:#86efac; --tl-accent:#22d3ee; --tl-active:#84cc16; --tl-flag-bg:#0f291f; --tl-flag-text:#eafff2; --tl-grid:#1a3127; --tl-axis:#86efac; }

    /* 3) Apply variables to TimelineJS internals with sufficiently specific selectors. */
    /* Slide/story area */
    #tl-compare-stack .tl-embed .tl-slider-container-mask { background: var(--tl-surface) !important; }
    #tl-compare-stack .tl-embed .tl-text { background: var(--tl-card) !important; color: var(--tl-text) !important; }
    #tl-compare-stack .tl-embed .tl-text .tl-headline-date { color: var(--tl-muted) !important; }
    #tl-compare-stack .tl-embed h2.tl-headline { color: var(--tl-text) !important; }
    #tl-compare-stack .tl-embed .tl-caption,
    #tl-compare-stack .tl-embed .tl-credit { color: var(--tl-muted) !important; }

    /* Nav background + axis */
    #tl-compare-stack .tl-embed .tl-timenav { background: var(--tl-bg) !important; }
    #tl-compare-stack .tl-embed .tl-timeaxis { color: var(--tl-axis) !important; }
    #tl-compare-stack .tl-embed .tl-timeaxis .tl-timeaxis-tick:before { background: var(--tl-grid) !important; }
    #tl-compare-stack .tl-embed .tl-timeaxis.tl-timeaxis-major .tl-timeaxis-tick:before { background: color-mix(in srgb, var(--tl-grid) 80%, #0000) !important; }

    /* Flags/markers: default and active */
    #tl-compare-stack .tl-embed .tl-timemarker .tl-timemarker-content-container {
        background: var(--tl-flag-bg) !important; color: var(--tl-flag-text) !important;
        border: 1px solid var(--tl-border) !important; box-shadow: 0 2px 8px var(--tl-shadow) !important;
    }
    #tl-compare-stack .tl-embed .tl-timemarker.tl-timemarker-active .tl-timemarker-content-container {
        background: var(--tl-active) !important; color: #111827 !important; border-color: color-mix(in srgb, var(--tl-active) 70%, #000) !important;
    }
    /* Timespan band behind markers (events with end date) */
    #tl-compare-stack .tl-embed .tl-timemarker .tl-timemarker-timespan {
        background: color-mix(in srgb, var(--tl-active) 22%, #0000) !important;
    }

    /* Zoom icons */
    #tl-compare-stack .tl-embed .tl-menubar { background: transparent !important; }
    #tl-compare-stack .tl-embed .tl-menubar * { color: var(--tl-muted) !important; }

    /* Optional: soften next/prev slab */
    #tl-compare-stack .tl-embed .tl-slidenav-next,
    #tl-compare-stack .tl-embed .tl-slidenav-previous { background: color-mix(in srgb, var(--tl-bg) 75%, #0000) !important; }
    #tl-compare-stack .tl-embed .tl-slidenav-title,
    #tl-compare-stack .tl-embed .tl-slidenav-description { color: var(--tl-muted) !important; }
</style>

@endpush

<div class="container">
    <div id="tl-compare-stack">
        <div class="tl-vcol top tl-skin-sand" id="col-top">
            <div id="timeline-top" class="tl-embed"></div>
            <div class="sync-overlay" aria-hidden="true">
                <div class="sync-line"></div>
                <div class="sync-tooltip"></div>
            </div>
        </div>
        <div class="tl-vcol bottom" id="col-bottom">
            <div id="timeline-bottom" class="tl-embed"></div>
            <div class="sync-overlay" aria-hidden="true">
                <div class="sync-line"></div>
                <div class="sync-tooltip"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="text/javascript">
    let isSyncingFrom = null;
    const isDragging = { top: false, bottom: false };
    const dataTop = {!! $this->dataTopJs !!};
    const dataBottom = {!! $this->dataBottomJs !!};
    const commonOptions = {
        hash_bookmark: true,
        timenav_height_percentage: 26,
        initial_zoom: 1,
        default_bg_color: '#0f172a',
        scale_factor: 1,
        font: 'Merriweather-NewsCycle'
    };
    const optionsTop = { ...commonOptions, timenav_position: 'bottom' };
    const optionsBottom = { ...commonOptions, timenav_position: 'top' };

    const TL_INST = {
        top: new TL.Timeline('timeline-top', dataTop, optionsTop),
        bottom: new TL.Timeline('timeline-bottom', dataBottom, optionsBottom)
    };


    function eventStartDate(ev) {
        const s = ev?.start_date || {};
        return new Date(
            s.year ?? 0, (s.month ?? 1) - 1, s.day ?? 1,
            s.hour ?? 0, s.minute ?? 0, s.second ?? 0, s.millisecond ?? 0
        );
    }
    function nearestIndex(events, targetDate) {
        if (!events || !events.length || !targetDate) return -1;
        const t = targetDate.getTime();
        let best = -1, bestDiff = Infinity;
        for (let i = 0; i < events.length; i++) {
            const dt = eventStartDate(events[i]).getTime();
            const diff = Math.abs(dt - t);
            if (diff < bestDiff) { bestDiff = diff; best = i; }
        }
        return best;
    }

    function eventIndexToSlideIndex(data, eidx) { return eidx < 0 ? -1 : (data?.title ? eidx + 1 : eidx); }
    function slideIndexToEventIndex(data, sidx) { return sidx < 0 ? -1 : (data?.title ? sidx - 1 : sidx); }

    function safeTimes(arr) { return (arr || []).map(eventStartDate).map(d => d.getTime()).filter(Number.isFinite); }
    const timesTop = safeTimes(dataTop.events);
    const timesBottom = safeTimes(dataBottom.events);
    const allTimes = [...timesTop, ...timesBottom];

    let unionMin, unionMax;
    if (allTimes.length) {
        unionMin = Math.min(...allTimes);
        unionMax = Math.max(...allTimes);
    } else {
        const now = Date.now();
        unionMin = now - 86400000; // -1 day
        unionMax = now + 86400000; // +1 day
    }
    const unionSpan = Math.max(1, unionMax - unionMin);

    function dateToRatio(date) { return (date.getTime() - unionMin) / unionSpan; }
    function ratioToDate(r) { return new Date(Math.round(unionMin + Math.max(0, Math.min(1, r)) * unionSpan)); }

    /* ------------------------------------------ */
    /* Crosshair overlay (shared)                  */
    /* ------------------------------------------ */
    const overlays = {
        top: document.querySelector('#col-top .sync-overlay'),
        bottom: document.querySelector('#col-bottom .sync-overlay')
    };
    function formatDate(d) {
        try { return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
        catch { return d.toISOString().slice(0, 10); }
    }
    function setMarker(side, date) {
        const ov = overlays[side]; if (!ov) return;
        const line = ov.querySelector('.sync-line');
        const tip = ov.querySelector('.sync-tooltip');
        const w = ov.clientWidth || 1;
        const x = Math.max(0, Math.min(1, dateToRatio(date))) * w;
        line.style.left = x + 'px';
        tip.style.left = x + 'px';
        tip.textContent = formatDate(date);
    }
    let lastMarkerDate = new Date((unionMin + unionMax) / 2);
    function setMarkersBoth(date) {
        setMarker('top', date);
        setMarker('bottom', date);
        lastMarkerDate = date;
    }


    const stackEl = document.getElementById('tl-compare-stack');
    let syncingSlides = false;
    let syncingZoom = false;
    // Track "current event index" (not slide index) per side
    const currentEventIndex = { top: -1, bottom: -1 };

    function gotoIfChanged(side, eidx) {
        if (eidx < 0) return;
        if (currentEventIndex[side] !== eidx) {
            currentEventIndex[side] = eidx;
            const data = side === 'top' ? dataTop : dataBottom;
            const slideIndex = eventIndexToSlideIndex(data, eidx);
            if (slideIndex >= 0) TL_INST[side].goTo(slideIndex);
        }
    }

    function previewBothAtDate(date) {
        const idxTop = nearestIndex(dataTop.events, date);
        const idxBottom = nearestIndex(dataBottom.events, date);
        syncingSlides = true; // prevent feedback loop
        gotoIfChanged('top', idxTop);
        gotoIfChanged('bottom', idxBottom);
        syncingSlides = false;
        setMarkersBoth(date);
    }

    function isOverTimenav(target) {
        return !!(target && (target.closest('.tl-timenav') || target.matches('.tl-timenav')));
    }
    let rafPending = false, lastHoverEvent = null;
    function onPointerMove(e) {
        lastHoverEvent = e;
        if (!rafPending) {
            rafPending = true;
            requestAnimationFrame(() => {
                rafPending = false;
                const { clientX, target } = lastHoverEvent || {};
                const rect = stackEl.getBoundingClientRect();
                const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
                const date = ratioToDate(ratio);
                if (isOverTimenav(target) && !(isDragging.top || isDragging.bottom)) {
                    previewBothAtDate(date);
                } else {
                    setMarkersBoth(date);
                }
            });
        }
    }
    stackEl.addEventListener('mousemove', onPointerMove, { passive: true, capture: true });
    stackEl.addEventListener('mouseleave', () => lastHoverEvent = null, { capture: true });

    function onSlideChange(source, payload) {
        // Ako je scroll (visible_ticks_change) – koristi center_ms
        if (payload?.type === 'visible_ticks_change' && typeof payload.center_ms === 'number') {
            console.log('onSlideChange', source, payload, 'center_ms', payload.center_ms, isSyncingFrom);

            const date = new Date(payload.center_ms);
            const other = source === 'top' ? 'bottom' : 'top';
            const dataOther = other === 'top' ? dataTop : dataBottom;
            isSyncingFrom = source;
            syncingSlides = true;
            try {
                // const idxOther = nearestIndex(dataOther.events, date);
                // gotoIfChanged(other, idxOther);
                //previewBothAtDate(date);
                setMarkersBoth(date);
            } finally {
                syncingSlides = false;
                isSyncingFrom = null;
            }
            // Vizualni crosshair na oba
            //setMarkersBoth(date);
            return;
        }

        // Inače (click, hash, tipka itd.) – stara logika preko index/unique_id
        const dataSrc = source === 'top' ? dataTop : dataBottom;
        const other = source === 'top' ? 'bottom' : 'top';
        const dataOther = other === 'top' ? dataTop : dataBottom;

        let date = null;

        // Ako payload.index postoji – to je SLIDE index; prevedi u event index
        if (typeof payload?.index === 'number') {
            const evIdx = slideIndexToEventIndex(dataSrc, payload.index);
            const ev = dataSrc.events?.[evIdx];
            if (ev) date = eventStartDate(ev);
            // console.log('onSlideChange', source, payload, 'slide_index', payload.index, 'event_index', evIdx, ev ? eventStartDate(ev) : null);
        }

        // Fallback: pokušaj preko unique_id u istom (source) datasetu
        if (!date && payload?.unique_id) {
            const ev = (dataSrc.events || []).find(e =>
                e.unique_id === payload.unique_id || e.id === payload.unique_id || e.slug === payload.unique_id
            );
            if (ev) date = eventStartDate(ev);
        }
        if (!date) return;

        syncingSlides = true;
        try {
            const idxOther = nearestIndex(dataOther.events, date);
            gotoIfChanged(other, idxOther);
        } finally {
            syncingSlides = false;
        }
        setMarkersBoth(date);
    }


    /* Zoom sync: mirror a single zoom step (in/out) to the other timeline by “clicking” its menubar button */
    function triggerZoom(side, dir /* 'in' | 'out' */) {
        const root = document.getElementById(side === 'top' ? 'timeline-top' : 'timeline-bottom');
        if (!root) return;
        const sels = dir === 'in'
            ? ['.tl-menubar [data-zoom="in"]', '.tl-menubar .tl-zoom-in', '.tl-menubar [title*="Zoom in" i]', '.tl-menubar [aria-label*="Zoom in" i]']
            : ['.tl-menubar [data-zoom="out"]', '.tl-menubar .tl-zoom-out', '.tl-menubar [title*="Zoom out" i]', '.tl-menubar [aria-label*="Zoom out" i]'];
        let btn = null; for (const s of sels) { btn = root.querySelector(s); if (btn) break; }
        if (btn) btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    }
    function onZoom(source, dir) {
        if (syncingZoom) return;
        syncingZoom = true;
        try { triggerZoom(source === 'top' ? 'bottom' : 'top', dir); }
        finally { syncingZoom = false; }
    }


    TL_INST.top.on('visible_ticks_change', (payload) => onSlideChange('top', payload));
    TL_INST.bottom.on('visible_ticks_change', (payload) => onSlideChange('bottom', payload));
    TL_INST.top.on('dragmove',  (payload) => { isDragging.top = true;  onSlideChange('top', payload); });
    TL_INST.bottom.on('dragmove',  (payload) => { isDragging.bottom = true;  onSlideChange('bottom', payload); });
    TL_INST.top.on('dragend',   (payload) => { isDragging.top = false; onSlideChange('top', payload); });
    TL_INST.bottom.on('dragend',   (payload) => { isDragging.bottom = false; onSlideChange('bottom', payload); });
    TL_INST.top.on('change', (payload) => onSlideChange('top', payload));
    TL_INST.bottom.on('change', (payload) => onSlideChange('bottom', payload));
    TL_INST.top.on('zoom_in', () => onZoom('top', 'in'));
    TL_INST.bottom.on('zoom_in', () => onZoom('bottom', 'in'));
    TL_INST.top.on('zoom_out', () => onZoom('top', 'out'));
    TL_INST.bottom.on('zoom_out', () => onZoom('bottom', 'out'));
    TL_INST.top.on('loaded', () => setMarkersBoth(lastMarkerDate));
    TL_INST.bottom.on('loaded', () => setMarkersBoth(lastMarkerDate));
    window.addEventListener('resize', () => { if (lastMarkerDate) setMarkersBoth(lastMarkerDate); });
</script>
@endpush
