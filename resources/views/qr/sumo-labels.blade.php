<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 16px; color: #111827; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        .labels { display: flex; flex-wrap: wrap; gap: 12px; }
        .label { width: 240px; border: 1px dashed #9ca3af; border-radius: 8px; padding: 10px; text-align: center; page-break-inside: avoid; }
        .label img { width: 180px; height: 180px; }
        .title { font-size: 13px; font-weight: 600; margin-top: 4px; }
        .subtitle, .code { font-size: 11px; color: #4b5563; }
        .actions { margin-bottom: 12px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Cetak</button></div>
    <h1>{{ $heading }}</h1>
    <div class="labels">
        @foreach ($labels as $label)
            <div class="label">
                <img src="data:image/png;base64,{{ $label['png'] }}" alt="QR {{ $label['code'] }}">
                <div class="title">{{ $label['title'] }}</div>
                @if ($label['subtitle'] !== '')
                    <div class="subtitle">{{ $label['subtitle'] }}</div>
                @endif
                <div class="code">#{{ $label['code'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
