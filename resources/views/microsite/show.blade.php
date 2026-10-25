<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $isDraft ? 'noindex, nofollow' : 'index, follow' }}">
    <title>{{ $content['meta']['title'] ?? $content['hero']['headline'] }}</title>
    <meta name="description" content="{{ $content['meta']['description'] ?? '' }}">
    <link rel="canonical" href="{{ $site->url() }}">
    <link rel="icon" href="{{ route('microsite.favicon', $site->slug) }}" type="image/svg+xml">

    {{-- Open Graph / Twitter: a shareable card with no external image dependency. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $content['meta']['title'] ?? $content['hero']['headline'] }}">
    <meta property="og:description" content="{{ $content['meta']['description'] ?? '' }}">
    <meta property="og:url" content="{{ $site->url() }}">
    <meta property="og:site_name" content="{{ $site->inputs['business_name'] ?? '' }}">
    <meta name="twitter:card" content="summary">

    <style>
        :root { --accent: {{ $accent }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #ffffff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 0 20px; }
        a { color: var(--accent); }
        .banner img { display: block; width: 100%; height: auto; max-height: 440px; object-fit: cover; }
        header { padding: 88px 0 64px; border-bottom: 6px solid var(--accent); }
        .banner + header { padding-top: 48px; }
        .kicker { text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.8rem; font-weight: 700; color: var(--accent); margin: 0 0 14px; }
        h1 { font-size: clamp(2.1rem, 6vw, 3.6rem); line-height: 1.08; margin: 0 0 16px; letter-spacing: -0.02em; }
        .subhead { font-size: clamp(1.1rem, 2.6vw, 1.4rem); color: var(--muted); margin: 0 0 28px; max-width: 42ch; }
        .hero { display: grid; grid-template-columns: 1fr; gap: 32px; align-items: center; }
        @media (min-width: 760px) { .hero.has-art { grid-template-columns: 1.4fr 1fr; } }
        .art { color: var(--accent); max-width: 260px; width: 100%; justify-self: center; }
        .art svg { display: block; width: 100%; height: auto; }
        .btn { display: inline-block; background: var(--accent); color: #fff; text-decoration: none; font-weight: 600; padding: 14px 26px; border-radius: 12px; border: 0; font-size: 1.05rem; cursor: pointer; }
        .btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--accent) 45%, #fff); outline-offset: 2px; }
        .cta-note { color: var(--muted); font-size: 0.9rem; margin: 12px 0 0; max-width: 46ch; }
        section { padding: 56px 0; }
        h2 { font-size: 1.6rem; margin: 0 0 16px; }
        .lead { font-size: 1.12rem; max-width: 62ch; margin: 0; }
        ul.bullets { margin: 20px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; max-width: 54ch; }
        ul.bullets li { padding-left: 28px; position: relative; }
        ul.bullets li::before { content: "✓"; position: absolute; left: 0; color: var(--accent); font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 8px; }
        .cardbox { background: var(--card); border: 1px solid var(--line); border-top: 4px solid var(--accent); border-radius: 12px; padding: 24px; }
        .cardbox h3 { margin: 0 0 8px; font-size: 1.15rem; }
        .cardbox p { margin: 0; color: var(--muted); }
        .extra p { color: var(--muted); max-width: 62ch; }
        .form-section { background: var(--card); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        form.entry { display: grid; gap: 14px; max-width: 520px; }
        form.entry label { font-weight: 600; font-size: 0.92rem; display: block; margin-bottom: 4px; }
        form.entry input, form.entry textarea { font: inherit; width: 100%; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        form.entry textarea { min-height: 90px; resize: vertical; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .msg { margin: 0 0 14px; padding: 12px 16px; border-radius: 10px; font-weight: 500; }
        .msg.ok { background: color-mix(in srgb, var(--accent) 16%, var(--paper)); color: var(--ink); }
        .msg.err { background: #fee2e2; color: #991b1b; }
        [hidden] { display: none !important; }
        footer { padding: 32px 0 64px; color: var(--muted); font-size: 0.9rem; border-top: 1px solid var(--line); }
        footer a { color: var(--muted); }
        .qr { position: fixed; left: 16px; bottom: 16px; width: 206px; padding: 14px; text-align: center; background: #fff; color: #1c1917; border: 1px solid #d6d3d1; border-radius: 14px; box-shadow: 0 8px 28px rgba(0,0,0,.18); z-index: 10; }
        .qr p { margin: 0 20px 10px 0; text-align: left; font-size: 0.85rem; }
        .qr svg { display: block; width: 150px; height: 150px; margin: 0 auto; }
        .qr .close { position: absolute; top: 6px; right: 8px; border: 0; background: none; font-size: 1.2rem; line-height: 1; color: #57534e; cursor: pointer; }
        @media (max-width: 820px) { .qr { display: none; } }
    </style>
</head>
<body>
    @if ($isDraft)
        <div style="background:#92400e;color:#fff;text-align:center;padding:8px 16px;font-weight:600;font-size:0.9rem">
            Draft preview — not live and not accepting entries. Visible only to people with this link.
        </div>
    @endif
    @if ($banners['desktop'] || $banners['mobile'])
        <div class="banner">
            <picture>
                @if ($banners['mobile'])
                    <source media="(max-width: 759px)" srcset="{{ $banners['mobile'] }}">
                @endif
                <img src="{{ $banners['desktop'] ?? $banners['mobile'] }}" alt="{{ $content['hero']['headline'] }}">
            </picture>
        </div>
    @endif

    @php($showArt = $hero && ! $banners['desktop'] && ! $banners['mobile'])
    <header>
        <div class="wrap hero{{ $showArt ? ' has-art' : '' }}">
            <div>
                @if (!empty($content['hero']['kicker']))
                    <p class="kicker">{{ $content['hero']['kicker'] }}</p>
                @endif
                <h1>{{ $content['hero']['headline'] }}</h1>
                <p class="subhead">{{ $content['hero']['subhead'] }}</p>
                <a class="btn" href="#enter">{{ $content['cta']['label'] }}</a>
                @if (!empty($content['cta']['note']))
                    <p class="cta-note">{{ $content['cta']['note'] }}</p>
                @endif
            </div>
            @if ($showArt)
                <div class="art">@include('microsite.art.'.$hero)</div>
            @endif
        </div>
    </header>

    <main>
        <section>
            <div class="wrap">
                <h2>{{ $content['offer']['heading'] }}</h2>
                <p class="lead">{{ $content['offer']['body'] }}</p>
                @if (!empty($content['offer']['bullets']))
                    <ul class="bullets">
                        @foreach ($content['offer']['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section>
            <div class="wrap">
                <div class="grid">
                    @foreach ($content['features'] as $feature)
                        <div class="cardbox">
                            <h3>{{ $feature['title'] }}</h3>
                            <p>{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if (!empty($content['about']))
            <section class="extra">
                <div class="wrap">
                    <h2>About</h2>
                    <p class="lead">{{ $content['about'] }}</p>
                </div>
            </section>
        @endif

        <section class="form-section" id="enter">
            <div class="wrap">
                <h2>{{ $content['form']['heading'] }}</h2>
                <p class="lead" style="margin-bottom:22px">{{ $content['form']['note'] }}</p>

                <div class="msg ok" id="entry-ok" hidden></div>
                <div class="msg err" id="entry-err" hidden></div>

                @if (! $acceptsEntries)
                    <p class="msg err">{{ $isDraft ? 'This is a draft preview — the form is disabled until it goes live.' : 'Entries are closed for this promotion.' }}</p>
                @endif

                <form class="entry" id="entry-form" method="post" action="{{ route('microsite.enter', $site->slug) }}" {{ $acceptsEntries ? '' : 'hidden' }}>
                    <div>
                        <label for="f-name">Your name</label>
                        <input type="text" id="f-name" name="name" maxlength="80" required autocomplete="name">
                    </div>
                    <div>
                        <label for="f-email">Email</label>
                        <input type="email" id="f-email" name="email" maxlength="120" autocomplete="email">
                    </div>
                    <div>
                        <label for="f-phone">Phone</label>
                        <input type="text" id="f-phone" name="phone" maxlength="40" autocomplete="tel">
                    </div>
                    <div>
                        <label for="f-message">Anything to add? (optional)</label>
                        <textarea id="f-message" name="message" maxlength="1000"></textarea>
                    </div>
                    <div class="hp" aria-hidden="true">
                        <label for="f-website">Leave this field empty</label>
                        <input type="text" id="f-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div>
                        <button type="submit" class="btn">{{ $content['cta']['label'] }}</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            @if ($site->isSweepstakes() && is_array($content['rules'] ?? null))
                <p><a href="{{ route('microsite.rules', $site->slug) }}">Official rules, eligibility &amp; free entry</a>
                    @if ($rulesDocUrl) · <a href="{{ $rulesDocUrl }}">Official rules (document)</a>@endif ·
                    No purchase necessary. Open to {{ \Illuminate\Support\Str::limit($content['rules']['eligibility'], 90) }}</p>
            @endif
            @if ($showBranding)
                <p>Made with <a href="{{ route('home') }}">Microsites in Minutes</a></p>
            @endif
        </div>
    </footer>

    <aside class="qr" id="qr" aria-label="Open on your phone">
        <button type="button" class="close" id="qr-close" aria-label="Hide the QR code">&times;</button>
        <p>Scan to open on your phone</p>
        {!! $qr !!}
    </aside>

    <script>
        (() => {
            const data = @json($client);
            const $ = (id) => document.getElementById(id);

            /* QR notice: hidden for good once dismissed in this browser. */
            const qr = $('qr');
            try { if (localStorage.getItem('mim-qr-hidden') === '1') { qr.hidden = true; } } catch (e) {}
            $('qr-close').addEventListener('click', () => {
                qr.hidden = true;
                try { localStorage.setItem('mim-qr-hidden', '1'); } catch (e) {}
            });

            /* Entry form: post with fetch, show the result inline. Keeps the page cacheable. */
            const form = $('entry-form');
            const ok = $('entry-ok');
            const err = $('entry-err');
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
                        body: JSON.stringify(Object.fromEntries(new FormData(form))),
                    });
                    body = await res.json().catch(() => ({}));
                } catch (e) {
                    button.disabled = false;
                    err.textContent = 'No connection just now. Please try again.';
                    err.hidden = false;
                    return;
                }
                button.disabled = false;
                if (res.ok && body.ok) {
                    form.hidden = true;
                    ok.textContent = body.message || 'Thanks — you are all set.';
                    ok.hidden = false;
                } else {
                    const first = body.errors ? Object.values(body.errors)[0][0] : (body.message || 'Please check the form and try again.');
                    err.textContent = first;
                    err.hidden = false;
                }
            });

            /* Reload when the owner regenerates the page elsewhere. */
            let current = data.version;
            setInterval(async () => {
                if (document.hidden) { return; }
                try {
                    const res = await fetch(data.version_url, { cache: 'no-store', headers: { Accept: 'application/json' } });
                    if (!res.ok) { return; }
                    const body = await res.json();
                    if (body.version && body.version !== current && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
                        location.reload();
                    }
                } catch (e) {}
            }, 5000);
        })();
    </script>

    @if ($ga4)
        {{-- The owner's own Google Analytics 4 tag: the one external script a page may load, and only when they add an ID. --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($ga4));
        </script>
    @endif
</body>
</html>
