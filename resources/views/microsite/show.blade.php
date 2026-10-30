<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $isDraft ? 'noindex, nofollow' : 'index, follow' }}">
    <title>{{ $content['meta']['title'] ?? $content['hero']['headline'] }}</title>
    <meta name="description" content="{{ $content['meta']['description'] ?? '' }}">
    <link rel="canonical" href="{{ $site->url() }}">
    <link rel="icon" href="{{ route('microsite.favicon', $site->slug) }}">

    {{-- Open Graph / Twitter: a shareable card with no external image dependency. --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $content['meta']['title'] ?? $content['hero']['headline'] }}">
    <meta property="og:description" content="{{ $content['meta']['description'] ?? '' }}">
    <meta property="og:url" content="{{ $site->url() }}">
    <meta property="og:site_name" content="{{ $site->inputs['business_name'] ?? '' }}">
    <meta property="og:image" content="{{ route('microsite.og', $site->slug, absolute: true) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ route('microsite.og', $site->slug, absolute: true) }}">

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
        /* Hero text over the banner: both sit in one grid cell, the text at the bottom on a dark scrim. */
        .hero-stack.overlay { display: grid; }
        .hero-stack.overlay > * { grid-area: 1 / 1; }
        .hero-stack.overlay header { align-self: end; color: #fff; border-bottom: 0; padding: 48px 0 40px; background: linear-gradient(to top, rgba(0,0,0,.72), rgba(0,0,0,0)); }
        .hero-stack.overlay .kicker, .hero-stack.overlay .cta-note { color: #fff; }
        .hero-stack.overlay .subhead { color: rgba(255,255,255,.92); }
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
        /* Smile capture: mobile camera viewfinder with a framing mask. */
        .photo-cap .opt { color: var(--muted); font-weight: 400; }
        .vf { position: relative; width: 100%; max-width: 300px; aspect-ratio: 3 / 4; margin: 10px auto; border-radius: 16px; overflow: hidden; background: #000; }
        .vf video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1) scale(2); background: #000; }
        /* Letterbox mask: solid black bars top and bottom, a clear slit in the
           middle for the smile. The slit height (26%) must match SLIT in the JS
           so the captured crop lines up with what the visitor framed. */
        .vf .bars { position: absolute; inset: 0; display: flex; flex-direction: column; pointer-events: none; }
        .vf .bars .bar { flex: 1 1 auto; background: #000; }
        .vf .bars .slit { flex: 0 0 26%; position: relative; display: flex; align-items: flex-end; justify-content: center; box-shadow: inset 0 0 0 1px rgba(255,255,255,.35); }
        .vf .vf-hint { color: #fff; font-size: 0.85rem; margin-bottom: 6px; text-shadow: 0 1px 4px rgba(0,0,0,.7); }
        #shot { width: 100%; max-width: 300px; border-radius: 16px; display: block; margin: 10px auto; }
        .cap-btns { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn.ghost { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
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
        /* Email code verification: six single-digit boxes feeding a hidden field. */
        .code-boxes { display: flex; gap: 8px; max-width: 340px; }
        .code-boxes input { text-align: center; font-size: 1.5rem; font-weight: 700; padding: 10px 0; letter-spacing: 0; }
        .code-meta { color: var(--muted); font-size: 0.9rem; margin: 10px 0 0; }
        .linkbtn { background: none; border: 0; color: var(--accent); font: inherit; cursor: pointer; padding: 0; text-decoration: underline; }
        .linkbtn:disabled { color: var(--muted); cursor: default; text-decoration: none; }
        .cf-turnstile { margin: 4px 0; }
        [hidden] { display: none !important; }
        footer { padding: 32px 0 64px; color: var(--muted); font-size: 0.9rem; border-top: 1px solid var(--line); }
        footer a { color: var(--muted); }
        .qr { position: fixed; left: 16px; bottom: 16px; width: 206px; padding: 14px; text-align: center; background: #fff; color: #1c1917; border: 1px solid #d6d3d1; border-radius: 14px; box-shadow: 0 8px 28px rgba(0,0,0,.18); z-index: 10; }
        .qr p { margin: 0 20px 10px 0; text-align: left; font-size: 0.85rem; }
        .qr svg { display: block; width: 150px; height: 150px; margin: 0 auto; }
        .qr .close { position: absolute; top: 6px; right: 8px; border: 0; background: none; font-size: 1.2rem; line-height: 1; color: #57534e; cursor: pointer; }
        @media (max-width: 820px) { .qr { display: none; } }
        /* Draft notice, styled like Stripe's sandbox banner: full-bleed bar with a dark
           shoulder below the text, label pinned left, note centred. The content below
           tucks up into the shoulder with rounded top corners. */
        .preview-bar { position: relative; z-index: 1; background: #0b2d4f; color: #fff; padding: 12px 24px 32px; font-size: 0.95rem; text-align: center; }
        .preview-bar strong { position: absolute; left: 24px; top: 14px; font-size: 1.05rem; }
        .preview-toggle, .preview-info { display: none; }
        /* Paper content pulled up into the bar's navy shoulder, so its rounded top sits on navy. */
        .under-preview { position: relative; z-index: 2; margin-top: -22px; border-radius: 22px 22px 0 0; overflow: hidden; background: var(--paper); }
        @media (max-width: 640px) {
            /* Collapse to just "Preview" + an (i) icon; the note expands on tap (pure CSS). */
            .preview-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-start; gap: 8px; text-align: left; }
            .preview-bar strong { position: static; }
            .preview-info { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border: 1px solid rgba(255,255,255,.55); border-radius: 6px; font: italic 700 0.72rem/1 Georgia, serif; color: #fff; cursor: pointer; }
            .preview-note { display: none; flex-basis: 100%; margin-top: 8px; }
            .preview-toggle:checked ~ .preview-note { display: block; }
        }
    </style>
</head>
<body>
    @if ($isDraft)
        <div class="preview-bar">
            <strong>Preview</strong>
            <input type="checkbox" id="preview-toggle" class="preview-toggle">
            <label for="preview-toggle" class="preview-info" role="button" aria-label="Show preview details">i</label>
            <span class="preview-note">Not live and not accepting entries. Visible only to people with this link.</span>
        </div>
    @endif
    @php($hasBanner = $banners['desktop'] || $banners['mobile'])
    @php($overlay = $hasBanner && $site->heroOverlay())
    <div class="hero-stack{{ $overlay ? ' overlay' : '' }}{{ $isDraft ? ' under-preview' : '' }}">
    @if ($hasBanner)
        <div class="banner">
            <picture>
                @if ($banners['mobile'])
                    <source media="(max-width: 759px)" srcset="{{ $banners['mobile'] }}">
                @endif
                <img src="{{ $banners['desktop'] ?? $banners['mobile'] }}" alt="{{ $content['hero']['headline'] }}">
            </picture>
        </div>
    @endif

    @php($showArt = $hero && ! $hasBanner)
    <header>
        <div class="wrap hero{{ $showArt ? ' has-art' : '' }}">
            <div>
                @if (!empty($content['hero']['kicker']))
                    <p class="kicker">{{ $content['hero']['kicker'] }}</p>
                @endif
                <h1>{{ $content['hero']['headline'] }}</h1>
                <p class="subhead">{{ $content['hero']['subhead'] }}</p>
                <a class="btn" href="#enter"@isset($trackIds['hero_cta']) data-mim-id="{{ $trackIds['hero_cta'] }}"@endisset>{{ $content['cta']['label'] }}</a>
                @if (!empty($content['cta']['note']))
                    <p class="cta-note">{{ $content['cta']['note'] }}</p>
                @endif
            </div>
            @if ($showArt)
                <div class="art">@include('microsite.art.'.$hero)</div>
            @endif
        </div>
    </header>
    </div>

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

                <form class="entry" id="entry-form" method="post" action="{{ route('microsite.enter', $site->slug, absolute: false) }}" {{ $acceptsEntries ? '' : 'hidden' }}>
                    <div>
                        <label for="f-name">Your name</label>
                        <input type="text" id="f-name" name="name" maxlength="80" required autocomplete="name">
                    </div>
                    <div>
                        <label for="f-email">Email</label>
                        <input type="email" id="f-email" name="email" maxlength="120" autocomplete="email">
                    </div>
                    @if ($site->requiresEmailCode())
                        <div id="email-confirm-row">
                            <label for="f-email-confirm">Confirm email</label>
                            <input type="email" id="f-email-confirm" name="email_confirm" maxlength="120" autocomplete="email">
                        </div>
                    @endif
                    <div>
                        <label for="f-phone">Phone</label>
                        <input type="text" id="f-phone" name="phone" maxlength="40" autocomplete="tel">
                    </div>
                    @if ($site->collectsDateOfBirth())
                        <div>
                            <label for="f-dob">Date of birth</label>
                            <input type="date" id="f-dob" name="dob" max="{{ now()->toDateString() }}" min="1900-01-01" autocomplete="bday">
                        </div>
                    @endif
                    <div>
                        <label for="f-message">Anything to add? (optional)</label>
                        <textarea id="f-message" name="message" maxlength="1000"></textarea>
                    </div>
                    <div class="hp" aria-hidden="true">
                        <label for="f-website">Leave this field empty</label>
                        <input type="text" id="f-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    @if ($site->collectsPhotos())
                        <div class="photo-cap" id="photo-cap" hidden>
                            <label>Add your smile <span class="opt">(optional)</span></label>
                            <div class="vf" id="vf" hidden>
                                <video id="cam" playsinline autoplay muted></video>
                                <div class="bars" aria-hidden="true">
                                    <div class="bar"></div>
                                    <div class="slit"><span class="vf-hint">Line up your smile 😁</span></div>
                                    <div class="bar"></div>
                                </div>
                            </div>
                            <img id="shot" alt="Your photo" hidden>
                            <input type="file" id="photo-file" accept="image/*" capture="user" hidden>
                            <div class="cap-btns">
                                <button type="button" class="btn ghost" id="cap-start">📸 Take your smile</button>
                                <button type="button" class="btn" id="cap-shoot" hidden>Capture</button>
                                <button type="button" class="btn ghost" id="cap-redo" hidden>Retake</button>
                            </div>
                        </div>
                    @endif
                    @if ($site->requiresEmailCode())
                        <div id="code-step" hidden>
                            <label id="code-label">Enter the 6-digit code we emailed you</label>
                            <div class="code-boxes" role="group" aria-labelledby="code-label">
                                @for ($i = 0; $i < 6; $i++)
                                    <input class="code-box" inputmode="numeric" maxlength="6" pattern="[0-9]*"
                                        autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="Digit {{ $i + 1 }}">
                                @endfor
                            </div>
                            <input type="hidden" id="f-code" name="code">
                            <p class="code-meta">Can't find it? Check your spam folder.
                                <button type="button" class="linkbtn" id="code-resend" disabled>Resend code</button>
                                <span id="code-timer"></span></p>
                        </div>
                        @if ($turnstileSiteKey)
                            <div id="ts-widget" class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
                        @endif
                    @endif
                    <div>
                        <button type="submit" class="btn" id="entry-submit"@isset($trackIds['entry_submit']) data-mim-id="{{ $trackIds['entry_submit'] }}"@endisset>{{ $content['cta']['label'] }}</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            @if ($site->isSweepstakes() && is_array($content['rules'] ?? null))
                <p><a href="{{ route('microsite.rules', $site->slug) }}"@isset($trackIds['rules']) data-mim-id="{{ $trackIds['rules'] }}"@endisset>Official rules, eligibility &amp; free entry</a>
                    @if ($rulesDocUrl) · <a href="{{ $rulesDocUrl }}"@isset($trackIds['rules_doc']) data-mim-id="{{ $trackIds['rules_doc'] }}"@endisset>Official rules (document)</a>@endif ·
                    No purchase necessary. Open to {{ \Illuminate\Support\Str::limit($content['rules']['eligibility'], 90) }}</p>
            @endif
            @if ($showBranding)
                <p>Made with <a href="{{ route('home') }}"@isset($trackIds['branding']) data-mim-id="{{ $trackIds['branding'] }}"@endisset>Microsites in Minutes</a></p>
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

            /* Smile capture (mobile only): live camera with a framing mask, a
               capture button, and a native-picker fallback. The chosen image is
               held as a blob and sent with the entry as multipart form data. */
            let photoBlob = null;
            const photoCap = $('photo-cap');
            if (photoCap && /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                photoCap.hidden = false;
                const vf = $('vf'), cam = $('cam'), shot = $('shot'), file = $('photo-file');
                const bStart = $('cap-start'), bShoot = $('cap-shoot'), bRedo = $('cap-redo');
                let stream = null;
                const SLIT = 0.26; /* smile strip height — must match .slit in the CSS */
                const ZOOM = 2;    /* viewfinder zoom; the preview transform and the capture crop share it */
                const stop = () => { if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; } };
                const show = (blob, url) => {
                    photoBlob = blob; shot.src = url; shot.hidden = false;
                    vf.hidden = true; stop(); bShoot.hidden = true; bStart.hidden = true; bRedo.hidden = false;
                };
                bStart.addEventListener('click', async () => {
                    photoBlob = null;
                    bStart.disabled = true;
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                        cam.srcObject = stream;
                        // Reveal the viewfinder only once the stream is sized, so it
                        // doesn't flash empty and reflow as the camera warms up.
                        cam.onloadedmetadata = () => { vf.hidden = false; bStart.hidden = true; bStart.disabled = false; bShoot.hidden = false; };
                    } catch (e) {
                        bStart.disabled = false;
                        file.click(); /* no camera access: use the native photo picker */
                    }
                });
                bShoot.addEventListener('click', () => {
                    const w = cam.videoWidth, h = cam.videoHeight;
                    if (!w || !h) { return; }
                    // Match the zoomed slit view: centre-crop by the zoom factor,
                    // then keep only the slit band of the visible height.
                    const sw = Math.round(w / ZOOM), sx = Math.round((w - sw) / 2);
                    const sh = Math.round(h * SLIT / ZOOM), sy = Math.round((h - sh) / 2);
                    const c = document.createElement('canvas'); c.width = sw; c.height = sh;
                    const ctx = c.getContext('2d');
                    ctx.translate(sw, 0); ctx.scale(-1, 1); /* un-mirror the selfie view */
                    ctx.drawImage(cam, sx, sy, sw, sh, 0, 0, sw, sh);
                    c.toBlob((blob) => { if (blob) { show(blob, URL.createObjectURL(blob)); } }, 'image/jpeg', 0.9);
                });
                bRedo.addEventListener('click', () => { photoBlob = null; shot.src = ''; shot.hidden = true; bRedo.hidden = true; bStart.hidden = false; });
                file.addEventListener('change', () => {
                    const f = file.files[0];
                    if (!f) { return; }
                    // Downscale the picked photo in the browser so the upload stays
                    // small (a raw camera original can exceed the server's limit).
                    const img = new Image();
                    img.onload = () => {
                        const max = 1600, s = Math.min(1, max / Math.max(img.width, img.height));
                        const c = document.createElement('canvas');
                        c.width = Math.max(1, Math.round(img.width * s));
                        c.height = Math.max(1, Math.round(img.height * s));
                        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                        c.toBlob((blob) => show(blob || f, URL.createObjectURL(blob || f)), 'image/jpeg', 0.9);
                        URL.revokeObjectURL(img.src);
                    };
                    img.onerror = () => show(f, URL.createObjectURL(f));
                    img.src = URL.createObjectURL(f);
                });
            }

            /* Entry form: post with fetch, show the result inline. Keeps the page cacheable. */
            const form = $('entry-form');
            const ok = $('entry-ok');
            const err = $('entry-err');
            const submitBtn = $('entry-submit');
            const requireCode = !!data.require_code;
            const showErr = (m) => { err.textContent = m; err.hidden = false; };

            /* Cloudflare Turnstile token broker. The widget renders in explicit
               mode; freshToken() resets it and resolves with a new token for each
               server call (both send-code and enter require one). With Turnstile
               unconfigured no widget exists and the token is empty — which the
               server accepts only because it is likewise unconfigured. */
            let tsId = null, tsWaiter = null, tsLast = '';
            const settleToken = (t) => { if (tsWaiter) { const r = tsWaiter; tsWaiter = null; r(t); } };
            window.onTurnstileLoad = () => {
                const el = $('ts-widget');
                if (!el || !window.turnstile) { return; }
                try {
                    tsId = window.turnstile.render(el, {
                        sitekey: el.dataset.sitekey,
                        callback: (t) => { tsLast = t; settleToken(t); },
                        'error-callback': () => settleToken(''),
                    });
                } catch (e) {}
            };
            const freshToken = () => new Promise((resolve) => {
                if (!window.turnstile || tsId === null) { resolve(''); return; }
                tsWaiter = resolve;
                try { window.turnstile.reset(tsId); } catch (e) {}
                // Managed mode auto-runs after reset; fall back so submit never hangs.
                setTimeout(() => { if (tsWaiter === resolve) { tsWaiter = null; resolve(tsLast || ''); } }, 8000);
            });

            /* Six-box code input writes the combined digits into hidden #f-code. */
            if (requireCode) {
                const boxes = Array.from(document.querySelectorAll('.code-box'));
                const hidden = $('f-code');
                const sync = () => { hidden.value = boxes.map((b) => b.value).join('').replace(/\D/g, '').slice(0, 6); };
                const fill = (raw) => {
                    const digits = (raw || '').replace(/\D/g, '').slice(0, 6).split('');
                    boxes.forEach((b, i) => { b.value = digits[i] || ''; });
                    sync();
                    (boxes[Math.min(digits.length, 5)] || boxes[5]).focus();
                };
                boxes.forEach((box, i) => {
                    box.addEventListener('input', () => {
                        if (box.value.length > 1) { fill(boxes.map((b) => b.value).join('')); return; }
                        box.value = box.value.replace(/\D/g, '');
                        sync();
                        if (box.value && boxes[i + 1]) { boxes[i + 1].focus(); }
                    });
                    box.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace' && !box.value && boxes[i - 1]) { boxes[i - 1].focus(); }
                    });
                    box.addEventListener('paste', (e) => { e.preventDefault(); fill((e.clipboardData || window.clipboardData).getData('text')); });
                });
            }

            /* Resend countdown: disable the resend link until the window passes. */
            let codeSent = false;
            let resendTimer = null;
            const startCountdown = (seconds) => {
                const link = $('code-resend'), label = $('code-timer');
                if (!link) { return; }
                let left = Math.max(0, parseInt(seconds, 10) || 0);
                const tick = () => {
                    if (left <= 0) {
                        link.disabled = false; label.textContent = '';
                        if (resendTimer) { clearInterval(resendTimer); resendTimer = null; }
                        return;
                    }
                    link.disabled = true;
                    label.textContent = '— new code in 0:' + String(left).padStart(2, '0');
                    left -= 1;
                };
                if (resendTimer) { clearInterval(resendTimer); }
                tick();
                resendTimer = setInterval(tick, 1000);
            };

            const utm = () => {
                const p = new URLSearchParams(location.search);
                return { utm_source: p.get('utm_source') || '', utm_campaign: p.get('utm_campaign') || '', referrer: document.referrer || '' };
            };

            /* Request a code for the entered (and confirmed) email. */
            const requestCode = async () => {
                const email = $('f-email').value.trim();
                const confirmEl = $('f-email-confirm');
                const confirm = confirmEl ? confirmEl.value.trim() : email;
                if (!email) { showErr('Please enter your email.'); return false; }
                if (email.toLowerCase() !== confirm.toLowerCase()) { showErr('The two email addresses do not match.'); return false; }
                const token = await freshToken();
                let body = {};
                try {
                    const res = await fetch(data.send_code, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email, email_confirm: confirm, website: $('f-website').value, 'cf-turnstile-response': token }),
                    });
                    body = await res.json().catch(() => ({}));
                    if (!res.ok || !body.ok) { showErr(body.message || 'Could not send a code. Please try again.'); return false; }
                } catch (e) { showErr('No connection just now. Please try again.'); return false; }
                startCountdown(body.retry_after || data.resend_seconds || 30);
                return true;
            };

            const resendBtn = $('code-resend');
            if (resendBtn) {
                resendBtn.addEventListener('click', async () => {
                    err.hidden = true; resendBtn.disabled = true;
                    if (! await requestCode()) { resendBtn.disabled = false; }
                });
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                ok.hidden = true; err.hidden = true;
                submitBtn.disabled = true;

                // Step 1 (code add-on): send a code, then reveal the code input.
                if (requireCode && !codeSent) {
                    const sent = await requestCode();
                    submitBtn.disabled = false;
                    if (sent !== true) { return; }
                    codeSent = true;
                    $('code-step').hidden = false;
                    submitBtn.textContent = 'Verify & enter';
                    const first = document.querySelector('.code-box');
                    if (first) { first.focus(); }
                    $('code-step').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                if (requireCode && ($('f-code').value || '').length !== 6) {
                    submitBtn.disabled = false; showErr('Enter the 6-digit code we emailed you.'); return;
                }

                const token = requireCode ? await freshToken() : '';
                let res, body = {}, opts;
                const extra = utm();
                if (photoBlob) {
                    const fd = new FormData(form);
                    fd.append('photo', photoBlob, 'smile.jpg');
                    Object.entries(extra).forEach(([k, v]) => fd.set(k, v));
                    if (requireCode) { fd.set('cf-turnstile-response', token); }
                    opts = { method: 'POST', headers: { Accept: 'application/json' }, body: fd };
                } else {
                    const payload = Object.assign(Object.fromEntries(new FormData(form)), extra);
                    if (requireCode) { payload['cf-turnstile-response'] = token; }
                    opts = { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload) };
                }
                try {
                    res = await fetch(data.enter, opts);
                    body = await res.json().catch(() => ({}));
                } catch (e) {
                    submitBtn.disabled = false;
                    showErr('No connection just now. Please try again.');
                    return;
                }
                submitBtn.disabled = false;
                if (res.ok && body.ok) {
                    form.hidden = true;
                    ok.textContent = body.message || 'Thanks — you are all set.';
                    ok.hidden = false;
                } else {
                    const first = body.errors ? Object.values(body.errors)[0][0] : (body.message || 'Please check the form and try again.');
                    showErr(first);
                }
            });

            /* Reload when the owner regenerates the page elsewhere. The same poll
               carries a per-tab id so the dashboard can show "viewing now". */
            let current = data.version;
            const vid = Math.random().toString(36).slice(2, 12);
            const beat = async () => {
                if (document.hidden) { return; }
                try {
                    const res = await fetch(data.version_url + (data.version_url.includes('?') ? '&' : '?') + 'v=' + vid, { cache: 'no-store', headers: { Accept: 'application/json' } });
                    if (!res.ok) { return; }
                    const body = await res.json();
                    if (body.version && body.version !== current && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
                        location.reload();
                    }
                } catch (e) {}
            };
            beat();
            setInterval(beat, 5000);

            /* Time on page: count only the time the page is actually visible, and
               send it once when the visitor leaves (sendBeacon survives unload). */
            let activeMs = 0;
            let lastTick = Date.now();
            let dwellSent = false;
            const accrue = () => { if (!document.hidden) { activeMs += Date.now() - lastTick; } lastTick = Date.now(); };
            const sendDwell = () => {
                accrue();
                if (dwellSent) { return; }
                const seconds = Math.round(activeMs / 1000);
                if (seconds < 1 || !navigator.sendBeacon) { return; }
                dwellSent = true;
                try {
                    navigator.sendBeacon(data.ping, new Blob([JSON.stringify({ seconds })], { type: 'application/json' }));
                } catch (e) { /* leaving anyway */ }
            };
            document.addEventListener('visibilitychange', () => { accrue(); if (document.hidden) { sendDwell(); } });
            window.addEventListener('pagehide', sendDwell);

            @if (! empty($trackIds))
            /* Click tracking (Pro add-on): one delegated listener, emitted only
               on a site with the add-on on. On a click on or inside a tracked
               link or button, send its stable id — assigned at publish time —
               with sendBeacon, so navigation is never delayed. Anonymous and
               cookieless: only the element id leaves the page. */
            if (data.track && navigator.sendBeacon) {
                document.addEventListener('click', (event) => {
                    const hit = event.target.closest && event.target.closest('a, button, [role="button"]');
                    if (!hit) { return; }
                    const node = hit.closest('[data-mim-id]');
                    const id = node && node.getAttribute('data-mim-id');
                    if (!id) { return; }
                    try {
                        navigator.sendBeacon(data.track, new Blob([JSON.stringify({ id })], { type: 'application/json' }));
                    } catch (e) { /* navigating anyway */ }
                }, true);
            }
            @endif
        })();
    </script>

    @if ($site->requiresEmailCode() && $turnstileSiteKey)
        {{-- Cloudflare Turnstile: the one extra external script a page may load, and only when the email-code add-on and keys are set. --}}
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad&render=explicit" async defer></script>
    @endif

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
