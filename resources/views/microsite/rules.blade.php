<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>Official Rules — {{ $content['hero']['headline'] ?? $site->inputs['business_name'] }}</title>
    <meta name="description" content="Official rules, eligibility and free method of entry.">
    <link rel="icon" href="{{ route('microsite.favicon', $site->slug) }}" type="image/svg+xml">
    <style>
        :root { --accent: {{ $accent }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --line: #44403c; } }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.65; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
        a { color: var(--accent); }
        h1 { font-size: 1.9rem; letter-spacing: -0.02em; border-bottom: 4px solid var(--accent); padding-bottom: 14px; }
        h2 { font-size: 1.15rem; margin-top: 32px; }
        p, li { color: var(--ink); }
        .muted { color: var(--muted); font-size: 0.92rem; }
        .no-purchase { background: color-mix(in srgb, var(--accent) 12%, var(--paper)); border-left: 4px solid var(--accent); padding: 12px 16px; border-radius: 8px; font-weight: 600; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; }
        dt { font-weight: 700; }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="muted"><a href="{{ $site->url() }}">&larr; Back to the giveaway</a></p>
        <h1>Official Rules</h1>

        <p class="no-purchase">{{ $rules['no_purchase'] }}</p>

        <h2>Sponsor</h2>
        <p>{{ $rules['sponsor'] }}</p>

        <h2>Entry period</h2>
        <p>The promotion begins on {{ $rules['start_date'] }} and ends on {{ $rules['end_date'] }}.</p>

        <h2>Eligibility</h2>
        <p>{{ $rules['eligibility'] }}</p>

        <h2>How to enter</h2>
        <p>Complete the entry form on the <a href="{{ $site->url() }}#enter">promotion page</a>. One entry per person. Entry is free.</p>

        <h2>Free alternate method of entry</h2>
        <p>{{ $rules['amoe'] }}</p>

        <h2>Prize</h2>
        <dl>
            <dt>Prize</dt><dd>{{ $rules['prize_description'] }}</dd>
            <dt>Approximate value</dt><dd>{{ $rules['prize_value'] }}</dd>
        </dl>

        <h2>Winner selection &amp; notification</h2>
        <p>{{ $rules['winner_selection'] }}</p>

        <h2>General conditions</h2>
        <p>{{ $rules['disclaimer'] }}</p>
    </div>
</body>
</html>
