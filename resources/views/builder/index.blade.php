<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Microsites in Minutes — one-page sites for promos, events &amp; giveaways</title>
    <meta name="description" content="Describe what you need and get a polished, mobile-first one-page site — a promo, an event with RSVP, a giveaway, or any landing page — published to a shareable link in seconds. A faster alternative to template builders.">
    <link rel="canonical" href="{{ route('home') }}">
    <style>
        :root { --accent: #0f766e; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 0 20px; }
        header { padding: 72px 0 32px; text-align: center; }
        h1 { font-size: clamp(2rem, 6vw, 3.2rem); letter-spacing: -0.02em; margin: 0 0 14px; }
        .sub { font-size: 1.2rem; color: var(--muted); max-width: 40ch; margin: 0 auto; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 28px; margin: 28px 0; }
        label { font-weight: 600; display: block; margin: 0 0 6px; }
        .hint { font-weight: 400; color: var(--muted); font-size: 0.88rem; }
        input, textarea, select { font: inherit; width: 100%; padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        textarea { min-height: 110px; resize: vertical; }
        .field { margin: 0 0 20px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 620px) { .row { grid-template-columns: 1fr; } }
        .toggle { display: flex; gap: 10px; }
        .toggle label { flex: 1; border: 1px solid var(--line); border-radius: 10px; padding: 12px; text-align: center; cursor: pointer; font-weight: 600; }
        .toggle input { display: none; }
        .toggle input:checked + span { color: var(--accent); }
        .toggle label:has(input:checked) { border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
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
        .muted { color: var(--muted); }
        details.adv summary { cursor: pointer; font-weight: 600; margin-bottom: 12px; }
        fieldset { border: 1px dashed var(--line); border-radius: 12px; padding: 18px; margin: 0 0 20px; }
        legend { font-weight: 700; padding: 0 8px; }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <h1>Microsites in Minutes</h1>
            <p class="sub">Describe what you need — a promo, an event, a giveaway, any one-page site. Get a polished, mobile-first page with a built-in form, published to a shareable link in seconds.</p>
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
                <label>What kind of page?</label>
                <div class="toggle">
                    @foreach ($types as $value => $spec)
                        <label><input type="radio" name="type" value="{{ $value }}" {{ old('type', 'standard') === $value ? 'checked' : '' }}><span>{{ $spec['label'] }}</span></label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label for="business_name">Business name</label>
                <input type="text" id="business_name" name="business_name" maxlength="80" value="{{ old('business_name') }}" required>
            </div>

            <div class="field">
                <label for="prompt">Describe the promotion <span class="hint">— the prompt. What are you offering, and to whom?</span></label>
                <textarea id="prompt" name="prompt" maxlength="1500" required placeholder="e.g. We're a dental practice on Central Park South giving away a professional teeth-whitening kit to new patients in NYC.">{{ old('prompt') }}</textarea>
            </div>

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

            <details class="adv">
                <summary>More options</summary>
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
            </details>

            <div class="row">
                <div class="field">
                    <label for="owner_name">Your name</label>
                    <input type="text" id="owner_name" name="owner_name" maxlength="80" value="{{ old('owner_name') }}">
                </div>
                <div class="field">
                    <label for="owner_email">Your email <span class="hint">— where your manage link goes</span></label>
                    <input type="email" id="owner_email" name="owner_email" maxlength="120" value="{{ old('owner_email') }}" required>
                </div>
            </div>

            <button type="submit" class="btn">Generate my site</button>
        </form>

        <div class="card">
            <p class="muted" style="margin-top:0">Start free. Upgrade when you need a custom domain, no branding, analytics or more entries.</p>
            <div class="plans">
                @foreach ($plans as $key => $plan)
                    <div class="plan">
                        <b>{{ $plan['name'] }}</b>
                        <span class="price">{{ $plan['price_label'] }}</span>
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
            const sweeps = document.getElementById('sweeps');
            const event = document.getElementById('event');
            const toggle = () => {
                const type = document.querySelector('input[name=type]:checked')?.value;
                sweeps.hidden = type !== 'sweepstakes';
                event.hidden = type !== 'event';
            };
            document.querySelectorAll('input[name=type]').forEach((r) => r.addEventListener('change', toggle));
            toggle();

            const picker = document.getElementById('accent_picker');
            const hex = document.getElementById('accent_color');
            picker.addEventListener('input', () => { hex.value = picker.value; });
            hex.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) { picker.value = hex.value; } });
        })();
    </script>
</body>
</html>
