<x-mail::message>
# 💬 New WhatsApp Reply Received!

Hi {{ $targetUser->first_name ?? $targetUser->name ?? 'Team' }},

A client has replied to a WhatsApp campaign message on **{{ $appName ?? 'NexusCRM' }}**.

<x-mail::panel>
**Client Message:**
"{{ $clientMessage ?? '' }}"
</x-mail::panel>

---

### 👤 Client Details
- **Name:** {{ $clientName ?? $client?->name ?? 'N/A' }}
- **Phone:** {{ $clientPhone ?? $phone ?? 'N/A' }}
- **Email:** {{ $clientEmail ?? $client?->email ?? 'N/A' }}
- **Account Number:** {{ $accountNumber ?? $client?->account_number ?? 'N/A' }}
- **Bank / Portfolio:** {{ $bankName ?? 'N/A' }}

---

### 📩 WhatsApp Campaign Details
- **Campaign Name:** {{ $campaignName ?? 'N/A' }}
- **Template / Flow:** {{ $templateName ?? 'N/A' }}
- **Sent Date:** {{ $sentAt ?? 'N/A' }}
- **Original Message Preview:** {{ $outboundBody ?? 'N/A' }}

<x-mail::button :url="$chatUrl ?? '#'">
Open Client Live Chat
</x-mail::button>

Thanks,<br>
{{ $appName ?? 'NexusCRM' }}
</x-mail::message>
