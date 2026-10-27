<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $content['form']['heading'] ?? 'Sign up' }}</title>
    <style>
        :root { --accent: {{ $accent }}; --ink: #1c1917; --muted: #57534e; --line: #e7e5e4; --paper: #fff; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --line: #44403c; --paper: #1c1917; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: transparent; }
        .box { max-width: 420px; margin: 0 auto; padding: 8px; }
        h2 { font-size: 1.2rem; margin: 0 0 4px; }
        p.note { color: var(--muted); margin: 0 0 16px; font-size: 0.92rem; }
        form { display: grid; gap: 12px; }
        label { font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 4px; }
        input, textarea { font: inherit; width: 100%; padding: 11px 13px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        textarea { min-height: 80px; resize: vertical; }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 10px; padding: 13px 22px; font-size: 1.02rem; font-weight: 600; cursor: pointer; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .msg { padding: 12px 14px; border-radius: 10px; font-weight: 500; }
        .msg.ok { background: color-mix(in srgb, var(--accent) 16%, var(--paper)); color: var(--ink); }
        .msg.err { background: #fee2e2; color: #991b1b; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <div class="box">
        <h2>{{ $content['form']['heading'] ?? 'Sign up' }}</h2>
        <p class="note">{{ $content['form']['note'] ?? '' }}</p>

        @if ($requiresEmailCode ?? false)
            {{-- This site verifies entrants with an emailed code, which needs the
                 full page. The embedded form can't run that step, so point there. --}}
            <p><a class="btn" href="{{ $fullUrl }}" target="_blank" rel="noopener" style="display:inline-block;text-decoration:none">Enter on the full page →</a></p>
        @else
            <div class="msg ok" id="ok" hidden></div>
            <div class="msg err" id="err" hidden></div>

            <form id="f" method="post" action="{{ $client['enter'] }}">
                <div><label for="n">Your name</label><input type="text" id="n" name="name" maxlength="80" required autocomplete="name"></div>
                <div><label for="e">Email</label><input type="email" id="e" name="email" maxlength="120" autocomplete="email"></div>
                <div><label for="p">Phone</label><input type="text" id="p" name="phone" maxlength="40" autocomplete="tel"></div>
                <div class="hp" aria-hidden="true"><label for="w">Leave empty</label><input type="text" id="w" name="website" tabindex="-1" autocomplete="off"></div>
                <div><button type="submit" class="btn">{{ $content['cta']['label'] ?? 'Submit' }}</button></div>
            </form>
        @endif
    </div>

    @unless ($requiresEmailCode ?? false)
    <script>
        (() => {
            const data = @json($client);
            const form = document.getElementById('f');
            const ok = document.getElementById('ok');
            const err = document.getElementById('err');
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                ok.hidden = true; err.hidden = true;
                const button = form.querySelector('button');
                button.disabled = true;
                let res, body = {};
                try {
                    res = await fetch(data.enter, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ...Object.fromEntries(new FormData(form)), referrer: document.referrer }),
                    });
                    body = await res.json().catch(() => ({}));
                } catch (e) { button.disabled = false; err.textContent = 'No connection just now. Please try again.'; err.hidden = false; return; }
                button.disabled = false;
                if (res.ok && body.ok) { form.hidden = true; ok.textContent = body.message || 'Thanks — you are all set.'; ok.hidden = false; }
                else { err.textContent = body.errors ? Object.values(body.errors)[0][0] : (body.message || 'Please check the form.'); err.hidden = false; }
            });
        })();
    </script>
    @endunless
</body>
</html>
