<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>@yield('title', 'SRS DailyCRM | Secure Data Management & Compliance')</title>
    <meta name="description" content="@yield('meta_description', 'Public information page for SRS DailyCRM, including security, compliance, and privacy information for Strauss Recovery Solutions and Meta WhatsApp Business usage.')"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&amp;family=Manrope:wght@600;700;800&amp;family=JetBrains+Mono:wght@500&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-container-lowest": "#ffffff",
                        "surface-variant": "#dae2fd",
                        "surface": "#faf8ff",
                        "surface-container-highest": "#dae2fd",
                        "inverse-primary": "#b8c4ff",
                        "on-tertiary-fixed-variant": "#2f2ebe",
                        "on-tertiary-container": "#b3b5ff",
                        "on-error": "#ffffff",
                        "on-secondary-fixed": "#00201d",
                        "on-primary-fixed-variant": "#173bab",
                        "primary-fixed-dim": "#b8c4ff",
                        "background": "#faf8ff",
                        "tertiary-container": "#3433c3",
                        "surface-bright": "#faf8ff",
                        "on-primary": "#ffffff",
                        "inverse-surface": "#283044",
                        "on-surface-variant": "#444653",
                        "on-primary-fixed": "#001453",
                        "secondary": "#006a61",
                        "surface-dim": "#d2d9f4",
                        "on-secondary-container": "#006f66",
                        "inverse-on-surface": "#eef0ff",
                        "on-tertiary-fixed": "#07006c",
                        "error-container": "#ffdad6",
                        "secondary-container": "#86f2e4",
                        "surface-tint": "#3755c3",
                        "primary": "#00288e",
                        "on-background": "#131b2e",
                        "outline-variant": "#c4c5d5",
                        "primary-container": "#1e40af",
                        "on-secondary-fixed-variant": "#005049",
                        "on-error-container": "#93000a",
                        "tertiary-fixed": "#e1e0ff",
                        "on-primary-container": "#a8b8ff",
                        "outline": "#757684",
                        "on-surface": "#131b2e",
                        "tertiary-fixed-dim": "#c0c1ff",
                        "surface-container": "#eaedff",
                        "on-secondary": "#ffffff",
                        "surface-container-low": "#f2f3ff",
                        "error": "#ba1a1a",
                        "on-tertiary": "#ffffff",
                        "surface-container-high": "#e2e7ff",
                        "tertiary": "#170cae",
                        "primary-fixed": "#dde1ff",
                        "secondary-fixed-dim": "#6bd8cb",
                        "secondary-fixed": "#89f5e7"
                    },
                    borderRadius: {
                        DEFAULT: "0.125rem",
                        lg: "0.25rem",
                        xl: "0.5rem",
                        full: "0.75rem"
                    },
                    spacing: {
                        gutter: "24px",
                        "stack-xs": "4px",
                        unit: "4px",
                        "stack-xl": "64px",
                        "stack-sm": "8px",
                        "margin-mobile": "16px",
                        "margin-desktop": "40px",
                        "stack-md": "16px",
                        "container-max": "1280px",
                        "stack-lg": "32px"
                    },
                    fontFamily: {
                        "body-md": ["Inter"],
                        "label-md": ["JetBrains Mono"],
                        "display-lg": ["Manrope"],
                        "headline-lg-mobile": ["Manrope"],
                        button: ["Inter"],
                        "body-lg": ["Inter"],
                        "headline-md": ["Manrope"],
                        "body-sm": ["Inter"],
                        "headline-lg": ["Manrope"]
                    },
                    fontSize: {
                        "body-md": ["16px", { lineHeight: "24px", fontWeight: "400" }],
                        "label-md": ["12px", { lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "500" }],
                        "display-lg": ["48px", { lineHeight: "56px", letterSpacing: "-0.02em", fontWeight: "800" }],
                        "headline-lg-mobile": ["28px", { lineHeight: "36px", fontWeight: "700" }],
                        button: ["14px", { lineHeight: "20px", fontWeight: "600" }],
                        "body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }],
                        "headline-md": ["24px", { lineHeight: "32px", fontWeight: "600" }],
                        "body-sm": ["14px", { lineHeight: "20px", fontWeight: "400" }],
                        "headline-lg": ["32px", { lineHeight: "40px", letterSpacing: "-0.01em", fontWeight: "700" }]
                    }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.5);
        }
        .hero-gradient {
            background: radial-gradient(circle at top right, #eaedff 0%, #faf8ff 100%);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .doc-gradient {
            background: linear-gradient(180deg, rgba(250,248,255,1) 0%, rgba(242,245,255,1) 100%);
        }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md selection:bg-primary-container selection:text-on-primary-container">
    <nav class="fixed top-0 z-50 w-full border-b border-outline-variant/30 bg-surface/80 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-container-max items-center justify-between px-margin-desktop">
            <a class="flex items-center gap-2" href="{{ route('public.landing') }}">
                <div class="flex h-8 w-8 items-center justify-center rounded bg-primary">
                    <span class="material-symbols-outlined text-[20px] text-on-primary">security</span>
                </div>
                <span class="font-headline-md text-headline-md font-bold text-on-surface">SRS DailyCRM</span>
            </a>
            <div class="hidden items-center gap-stack-lg md:flex">
                <a class="{{ request()->routeIs('public.landing') ? 'border-b-2 border-primary pb-1 text-primary font-bold' : 'text-on-surface-variant' }} font-body-md text-body-md transition-colors hover:text-primary" href="{{ route('public.landing') }}#features">Features</a>
                <a class="{{ request()->routeIs('public.landing') ? 'text-on-surface-variant' : 'text-on-surface-variant' }} font-body-md text-body-md transition-colors hover:text-primary" href="{{ route('public.landing') }}#security">Security</a>
                <a class="{{ request()->routeIs('public.compliance') ? 'border-b-2 border-primary pb-1 text-primary font-bold' : 'text-on-surface-variant' }} font-body-md text-body-md transition-colors hover:text-primary" href="{{ route('public.compliance') }}">Compliance</a>
                <a class="{{ request()->routeIs('public.privacy') ? 'border-b-2 border-primary pb-1 text-primary font-bold' : 'text-on-surface-variant' }} font-body-md text-body-md transition-colors hover:text-primary" href="{{ route('public.privacy') }}">Privacy Policy</a>
                <a class="{{ request()->routeIs('public.terms') ? 'border-b-2 border-primary pb-1 text-primary font-bold' : 'text-on-surface-variant' }} font-body-md text-body-md transition-colors hover:text-primary" href="{{ route('public.terms') }}">Terms</a>
            </div>
            <a class="rounded bg-primary-container px-6 py-2 font-button text-white transition-opacity hover:opacity-90" href="/login">Login</a>
        </div>
    </nav>

    <main class="pt-16">
        @yield('content')
    </main>

    <footer class="w-full border-t border-outline-variant/50 bg-surface-container-low py-stack-lg">
        <div class="mx-auto grid max-w-container-max grid-cols-1 gap-stack-lg px-margin-desktop md:grid-cols-2">
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded bg-primary">
                        <span class="material-symbols-outlined text-[20px] text-on-primary">security</span>
                    </div>
                    <span class="font-headline-md text-headline-md font-extrabold text-on-surface">SRS DailyCRM</span>
                </div>
                <p class="max-w-sm font-body-sm text-on-surface-variant">
                    Collections workflow, WhatsApp engagement, audit, and compliance tooling for Strauss Recovery Solutions.
                </p>
                @if(!empty($publicSettings?->support_email) || !empty($publicSettings?->support_phone))
                    <div class="space-y-1 font-body-sm text-on-surface-variant">
                        @if(!empty($publicSettings?->support_email))
                            <div>Privacy and support contact: {{ $publicSettings->support_email }}</div>
                        @endif
                        @if(!empty($publicSettings?->support_phone))
                            <div>Support phone: {{ $publicSettings->support_phone }}</div>
                        @endif
                    </div>
                @endif
                <div class="pt-4 font-body-sm text-body-sm text-on-surface-variant">
                    © 2024 SRS DailyCRM - Strauss Recovery Solutions. All rights reserved.
                </div>
            </div>
            <div class="flex flex-col justify-between md:items-end">
                <div class="flex flex-wrap gap-x-stack-lg gap-y-stack-sm md:justify-end">
                    <a class="font-body-sm text-body-sm text-on-surface-variant underline transition-all hover:text-primary" href="{{ route('public.privacy') }}">Privacy Policy</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant underline transition-all hover:text-primary" href="{{ route('public.compliance') }}">Compliance</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant underline transition-all hover:text-primary" href="{{ route('public.data-deletion') }}">Data Deletion</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant underline transition-all hover:text-primary" href="{{ route('public.terms') }}">Terms of Service</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant underline transition-all hover:text-primary" href="/login">Login</a>
                </div>
                <div class="mt-stack-md flex gap-4 text-on-surface-variant">
                    <span class="material-symbols-outlined cursor-pointer transition-colors hover:text-primary">lock</span>
                    <span class="material-symbols-outlined cursor-pointer transition-colors hover:text-primary">verified_user</span>
                    <span class="material-symbols-outlined cursor-pointer transition-colors hover:text-primary">cloud_done</span>
                </div>
            </div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
