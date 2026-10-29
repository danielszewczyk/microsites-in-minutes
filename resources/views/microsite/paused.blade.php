<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $site->inputs['business_name'] ?? 'Microsite' }} — paused</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; background: #fafaf9; color: #1c1917; }
        main { max-width: 420px; text-align: center; }
        h1 { font-size: 1.6rem; margin: 0 0 12px; }
        p { color: #57534e; line-height: 1.6; }
    </style>
</head>
<body>
    <main>
        <h1>This page is paused</h1>
        <p>{{ $site->inputs['business_name'] ?? 'This site' }} is taking a short break. It will be back as soon as its owner renews.</p>
    </main>
</body>
</html>
