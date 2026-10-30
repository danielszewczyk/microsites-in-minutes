<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $confirmed ? 'Entry confirmed' : 'Link not valid' }}</title>
    <style>
        :root { --accent: {{ $accent }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); display: grid; place-items: center; min-height: 100vh; text-align: center; }
        .box { max-width: 420px; padding: 32px 24px; }
        .mark { width: 64px; height: 64px; border-radius: 50%; background: var(--accent); color: #fff; display: grid; place-items: center; font-size: 2rem; margin: 0 auto 18px; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="box">
        @if ($confirmed)
            <div class="mark">✓</div>
            <h1>You're confirmed!</h1>
            <p class="muted">Thanks — your entry is in. You can close this tab.</p>
        @else
            <h1>This link isn't valid</h1>
            <p class="muted">It may have already been used or expired.</p>
        @endif
        <p><a href="{{ $site->url() }}">Back to the page</a></p>
    </div>
</body>
</html>
