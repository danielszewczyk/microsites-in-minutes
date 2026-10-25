<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $isDraft ? 'noindex, nofollow' : 'index, follow' }}">
    <title>{{ $content['meta']['title'] ?? $content['hero']['headline'] }}</title>
    <meta name="description" content="{{ $content['meta']['description'] ?? '' }}">
    <link rel="icon" href="{{ route('microsite.favicon', $site->slug) }}">
    <style>
        :root { --accent: {{ $accent }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); display: grid; place-items: center; min-height: 100vh; text-align: center; }
        .wrap { max-width: 560px; padding: 32px 22px; }
        .kicker { text-transform: uppercase; letter-spacing: 0.14em; font-weight: 700; color: var(--accent); font-size: 0.85rem; }
        h1 { font-size: clamp(2rem, 7vw, 3.2rem); letter-spacing: -0.02em; margin: 10px 0 14px; }
        .sub { font-size: 1.15rem; color: var(--muted); max-width: 42ch; margin: 0 auto 10px; }
        .when { color: var(--accent); font-weight: 600; margin: 8px 0 24px; }
        form { display: flex; gap: 8px; max-width: 380px; margin: 0 auto; }
        input { font: inherit; flex: 1; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 10px; padding: 12px 20px; font-weight: 600; cursor: pointer; }
        .msg { margin: 14px auto 0; max-width: 380px; padding: 10px 14px; border-radius: 10px; }
        .msg.ok { background: color-mix(in srgb, var(--accent) 16%, var(--paper)); }
        .msg.err { background: #fee2e2; color: #991b1b; }
        [hidden] { display: none !important; }
        footer { position: fixed; bottom: 14px; left: 0; right: 0; color: var(--muted); font-size: 0.85rem; }
        footer a { color: var(--muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="kicker">Coming soon</p>
        <h1>{{ $content['hero']['headline'] }}</h1>
        <p class="sub">{{ $content['hero']['subhead'] }}</p>
        @if ($goLiveAt)
            <p class="when">Goes live {{ $goLiveAt->format('F j, Y') }}@if ($goLiveAt->format('g:i a') !== '12:00 am') at {{ $goLiveAt->format('g:i a') }}@endif</p>
        @endif

        <div class="msg ok" id="ok" hidden></div>
        <div class="msg err" id="err" hidden></div>
        <form id="f">
            <input type="email" id="e" name="email" placeholder="Email me when it's live" aria-label="Email" required autocomplete="email">
            <input type="text" name="name" value="Waitlist" hidden>
            <button type="submit" class="btn">Notify me</button>
        </form>
    </div>

    @if ($showBranding)
        <footer><a href="{{ route('home') }}">Made with Microsites in Minutes</a></footer>
    @endif

    <script>
        (() => {
            const data = @json($client);
            const goLive = @json($goLiveAt?->toIso8601String());
            const f = document.getElementById('f'), ok = document.getElementById('ok'), err = document.getElementById('err');
            f.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                ok.hidden = true; err.hidden = true;
                const btn = f.querySelector('button'); btn.disabled = true;
                let res, body = {};
                try {
                    res = await fetch(data.enter, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: 'Waitlist', email: document.getElementById('e').value, waitlist: 1 }) });
                    body = await res.json().catch(() => ({}));
                } catch (e) { btn.disabled = false; err.textContent = 'Please try again.'; err.hidden = false; return; }
                btn.disabled = false;
                if (res.ok && body.ok) { f.hidden = true; ok.textContent = "You're on the list — we'll email you when it goes live."; ok.hidden = false; }
                else { err.textContent = body.message || 'Please check your email and try again.'; err.hidden = false; }
            });
            // Flip to the live page at the go-live moment, without a manual refresh.
            if (goLive) {
                const ms = new Date(goLive).getTime() - Date.now();
                if (ms > 0 && ms < 86400000) { setTimeout(() => location.reload(), ms + 1500); }
            }
        })();
    </script>
</body>
</html>
