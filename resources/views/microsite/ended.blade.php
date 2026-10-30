<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $content['hero']['headline'] }} — ended</title>
    <link rel="icon" href="{{ route('microsite.favicon', $site->slug) }}">
    <style>
        :root { --accent: {{ $accent }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); display: grid; place-items: center; min-height: 100vh; text-align: center; }
        .wrap { max-width: 540px; padding: 32px 22px; }
        .kicker { text-transform: uppercase; letter-spacing: 0.14em; font-weight: 700; color: var(--muted); font-size: 0.85rem; }
        h1 { font-size: clamp(1.8rem, 6vw, 2.8rem); letter-spacing: -0.02em; margin: 10px 0 14px; }
        .sub { font-size: 1.1rem; color: var(--muted); max-width: 44ch; margin: 0 auto; }
        footer { position: fixed; bottom: 14px; left: 0; right: 0; color: var(--muted); font-size: 0.85rem; }
        footer a { color: var(--muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="kicker">This has ended</p>
        <h1>{{ $content['hero']['headline'] }}</h1>
        <p class="sub">Thanks for your interest. {{ $content['hero']['headline'] }} has ended.
            @if ($endsAt) Closed {{ $endsAt->format('F j, Y') }}. @endif Check back for what's next.</p>
    </div>
    @if ($showBranding)
        <footer><a href="{{ route('home') }}">Made with Microsites in Minutes</a></footer>
    @endif
</body>
</html>
