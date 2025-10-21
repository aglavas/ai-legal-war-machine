<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transcript Preview</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
    <style>
        :root{
            --bg:#0f172a; --card:#111827; --fg:#e5e7eb; --muted:#9ca3af; --accent:#22d3ee;
            --border:#1f2937; --chip:#334155; --shadow:0 10px 30px rgba(0,0,0,0.35);
            --good:#22c55e; --info:#0ea5e9; --warn:#eab308;
        }
        html,body{ margin:0; padding:0; background:var(--bg); color:var(--fg);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        .wrap{ margin:24px auto; padding:0 16px 40px; max-width: 1100px; }
        .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow:var(--shadow); }
        h1{ font-size:20px; margin:0 0 6px 0; letter-spacing:0.2px; }
        .sub{ color:var(--muted); font-size:13px; margin-bottom:14px; }

        .controls{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin:10px 0 12px; }
        .ctrl{ display:flex; flex-direction:column; gap:6px; }
        .ctrl label{ font-size:12px; color:var(--muted); }
        .in{ background:#0b1220; color:var(--fg); border:1px solid var(--border); border-radius:10px; padding:9px 10px; min-width:260px; }
        .in.small{ min-width:220px; }
        .btn{ background:linear-gradient(180deg,#1f2937,#111827); border:1px solid var(--border);
              color:var(--fg); padding:9px 12px; border-radius:10px; cursor:pointer; font-weight:600; font-size:13px;
              transition:transform .06s ease, filter .15s ease; }
        .btn:hover{ filter:brightness(1.1); }
        .btn:active{ transform:translateY(1px); }
        .switch{ display:inline-flex; gap:8px; align-items:center; background:#0b1220; border:1px solid var(--border);
                 border-radius:999px; padding:7px 10px; font-size:13px; }
        .switch input{ accent-color: var(--accent); }
        .chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--chip); color:#d1d5db; border:1px solid var(--border); font-size:12px; }

        ul.seg-list{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; }
        .seg{ background:#0b1220; border:1px solid var(--border); border-radius:12px; padding:10px 12px; }
        .seg .head{ display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-bottom:6px; }
        .seg .txt{ line-height:1.6; color:#e2e8f0; font-size:14px; }
        mark{ background: color-mix(in oklab, var(--warn) 55%, transparent); color:#111827; padding:0 3px; border-radius:4px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Pregled transkripta</h1>
        <div class="sub">Elegantni pregled s filtrima i isticanjem. Baza vremena: <strong>2025-06-09 14:45:00</strong> (Europe/Zagreb).</div>
        <livewire:transcript-previewer />
    </div>
</div>
@livewireScripts
</body>
</html>
