<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Models\SystemSetting::first()?->app_name ?: config('app.name', 'SR Solution') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/strauss-recovery-solution-icon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/strauss-recovery-solution-icon-192.png') }}">
    @vite(['resources/js/app.js'])
</head>
<body class="bg-light">
    <div id="app"></div>
</body>
</html>
