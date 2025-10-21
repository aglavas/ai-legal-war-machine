<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Textract Pipeline Manager</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    <style>
        :root{
            --bg:#0f172a; --card:#111827; --fg:#e5e7eb; --muted:#9ca3af; --accent:#22d3ee;
            --border:#1f2937; --chip:#334155; --shadow:0 10px 30px rgba(0,0,0,0.35);
            --success:#22c55e; --info:#0ea5e9; --warn:#eab308; --error:#ef4444;
        }
        html,body{ margin:0; padding:0; background:var(--bg); color:var(--fg);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        .wrap{ margin:24px auto; padding:0 16px 40px; max-width: 1200px; }
        .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px; box-shadow:var(--shadow); }
        h1{ font-size:24px; margin:0 0 8px 0; letter-spacing:0.2px; font-weight:600; }
        .sub{ color:var(--muted); font-size:14px; margin-bottom:16px; }

        .controls{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:16px 0; }
        .ctrl{ display:flex; flex-direction:column; gap:6px; }
        .ctrl label{ font-size:12px; color:var(--muted); font-weight:500; }
        .in{ background:#0b1220; color:var(--fg); border:1px solid var(--border); border-radius:10px; padding:9px 12px; min-width:200px; font-size:14px; }
        .in:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(34,211,238,0.1); }
        .in.small{ min-width:160px; }
        .btn{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border);
              color:var(--fg); padding:9px 14px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
              transition:transform .06s ease, filter .15s ease; white-space:nowrap; }
        .btn:hover{ filter:brightness(1.15); }
        .btn:active{ transform:translateY(1px); }
        .btn.success{ background:linear-gradient(180deg,var(--success),#16a34a); border-color:#15803d; }
        .btn.info{ background:linear-gradient(180deg,var(--info),#0284c7); border-color:#0369a1; }
        .btn.warn{ background:linear-gradient(180deg,var(--warn),#ca8a04); border-color:#a16207; }
        .btn.error{ background:linear-gradient(180deg,var(--error),#dc2626); border-color:#b91c1c; }

        .switch{ display:inline-flex; gap:8px; align-items:center; background:#0b1220; border:1px solid var(--border);
                 border-radius:999px; padding:7px 12px; font-size:13px; cursor:pointer; }
        .switch:hover{ background:#131b2e; }
        .switch input{ accent-color: var(--accent); cursor:pointer; }

        .chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border-radius:999px;
               background:var(--chip); color:#d1d5db; border:1px solid var(--border); font-size:12px; font-weight:500; }
        .chip.success{ background:rgba(34,197,94,0.15); border-color:rgba(34,197,94,0.3); color:#86efac; }
        .chip.info{ background:rgba(14,165,233,0.15); border-color:rgba(14,165,233,0.3); color:#7dd3fc; }
        .chip.warn{ background:rgba(234,179,8,0.15); border-color:rgba(234,179,8,0.3); color:#fde047; }
        .chip.error{ background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3); color:#fca5a5; }

        ul.seg-list{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px; }
        .seg{ background:#0b1220; border:1px solid var(--border); border-radius:12px; padding:14px 16px;
              transition:border-color .2s ease; }
        .seg:hover{ border-color:#2d3748; }
        .seg .head{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .seg .txt{ line-height:1.6; color:#e2e8f0; font-size:14px; margin-top:8px; }

        details{ margin-top:16px; }
        details summary{ cursor:pointer; font-weight:600; color:#e2e8f0; padding:12px 16px;
                        background:#0b1220; border:1px solid var(--border); border-radius:10px;
                        transition:background .2s ease; }
        details summary:hover{ background:#131b2e; }
        details[open] summary{ border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom-color:transparent; }
        details .detail-content{ background:#0b1220; border:1px solid var(--border); border-top:none;
                                 border-radius:0 0 10px 10px; padding:16px; }

        .modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,0.85); display:flex; align-items:center;
                        justify-content:center; z-index:50; padding:20px; backdrop-filter:blur(4px); }
        .modal{ background:var(--card); border:1px solid var(--border); border-radius:14px;
                max-width:800px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow); }
        .modal-header{ display:flex; align-items:center; justify-content:space-between; padding:20px 24px;
                      border-bottom:1px solid var(--border); }
        .modal-header h2{ font-size:20px; font-weight:600; margin:0; }
        .modal-body{ padding:24px; }
        .modal-section{ margin-bottom:20px; }
        .modal-section:last-child{ margin-bottom:0; }
        .modal-label{ font-weight:600; color:#cbd5e1; margin-bottom:8px; font-size:14px; }
        .modal-value{ color:#e2e8f0; font-size:14px; line-height:1.6; }
        .modal-value.mono{ font-family:monospace; font-size:12px; word-break:break-all; }

        .stats{ display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; }
        .stat{ flex:1; min-width:140px; background:#0b1220; border:1px solid var(--border);
               border-radius:10px; padding:12px 14px; }
        .stat-label{ font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
        .stat-value{ font-size:24px; font-weight:700; line-height:1; }

        .file-badge{ display:inline-flex; align-items:center; gap:6px; padding:8px 12px;
                    border-radius:8px; font-size:13px; margin:4px 0; }
        .file-badge.found{ background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.2); color:#86efac; }
        .file-badge.missing{ background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); color:#fca5a5; }

        .pagination{ display:flex; gap:8px; justify-content:center; margin-top:16px; }
        .pagination a, .pagination span{ padding:8px 12px; border-radius:8px; font-size:13px; font-weight:500; }
        .pagination a{ background:#0b1220; border:1px solid var(--border); color:var(--fg); }
        .pagination a:hover{ background:#131b2e; border-color:#2d3748; }
        .pagination span{ background:var(--accent); border:1px solid var(--accent); color:#0f172a; }

        @keyframes pulse{ 0%, 100%{ opacity:1; } 50%{ opacity:0.5; } }
        [wire\:loading]{ animation:pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <livewire:textract-manager />
    </div>
</div>
@livewireScripts
</body>
</html>

