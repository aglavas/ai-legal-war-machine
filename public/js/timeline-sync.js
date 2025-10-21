window.TimelineSync = (function () {
    'use strict';

    class TimelineSyncManager {
        constructor(instances, options = {}) {
            // instances: [{ name, tl, data, root }]
            this.instances = (instances || []).map((it, i) => ({
                name: it.name || `tl${i}`,
                tl: it.tl,
                data: it.data || {},
                root: it.root || null
            }));
            this.options = { syncSlides: true, syncZoom: true, ...options };
            this._bound = [];
            this._syncingSlides = false;
            this._syncingZoom = false;
            this.currentEventIndex = {};
            this.instances.forEach(it => { this.currentEventIndex[it.name] = -1; });
            this.union = this._computeUnion();
        }

        attach() {
            this.detach();
            this.instances.forEach(it => {
                const onChange = (payload) => this._onChange(it, payload);
                const onZoomIn = () => this._onZoom(it, 'in');
                const onZoomOut = () => this._onZoom(it, 'out');
                it.tl.on('change', onChange);
                it.tl.on('zoom_in', onZoomIn);
                it.tl.on('zoom_out', onZoomOut);
                this._bound.push({ it, onChange, onZoomIn, onZoomOut });
            });
        }

        detach() {
            this._bound.forEach(({ it, onChange, onZoomIn, onZoomOut }) => {
                try { it.tl.off && it.tl.off('change', onChange); } catch {}
                try { it.tl.off && it.tl.off('zoom_in', onZoomIn); } catch {}
                try { it.tl.off && it.tl.off('zoom_out', onZoomOut); } catch {}
            });
            this._bound = [];
        }

        goToDateAll(date) {
            if (!date) return;
            this._syncingSlides = true;
            this.instances.forEach(inst => {
                const idx = this._nearestIndex(inst.data.events || [], date);
                this._gotoIfChanged(inst, idx);
            });
            this._syncingSlides = false;
        }

        getUnionScale() { return { min: this.union.min, max: this.union.max, span: this.union.span }; }

        /* Internals */
        _onChange(sourceInst, payload) {
            if (!this.options.syncSlides || this._syncingSlides) return;
            const date = this._dateFromPayload(sourceInst, payload);
            if (!date) return;
            this._syncingSlides = true;
            this.instances.forEach(inst => {
                if (inst.name === sourceInst.name) return;
                const idx = this._nearestIndex(inst.data.events || [], date);
                this._gotoIfChanged(inst, idx);
            });
            this._syncingSlides = false;
        }

        _onZoom(sourceInst, dir) {
            if (!this.options.syncZoom || this._syncingZoom) return;
            this._syncingZoom = true;
            try {
                this.instances.forEach(inst => {
                    if (inst.name === sourceInst.name) return;
                    this._triggerZoom(inst.root, dir);
                });
            } finally {
                this._syncingZoom = false;
            }
        }

        _triggerZoom(rootEl, dir) {
            if (!rootEl) return;
            const sels = dir === 'in'
                ? ['.tl-menubar [data-zoom="in"]', '.tl-menubar .tl-zoom-in', '.tl-menubar [title*="Zoom in" i]', '.tl-menubar [aria-label*="Zoom in" i]']
                : ['.tl-menubar [data-zoom="out"]', '.tl-menubar .tl-zoom-out', '.tl-menubar [title*="Zoom out" i]', '.tl-menubar [aria-label*="Zoom out" i]'];
            let btn = null;
            for (const s of sels) { btn = rootEl.querySelector(s); if (btn) break; }
            if (btn) btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        }

        _gotoIfChanged(inst, eidx) {
            if (eidx < 0) return;
            const name = inst.name;
            if (this.currentEventIndex[name] !== eidx) {
                this.currentEventIndex[name] = eidx;
                const slideIndex = this._eventToSlide(inst.data, eidx);
                if (slideIndex >= 0) inst.tl.goTo(slideIndex);
            }
        }

        _dateFromPayload(inst, payload) {
            let date = null;
            if (typeof payload?.index === 'number') {
                const eidx = this._slideToEvent(inst.data, payload.index);
                const ev = inst.data.events?.[eidx];
                if (ev) date = this._eventStartDate(ev);
            }
            if (!date && payload?.unique_id) {
                const ev = (inst.data.events || []).find(e =>
                    e.unique_id === payload.unique_id || e.id === payload.unique_id || e.slug === payload.unique_id
                );
                if (ev) date = this._eventStartDate(ev);
            }
            return date;
        }

        _eventStartDate(ev) {
            const s = ev?.start_date || {};
            return new Date(
                s.year ?? 0, (s.month ?? 1) - 1, s.day ?? 1,
                s.hour ?? 0, s.minute ?? 0, s.second ?? 0, s.millisecond ?? 0
            );
        }

        _nearestIndex(events, targetDate) {
            if (!events || !events.length || !targetDate) return -1;
            const t = targetDate.getTime();
            let best = -1, bestDiff = Infinity;
            for (let i = 0; i < events.length; i++) {
                const dt = this._eventStartDate(events[i]).getTime();
                const diff = Math.abs(dt - t);
                if (diff < bestDiff) { bestDiff = diff; best = i; }
            }
            return best;
        }

        _hasTitle(data) { return !!(data && data.title); }
        _eventToSlide(data, eidx) { return eidx < 0 ? -1 : (this._hasTitle(data) ? eidx + 1 : eidx); }
        _slideToEvent(data, sidx) { return sidx < 0 ? -1 : (this._hasTitle(data) ? sidx - 1 : sidx); }

        _computeUnion() {
            const times = [];
            this.instances.forEach(inst => {
                (inst.data.events || []).forEach(ev => {
                    const t = this._eventStartDate(ev).getTime();
                    if (Number.isFinite(t)) times.push(t);
                });
            });
            if (!times.length) {
                const now = Date.now();
                return { min: now - 86400000, max: now + 86400000, span: 172800000 };
            }
            const min = Math.min(...times);
            const max = Math.max(...times);
            return { min, max, span: Math.max(1, max - min) };
        }
    }

    /* Crosshair overlay helper: reuses your .sync-overlay boxes per column */
    function createCrosshairOverlaySync({ container, manager, resolveOverlay } = {}) {
        if (!container || !manager) return { destroy(){} };
        const overlays = {};

        // Default overlay resolver assumes wrappers like #col-top, #col-bottom
        const resolver = resolveOverlay || (name => {
            const el = document.querySelector(`#col-${name} .sync-overlay`);
            return el || null;
        });

        manager.instances.forEach(inst => {
            const ov = resolver(inst.name);
            if (ov) overlays[inst.name] = ov;
        });

        const union = manager.getUnionScale();
        function dateToRatio(date) { return (date.getTime() - union.min) / union.span; }
        function ratioToDate(r) {
            const clamp = Math.max(0, Math.min(1, r));
            return new Date(Math.round(union.min + clamp * union.span));
        }
        function formatDate(d) {
            try { return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
            catch { return d.toISOString().slice(0,10); }
        }

        function setMarker(name, date) {
            const ov = overlays[name]; if (!ov) return;
            const line = ov.querySelector('.sync-line');
            const tip = ov.querySelector('.sync-tooltip');
            const w = ov.clientWidth || 1;
            const x = Math.max(0, Math.min(1, dateToRatio(date))) * w;
            if (line) line.style.left = x + 'px';
            if (tip) { tip.style.left = x + 'px'; tip.textContent = formatDate(date); }
        }
        function setMarkersAll(date) {
            Object.keys(overlays).forEach(name => setMarker(name, date));
            lastDate = date;
        }

        function isOverTimenav(target) {
            return !!(target && (target.closest('.tl-timenav') || target.matches('.tl-timenav')));
        }

        let rafPending = false, lastEvt = null, lastDate = new Date((union.min + union.max) / 2);

        function onMove(e) {
            lastEvt = e;
            if (rafPending) return;
            rafPending = true;
            requestAnimationFrame(() => {
                rafPending = false;
                const rect = container.getBoundingClientRect();
                const ratio = Math.max(0, Math.min(1, (lastEvt.clientX - rect.left) / rect.width));
                const date = ratioToDate(ratio);
                if (isOverTimenav(lastEvt.target)) manager.goToDateAll(date);
                setMarkersAll(date);
            });
        }

        container.addEventListener('mousemove', onMove, { passive: true, capture: true });
        container.addEventListener('mouseleave', () => { lastEvt = null; }, { capture: true });
        window.addEventListener('resize', () => { if (lastDate) setMarkersAll(lastDate); });

        // Initialize
        setMarkersAll(lastDate);

        return {
            update(date) { if (date) setMarkersAll(date); },
            destroy() {
                container.removeEventListener('mousemove', onMove, { capture: true });
                container.removeEventListener('mouseleave', () => {}, { capture: true });
            }
        };
    }

    return { TimelineSyncManager, createCrosshairOverlaySync };
})();
