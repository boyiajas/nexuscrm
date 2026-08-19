<x-mail::message>
# 💬 New WhatsApp Reply Received!

Hi {{ $targetUser->first_name ?? $targetUser->name }},

A client has replied to a WhatsApp campaign message on **{{ $appName }}**.

<x-mail::panel>
**Client Message:**
"{{ $clientMessage }}"
</x-mail::panel>

---

### 👤 Client Details
- **Name:** {{ $clientName }}
- **Phone:** {{ $clientPhone }}
- **Email:** {{ $clientEmail }}
- **Bank / Portfolio:** {{ $bankName }}

---

### 📩 WhatsApp Campaign Details
- **Campaign Name:** {{ $campaignName }}
- **Template / Flow:** {{ $templateName }}
- **Sent Date:** {{ $sentAt }}
- **Original Message Preview:** {{ $outboundBody }}

<x-mail::button :url="$chatUrl">
Open Client Live Chat
</x-mail::button>

Thanks,<br>
{{ $appName }}
</x-mail::message>
