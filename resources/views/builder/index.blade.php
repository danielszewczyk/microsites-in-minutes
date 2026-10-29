<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Microsites in Minutes — one-page sites for promos, events &amp; giveaways</title>
    @php($ogDescription = 'Describe what you need and get a polished, mobile-first one-page site — a promo, an event with RSVP, a giveaway, or any landing page — published to a shareable link in seconds. A faster alternative to template builders.')
    <meta name="description" content="{{ $ogDescription }}">
    <link rel="canonical" href="{{ route('home') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Microsites in Minutes">
    <meta property="og:title" content="Microsites in Minutes — a promo site in minutes">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ route('home') }}">
    <meta property="og:image" content="{{ route('home.og') }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Microsites in Minutes — a promo site in minutes">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Microsites in Minutes — a promo site in minutes">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ route('home.og') }}">

    <link rel="icon" href="{{ route('home.favicon') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ route('home.icon') }}">
    <style>
        :root { --accent: #0f766e; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 0 20px; }
        header { padding: 64px 0 24px; text-align: center; }
        h1 { font-size: clamp(2rem, 6vw, 3.2rem); letter-spacing: -0.02em; margin: 0 0 14px; }
        .sub { font-size: 1.2rem; color: var(--muted); max-width: 40ch; margin: 0 auto; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 28px; margin: 28px 0; }
        @media (max-width: 620px) { .card { padding: 20px; } header { padding: 40px 0 16px; } }
        label { font-weight: 600; display: block; margin: 0 0 6px; }
        .hint { font-weight: 400; color: var(--muted); font-size: 0.88rem; }
        input, textarea, select { font: inherit; width: 100%; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        textarea { min-height: 120px; resize: vertical; }
        .field { margin: 0 0 20px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 620px) { .row { grid-template-columns: 1fr; } }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 12px; padding: 15px 28px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; }
        .err { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 10px; margin: 0 0 8px; font-size: 0.92rem; }
        .errors { margin: 0 0 16px; }
        .color-row { display: flex; gap: 10px; align-items: center; }
        .color-row input[type=color] { width: 54px; height: 46px; padding: 2px; }
        .plans { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-top: 10px; }
        @media (max-width: 620px) { .plans { grid-template-columns: 1fr; } }
        .plan { border: 1px solid var(--line); border-radius: 12px; padding: 16px; font-size: 0.9rem; }
        .plan b { display: block; font-size: 1.05rem; }
        .plan .price { color: var(--accent); font-weight: 700; }
        .promo { display: inline-block; background: var(--accent); color: #fff; border-radius: 999px; padding: 8px 16px; font-size: 0.95rem; font-weight: 600; margin: 0 0 18px; }
        .plan .was { text-decoration: line-through; color: var(--muted); font-weight: 400; margin-left: 6px; font-size: 0.85rem; }
        .muted { color: var(--muted); }
        details.adv summary { cursor: pointer; font-weight: 600; margin-bottom: 12px; }
        fieldset { border: 1px dashed var(--line); border-radius: 12px; padding: 18px; margin: 0 0 20px; }
        legend { font-weight: 700; padding: 0 8px; }
        /* Add-ons: optional toggle cards that reveal type-specific fields. */
        .addons { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 620px) { .addons { grid-template-columns: 1fr; } }
        .addon { display: flex; align-items: flex-start; gap: 10px; border: 1px solid var(--line); border-radius: 12px; padding: 14px; cursor: pointer; font-weight: 400; margin: 0; }
        .addon:has(input:checked) { border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
        .addon input { width: auto; margin: 3px 0 0; accent-color: var(--accent); flex: none; }
        .addon b { display: block; font-weight: 700; }
        .addon small { color: var(--muted); }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            @if ($launchFree)
                <p class="promo">🎉 Launch promotion — every site is <strong>free</strong> while we're in beta. No card required.</p>
            @endif
            <h1>Microsites in Minutes</h1>
            <p class="sub">Describe what you're promoting and get a polished, mobile-first page with a built-in form — published to a shareable link in seconds.</p>
        </div>
    </header>

    <div class="wrap">
        <form class="card" method="post" action="{{ route('sites.store') }}">
            @csrf

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div class="err">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="field">
                <label for="business_name">Site name</label>
                <input type="text" id="business_name" name="business_name" maxlength="80" value="{{ old('business_name') }}" autofocus required>
            </div>

            <div class="field">
                <label for="prompt">Describe the promotion <span class="hint">— what are you offering, and to whom?</span></label>
                <textarea id="prompt" name="prompt" maxlength="1500" required placeholder="e.g. We're a dental practice on Central Park South giving away a professional teeth-whitening kit to new patients in NYC.">{{ old('prompt') }}</textarea>
            </div>

            {{-- Optional add-ons. By default a page is a simple promo/landing page;
                 turning one on reveals the fields it needs and sets the page type. --}}
            <input type="hidden" name="addon" id="addon" value="{{ old('addon') }}">
            <div class="field">
                <label>Add-ons <span class="hint">— optional. Start with a simple promo page, or turn one on.</span></label>
                <div class="addons">
                    <label class="addon"><input type="checkbox" id="addon_event"><span><b>Event</b><small>Date, venue &amp; RSVP</small></span></label>
                    <label class="addon"><input type="checkbox" id="addon_sweeps"><span><b>Sweepstakes / giveaway</b><small>Prize, official rules &amp; winner draw</small></span></label>
                </div>
            </div>

            <fieldset id="event" hidden>
                <legend>Event details</legend>
                <div class="row">
                    <div class="field">
                        <label for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" value="{{ old('event_date') }}">
                    </div>
                    <div class="field">
                        <label for="event_time">Time</label>
                        <input type="text" id="event_time" name="event_time" maxlength="60" value="{{ old('event_time') }}" placeholder="6:00 PM">
                    </div>
                </div>
                <div class="field">
                    <label for="venue">Venue</label>
                    <input type="text" id="venue" name="venue" maxlength="160" value="{{ old('venue') }}" placeholder="The Grand Hall">
                </div>
                <div class="field">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" maxlength="200" value="{{ old('address') }}">
                </div>
            </fieldset>

            <fieldset id="sweeps" hidden>
                <legend>Giveaway details</legend>
                <div class="field">
                    <label for="prize">Prize <span class="hint">— products or swag only, never free or discounted treatment</span></label>
                    <input type="text" id="prize" name="prize" maxlength="300" value="{{ old('prize') }}">
                </div>
                <div class="row">
                    <div class="field">
                        <label for="prize_value">Approx. prize value <span class="hint">— keep under $5,000</span></label>
                        <input type="text" id="prize_value" name="prize_value" maxlength="60" value="{{ old('prize_value') }}" placeholder="$250">
                    </div>
                    <div class="field">
                        <label for="sponsor">Sponsor (legal name)</label>
                        <input type="text" id="sponsor" name="sponsor" maxlength="160" value="{{ old('sponsor') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="start_date">Start date</label>
                        <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}">
                    </div>
                    <div class="field">
                        <label for="end_date">End date</label>
                        <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}">
                    </div>
                </div>
                <div class="field">
                    <label for="eligibility">Eligibility <span class="hint">— limit to your state to avoid other states' rules</span></label>
                    <input type="text" id="eligibility" name="eligibility" maxlength="400" value="{{ old('eligibility') }}" placeholder="Legal U.S. residents of New York, 18 or older.">
                </div>
            </fieldset>

            <details class="adv">
                <summary>More options</summary>
                <div class="row">
                    <div class="field">
                        <label for="accent_color">Accent color</label>
                        <div class="color-row">
                            <input type="color" id="accent_picker" value="{{ old('accent_color', '#0f766e') }}">
                            <input type="text" id="accent_color" name="accent_color" maxlength="7" value="{{ old('accent_color', '#0f766e') }}" pattern="#[0-9a-fA-F]{6}">
                        </div>
                    </div>
                    <div class="field">
                        <label for="hero_image">Hero picture</label>
                        <select id="hero_image" name="hero_image">
                            <option value="auto" {{ old('hero_image', 'auto') === 'auto' ? 'selected' : '' }}>Pick one for me</option>
                            <option value="none" {{ old('hero_image') === 'none' ? 'selected' : '' }}>No picture</option>
                            @foreach ($illustrations as $art)
                                <option value="{{ $art }}" {{ old('hero_image') === $art ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $art)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="offer_headline">Headline <span class="hint">(optional)</span></label>
                        <input type="text" id="offer_headline" name="offer_headline" maxlength="120" value="{{ old('offer_headline') }}">
                    </div>
                    <div class="field">
                        <label for="cta_label">Button text <span class="hint">(optional)</span></label>
                        <input type="text" id="cta_label" name="cta_label" maxlength="40" value="{{ old('cta_label') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="location">Location <span class="hint">(optional)</span></label>
                        <input type="text" id="location" name="location" maxlength="120" value="{{ old('location') }}">
                    </div>
                    <div class="field">
                        <label for="contact_phone">Contact phone <span class="hint">(optional)</span></label>
                        <input type="text" id="contact_phone" name="contact_phone" maxlength="40" value="{{ old('contact_phone') }}">
                    </div>
                </div>
                <div class="field">
                    <label for="ga4_id">Google Analytics 4 ID <span class="hint">(optional, Pro plan)</span></label>
                    <input type="text" id="ga4_id" name="ga4_id" maxlength="40" value="{{ old('ga4_id') }}" placeholder="G-XXXXXXXXXX">
                </div>
                <div class="field">
                    <label for="seo_title">Page title <span class="hint">(optional, for search/sharing)</span></label>
                    <input type="text" id="seo_title" name="seo_title" maxlength="70" value="{{ old('seo_title') }}">
                </div>
                <div class="field">
                    <label for="seo_description">Meta description <span class="hint">(optional, up to 160 chars)</span></label>
                    <textarea id="seo_description" name="seo_description" maxlength="160">{{ old('seo_description') }}</textarea>
                </div>
            </details>

            @auth
                @if (auth()->user()->hasVerifiedEmail())
                    <p class="muted" style="margin:0 0 10px">Publishing as <strong>{{ auth()->user()->email }}</strong>.</p>
                    <button type="submit" class="btn">Generate my site</button>
                @else
                    <p class="muted" style="margin:0 0 10px">Verify your email to publish. <a href="{{ route('verification.notice') }}">Resend the link</a>.</p>
                    <button type="submit" class="btn" disabled style="opacity:.6">Verify your email to publish</button>
                @endif
            @else
                <div class="field">
                    <label for="guest_email">Enter your email to get started</label>
                    <input type="email" id="guest_email" name="email" value="{{ old('email') }}" maxlength="120" autocomplete="email" inputmode="email" placeholder="you@example.com">
                    <p class="hint" style="margin:6px 0 0">Log in or create a free account to publish — no card required.</p>
                </div>
                <a href="{{ route('login') }}" id="auth_cta" class="btn" style="display:block;text-align:center;text-decoration:none">Log in or sign up to publish</a>
            @endauth
        </form>

        <div class="card">
            <p class="muted" style="margin-top:0">
                @if ($launchFree)
                    Every plan is free during our launch promotion — build and publish as much as you like, no card required.
                @else
                    Start free. Upgrade when you need a custom domain, no branding, analytics or more entries.
                @endif
            </p>
            <div class="plans">
                @foreach ($plans as $key => $plan)
                    <div class="plan">
                        <b>{{ $plan['name'] }}</b>
                        @if ($launchFree)
                            <span class="price">Free<span class="was">{{ $plan['price_label'] }}</span></span>
                        @else
                            <span class="price">{{ $plan['price_label'] }}</span>
                        @endif
                        <p class="muted">{{ $plan['max_sites'] }} site(s) · {{ number_format($plan['monthly_entries']) }} entries/mo
                            @if ($plan['custom_domain']) · custom domain @endif
                            @if ($plan['remove_branding']) · no branding @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (() => {
            // Add-ons drive the hidden "addon" field and reveal their fields. A
            // page is one kind at a time, so turning one on turns the other off.
            const form = document.querySelector('form.card');
            const addonEvent = document.getElementById('addon_event');
            const addonSweeps = document.getElementById('addon_sweeps');
            const addonInput = document.getElementById('addon');
            const event = document.getElementById('event');
            const sweeps = document.getElementById('sweeps');
            const picker = document.getElementById('accent_picker');
            const hex = document.getElementById('accent_color');

            const sync = () => {
                event.hidden = !addonEvent.checked;
                sweeps.hidden = !addonSweeps.checked;
                addonInput.value = addonSweeps.checked ? 'sweepstakes' : (addonEvent.checked ? 'event' : '');
            };

            // Preserve the whole form across the login / sign-up round-trip (#7):
            // a guest fills it in, hits the publish wall, logs in, and lands back
            // on a blank form. We stash the form locally as they type and restore
            // it into a blank form on return, so nothing is lost at the auth wall.
            const DRAFT_KEY = 'mim:builder-draft';
            const DRAFT_MAX_AGE = 3 * 24 * 60 * 60 * 1000; // discard a draft older than 3 days
            const draftFields = () => Array.from(form.querySelectorAll('input[name], textarea[name], select[name]'))
                .filter((el) => el.name !== '_token');

            const saveDraft = () => {
                try {
                    const data = {};
                    draftFields().forEach((el) => { data[el.name] = el.type === 'checkbox' ? el.checked : el.value; });
                    localStorage.setItem(DRAFT_KEY, JSON.stringify({ savedAt: Date.now(), data }));
                } catch (e) { /* storage unavailable — carry on without it */ }
            };

            const restoreDraft = () => {
                let stored = null;
                try { stored = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return; }
                // Ignore (and clear) a draft that is missing, malformed or stale,
                // so an abandoned draft can't resurface days later or on a shared
                // computer.
                if (!stored || !stored.data || !stored.savedAt || (Date.now() - stored.savedAt) > DRAFT_MAX_AGE) {
                    try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* no-op */ }
                    return;
                }
                const data = stored.data;
                // Only restore into a blank form, so server-rendered old() values
                // (after a validation error) or signed-in prefills are never lost.
                if (addonInput.value || form.querySelector('[name="business_name"]').value.trim()
                    || form.querySelector('[name="prompt"]').value.trim()) { return; }
                draftFields().forEach((el) => {
                    if (!(el.name in data)) { return; }
                    if (el.type === 'checkbox') { el.checked = !!data[el.name]; } else { el.value = data[el.name]; }
                });
            };

            addonEvent.addEventListener('change', () => { if (addonEvent.checked) { addonSweeps.checked = false; } sync(); });
            addonSweeps.addEventListener('change', () => { if (addonSweeps.checked) { addonEvent.checked = false; } sync(); });

            // Restore a local draft first, then reflect the add-on selection (from
            // the draft, or from old() after a validation error) in the toggles.
            restoreDraft();
            if (addonInput.value === 'event') { addonEvent.checked = true; }
            if (addonInput.value === 'sweepstakes') { addonSweeps.checked = true; }
            sync();
            if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) { picker.value = hex.value; }

            picker.addEventListener('input', () => { hex.value = picker.value; });
            hex.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) { picker.value = hex.value; } });

            // Save on every edit — input covers text/select fields; change covers
            // the add-on checkboxes, which drive the named hidden "addon" field.
            form.addEventListener('input', saveDraft);
            form.addEventListener('change', saveDraft);

            // Guest view only: carry the email they type into the login/sign-up
            // link so it lands prefilled, and let Enter follow the CTA rather
            // than submit the builder form (which a guest can't publish anyway).
            const guestEmail = document.getElementById('guest_email');
            const authCta = document.getElementById('auth_cta');
            if (guestEmail && authCta) {
                const base = authCta.getAttribute('href');
                const syncCta = () => {
                    const v = guestEmail.value.trim();
                    authCta.href = v ? base + (base.includes('?') ? '&' : '?') + 'email=' + encodeURIComponent(v) : base;
                };
                guestEmail.addEventListener('input', syncCta);
                guestEmail.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); syncCta(); authCta.click(); }
                });
                syncCta();
            }
        })();
    </script>
</body>
</html>
