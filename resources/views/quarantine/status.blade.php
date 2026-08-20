<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarantine Status</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 2rem; }
        .box { max-width: 36rem; margin: 3rem auto; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.75rem; }
        h1 { margin: 0 0 0.75rem; font-size: 1.35rem; }
        .ok { color: #4ade80; }
        .bad { color: #f87171; }
        ul { color: #94a3b8; }
        a { color: #93c5fd; }
        code { background: #0f172a; padding: 0.15rem 0.35rem; border-radius: 4px; }
    </style>
</head>
<body>
<div class="box">
    <h1 class="{{ $active ? 'bad' : 'ok' }}">
        {{ $active ? 'Quarantine active' : 'Quarantine clear' }}
    </h1>
    <p>Feature enabled: <code>{{ $enabled ? 'yes' : 'no' }}</code></p>
    @if(!empty($reasons))
        <p>Reasons:</p>
        <ul>
            @foreach($reasons as $reason)
                <li><code>{{ $reason }}</code></li>
            @endforeach
        </ul>
    @endif
    @if(!empty($lock['tripped_at']))
        <p>Tripped at: <code>{{ $lock['tripped_at'] }}</code></p>
    @endif
    <p><a href="{{ url('/quarantine/unlock') }}">Unlock form</a></p>
</div>
</body>
</html>
