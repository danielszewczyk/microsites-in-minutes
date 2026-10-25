<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — Microsites in Minutes</title>
    <style>
        :root { --accent: #0f766e; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); display: grid; place-items: center; min-height: 100vh; }
        .box { width: 100%; max-width: 400px; padding: 24px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 28px; }
        h1 { font-size: 1.5rem; margin: 0 0 6px; }
        p.sub { color: var(--muted); margin: 0 0 20px; }
        label { font-weight: 600; display: block; margin: 0 0 6px; }
        input { font: inherit; width: 100%; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        .field { margin: 0 0 16px; }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 11px; padding: 13px 24px; font-size: 1.05rem; font-weight: 600; cursor: pointer; width: 100%; }
        .err { background: #fee2e2; color: #991b1b; padding: 9px 13px; border-radius: 9px; margin: 0 0 12px; font-size: 0.9rem; }
        .ok { background: color-mix(in srgb, var(--accent) 14%, var(--paper)); border: 1px solid var(--accent); padding: 9px 13px; border-radius: 9px; margin: 0 0 12px; }
        a { color: var(--accent); }
        .muted { color: var(--muted); font-size: 0.9rem; }
        .foot { text-align: center; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="card">
            @if ($errors->any())
                @foreach ($errors->all() as $error)<div class="err">{{ $error }}</div>@endforeach
            @endif
            @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif
            @yield('body')
        </div>
        <div class="foot muted">@yield('foot')</div>
    </div>
</body>
</html>
