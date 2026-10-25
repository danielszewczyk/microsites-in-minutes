<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage — {{ $inputs['business_name'] ?? 'your site' }}</title>
    <style>
        :root { --accent: {{ $site->accent() }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; }
        .wrap { max-width: 900px; margin: 0 auto; padding: 0 20px 80px; }
        header { padding: 32px 0; border-bottom: 1px solid var(--line); }
        h1 { margin: 0 0 4px; font-size: 1.5rem; }
        a { color: var(--accent); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 24px; margin: 22px 0; }
        .ok { background: color-mix(in srgb, var(--accent) 14%, var(--paper)); border: 1px solid var(--accent); }
        .share { font-family: ui-monospace, monospace; background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; word-break: break-all; font-size: 0.85rem; }
        .stats { display: flex; gap: 28px; flex-wrap: wrap; }
        .stat b { display: block; font-size: 2rem; }
        .stat span { color: var(--muted); font-size: 0.9rem; }
        .bars { display: flex; align-items: flex-end; gap: 3px; height: 60px; margin-top: 14px; }
        .bars i { flex: 1; background: var(--accent); min-height: 2px; border-radius: 2px 2px 0 0; opacity: 0.85; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 600; }
        label { font-weight: 600; display: block; margin: 0 0 6px; }
        input, textarea, select { font: inherit; width: 100%; padding: 11px 13px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        textarea { min-height: 100px; }
        .field { margin: 0 0 18px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 620px) { .row { grid-template-columns: 1fr; } }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 11px; padding: 13px 24px; font-weight: 600; cursor: pointer; }
        .muted { color: var(--muted); }
        fieldset { border: 1px dashed var(--line); border-radius: 12px; padding: 18px; }
        legend { font-weight: 700; }
        .err { background: #fee2e2; color: #991b1b; padding: 8px 12px; border-radius: 8px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <header>
        <div class="wrap" style="padding-bottom:0">
            <h1>{{ $inputs['business_name'] ?? 'Your site' }}</h1>
            <p class="muted" style="margin:0">
                <a href="{{ $site->url() }}" target="_blank" rel="noopener">View live site &nearr;</a>
                · {{ ucfirst($site->type) }} · {{ $plan['name'] }} plan
            </p>
        </div>
    </header>

    <div class="wrap">
        @if (session('created'))
            <div class="card ok">
                <p style="margin-top:0"><strong>Your site is live.</strong></p>
                <p class="muted">Live link (share this):</p>
                <p class="share">{{ session('created')['url'] }}</p>
                <p class="muted" style="margin-top:14px">Manage link (bookmark this to edit later — keep it private):</p>
                <p class="share">{{ session('created')['manage_url'] }}</p>
            </div>
        @endif

        @if (session('status'))
            <div class="card ok"><p style="margin:0">{{ session('status') }}</p></div>
        @endif

        <div class="card">
            <div class="stats">
                <div class="stat"><b>{{ number_format($site->view_count) }}</b><span>views</span></div>
                <div class="stat"><b>{{ number_format($site->entry_count) }}</b><span>entries</span></div>
                <div class="stat"><b>{{ $site->view_count > 0 ? number_format($site->entry_count / $site->view_count * 100, 1) : '0.0' }}%</b><span>conversion</span></div>
            </div>
            @php($peak = max(1, collect($stats)->max('views')))
            <div class="bars" title="Views per day, last 30 days">
                @foreach ($stats as $day)
                    <i style="height: {{ max(2, (int) round($day['views'] / $peak * 60)) }}px" title="{{ $day['date'] }}: {{ $day['views'] }} views, {{ $day['entries'] }} entries"></i>
                @endforeach
            </div>
            <p class="muted" style="font-size:0.82rem;margin:8px 0 0">Views per day, last 30 days (built-in analytics)</p>
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Upload from your phone</h2>
            <p class="muted">Scan this with your phone to upload banners (desktop + mobile), a copy deck and a rules document straight from your camera roll. The QR carries your private key — don't share it.</p>
            <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:center">
                <div style="width:170px">{!! $uploadQr !!}</div>
                <div style="flex:1;min-width:220px">
                    <p style="margin:0 0 10px"><a href="{{ route('microsite.upload', $site->slug) }}">Open the upload page here &nearr;</a></p>
                    <ul style="margin:0;padding-left:18px" class="muted">
                        @foreach (\App\Models\SiteAsset::SLOTS as $slot => $spec)
                            <li>{{ $spec['label'] }}:
                                @if (isset($assets[$slot]))
                                    <a href="{{ route('microsite.asset', [$site->slug, $slot]) }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($assets[$slot]->original_name, 30) }}</a>
                                @else
                                    <span>not uploaded</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <h2 style="margin:0;font-size:1.2rem">Entries ({{ number_format($site->entry_count) }})</h2>
                @if ($entries->isNotEmpty())
                    <a href="{{ route('microsite.entries.export', $site->slug) }}">Export CSV</a>
                @endif
            </div>
            @if ($entries->isEmpty())
                <p class="muted">No entries yet. Share your link to start collecting.</p>
            @else
                <table>
                    <thead><tr><th>When</th><th>Name</th><th>Email</th><th>Phone</th></tr></thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="muted">{{ $entry->created_at?->diffForHumans() }}</td>
                                <td>{{ $entry->name }}</td>
                                <td>{{ $entry->email }}</td>
                                <td>{{ $entry->phone }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Edit &amp; regenerate</h2>
            <p class="muted">Change the prompt or any detail and regenerate. Your live page updates in place within seconds.</p>

            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    <div class="err">{{ $error }}</div>
                @endforeach
            @endif

            <form method="post" action="{{ route('microsite.regenerate', $site->slug) }}">
                @csrf
                <input type="hidden" name="type" value="{{ $inputs['type'] ?? 'standard' }}">

                <div class="field">
                    <label for="business_name">Business name</label>
                    <input type="text" id="business_name" name="business_name" maxlength="80" value="{{ old('business_name', $inputs['business_name'] ?? '') }}" required>
                </div>
                <div class="field">
                    <label for="prompt">Describe the promotion (the prompt)</label>
                    <textarea id="prompt" name="prompt" maxlength="1500" required>{{ old('prompt', $inputs['prompt'] ?? '') }}</textarea>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="accent_color">Accent color</label>
                        <input type="text" id="accent_color" name="accent_color" maxlength="7" value="{{ old('accent_color', $inputs['accent_color'] ?? '#0f766e') }}">
                    </div>
                    <div class="field">
                        <label for="hero_image">Hero picture</label>
                        <select id="hero_image" name="hero_image">
                            <option value="auto">Pick one for me</option>
                            <option value="none" {{ ($inputs['hero_image'] ?? '') === 'none' ? 'selected' : '' }}>No picture</option>
                            @foreach ($illustrations as $art)
                                <option value="{{ $art }}" {{ ($inputs['hero_image'] ?? '') === $art ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $art)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if (($inputs['type'] ?? '') === 'sweepstakes')
                    <fieldset>
                        <legend>Giveaway details</legend>
                        <div class="field">
                            <label for="prize">Prize</label>
                            <input type="text" id="prize" name="prize" maxlength="300" value="{{ old('prize', $inputs['prize'] ?? '') }}">
                        </div>
                        <div class="row">
                            <div class="field"><label for="prize_value">Approx. value</label><input type="text" id="prize_value" name="prize_value" maxlength="60" value="{{ old('prize_value', $inputs['prize_value'] ?? '') }}"></div>
                            <div class="field"><label for="sponsor">Sponsor</label><input type="text" id="sponsor" name="sponsor" maxlength="160" value="{{ old('sponsor', $inputs['sponsor'] ?? '') }}"></div>
                        </div>
                        <div class="row">
                            <div class="field"><label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" value="{{ old('start_date', $inputs['start_date'] ?? '') }}"></div>
                            <div class="field"><label for="end_date">End date</label><input type="date" id="end_date" name="end_date" value="{{ old('end_date', $inputs['end_date'] ?? '') }}"></div>
                        </div>
                        <div class="field"><label for="eligibility">Eligibility</label><input type="text" id="eligibility" name="eligibility" maxlength="400" value="{{ old('eligibility', $inputs['eligibility'] ?? '') }}"></div>
                        <div class="field"><label for="amoe">Free alternate method of entry</label><textarea id="amoe" name="amoe" maxlength="800">{{ old('amoe', $inputs['amoe'] ?? '') }}</textarea></div>
                    </fieldset>
                @endif

                <div class="row" style="margin-top:18px">
                    <div class="field">
                        <label for="ga4_id">Google Analytics 4 ID {{ $plan['analytics'] ? '' : '(Pro plan)' }}</label>
                        <input type="text" id="ga4_id" name="ga4_id" maxlength="40" value="{{ old('ga4_id', $inputs['ga4_id'] ?? '') }}" placeholder="G-XXXXXXXXXX" {{ $plan['analytics'] ? '' : 'disabled' }}>
                    </div>
                    <div class="field">
                        <label>Branding</label>
                        <label class="muted" style="font-weight:400">
                            <input type="checkbox" name="remove_branding" value="1" style="width:auto" {{ ($inputs['remove_branding'] ?? false) ? 'checked' : '' }} {{ $plan['remove_branding'] ? '' : 'disabled' }}>
                            Remove "Made with Microsites in Minutes" {{ $plan['remove_branding'] ? '' : '(Pro plan)' }}
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn">Save &amp; regenerate</button>
            </form>
        </div>
    </div>
</body>
</html>
