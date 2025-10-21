@push('head')
    <link title="timeline-styles" rel="stylesheet" href="https://cdn.knightlab.com/libs/timeline3/latest/css/timeline.css">
    <script src="https://cdn.knightlab.com/libs/timeline3/latest/js/timeline.js"></script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
        .container { margin: 2rem auto; padding: 0 1rem; }
    </style>
@endpush

<div class="container">
    <div id='timeline-embed' style="width: 100%; height: 600px"></div>
</div>

@push('scripts')
    <script type="text/javascript">
        // Injected from Livewire component (hardcoded JSON literal)
        const timeline_json = {!! $this->timelineJs !!};

        // Visual tuning and UX enhancements for TimelineJS
        const timelineOptions = {
            hash_bookmark: true,
            timenav_position: 'top',
            timenav_height_percentage: 26,
            initial_zoom: 6,
            default_bg_color: '#f9fafb',
            scale_factor: 1.2,
            font: 'Merriweather-NewsCycle'
        };

        // Preserve original data for filtering
        const BASE_DATA = JSON.parse(JSON.stringify(timeline_json));
        const BASE_EVENTS = BASE_DATA.events.slice();
        let activeGroups = new Set();
        let lastRenderedEvents = BASE_EVENTS;

        // Lightweight visual tweaks + full-width layout + toolbar styles
        (function injectTimelineStyles() {
            const style = document.createElement('style');
            style.textContent = `
:root {
  --tl-accent: #0ea5e9;
  --tl-bg: #ffffff;
  --tl-muted: #64748b;
}

#timeline-toolbar {
  display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between;
  margin: 0 0 12px 0; padding: 10px 12px; background: #fff;
  border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 4px 12px rgba(2,6,23,.06);
}
#timeline-toolbar .tl-filters { display:flex; flex-wrap:wrap; gap:.5rem .75rem; align-items:center; }
#timeline-toolbar label { display:inline-flex; align-items:center; gap:.35rem; padding:4px 8px; border-radius:6px; background:#f1f5f9; }
#timeline-toolbar input[type="checkbox"] { accent-color: var(--tl-accent); }
#timeline-toolbar .tl-actions button,
#timeline-toolbar .tl-date button {
  padding: 6px 10px; border-radius: 6px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer;
}
#timeline-toolbar .tl-actions button:hover,
#timeline-toolbar .tl-date button:hover { border-color: var(--tl-accent); color: var(--tl-accent); }
#timeline-toolbar .tl-date { display:flex; gap:.5rem; align-items:center; }
#timeline-toolbar .tl-date input[type="date"] { padding:6px 8px; border:1px solid #e5e7eb; border-radius:6px; }

#timeline-embed { width: 100%; max-width: 100%; height: 78vh; min-height: 480px;
  background: var(--tl-bg); border-radius: 10px; box-shadow: 0 8px 24px rgba(2,6,23,.08); }

#timeline-embed .tl-text-content { line-height: 1.6; }
#timeline-embed .tl-headline { font-weight: 800; letter-spacing: .2px; }
#timeline-embed .tl-timenav .tl-timeaxis { opacity: .95; }
#timeline-embed .tl-attribution { display: none !important; }
#timeline-embed .tl-media .tl-media-content { border-radius: 10px; overflow: hidden; box-shadow: 0 6px 18px rgba(2,6,23,.08); }
`;
            document.head.appendChild(style);
        })();

        function getGroups() {
            const groups = new Set();
            for (const ev of BASE_EVENTS) if (ev.group) groups.add(ev.group);
            return Array.from(groups).sort((a, b) => a.localeCompare(b));
        }

        function buildToolbar() {
            const groups = getGroups();
            activeGroups = new Set(groups); // all enabled by default

            const toolbar = document.createElement('div');
            toolbar.id = 'timeline-toolbar';

            const filters = document.createElement('div');
            filters.className = 'tl-filters';
            filters.setAttribute('role', 'group');
            for (const g of groups) {
                const id = `tl-group-${btoa(unescape(encodeURIComponent(g))).replace(/=+$/,'')}`;
                const label = document.createElement('label');
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = true;
                cb.dataset.group = g;
                cb.id = id;
                const span = document.createElement('span');
                span.textContent = g;
                label.htmlFor = id;
                label.appendChild(cb);
                label.appendChild(span);
                filters.appendChild(label);
            }

            const actions = document.createElement('div');
            actions.className = 'tl-actions';
            const btnAll = document.createElement('button');
            btnAll.type = 'button';
            btnAll.textContent = 'All';
            const btnNone = document.createElement('button');
            btnNone.type = 'button';
            btnNone.textContent = 'None';
            actions.appendChild(btnAll);
            actions.appendChild(btnNone);

            const dateCtrl = document.createElement('div');
            dateCtrl.className = 'tl-date';
            const dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.id = 'tl-date';
            const btnGo = document.createElement('button');
            btnGo.type = 'button';
            btnGo.textContent = 'Go';
            dateCtrl.appendChild(dateInput);
            dateCtrl.appendChild(btnGo);

            toolbar.appendChild(filters);
            toolbar.appendChild(actions);
            toolbar.appendChild(dateCtrl);

            const anchor = document.getElementById('timeline-embed');
            if (anchor) anchor.insertAdjacentElement('beforebegin', toolbar);

            // handlers
            toolbar.addEventListener('change', (e) => {
                if (e.target && e.target.matches('input[type="checkbox"][data-group]')) {
                    const g = e.target.dataset.group;
                    if (e.target.checked) activeGroups.add(g);
                    else activeGroups.delete(g);
                    renderTimeline();
                }
            });
            btnAll.addEventListener('click', () => {
                for (const cb of toolbar.querySelectorAll('input[type="checkbox"][data-group]')) {
                    cb.checked = true;
                    activeGroups.add(cb.dataset.group);
                }
                renderTimeline();
            });
            btnNone.addEventListener('click', () => {
                for (const cb of toolbar.querySelectorAll('input[type="checkbox"][data-group]')) {
                    cb.checked = false;
                }
                activeGroups.clear();
                renderTimeline();
            });
            const go = () => goToDate(dateInput.value);
            btnGo.addEventListener('click', go);
            dateInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') go(); });
        }

        function renderTimeline() {
            const filtered = BASE_EVENTS.filter(ev => !ev.group || activeGroups.has(ev.group));
            lastRenderedEvents = filtered;
            const data = JSON.parse(JSON.stringify(BASE_DATA));
            data.events = filtered;

            const el = document.getElementById('timeline-embed');
            if (el) el.innerHTML = '';
            window.timeline = new TL.Timeline('timeline-embed', data, timelineOptions);
        }

        function goToDate(dateStr) {
            if (!dateStr) return;
            const target = new Date(dateStr);
            if (isNaN(target)) return;

            let idx = lastRenderedEvents.findIndex(ev => {
                const s = ev.start_date || {};
                const y = s.year, m = (s.month || 1) - 1, d = s.day || 1;
                const dt = new Date(y, m, d);
                return dt.getFullYear() === target.getFullYear() &&
                    dt.getMonth() === target.getMonth() &&
                    dt.getDate() === target.getDate();
            });

            if (idx === -1) {
                let bestI = -1, bestDiff = Infinity;
                for (let i = 0; i < lastRenderedEvents.length; i++) {
                    const s = lastRenderedEvents[i].start_date || {};
                    const dt = new Date(s.year, (s.month || 1) - 1, s.day || 1, s.hour || 0, s.minute || 0);
                    const diff = Math.abs(dt - target);
                    if (diff < bestDiff) { bestDiff = diff; bestI = i; }
                }
                idx = bestI;
            }

            if (idx >= 0 && window.timeline) {
                const ev = lastRenderedEvents[idx];
                if (ev && ev.unique_id && typeof window.timeline.goToId === 'function') {
                    window.timeline.goToId(ev.unique_id);
                } else if (typeof window.timeline.goTo === 'function') {
                    window.timeline.goTo(idx);
                }
            }
        }

        // Build UI, render timeline
        buildToolbar();
        renderTimeline();

        // Keep layout crisp on resizes (e.g., sidebar toggles)
        window.addEventListener('resize', () => {
            if (window.timeline && typeof window.timeline.updateDisplay === 'function') {
                window.timeline.updateDisplay();
            }
        });
    </script>
@endpush
