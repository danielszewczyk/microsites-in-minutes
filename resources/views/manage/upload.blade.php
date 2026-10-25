<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Upload — {{ $site->inputs['business_name'] ?? 'your site' }}</title>
    <style>
        :root { --accent: {{ $site->accent() }}; --ink: #1c1917; --muted: #57534e; --paper: #fafaf9; --card: #fff; --line: #e7e5e4; }
        @media (prefers-color-scheme: dark) { :root { --ink: #fafaf9; --muted: #a8a29e; --paper: #1c1917; --card: #292524; --line: #44403c; } }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--paper); line-height: 1.55; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 24px 18px 64px; }
        h1 { font-size: 1.4rem; margin: 0 0 4px; }
        .muted { color: var(--muted); }
        .ok { background: color-mix(in srgb, var(--accent) 14%, var(--paper)); border: 1px solid var(--accent); border-radius: 12px; padding: 12px 14px; margin: 16px 0; }
        .slot { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin: 16px 0; }
        .slot h2 { font-size: 1.05rem; margin: 0 0 4px; }
        .slot .have { font-size: 0.85rem; color: var(--accent); margin: 6px 0; word-break: break-all; }
        input[type=file] { font: inherit; width: 100%; margin-top: 10px; padding: 12px; border: 1px dashed var(--line); border-radius: 10px; background: var(--paper); color: var(--ink); }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 12px; padding: 15px 24px; font-size: 1.05rem; font-weight: 600; cursor: pointer; width: 100%; }
        .rm { background: none; border: 0; color: #b91c1c; font: inherit; cursor: pointer; padding: 4px 0; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Upload for {{ $site->inputs['business_name'] ?? 'your site' }}</h1>
        <p class="muted">Add banners and documents straight from your phone. They attach to your live page.</p>

        @if (session('status'))
            <div class="ok">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <div class="ok" style="border-color:#b91c1c;background:#fee2e2;color:#991b1b">{{ $error }}</div>
            @endforeach
        @endif

        <form method="post" action="{{ route('microsite.upload.store', $site->slug) }}" enctype="multipart/form-data">
            @csrf
            @foreach ($slots as $slot => $spec)
                <div class="slot">
                    <h2>{{ $spec['label'] }}</h2>
                    <p class="muted" style="margin:0;font-size:0.85rem">
                        {{ $spec['kind'] === 'image' ? 'JPG, PNG or WebP — up to 12 MB.' : 'PDF, Word or image — up to 20 MB.' }}
                        @unless ($spec['public']) (private — only you can see it) @endunless
                    </p>
                    @if (isset($assets[$slot]))
                        <p class="have">✓ {{ $assets[$slot]->original_name }}</p>
                        <button type="submit" form="rm-{{ $slot }}" class="rm">Remove</button>
                    @endif
                    <input type="file" name="{{ $slot }}"
                        accept="{{ $spec['kind'] === 'image' ? 'image/*' : '.pdf,.doc,.docx,image/*' }}">
                </div>
            @endforeach
            <button type="submit" class="btn">Upload</button>
        </form>

        {{-- Separate tiny forms so a "Remove" never carries a pending file upload. --}}
        @foreach ($slots as $slot => $spec)
            @if (isset($assets[$slot]))
                <form id="rm-{{ $slot }}" method="post" action="{{ route('microsite.upload.remove', [$site->slug, $slot]) }}">@csrf</form>
            @endif
        @endforeach

        <p style="margin-top:24px"><a href="{{ route('microsite.manage', $site->slug) }}">← Back to the dashboard</a></p>
    </div>
</body>
</html>
