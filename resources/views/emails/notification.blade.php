<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $title }}</title>
    </head>
    <body style="margin:0;background:#020617;font-family:Figtree,Arial,sans-serif;color:#e2e8f0;">
        <div style="margin:0 auto;max-width:640px;padding:32px 16px;">
            <div style="border:1px solid rgba(148,163,184,0.24);border-radius:16px;background:linear-gradient(180deg,#0f172a 0%,#111827 100%);overflow:hidden;">
                <div style="padding:24px 24px 12px;">
                    <div style="font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#38bdf8;">Backstage</div>
                    <h1 style="margin:12px 0 0;font-size:24px;line-height:1.25;color:#f8fafc;">{{ $title }}</h1>
                </div>
                <div style="padding:0 24px 24px;font-size:15px;line-height:1.7;color:#cbd5e1;">
                    <p style="margin:0 0 20px;">{{ $body }}</p>
                    @if ($actionUrl)
                        <p style="margin:0;">
                            <a href="{{ $actionUrl }}" style="display:inline-block;border-radius:9999px;background:#0ea5e9;padding:12px 18px;font-size:14px;font-weight:700;color:#082f49;text-decoration:none;">
                                {{ $actionLabel }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </body>
</html>
