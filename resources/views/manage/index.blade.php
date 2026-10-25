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
                <p style="margin-top:0"><strong>Your site is live.</strong>
                    @isset(session('created')['build_ms'])
                        <span class="muted">Built in {{ number_format(session('created')['build_ms'] / 1000, 1) }}s — ${{ number_format(session('created')['build_cents'] / 100, 2) }} (first build minute free).</span>
                    @endisset
                </p>
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
            <h2 style="margin-top:0;font-size:1.2rem">Custom domain</h2>
            @if (! $plan['custom_domain'])
                <p class="muted">Put this site on your own domain (e.g. submityoursmile.com) for <strong>$25/yr</strong> — available on paid plans.</p>
            @elseif ($domain)
                <p>Your domain: <strong>{{ $domain->domain }}</strong>
                    @if ($domain->isActive())
                        <span style="color:var(--accent)">· live</span> — <a href="https://{{ $domain->domain }}" target="_blank" rel="noopener">visit &nearr;</a>
                    @else
                        <span style="color:#b45309">· pending DNS</span>
                    @endif
                </p>
                @unless ($domain->isActive())
                    <p class="muted">Point it at us: add a <strong>CNAME</strong> from <code>{{ $domain->domain }}</code> to <code>{{ config('domains.cname_target') }}</code>, then activate.</p>
                    <form method="post" action="{{ route('microsite.domain.activate', $site->slug) }}" style="display:inline">@csrf<button class="btn" type="submit">Activate</button></form>
                @endunless
                <form method="post" action="{{ route('microsite.domain.remove', $site->slug) }}" style="display:inline;margin-left:8px">@csrf<button class="rm" type="submit">Remove</button></form>
            @else
                <p class="muted">Put this site on your own vanity domain for <strong>$25/yr</strong> (DJA charges $250). Search for one:</p>
                <form method="post" action="{{ route('microsite.domain.search', $site->slug) }}" style="display:flex;gap:8px;max-width:460px">
                    @csrf
                    <input type="text" name="domain" placeholder="submityoursmile.com" value="{{ old('domain') }}" style="flex:1">
                    <button class="btn" type="submit">Search</button>
                </form>
                @if (session('domain_search'))
                    @php($ds = session('domain_search'))
                    <div style="margin-top:12px">
                        @if ($ds['available'])
                            <p><strong>{{ $ds['domain'] }}</strong> is available — ${{ number_format($ds['price_cents'] / 100, 2) }}/yr.</p>
                            <form method="post" action="{{ route('microsite.domain.buy', $site->slug) }}">
                                @csrf
                                <input type="hidden" name="domain" value="{{ $ds['domain'] }}">
                                <button class="btn" type="submit">Buy {{ $ds['domain'] }} — ${{ number_format($ds['price_cents'] / 100, 2) }}/yr</button>
                            </form>
                            @if (config('domains.stub'))<p class="muted" style="font-size:0.8rem;margin-top:8px">Demo: no domain is registered and nothing is charged yet.</p>@endif
                        @else
                            <p class="muted">{{ $ds['domain'] }} is already taken. Try another.</p>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Where it's coming from</h2>
            <p class="muted" style="margin-top:0">Views, entries and conversion by source — counted on our side, no cookies or consent banner.</p>
            @php($reportTable = function ($label, $rows) {
                if (empty($rows)) {
                    return '<p class="muted">No '.$label.' data yet.</p>';
                }
                $html = '<table><thead><tr><th>'.$label.'</th><th>Views</th><th>Entries</th><th>Conv.</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $html .= '<tr><td>'.e($r['key']).'</td><td>'.number_format($r['views']).'</td><td>'.number_format($r['entries']).'</td><td>'.number_format($r['conv'], 1).'%</td></tr>';
                }
                return $html.'</tbody></table>';
            })
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px">
                <div>{!! $reportTable('Source', $breakdowns['source']) !!}</div>
                <div>{!! $reportTable('Device', $breakdowns['device']) !!}</div>
            </div>
            @if (!empty($breakdowns['campaign']))
                <div style="margin-top:18px">{!! $reportTable('Campaign', $breakdowns['campaign']) !!}</div>
            @endif
            <p class="muted" style="font-size:0.8rem;margin:12px 0 0">Tag your links with <code>?utm_source=…&amp;utm_campaign=…</code> to see each channel here. Export includes the source per entry.</p>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px">
                <h2 style="margin:0;font-size:1.2rem">Plan &amp; usage</h2>
                <span class="muted">{{ $plan['name'] }} · balance <strong style="color:var(--accent)">${{ number_format($usage['balance_cents'] / 100, 2) }}</strong></span>
            </div>
            @php($mb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format(max(0, $b) / 1024, 0).' KB')
            @php($meter = function ($label, $pct, $detail, $over) {
                $color = $over ? '#b45309' : 'var(--accent)';
                return '<div style="margin:12px 0"><div style="display:flex;justify-content:space-between;font-size:0.88rem"><span>'.$label.'</span><span class="muted">'.$detail.'</span></div>'
                    .'<div style="height:8px;background:var(--line);border-radius:4px;margin-top:5px;overflow:hidden"><i style="display:block;height:100%;width:'.$pct.'%;background:'.$color.'"></i></div></div>';
            })
            {!! $meter(
                'Build minutes',
                $usage['build']['pct'],
                $usage['build']['over']
                    ? 'free minute used — now metered'
                    : number_format($usage['build']['free_remaining_ms'] / 1000, 1).'s of free build time left',
                $usage['build']['over'],
            ) !!}
            {!! $meter(
                'Traffic (views)',
                $usage['traffic']['pct'],
                number_format($usage['traffic']['used']).' / '.number_format($usage['traffic']['allowance']).($usage['traffic']['over'] ? ' · over' : ''),
                $usage['traffic']['over'],
            ) !!}
            {!! $meter(
                'Storage',
                $usage['storage']['pct'],
                $mb($usage['storage']['used_bytes']).' / '.number_format($usage['storage']['allowance_bytes'] / 1048576).' MB'.($usage['storage']['over'] ? ' · over' : ''),
                $usage['storage']['over'],
            ) !!}
            <p class="muted" style="font-size:0.8rem;margin:10px 0 0">First build minute is free, then $0.50/min. Traffic and storage over the plan's allowance are billed per the plan. Billing is not live yet.</p>
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

                @if (($inputs['type'] ?? '') === 'event')
                    <fieldset>
                        <legend>Event details</legend>
                        <div class="row">
                            <div class="field"><label for="event_date">Date</label><input type="date" id="event_date" name="event_date" value="{{ old('event_date', $inputs['event_date'] ?? '') }}"></div>
                            <div class="field"><label for="event_time">Time</label><input type="text" id="event_time" name="event_time" maxlength="60" value="{{ old('event_time', $inputs['event_time'] ?? '') }}"></div>
                        </div>
                        <div class="field"><label for="venue">Venue</label><input type="text" id="venue" name="venue" maxlength="160" value="{{ old('venue', $inputs['venue'] ?? '') }}"></div>
                        <div class="field"><label for="address">Address</label><input type="text" id="address" name="address" maxlength="200" value="{{ old('address', $inputs['address'] ?? '') }}"></div>
                    </fieldset>
                @endif

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
