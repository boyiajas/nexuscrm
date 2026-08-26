@component('mail::message')
# 🚨 URGENT: Meta WhatsApp Account Alert

Meta has issued an alert or restriction on your WhatsApp Business Account. **Active WhatsApp Campaigns may have been automatically paused to prevent further damage to your account reputation.**

**Alert Type:** {{ $alertType }}
**WABA ID:** {{ $wabaId }}
**Phone Number / Account:** {{ $phoneOrAccount }}

**Event Details:**
```
{{ $eventDetails }}
```

### Next Steps:
1. Immediately log into your **[Meta Business Support Home](https://business.facebook.com/business-support-home)** to view the exact violation or alert.
2. If your account was restricted for policy violations (like Spam), you must submit an appeal from that page.
3. Review your recent campaign templates and ensure you are strictly following WhatsApp Messaging Policies (e.g., categorizing collections as Utility, including Opt-out instructions).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
