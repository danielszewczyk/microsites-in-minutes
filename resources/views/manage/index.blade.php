<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage — {{ $inputs['business_name'] ?? 'your site' }}</title>
    <style>
        :root { --accent: {{ $site->accent() }}; --entries: #f59e0b; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --entries: #fbbf24; --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
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
        .stat > span { color: var(--muted); font-size: 0.9rem; }
        .live-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #16a34a; margin-right: 8px; vertical-align: baseline; animation: livepulse 1.6s ease-in-out infinite; }
        @keyframes livepulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .bars { display: flex; align-items: flex-end; gap: 3px; height: 60px; }
        /* Each bucket holds two bars: views (accent) and entries (amber), each
           scaled to its own peak so the smaller entries trend stays visible. */
        /* One bar per bucket, height = views; the amber segment is entries as a
           share of that bar, so its fill visualises the conversion rate. */
        .bars .bar { flex: 1; position: relative; min-width: 0; min-height: 2px; }
        .bars .bar .v { position: absolute; inset: 0; background: var(--accent); border-radius: 2px 2px 0 0; opacity: 0.9; }
        .bars .bar .e { position: absolute; left: 0; right: 0; bottom: 0; background: var(--entries); border-radius: 0 0 2px 2px; }
        .chart-legend { display: flex; gap: 16px; margin: 10px 0 0; font-size: 0.8rem; color: var(--muted); }
        .chart-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .chart-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
        /* Positioned wrapper so the hover tooltip (a sibling of .bars that
           survives the live re-render) anchors to each bucket. */
        .chart-wrap { position: relative; }
        .chart-tip { position: absolute; z-index: 5; pointer-events: none; transform: translate(-50%, -100%); background: var(--ink); color: var(--paper); font-size: 0.72rem; line-height: 1.4; padding: 6px 9px; border-radius: 7px; white-space: nowrap; opacity: 0; transition: opacity 0.1s ease; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18); }
        .chart-tip.show { opacity: 1; }
        .chart-tip .tl { font-weight: 600; margin-bottom: 3px; }
        .chart-tip .sw { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 5px; vertical-align: middle; }
        /* Caption left, range controls bottom-right. */
        .chart-foot { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px 16px; margin-top: 12px; }
        .chart-foot .cap { font-size: 0.82rem; }
        .chart-foot .ranges { margin: 0 0 0 auto; }
        /* Chart plot: left y-axis (views), bars, right y-axis (entries), x-axis. */
        .chart-plot { display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 8px; align-items: end; margin-top: 14px; }
        .chart-plot .chart-wrap { grid-row: 1; grid-column: 2; }
        .chart-plot .y-axis { grid-row: 1; grid-column: 1; }
        .chart-plot .x-axis { grid-row: 2; grid-column: 2; }
        .y-axis { display: flex; flex-direction: column; justify-content: space-between; height: 60px; font-size: 0.68rem; font-variant-numeric: tabular-nums; line-height: 1; text-align: right; color: var(--accent); }
        .x-axis { display: flex; justify-content: space-between; font-size: 0.68rem; color: var(--muted); margin-top: 5px; }
        /* Legend click toggles a series layer on/off. */
        .chart-plot.hide-v .bar .v { display: none; }
        .chart-plot.hide-e .bar .e { display: none; }
        .chart-plot.hide-v .y-axis { opacity: 0.3; }
        .chart-legend span { cursor: pointer; user-select: none; }
        .chart-legend span.off { opacity: 0.4; text-decoration: line-through; }
        .ranges { display: flex; gap: 6px; flex-wrap: wrap; margin: 4px 0 2px; }
        .ranges .range { font-size: 0.8rem; padding: 4px 11px; border: 1px solid rgba(0, 0, 0, 0.12); background: transparent; color: var(--muted); border-radius: 999px; cursor: pointer; }
        .ranges .range.is-active { background: var(--accent); border-color: var(--accent); color: #fff; }
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
        /* Destructive actions (delete an alarm, a link; remove a domain). */
        .rm { background: transparent; color: #b91c1c; border: 1px solid color-mix(in srgb, #b91c1c 35%, var(--line)); border-radius: 9px; padding: 8px 14px; font: inherit; font-weight: 600; font-size: 0.85rem; line-height: 1; cursor: pointer; }
        .rm:hover { background: #fee2e2; border-color: #b91c1c; }
        @media (prefers-color-scheme: dark) { .rm:hover { background: color-mix(in srgb, #b91c1c 28%, var(--card)); color: #fecaca; } }
        /* Add-alarm row: labels above compact controls, all the same height. */
        .alarm-add { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-top: 14px; }
        .alarm-add .fld { display: flex; flex-direction: column; gap: 5px; }
        .alarm-add label { font-size: 0.8rem; font-weight: 600; margin: 0; }
        .alarm-add select, .alarm-add input { padding: 10px 12px; height: 42px; }
        .alarm-add select { width: auto; }
        .alarm-add input[type=number] { width: 130px; }
        .alarm-add .btn { padding: 0 22px; height: 42px; }
        .alarm-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px solid var(--line); padding: 10px 0; }
        .alarm-row:first-of-type { border-top: 1px solid var(--line); }
        /* Inline copy button next to the live URL in the header. */
        .url-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 8px 0 0; }
        .url-bar code { font-family: ui-monospace, monospace; font-size: 0.85rem; background: var(--paper); border: 1px solid var(--line); border-radius: 8px; padding: 6px 10px; word-break: break-all; }
        .copy-inline { background: transparent; color: var(--accent); border: 1px solid var(--accent); border-radius: 8px; padding: 6px 12px; font: inherit; font-size: 0.82rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
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
            <div class="url-bar">
                <code id="site-url">{{ $site->url() }}</code>
                <button type="button" class="copy-inline" id="url-copy" data-copy="{{ $site->url() }}">Copy URL</button>
            </div>
        </div>
    </header>

    <div class="wrap">
        @if (session('created'))
            <div class="card ok">
                <p style="margin-top:0"><strong>Your site is live.</strong>
                    @isset(session('created')['build_ms'])
                        <span class="muted">Built in {{ number_format(session('created')['build_ms'] / 1000, 1) }}s @if ($launchFree)— free (launch promotion).@else— ${{ number_format(session('created')['build_cents'] / 100, 2) }} (first build minute free).@endif</span>
                    @endisset
                </p>
                <p class="muted">Live link (share this):</p>
                <p class="share" id="created-url">{{ session('created')['url'] }}</p>
                <button type="button" class="btn" id="created-copy" data-copy="{{ session('created')['url'] }}" style="width:auto;display:inline-block;margin-top:10px;padding:11px 18px">📋&nbsp; Copy link</button>
                <p class="muted" style="margin-top:14px">Manage link (bookmark this to edit later — keep it private):</p>
                <p class="share">{{ session('created')['manage_url'] }}</p>
            </div>
        @endif

        @if (session('status'))
            <div class="card ok"><p style="margin:0">{{ session('status') }}</p></div>
        @endif

        @if (session('error'))
            <div class="card" style="border-color:#b91c1c"><p style="margin:0">{{ session('error') }}</p></div>
        @endif

        @if (config('plans.paywall'))
            <div class="card">
                <h2 style="margin-top:0;font-size:1.2rem">Keep it live</h2>
                @if ($site->isPaid())
                    <p class="muted" style="margin-top:0">Paid through {{ $site->paid_through->format('M j, Y g:ia') }}.</p>
                @elseif ($site->isLapsed())
                    <p class="muted" style="margin-top:0">Paused. Your time ran out on {{ $site->paid_through->format('M j, Y') }}. Pay to reopen entries.</p>
                @else
                    <p class="muted" style="margin-top:0">Pay $1 for a day to publish. Apple Pay works on iPhone and Mac.</p>
                @endif
                <form method="post" action="{{ route('microsite.billing.checkout', [$site->slug, 'day']) }}" style="display:inline">@csrf<button class="btn" type="submit">Pay $1 for a day</button></form>
                @if ($site->owner?->stripe_payment_method_id)
                    <form method="post" action="{{ route('microsite.billing.extend', $site->slug) }}" style="display:inline">@csrf<button class="btn" type="submit">Extend 1 day ($1, saved card)</button></form>
                @endif
                <form method="post" action="{{ route('microsite.billing.checkout', [$site->slug, 'monthly']) }}" style="display:inline">@csrf<button class="btn" type="submit">Subscribe $25/month</button></form>
            </div>
        @endif

        @if ($site->isDraft())
            <div class="card" style="border-color:#b45309">
                <h2 style="margin-top:0;font-size:1.2rem">This site is a draft</h2>
                <p class="muted" style="margin-top:0">It's not indexed and isn't accepting entries. Review it, then publish when you're ready.</p>
                <form method="post" action="{{ route('microsite.publish', $site->slug) }}">@csrf<button class="btn" type="submit">Publish &amp; go live</button></form>
            </div>
        @endif

        <div class="card">
            <div class="stats">
                <div class="stat"><b id="stat-views">{{ number_format($site->view_count) }}</b><span>views</span></div>
                <div class="stat"><b><span class="live-dot"></span><span id="stat-live">{{ $live }}</span></b><span>viewing now</span></div>
                <div class="stat"><b id="stat-entries">{{ number_format($site->entry_count) }}</b><span>entries</span></div>
                <div class="stat"><b id="stat-conv">{{ $site->view_count > 0 ? number_format($site->entry_count / $site->view_count * 100, 1) : '0.0' }}%</b><span>conversion</span></div>
                @php($avg = $site->avgDwellSeconds())
                <div class="stat"><b id="stat-dwell">{{ $avg > 0 ? sprintf('%d:%02d', intdiv($avg, 60), $avg % 60) : '—' }}</b><span>avg. time on page</span></div>
            </div>
            @php($viewMax = (int) collect($stats)->max('views'))
            @php($peak = max(1, $viewMax))
            @php($n = count($stats))
            @php($xTicks = $n ? collect([0, 0.25, 0.5, 0.75, 1])->map(fn ($f) => (int) round($f * ($n - 1)))->unique()->map(fn ($i) => $stats[$i]['label'])->values() : collect())
            <div class="chart-plot" id="stat-plot">
                <div class="y-axis"><span id="y-views-peak">{{ number_format($viewMax) }}</span><span>0</span></div>
                <div class="chart-wrap">
                    <div class="bars" id="stat-bars" data-url="{{ route('microsite.stats', $site->slug) }}" data-range="{{ $statRange }}">
                        @foreach ($stats as $point)
                            @php($bh = max(2, (int) round($point['views'] / $peak * 60)))
                            @php($es = $point['views'] > 0 ? min($bh, max($point['entries'] > 0 ? 2 : 0, (int) round($bh * $point['entries'] / $point['views']))) : 0)
                            @php($conv = $point['views'] > 0 ? round($point['entries'] / $point['views'] * 100, 1) : 0)
                            <span class="bar" style="height: {{ $bh }}px" aria-label="{{ $point['label'] }}: {{ $point['views'] }} views, {{ $point['entries'] }} entries ({{ $conv }}%)" data-label="{{ $point['label'] }}" data-views="{{ $point['views'] }}" data-entries="{{ $point['entries'] }}" data-conv="{{ $conv }}">
                                <i class="v"></i>
                                <i class="e" style="height: {{ $es }}px"></i>
                            </span>
                        @endforeach
                    </div>
                    <div class="chart-tip" id="stat-tip"></div>
                </div>
                <div class="x-axis" id="stat-xaxis">@foreach ($xTicks as $t)<span>{{ $t }}</span>@endforeach</div>
            </div>
            <div class="chart-legend" id="stat-legend">
                <span data-series="v" role="button" tabindex="0" aria-pressed="true"><i style="background: var(--accent)"></i>Views</span>
                <span data-series="e" role="button" tabindex="0" aria-pressed="true"><i style="background: var(--entries)"></i>Entries</span>
            </div>
            <div class="chart-foot">
                <span class="muted cap"><span id="stat-caption">{{ $statCaption }}</span> (built-in analytics) · updates live</span>
                <div class="ranges" id="stat-ranges">
                    @foreach (['hour' => 'Hour', 'day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'all' => 'All'] as $key => $label)
                        <button type="button" class="range{{ $key === $statRange ? ' is-active' : '' }}" data-range="{{ $key }}" aria-pressed="{{ $key === $statRange ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Custom domain</h2>
            @if (! $plan['custom_domain'])
                <p class="muted">Put this site on your own domain (e.g. submityoursmile.com) for <strong>$25/yr</strong> — domain registration included. Available on paid plans.</p>
            @elseif ($domain)
                @if ($domain->isActive())
                    <p>Your domain: <strong>{{ $domain->domain }}</strong> <span style="color:var(--accent)">· live</span> — <a href="https://{{ $domain->domain }}" target="_blank" rel="noopener">visit &nearr;</a></p>
                @elseif ($domain->isRegistering())
                    <p><strong>{{ $domain->domain }}</strong> <span style="color:#b45309">· setting up…</span></p>
                    <p class="muted">We're registering it and issuing SSL — no DNS for you to configure. This usually takes about a minute; the page will update on its own.</p>
                    <div id="dom-poll" data-url="{{ route('microsite.domain.status', $site->slug) }}"></div>
                @else
                    <p><strong>{{ $domain->domain }}</strong> <span style="color:#b91c1c">· setup failed</span></p>
                    <form method="post" action="{{ route('microsite.domain.retry', $site->slug) }}" style="display:inline">@csrf<button class="btn" type="submit">Retry setup</button></form>
                @endif
                <form method="post" action="{{ route('microsite.domain.remove', $site->slug) }}" style="display:inline;margin-left:8px">@csrf<button class="rm" type="submit">Remove</button></form>
            @else
                <p class="muted">Put this site on your own vanity domain for <strong>$25/yr</strong> — domain registration included (DJA charges $250). Search for one:</p>
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
                            <p class="muted" style="font-size:0.8rem;margin-top:8px">We register it and point it at your site automatically — no DNS to configure.@if (config('domains.stub')) <em>(Demo: nothing is registered or charged.)</em>@endif</p>
                        @else
                            <p class="muted">{{ $ds['domain'] }} is already taken. Try another.</p>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Social preview</h2>
            <p class="muted" style="margin-top:0">How your link looks when shared. Upload a desktop banner for a custom image, or we draw one from your brand.</p>
            <style>
                .sp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; }
                .sp h4 { margin:0 0 8px; font-size:0.85rem; color:var(--muted); }
                .sp img { display:block; width:100%; aspect-ratio:1200/630; object-fit:cover; background:var(--line); }
                .sp-imsg { border-radius:16px; overflow:hidden; border:1px solid var(--line); }
                .sp-imsg .m { background:var(--paper); padding:8px 12px; font-size:0.8rem; }
                .sp-imsg .m b { display:block; }
                .sp-fb { border:1px solid var(--line); border-radius:4px; overflow:hidden; }
                .sp-fb .m { background:#f0f2f5; color:#1c1e21; padding:10px 12px; }
                .sp-fb .host { text-transform:uppercase; color:#606770; font-size:0.72rem; }
                .sp-fb .t { font-weight:700; font-size:0.95rem; margin:2px 0; }
                .sp-fb .d { color:#606770; font-size:0.82rem; }
                .sp-tw { border:1px solid var(--line); border-radius:16px; overflow:hidden; }
                .sp-tw .m { padding:10px 12px; }
                .sp-tw .t { font-weight:600; font-size:0.9rem; }
                .sp-tw .d { color:var(--muted); font-size:0.82rem; margin-top:2px; }
                .sp-tw .host { color:var(--muted); font-size:0.78rem; margin-top:4px; }
            </style>
            <div class="sp-grid">
                <div class="sp">
                    <h4>iMessage</h4>
                    <div class="sp-imsg">
                        <img src="{{ $og['image'] }}" alt="">
                        <div class="m"><b>{{ \Illuminate\Support\Str::limit($og['title'], 50) }}</b>{{ $og['host'] }}</div>
                    </div>
                </div>
                <div class="sp">
                    <h4>Facebook</h4>
                    <div class="sp-fb">
                        <img src="{{ $og['image'] }}" alt="">
                        <div class="m">
                            <div class="host">{{ $og['host'] }}</div>
                            <div class="t">{{ \Illuminate\Support\Str::limit($og['title'], 60) }}</div>
                            <div class="d">{{ \Illuminate\Support\Str::limit($og['description'], 70) }}</div>
                        </div>
                    </div>
                </div>
                <div class="sp">
                    <h4>Twitter / X</h4>
                    <div class="sp-tw">
                        <img src="{{ $og['image'] }}" alt="">
                        <div class="m">
                            <div class="t">{{ \Illuminate\Support\Str::limit($og['title'], 60) }}</div>
                            <div class="d">{{ \Illuminate\Support\Str::limit($og['description'], 70) }}</div>
                            <div class="host">🔗 {{ $og['host'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Google search preview</h2>
            <p class="muted" style="margin-top:0">How your page title and description show in search. Edit them in "Edit &amp; regenerate" below.</p>
            <div style="border:1px solid var(--line);border-radius:10px;padding:16px 18px;max-width:600px;background:#fff">
                <div style="display:flex;align-items:center;gap:10px">
                    {{-- Google shows the site's favicon; we fall back to the site's
                         generated default mark when none is uploaded (#17). --}}
                    <span style="display:flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid #dadce0;border-radius:50%;overflow:hidden;background:#fff;flex:none">
                        <img src="{{ route('microsite.favicon', $site->slug) }}" alt="" width="18" height="18" style="display:block">
                    </span>
                    <div style="line-height:1.3">
                        <div style="color:#202124;font-size:0.82rem">{{ $inputs['business_name'] ?? $og['host'] }}</div>
                        <div style="color:#5f6368;font-size:0.78rem">{{ $og['host'] }} › {{ $site->slug }}</div>
                    </div>
                </div>
                <div style="color:#1a0dab;font-size:1.25rem;line-height:1.3;margin:6px 0 2px">{{ \Illuminate\Support\Str::limit($og['title'], 60) }}</div>
                <div style="color:#4d5156;font-size:0.88rem;line-height:1.4">{{ \Illuminate\Support\Str::limit($og['description'], 160) ?: 'Add a meta description to control this text.' }}</div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Share links &amp; UTM builder</h2>
            <p class="muted" style="margin-top:0">Tag a link so you can see which channel it came from in the report, and get a short link to share.</p>

            <div class="field" style="margin-bottom:10px">
                <label>Tagged link preview</label>
                <input type="text" id="utm-preview" readonly value="{{ $site->url() }}" style="font-family:ui-monospace,monospace;font-size:0.82rem">
                <button type="button" class="btn" id="utm-copy" style="margin-top:8px;padding:9px 16px">Copy</button>
            </div>

            <form method="post" action="{{ route('microsite.links.store', $site->slug) }}">
                @csrf
                <div class="row">
                    <div class="field"><label for="l-source">Source</label><input type="text" id="l-source" name="source" maxlength="40" placeholder="facebook"></div>
                    <div class="field"><label for="l-medium">Medium</label><input type="text" id="l-medium" name="medium" maxlength="40" placeholder="social"></div>
                </div>
                <div class="row">
                    <div class="field"><label for="l-campaign">Campaign</label><input type="text" id="l-campaign" name="campaign" maxlength="60" placeholder="fall-launch"></div>
                    <div class="field"><label for="l-label">Label (optional)</label><input type="text" id="l-label" name="label" maxlength="60" placeholder="October FB post"></div>
                </div>
                <button type="submit" class="btn">Create short link</button>
            </form>

            @if ($links->isNotEmpty())
                <table style="margin-top:18px">
                    <thead><tr><th>Short link</th><th>Tags</th><th>Clicks</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($links as $link)
                            <tr>
                                <td><a href="{{ $link->shortUrl() }}" target="_blank" rel="noopener">{{ $link->shortUrl() }}</a>@if ($link->label)<br><span class="muted" style="font-size:0.8rem">{{ $link->label }}</span>@endif</td>
                                <td class="muted" style="font-size:0.82rem">{{ collect([$link->source, $link->medium, $link->campaign])->filter()->implode(' · ') ?: '—' }}</td>
                                <td>{{ number_format($link->clicks) }}</td>
                                <td style="white-space:nowrap">
                                    <details style="display:inline-block">
                                        <summary style="cursor:pointer;color:var(--accent)">QR</summary>
                                        <div style="width:120px;margin-top:6px">{!! \App\Support\QrCode::svg($link->shortUrl()) !!}</div>
                                    </details>
                                    <form method="post" action="{{ route('microsite.links.destroy', [$site->slug, $link]) }}" style="display:inline">@csrf<button class="rm" type="submit">Delete</button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Where it's coming from</h2>
            <p class="muted" style="margin-top:0">Views, entries and conversion by source — counted on our side, no cookies or consent banner.</p>
            @php($reportTable = function ($label, $rows) {
                $html = '<table><thead><tr><th>'.$label.'</th><th>Views</th><th>Entries</th><th>Conv.</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $html .= '<tr><td>'.e($r['key']).'</td><td>'.number_format($r['views']).'</td><td>'.number_format($r['entries']).'</td><td>'.number_format($r['conv'], 1).'%</td></tr>';
                }
                return $html.'</tbody></table>';
            })
            @php($hasSourceData = ! empty($breakdowns['source']) || ! empty($breakdowns['device']) || ! empty($breakdowns['campaign']))
            @if (! $hasSourceData)
                {{-- One empty state for the whole section, not one per table. --}}
                <p class="muted" style="margin:0">No traffic yet. Once people visit, you'll see where they came from — source, device and campaign — right here. Tag your links with the UTM builder above to label each channel.</p>
            @else
                <div style="display:grid;grid-template-columns:1fr;gap:22px">
                    @if (! empty($breakdowns['source']))
                        <div>{!! $reportTable('Source', $breakdowns['source']) !!}</div>
                    @endif
                    @if (! empty($breakdowns['device']))
                        <div>{!! $reportTable('Device', $breakdowns['device']) !!}</div>
                    @endif
                </div>
                @if (! empty($breakdowns['campaign']))
                    <div style="margin-top:18px">{!! $reportTable('Campaign', $breakdowns['campaign']) !!}</div>
                @endif
                <p class="muted" style="font-size:0.8rem;margin:12px 0 0">Tag your links with <code>?utm_source=…&amp;utm_campaign=…</code> to see each channel here. Export includes the source per entry.</p>
            @endif
        </div>

        @if ($tracksClicks)
            <div class="card">
                <h2 style="margin-top:0;font-size:1.2rem">Most-clicked links &amp; buttons</h2>
                <p class="muted" style="margin-top:0">Which links and buttons visitors tap most — last 30 days, counted on our side with no cookies or visitor tracking.</p>
                @if (empty($clicks))
                    <p class="muted" style="margin:0">No clicks yet. Once visitors start tapping your call-to-action and other links, you'll see them ranked here.</p>
                @else
                    <table>
                        <thead><tr><th>Link / button</th><th>Type</th><th>Clicks</th></tr></thead>
                        <tbody>
                            @foreach ($clicks as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ ucfirst($row['kind']) }}</td>
                                    <td>{{ number_format($row['clicks']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="muted" style="font-size:0.8rem;margin:12px 0 0">Each element is tracked by a stable id set when the page is built, so counts follow it even if you change its wording.</p>
                @endif
            </div>
        @endif

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px">
                <h2 style="margin:0;font-size:1.2rem">Plan &amp; usage</h2>
                <span class="muted">{{ $plan['name'] }} · @if ($launchFree)<strong style="color:var(--accent)">Free during launch</strong>@else balance <strong style="color:var(--accent)">${{ number_format($usage['balance_cents'] / 100, 2) }}</strong>@endif</span>
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
                $launchFree
                    ? 'free during launch'
                    : ($usage['build']['over']
                        ? 'free minute used — now metered'
                        : number_format($usage['build']['free_remaining_ms'] / 1000, 1).'s of free build time left'),
                $launchFree ? false : $usage['build']['over'],
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
            @if ($launchFree)
                <p class="muted" style="font-size:0.8rem;margin:10px 0 0">Free while we're in beta — you won't be charged for builds, traffic or storage during the launch promotion.</p>
            @else
                <p class="muted" style="font-size:0.8rem;margin:10px 0 0">First build minute is free, then $0.50/min. Traffic and storage over the plan's allowance are billed per the plan. Billing is not live yet.</p>
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Banners, deck &amp; rules</h2>
            <p class="muted">Upload a desktop + mobile banner, a copy deck and a rules document — from this computer, or scan the code to do it from your phone.</p>
            <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:flex-start">
                <div style="flex:1;min-width:220px">
                    <a href="{{ route('microsite.upload', $site->slug) }}" class="btn" style="display:inline-block;text-decoration:none">Upload files</a>
                    <ul style="margin:14px 0 0;padding-left:18px" class="muted">
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
                <div style="text-align:center">
                    <div style="width:150px">{!! $uploadQr !!}</div>
                    <p class="muted" style="font-size:0.8rem;margin:6px 0 0;max-width:150px">Or scan to upload from your phone (keeps the link private).</p>
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
                @php($badge = function ($status) {
                    $map = [
                        'deliverable' => ['#166534', '#dcfce7', '✓ deliverable'], 'valid' => ['#166534', '#dcfce7', '✓ valid'],
                        'risky' => ['#92400e', '#fef3c7', '⚠ risky'],
                        'undeliverable' => ['#991b1b', '#fee2e2', '✕ undeliverable'], 'invalid' => ['#991b1b', '#fee2e2', '✕ invalid'],
                    ];
                    if (! $status || ! isset($map[$status])) { return ''; }
                    [$fg, $bg, $text] = $map[$status];
                    return '<span style="font-size:0.7rem;padding:1px 6px;border-radius:6px;color:'.$fg.';background:'.$bg.';margin-left:6px;white-space:nowrap">'.$text.'</span>';
                })
                <table>
                    <thead><tr><th>When</th><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Photo</th><th>Details</th></tr></thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="muted">{{ $entry->created_at?->diffForHumans() }}</td>
                                <td>{{ $entry->name }}</td>
                                <td>{{ $entry->email }}{!! $badge($entry->email_status) !!}</td>
                                <td>{{ $entry->phone }}{!! $badge($entry->phone_status) !!}</td>
                                <td class="muted" style="font-size:0.82rem">{{ $entry->source ?: '—' }}</td>
                                <td>
                                    @if ($entry->photo_path)
                                        <a href="{{ route('microsite.entry.photo', [$site->slug, $entry]) }}" target="_blank" rel="noopener"><img src="{{ route('microsite.entry.photo', [$site->slug, $entry]) }}" alt="Entry photo" style="width:40px;height:40px;object-fit:cover;border-radius:6px;display:block"></a>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <details>
                                        <summary class="muted" style="cursor:pointer;font-size:0.82rem">view</summary>
                                        <div class="muted" style="font-size:0.78rem;line-height:1.5;padding-top:4px">
                                            DOB: {{ $entry->extra['dob'] ?? '—' }}<br>
                                            Device: {{ $entry->device ?: '—' }}<br>
                                            Country: {{ $entry->country ?: '—' }}<br>
                                            IP: {{ $entry->ip ?: '—' }}<br>
                                            Campaign: {{ $entry->campaign ?: '—' }}<br>
                                            Line: {{ $entry->phone_line ?: '—' }}<br>
                                            UA: <span style="word-break:break-all">{{ \Illuminate\Support\Str::limit($entry->user_agent, 120) ?: '—' }}</span>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Alarms</h2>
            <p class="muted" style="margin-top:0">Get an email at <strong>{{ $site->owner->email }}</strong> when this site crosses a threshold.</p>
            @forelse ($alarms as $alarm)
                <div class="alarm-row">
                    <span>At <strong>{{ $alarm->label() }}</strong>
                        @if ($alarm->triggered_at)<span class="muted" style="font-size:0.8rem">· triggered {{ $alarm->triggered_at->diffForHumans() }}</span>@endif
                    </span>
                    <form method="post" action="{{ route('microsite.alarms.destroy', [$site->slug, $alarm]) }}">@csrf<button class="rm" type="submit">Delete</button></form>
                </div>
            @empty
                <p class="muted" style="margin:0">No alarms yet. Add one below to be emailed when you hit a milestone.</p>
            @endforelse
            <form method="post" action="{{ route('microsite.alarms.store', $site->slug) }}" class="alarm-add">
                @csrf
                <div class="fld">
                    <label for="alarm-metric">When</label>
                    <select id="alarm-metric" name="metric"><option value="views">views</option><option value="entries">entries</option><option value="storage">storage (MB)</option></select>
                </div>
                <div class="fld">
                    <label for="alarm-threshold">reaches</label>
                    <input type="number" id="alarm-threshold" name="threshold" min="1" value="100">
                </div>
                <button class="btn" type="submit">Add alarm</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Embed the form</h2>
            <p class="muted" style="margin-top:0">Drop your entry form onto any website.</p>
            <textarea id="embed-code" readonly style="font-family:ui-monospace,monospace;font-size:0.8rem;min-height:70px">&lt;iframe src="{{ route('microsite.embed', $site->slug, absolute: true) }}" width="100%" height="520" style="border:0;max-width:440px" title="Sign up"&gt;&lt;/iframe&gt;</textarea>
            <button type="button" class="btn" id="embed-copy" style="margin-top:8px;padding:9px 16px">Copy</button>
        </div>

        @php($apiEndpoint = route('api.entries.store', $site->slug, absolute: true))
        @php($apiToken = $manageToken ?: 'YOUR_MANAGE_TOKEN')
        <div class="card">
            <h2 style="margin-top:0;font-size:1.2rem">Import API</h2>
            <p class="muted" style="margin-top:0">Push entries into this site from your own systems — one at a time, or up to 500 at once.</p>

            <p style="margin:0 0 4px;font-weight:600">Endpoint</p>
            <pre style="background:var(--paper);border:1px solid var(--line);border-radius:8px;padding:12px;overflow:auto;font-size:0.8rem">POST {{ $apiEndpoint }}</pre>

            <p style="margin:14px 0 4px;font-weight:600">Authentication</p>
            <p class="muted" style="margin:0 0 6px">Send your <strong>manage token</strong> as a bearer token. It's the same private secret that opens this dashboard — treat it like a password, and only from your server (never in client-side code).</p>
            <pre id="api-auth" data-token="{{ $apiToken }}" style="background:var(--paper);border:1px solid var(--line);border-radius:8px;padding:12px;overflow:auto;font-size:0.8rem">Authorization: Bearer <span id="token-text">••••••••••••••••••••</span></pre>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                <button type="button" class="btn" id="token-reveal" style="padding:9px 16px;width:auto;display:inline-block">Reveal token</button>
                <button type="button" class="btn" id="token-copy" style="padding:9px 16px;width:auto;display:inline-block;background:transparent;color:var(--accent);border:1px solid var(--accent)">Copy token</button>
            </div>
            <p class="muted" style="font-size:0.8rem;margin:8px 0 0">Hidden by default so it isn't exposed on a screen share or screenshot.</p>

            <p style="margin:16px 0 4px;font-weight:600">Hand it to a developer securely</p>
            <p class="muted" style="margin:0 0 6px">Generate a link that shows the endpoint and token <strong>once</strong>, then stops working. Send it to your developer over your usual channel — the token never sits in plaintext in your message.</p>
            @if (session('api_share_url'))
                <div class="share" style="margin-bottom:8px"><a href="{{ session('api_share_url') }}" id="api-share-url" target="_blank" rel="noopener">{{ session('api_share_url') }}</a></div>
                <button type="button" class="btn" id="api-share-copy" data-copy="{{ session('api_share_url') }}" style="padding:9px 16px;width:auto;display:inline-block">Copy link</button>
                <p class="muted" style="font-size:0.8rem;margin:8px 0 0">Opens once, then expires within an hour. Generate a new link each time you share.</p>
            @else
                <form method="post" action="{{ route('microsite.api-token.share', $site->slug) }}">
                    @csrf
                    <button type="submit" class="btn" style="padding:9px 16px;width:auto;display:inline-block">Create one-time share link</button>
                </form>
            @endif

            <p style="margin:14px 0 4px;font-weight:600">Fields</p>
            <ul class="muted" style="margin:0;font-size:0.9rem;padding-left:18px">
                <li><code>name</code> — required, up to 80 characters</li>
                <li><code>email</code> — optional, a valid email, up to 120</li>
                <li><code>phone</code> — optional, up to 40</li>
                <li><code>message</code> — optional, up to 1000</li>
                <li><code>source</code> — optional label, up to 40 (defaults to <code>api</code>) — shows per entry in your export/report</li>
            </ul>

            <p style="margin:14px 0 4px;font-weight:600">Example — one entry</p>
            <pre id="api-curl" data-token="{{ $apiToken }}" style="background:var(--paper);border:1px solid var(--line);border-radius:8px;padding:12px;overflow:auto;font-size:0.8rem">curl -X POST {{ $apiEndpoint }} \
  -H "Authorization: Bearer <span id="curl-token">••••••••••••••••••••</span>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe","email":"jane@example.com","source":"crm"}'</pre>
            <button type="button" class="btn" id="api-copy" style="margin-top:8px;padding:9px 16px;width:auto;display:inline-block">Copy</button>

            <p style="margin:14px 0 4px;font-weight:600">Example — a batch (up to 500)</p>
            <pre style="background:var(--paper);border:1px solid var(--line);border-radius:8px;padding:12px;overflow:auto;font-size:0.8rem">-d '{"entries":[
  {"name":"Jane Doe","email":"jane@example.com"},
  {"name":"John Roe","phone":"+1 555 0100"}
]}'</pre>

            <p style="margin:14px 0 4px;font-weight:600">Responses</p>
            <ul class="muted" style="margin:0;font-size:0.9rem;padding-left:18px">
                <li><code>201</code> — <code>{"ok":true,"created":N}</code>. Entries with an email already on file are skipped, so <code>created</code> may be fewer than you sent.</li>
                <li><code>401</code> — missing or wrong bearer token.</li>
                <li><code>422</code> — a row failed validation, or the batch was empty / over 500 (nothing is written when any row is invalid).</li>
            </ul>
            <p class="muted" style="font-size:0.8rem;margin:12px 0 0">Rate limit: 120 requests per minute. Send <code>Accept: application/json</code> to always get JSON back.</p>
        </div>

        @if ($entries->isNotEmpty())
            <div class="card ok">
                <h2 style="margin-top:0;font-size:1.2rem">Turn these leads into customers</h2>
                <p class="muted" style="margin-top:0">Send a promo email to everyone who entered — in minutes — with <strong>Emails in Minutes</strong>, our upcoming email-campaign tool. Your verified, deliverable list is ready to go.</p>
                <button type="button" class="btn" disabled style="opacity:.75;cursor:default">Emails in Minutes — coming soon</button>
            </div>
        @endif

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
                    <label for="business_name">Site name</label>
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
                <label class="muted" style="font-weight:400;display:block;margin-bottom:8px">
                    <input type="checkbox" name="hero_overlay" value="1" style="width:auto" {{ ($inputs['hero_overlay'] ?? false) ? 'checked' : '' }}>
                    Put the headline and button over the banner image
                </label>

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

                <fieldset style="margin-top:18px">
                    <legend>SEO &amp; sharing</legend>
                    <div class="field">
                        <label for="seo_title">Page title <span class="hint" style="font-weight:400;color:var(--muted)">— up to 70 chars; blank uses the auto title</span></label>
                        <input type="text" id="seo_title" name="seo_title" maxlength="70" value="{{ old('seo_title', $inputs['seo_title'] ?? '') }}" placeholder="{{ $og['title'] }}">
                    </div>
                    <div class="field">
                        <label for="seo_description">Meta description <span class="hint" style="font-weight:400;color:var(--muted)">— up to 160 chars</span></label>
                        <textarea id="seo_description" name="seo_description" maxlength="160" placeholder="{{ $og['description'] }}">{{ old('seo_description', $inputs['seo_description'] ?? '') }}</textarea>
                    </div>
                    <p class="muted" style="font-size:0.8rem;margin:0">Upload a favicon and a social banner in "Banners, deck &amp; rules" above to control the icon and share image.</p>
                </fieldset>

                <fieldset style="margin-top:18px">
                    <legend>Entry verification <span class="hint" style="font-weight:400;color:var(--muted)">— add-ons</span></legend>
                    <label class="muted" style="font-weight:400;display:block;margin-bottom:8px">
                        <input type="checkbox" name="verify_emails" value="1" style="width:auto" {{ ($inputs['verify_emails'] ?? false) ? 'checked' : '' }}>
                        Verify entry <strong>emails</strong> for deliverability (Bouncer){{ config('services.bouncer.key') ? '' : ' — needs a Bouncer key' }}
                    </label>
                    <label class="muted" style="font-weight:400;display:block;margin-bottom:8px">
                        <input type="checkbox" name="verify_phones" value="1" style="width:auto" {{ ($inputs['verify_phones'] ?? false) ? 'checked' : '' }}>
                        Verify entry <strong>phone numbers</strong> (SMS/line lookup) — wired at launch
                    </label>
                    <label class="muted" style="font-weight:400;display:block;margin-bottom:8px">
                        <input type="checkbox" name="require_confirmation" value="1" style="width:auto" {{ ($inputs['require_confirmation'] ?? false) ? 'checked' : '' }}>
                        Require <strong>confirmation</strong> — entrants click an emailed link to confirm (double opt-in)
                    </label>
                    <label class="muted" style="font-weight:400;display:block">
                        <input type="checkbox" name="require_email_code" value="1" style="width:auto" {{ ($inputs['require_email_code'] ?? false) ? 'checked' : '' }}>
                        Require an <strong>email code</strong> — entrants re-enter their email and type a 6-digit code before entering{{ config('services.turnstile.site_key') ? ' (with Cloudflare Turnstile bot protection)' : ' — add Turnstile keys to enable bot protection' }}
                    </label>
                </fieldset>

                <fieldset style="margin-top:18px">
                    <legend>Photo capture <span class="hint" style="font-weight:400;color:var(--muted)">— mobile</span></legend>
                    <label class="muted" style="font-weight:400;display:block">
                        <input type="checkbox" name="collect_photo" value="1" style="width:auto" {{ ($inputs['collect_photo'] ?? false) ? 'checked' : '' }}>
                        Ask entrants to <strong>add a photo</strong> (e.g. "submit your smile") — a live camera with a framing mask on phones. Photos are private to you.
                    </label>
                </fieldset>

                <fieldset style="margin-top:18px">
                    <legend>Entry fields <span class="hint" style="font-weight:400;color:var(--muted)">— add-ons</span></legend>
                    <label class="muted" style="font-weight:400;display:block">
                        <input type="checkbox" name="collect_dob" value="1" style="width:auto" {{ ($inputs['collect_dob'] ?? false) ? 'checked' : '' }}>
                        Ask entrants for their <strong>date of birth</strong> — an optional field on the entry form. Shown on entries and included in your CSV export.
                    </label>
                </fieldset>

                <fieldset style="margin-top:18px">
                    <legend>Integrations</legend>
                    <div class="field">
                        <label for="webhook_url">Webhook URL <span class="hint" style="font-weight:400;color:var(--muted)">— POST each entry here</span></label>
                        <input type="url" id="webhook_url" name="webhook_url" maxlength="300" value="{{ old('webhook_url', $inputs['webhook_url'] ?? '') }}" placeholder="https://hooks.zapier.com/...">
                    </div>
                    <p class="muted" style="font-size:0.8rem;margin:0">Connect to Google Sheets, Salesforce, Mailchimp and 6,000+ apps via a Zapier or Make webhook. Native integrations coming soon.</p>
                </fieldset>

                <fieldset style="margin-top:18px">
                    <legend>Schedule <span class="hint" style="font-weight:400;color:var(--muted)">— current phase: {{ str_replace('_', ' ', $site->phase()) }}</span></legend>
                    <div class="row">
                        <div class="field">
                            <label for="go_live_at">Go live at <span class="hint" style="font-weight:400;color:var(--muted)">— coming-soon page until then</span></label>
                            <input type="datetime-local" id="go_live_at" name="go_live_at" value="{{ old('go_live_at', $inputs['go_live_at'] ?? '') }}">
                        </div>
                        <div class="field">
                            <label for="ends_at">Ends at <span class="hint" style="font-weight:400;color:var(--muted)">— ended page after then</span></label>
                            <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', $inputs['ends_at'] ?? '') }}">
                        </div>
                    </div>
                    <p class="muted" style="font-size:0.8rem;margin:0">Leave blank to stay live. Visitors see a "coming soon" page (with a notify-me signup) before go-live, and an "ended" page after.</p>
                </fieldset>

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

    <script>
        /* Live dashboard: poll the stats endpoint and update the counters + chart. */
        (() => {
            const bars = document.getElementById('stat-bars');
            if (!bars) { return; }
            const url = bars.dataset.url;
            let range = bars.dataset.range || 'month';
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const set = (id, v) => { const el = document.getElementById(id); if (el) { el.textContent = v; } };
            const num = (el) => parseFloat((el.textContent || '0').replace(/[^0-9.]/g, '')) || 0;
            const fmt = (v, decimals, suffix) => (decimals ? v.toFixed(decimals) : Math.round(v).toLocaleString()) + suffix;

            /* Tick the counter up to the new value instead of snapping. */
            const countTo = (id, target, decimals = 0, suffix = '') => {
                const el = document.getElementById(id);
                if (!el) { return; }
                const from = num(el);
                if (from === target || reduce) { el.textContent = fmt(target, decimals, suffix); return; }
                const dur = 650, start = performance.now();
                const tick = (now) => {
                    const t = Math.min(1, (now - start) / dur);
                    const eased = 1 - Math.pow(1 - t, 3); /* easeOutCubic */
                    el.textContent = fmt(from + (target - from) * eased, decimals, suffix);
                    if (t < 1) { requestAnimationFrame(tick); } else { el.textContent = fmt(target, decimals, suffix); }
                };
                requestAnimationFrame(tick);
            };

            const renderBars = (series) => {
                const viewMax = Math.max(0, ...series.map((x) => x.views));
                const peak = Math.max(1, viewMax);
                bars.innerHTML = series.map((x) => {
                    const bh = Math.max(2, Math.round(x.views / peak * 60));
                    const conv = x.views > 0 ? x.entries / x.views : 0;
                    const es = x.views > 0 ? Math.min(bh, Math.max(x.entries > 0 ? 2 : 0, Math.round(bh * conv))) : 0;
                    const pct = (conv * 100).toFixed(1);
                    return `<span class="bar" style="height:${bh}px" aria-label="${x.label}: ${x.views} views, ${x.entries} entries (${pct}%)" data-label="${x.label}" data-views="${x.views}" data-entries="${x.entries}" data-conv="${pct}"><i class="v"></i><i class="e" style="height:${es}px"></i></span>`;
                }).join('');
                const yv = document.getElementById('y-views-peak'); if (yv) { yv.textContent = viewMax.toLocaleString(); }
                const xax = document.getElementById('stat-xaxis');
                if (xax && series.length) {
                    // Hour view reads cleaner at ~30-min spacing (3 ticks); others at 5.
                    const ticks = range === 'hour' ? 3 : 5;
                    const seen = new Set();
                    xax.innerHTML = Array.from({ length: ticks }, (_, k) => Math.round(k / (ticks - 1) * (series.length - 1)))
                        .filter((i) => (seen.has(i) ? false : seen.add(i)))
                        .map((i) => `<span>${series[i].label}</span>`).join('');
                }
            };

            /* Legend click toggles a series on/off. The hidden state lives on the
               plot container, so the live re-render of the bars keeps it. */
            const plot = document.getElementById('stat-plot');
            const legend = document.getElementById('stat-legend');
            if (plot && legend) {
                const toggleSeries = (span) => {
                    const hidden = plot.classList.toggle(span.dataset.series === 'v' ? 'hide-v' : 'hide-e');
                    span.classList.toggle('off', hidden);
                    span.setAttribute('aria-pressed', hidden ? 'false' : 'true');
                };
                legend.querySelectorAll('span[data-series]').forEach((span) => {
                    span.addEventListener('click', () => toggleSeries(span));
                    span.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleSeries(span); } });
                });
            }

            /* Instant, themed hover tooltip. The tip is a sibling of #stat-bars
               (inside .chart-wrap), so the live re-render of the bars never
               wipes it. Bars offset against .chart-wrap, their positioned
               ancestor. */
            const tip = document.getElementById('stat-tip');
            if (tip) {
                bars.addEventListener('mouseover', (e) => {
                    const bar = e.target.closest('.bar');
                    if (!bar || !bars.contains(bar)) { return; }
                    tip.innerHTML = `<div class="tl">${bar.dataset.label}</div>`
                        + `<div><span class="sw" style="background:var(--accent)"></span>${bar.dataset.views} views</div>`
                        + `<div><span class="sw" style="background:var(--entries)"></span>${bar.dataset.entries} entries</div>`
                        + `<div style="opacity:.7;margin-top:2px">${bar.dataset.conv}% conversion</div>`;
                    tip.style.left = (bar.offsetLeft + bar.offsetWidth / 2) + 'px';
                    tip.style.top = bar.offsetTop + 'px';
                    tip.classList.add('show');
                });
                bars.addEventListener('mouseleave', () => tip.classList.remove('show'));
            }

            const refresh = async () => {
                try {
                    const r = await fetch(`${url}?range=${encodeURIComponent(range)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    if (!r.ok) { return; }
                    const d = await r.json();
                    countTo('stat-views', Number(d.views));
                    countTo('stat-entries', Number(d.entries));
                    countTo('stat-conv', Number(d.conversion), 1, '%');
                    set('stat-dwell', d.dwell);
                    set('stat-live', String(d.live ?? 0));
                    if (Array.isArray(d.series)) { renderBars(d.series); }
                    if (d.caption) { set('stat-caption', d.caption); }
                } catch (e) { /* keep the last render on a hiccup */ }
            };

            /* Range toggles re-point the chart (Hour/Day/Week/Month/Year) without a reload. */
            const tabs = document.querySelectorAll('#stat-ranges .range');
            tabs.forEach((btn) => {
                btn.addEventListener('click', () => {
                    range = btn.dataset.range;
                    tabs.forEach((b) => {
                        const on = b === btn;
                        b.classList.toggle('is-active', on);
                        b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                    refresh();
                });
            });

            setInterval(refresh, 3000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) { refresh(); } });
        })();

        (() => {
            const base = @json($site->url());
            const preview = document.getElementById('utm-preview');
            const fields = { utm_source: 'l-source', utm_medium: 'l-medium', utm_campaign: 'l-campaign' };
            const build = () => {
                const params = new URLSearchParams();
                for (const [key, id] of Object.entries(fields)) {
                    const v = (document.getElementById(id)?.value || '').trim();
                    if (v) { params.set(key, v); }
                }
                const q = params.toString();
                preview.value = q ? base + '?' + q : base;
            };
            Object.values(fields).forEach((id) => document.getElementById(id)?.addEventListener('input', build));
            build();
            document.getElementById('utm-copy')?.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(preview.value); } catch (e) { preview.select(); document.execCommand('copy'); }
                const b = document.getElementById('utm-copy');
                b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy'; }, 1500);
            });

            /* A successful publish clears the saved builder draft (#7), so the
               next visit to the builder starts fresh rather than restoring it. */
            if (document.getElementById('created-url')) {
                try { localStorage.removeItem('mim:builder-draft'); } catch (e) { /* no-op */ }
            }

            /* Copy the live link straight from the just-created banner. */
            document.getElementById('created-copy')?.addEventListener('click', async () => {
                const b = document.getElementById('created-copy');
                const url = b.dataset.copy || document.getElementById('created-url')?.textContent || '';
                try { await navigator.clipboard.writeText(url); } catch (e) { const r = document.createRange(); r.selectNodeContents(document.getElementById('created-url')); const s = getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); s.removeAllRanges(); }
                b.textContent = '✓ Copied'; setTimeout(() => { b.textContent = '📋 Copy link'; }, 1500);
            });

            /* Copy the site's live URL from the persistent header bar. */
            document.getElementById('url-copy')?.addEventListener('click', async (e) => {
                const b = e.currentTarget;
                try { await navigator.clipboard.writeText(b.dataset.copy || ''); } catch (err) { /* no-op */ }
                b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy URL'; }, 1500);
            });

            /* Import API token: hidden behind a Reveal toggle so it isn't exposed
               on a screen share, and copied without ever showing it on screen. */
            const MASK = '••••••••••••••••••••';
            const apiAuth = document.getElementById('api-auth');
            const apiToken = apiAuth ? apiAuth.dataset.token : '';
            const tokenText = document.getElementById('token-text');
            const curlToken = document.getElementById('curl-token');
            let tokenShown = false;

            const applyToken = () => {
                const shown = tokenShown ? apiToken : MASK;
                if (tokenText) { tokenText.textContent = shown; }
                if (curlToken) { curlToken.textContent = shown; }
            };

            document.getElementById('token-reveal')?.addEventListener('click', () => {
                tokenShown = !tokenShown;
                applyToken();
                document.getElementById('token-reveal').textContent = tokenShown ? 'Hide token' : 'Reveal token';
            });

            const flash = (btn, label) => { const t = btn.textContent; btn.textContent = label; setTimeout(() => { btn.textContent = t; }, 1500); };

            document.getElementById('token-copy')?.addEventListener('click', async (e) => {
                try { await navigator.clipboard.writeText(apiToken); } catch (err) { /* no-op */ }
                flash(e.currentTarget, 'Copied');
            });

            document.getElementById('api-share-copy')?.addEventListener('click', async (e) => {
                try { await navigator.clipboard.writeText(e.currentTarget.dataset.copy || ''); } catch (err) { /* no-op */ }
                flash(e.currentTarget, 'Copied');
            });

            /* Copy the Import API curl example — always with the real token, even
               while it is masked on screen (copying is an explicit action). */
            document.getElementById('api-copy')?.addEventListener('click', async () => {
                const curl = document.getElementById('api-curl');
                const realToken = curl.dataset.token || '';
                // Function replacement so a token containing '$' is inserted literally.
                const code = curl.textContent.replace(MASK, () => realToken);
                try { await navigator.clipboard.writeText(code); } catch (e) { /* no-op */ }
                const b = document.getElementById('api-copy');
                b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy'; }, 1500);
            });

            /* Copy the embed snippet. */
            document.getElementById('embed-copy')?.addEventListener('click', async () => {
                const code = document.getElementById('embed-code');
                try { await navigator.clipboard.writeText(code.value); } catch (e) { code.select(); document.execCommand('copy'); }
                const b = document.getElementById('embed-copy');
                b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy'; }, 1500);
            });

            /* While a domain is registering, poll and reload when it goes live. */
            const dp = document.getElementById('dom-poll');
            if (dp) {
                setInterval(async () => {
                    try {
                        const r = await fetch(dp.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                        if (!r.ok) { return; }
                        if ((await r.json()).active) { location.reload(); }
                    } catch (e) {}
                }, 3000);
            }
        })();
    </script>
</body>
</html>
