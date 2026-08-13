<x-mail::message>
# Welcome to {{ $appName }}!

Hi {{ $user->first_name ?? $user->name }},

An account has been created for you on {{ $appName }}. Below are your login credentials:

**URL:** {{ url('/') }}
**Username:** {{ $user->username ?: 'N/A' }}
**Email:** {{ $user->email }}
**Password:** {{ $rawPassword }}

<x-mail::button :url="url('/login')">
Login Now
</x-mail::button>

Please log in and change your password as soon as possible.

Thanks,<br>
{{ $appName }}
</x-mail::message>
