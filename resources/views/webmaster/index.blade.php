<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Webmaster — all sites</title>
    <style>
        :root { --accent: #0f766e; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.5; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px 64px; }
        .head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 10px; margin: 0 0 20px; }
        h1 { font-size: 1.5rem; letter-spacing: -0.02em; margin: 0; }
        .count { color: var(--muted); font-size: 0.95rem; }
        a { color: var(--accent); }
        .home { font-size: 0.9rem; text-decoration: none; }
        .table-scroll { overflow-x: auto; border: 1px solid var(--line); border-radius: 12px; background: var(--card); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 11px 14px; border-bottom: 1px solid var(--line); white-space: nowrap; vertical-align: top; }
        th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); font-weight: 600; background: var(--paper); position: sticky; top: 0; }
        tr:last-child td { border-bottom: 0; }
        .site-name { font-weight: 600; }
        .site-name a { text-decoration: none; }
        .site-name a:hover { text-decoration: underline; }
        .slug { display: block; color: var(--muted); font-size: 0.8rem; font-weight: 400; margin-top: 2px; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .pill { display: inline-block; padding: 1px 9px; border-radius: 999px; font-size: 0.75rem; border: 1px solid var(--line); color: var(--muted); }
        .pill.published { color: var(--accent); border-color: var(--accent); }
        .muted { color: var(--muted); }
        .empty { padding: 40px; text-align: center; color: var(--muted); }
        .pager { margin-top: 18px; }
        /* Laravel's paginator markup, kept legible in both themes. */
        .pager nav div:first-child { color: var(--muted); font-size: 0.85rem; }
        .pager svg { height: 18px; width: 18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>All sites</h1>
            <span class="count">{{ number_format($totalSites) }} total · <a class="home" href="{{ route('home') }}">← builder</a></span>
        </div>

        @if ($sites->isEmpty())
            <div class="table-scroll"><p class="empty">No sites have been created yet.</p></div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Site</th>
                            <th>Owner</th>
                            <th>Plan</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="num">Views</th>
                            <th class="num">Entries</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sites as $site)
                            <tr>
                                <td class="site-name">
                                    <a href="{{ $site->url() }}" target="_blank" rel="noopener">{{ $site->inputs['business_name'] ?? $site->slug }}</a>
                                    <span class="slug">/s/{{ $site->slug }}</span>
                                </td>
                                <td>
                                    {{ $site->owner?->email ?? '—' }}
                                    @if ($site->owner?->name)
                                        <span class="slug">{{ $site->owner->name }}</span>
                                    @endif
                                </td>
                                <td><span class="muted">{{ ucfirst($site->owner?->plan ?? '—') }}</span></td>
                                <td>{{ str($site->type)->headline() }}</td>
                                <td><span class="pill {{ $site->status }}">{{ ucfirst($site->status) }}</span></td>
                                <td class="num">{{ number_format($site->view_count) }}</td>
                                <td class="num">{{ number_format($site->entry_count) }}</td>
                                <td class="muted">{{ $site->created_at?->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pager">{{ $sites->links() }}</div>
        @endif
    </div>
</body>
</html>
