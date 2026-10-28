<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Import API credentials</title>
    <style>
        :root { --accent: #0f766e; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.55; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 40px 18px 64px; }
        h1 { font-size: 1.4rem; margin: 0 0 6px; }
        .muted { color: var(--muted); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 22px; margin: 18px 0; }
        .label { font-weight: 600; margin: 14px 0 4px; }
        .val { font-family: ui-monospace, monospace; background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; word-break: break-all; font-size: 0.85rem; }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 10px; padding: 12px 22px; font: inherit; font-weight: 600; cursor: pointer; margin-top: 8px; }
        .warn { border-color: #b45309; }
    </style>
</head>
<body>
    <div class="wrap">
        @if ($state === 'expired')
            <div class="card warn">
                <h1>This link has expired</h1>
                <p class="muted" style="margin:0">A share link opens only once and lasts an hour. Ask the site owner to generate a fresh one from their dashboard.</p>
            </div>
        @elseif ($state === 'confirm')
            <h1>Import API credentials</h1>
            <p class="muted" style="margin:0">Someone shared this site's Import-API credentials with you. They can be viewed <strong>once</strong> — click below when you're ready to copy them.</p>
            <div class="card">
                <form method="post" action="{{ route('api-token.reveal.confirm', $id) }}">
                    @csrf
                    <button type="submit" class="btn">Reveal credentials</button>
                </form>
                <p class="muted" style="font-size:0.8rem;margin:12px 0 0">After you reveal them this link stops working, so copy what you need before leaving the page.</p>
            </div>
        @else
            <h1>Import API credentials</h1>
            <p class="muted" style="margin:0">For <strong>{{ $data['business_name'] }}</strong>. Copy these now — this link has now been used and won't load again.</p>

            <div class="card">
                <div class="label">Endpoint</div>
                <div class="val" id="endpoint">POST {{ $data['endpoint'] }}</div>

                <div class="label">Bearer token</div>
                <div class="val" id="token">{{ $data['token'] }}</div>
                <button type="button" class="btn" id="copy-token" data-copy="{{ $data['token'] }}">Copy token</button>

                <p class="muted" style="font-size:0.8rem;margin:16px 0 0">Send the token as <code>Authorization: Bearer &lt;token&gt;</code> from your server only — it's the private key to this site's dashboard, so treat it like a password.</p>
            </div>
        @endif
    </div>

    <script>
        document.getElementById('copy-token')?.addEventListener('click', async (e) => {
            const b = e.currentTarget;
            try { await navigator.clipboard.writeText(b.dataset.copy || ''); } catch (err) { /* no-op */ }
            b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy token'; }, 1500);
        });
    </script>
</body>
</html>
