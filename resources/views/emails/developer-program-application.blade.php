<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Program application</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111827;">
    <h1 style="font-size: 1.25rem;">New Developer Program application</h1>
    <table cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
        <tr><td><strong>Name</strong></td><td>{{ $application->name }}</td></tr>
        <tr><td><strong>Business ID</strong></td><td>{{ $application->business_id ?: '—' }}</td></tr>
        <tr><td><strong>Phone</strong></td><td>{{ $application->phone }}</td></tr>
        <tr><td><strong>Email</strong></td><td>{{ $application->email }}</td></tr>
        <tr><td><strong>WhatsApp</strong></td><td>{{ $application->whatsapp }}</td></tr>
        <tr><td><strong>Community</strong></td><td>{{ $application->community_preference }}</td></tr>
    </table>
    <p style="font-size: 0.875rem; color: #6b7280;">Submitted at {{ $application->created_at?->toDateTimeString() }}</p>
</body>
</html>
