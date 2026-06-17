<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>@yield('code') · PitchIQ</title>
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚽</text></svg>" />
    {{-- Self-contained styles: no external CSS, so the page still renders even
         when the asset pipeline or app layer is the thing that failed. --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #080C0A;
            color: #E6E8E6;
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            text-align: center;
            overflow: hidden;
        }
        .glow {
            position: fixed;
            width: 600px; height: 600px;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            background: radial-gradient(circle, rgba(0,230,118,0.10) 0%, transparent 70%);
            pointer-events: none;
        }
        .card { position: relative; z-index: 1; max-width: 460px; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; margin-bottom: 28px;
            border: 1px solid rgba(0,230,118,0.25);
            background: rgba(0,230,118,0.08);
            border-radius: 999px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
            color: #00E676;
            font-family: ui-monospace, 'JetBrains Mono', 'SF Mono', Menlo, monospace;
        }
        .code {
            font-size: clamp(72px, 18vw, 140px);
            font-weight: 900; line-height: 1;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, #75ff9e 0%, #00E676 60%, #00b359 100%);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }
        h1 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 12px; }
        p  { font-size: 14px; line-height: 1.6; color: #9aa39c; margin-bottom: 32px; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 22px; border-radius: 12px;
            font-size: 13px; font-weight: 700; text-decoration: none;
            font-family: ui-monospace, 'JetBrains Mono', 'SF Mono', Menlo, monospace;
            text-transform: uppercase; letter-spacing: 0.08em;
            transition: transform .15s ease, opacity .15s ease;
        }
        a.btn:active { transform: scale(0.98); }
        a.btn-primary { background: linear-gradient(135deg, #00E676 0%, #00b359 100%); color: #00210b; }
        a.btn-primary:hover { opacity: .92; }
        a.btn-ghost { border: 1px solid rgba(255,255,255,0.15); color: #9aa39c; }
        a.btn-ghost:hover { color: #fff; border-color: rgba(0,230,118,0.4); }
        .brand { margin-top: 40px; font-size: 12px; color: #4b524d; letter-spacing: 0.1em; }
        .brand b { color: #cdd3ce; font-weight: 800; }
        .brand span { color: #00E676; }
    </style>
</head>
<body>
    <div class="glow"></div>
    <div class="card">
        <span class="badge">⚽ PitchIQ</span>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Back to Home</a>
            <a class="btn btn-ghost" href="javascript:history.back()">Go Back</a>
        </div>
        <div class="brand"><b>Pitch<span>IQ</span></b> — Own Your Squad. Rule the Campus.</div>
    </div>
</body>
</html>
