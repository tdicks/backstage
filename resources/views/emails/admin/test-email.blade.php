<x-mail::message>
# Backstage test email

Hi {{ $recipientName }},

This is a test email sent from the Admin Settings page.

If this message reached your inbox, outgoing mail is configured and working for your account.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
