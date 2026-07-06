<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SRS DailyCRM | Secure Data Management &amp; Compliance</title>
    <meta name="description" content="Public information page for SRS DailyCRM, including security, compliance, and privacy information for Strauss Recovery Solutions and Meta WhatsApp Business usage."/>
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
        .animate-subtle-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
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
                <a class="border-b-2 border-primary pb-1 font-body-md text-body-md font-bold text-primary transition-colors hover:text-primary" href="#features">Features</a>
                <a class="font-body-md text-body-md text-on-surface-variant transition-colors hover:text-primary" href="#security">Security</a>
                <a class="font-body-md text-body-md text-on-surface-variant transition-colors hover:text-primary" href="{{ route('public.compliance') }}">Compliance</a>
                <a class="font-body-md text-body-md text-on-surface-variant transition-colors hover:text-primary" href="{{ route('public.privacy') }}">Privacy Policy</a>
                <a class="font-body-md text-body-md text-on-surface-variant transition-colors hover:text-primary" href="{{ route('public.terms') }}">Terms</a>
            </div>
            <a class="rounded bg-primary-container px-6 py-2 font-button text-white transition-opacity hover:opacity-90" href="/login">Login</a>
        </div>
    </nav>

    <main class="pt-16">
        <section class="hero-gradient relative flex min-h-[819px] items-center overflow-hidden py-stack-xl">
            <div class="mx-auto grid max-w-container-max grid-cols-1 items-center gap-stack-lg px-margin-desktop md:grid-cols-2">
                <div class="z-10 space-y-stack-md">
                    <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-high px-3 py-1">
                        <span class="material-symbols-outlined text-[16px] text-primary" style="font-variation-settings: 'FILL' 1;">verified_user</span>
                        <span class="font-label-md text-label-md uppercase tracking-wider text-primary">Security Controls</span>
                    </div>
                    <h1 class="max-w-xl font-display-lg text-display-lg text-on-surface">
                        Built around access control, auditability, governed exports, and security oversight.
                    </h1>
                    <p class="max-w-lg font-body-lg text-body-lg text-on-surface-variant">
                        A sentinel-grade platform designed for high-stakes debt recovery environments where compliance isn't just a feature—it's the foundation.
                    </p>
                    <div class="flex gap-stack-md pt-stack-sm">
                        <a class="flex items-center gap-2 rounded-lg bg-primary px-8 py-4 font-button text-on-primary shadow-lg shadow-primary/20 transition-transform hover:scale-[1.02]" href="{{ route('public.compliance') }}">
                            Explore Governance
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </a>
                        <a class="rounded-lg border border-outline px-8 py-4 font-button text-on-surface transition-colors hover:bg-surface-container-low" href="{{ route('public.compliance') }}">
                            View Compliance Docs
                        </a>
                    </div>
                </div>
                <div class="relative hidden md:block">
                    <div class="relative z-10 animate-subtle-float">
                        <div class="glass-card overflow-hidden rounded-xl border border-outline-variant shadow-2xl">
                            <div class="mb-stack-md flex items-center justify-between border-b border-outline-variant/30 px-6 pb-stack-sm pt-6">
                                <div class="flex gap-2">
                                    <div class="h-3 w-3 rounded-full bg-error"></div>
                                    <div class="h-3 w-3 rounded-full bg-secondary-container"></div>
                                    <div class="h-3 w-3 rounded-full bg-primary-container"></div>
                                </div>
                                <span class="font-label-md text-label-md text-on-surface-variant">OPERATIONAL_VIEW_V4</span>
                            </div>
                            <div class="space-y-4 p-6">
                                <div class="h-8 w-3/4 animate-pulse rounded-lg bg-surface-container-high"></div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="h-24 rounded-lg border border-primary/20 bg-primary-container/10"></div>
                                    <div class="h-24 rounded-lg bg-surface-container-highest"></div>
                                    <div class="h-24 rounded-lg bg-surface-container-highest"></div>
                                </div>
                                <div class="flex h-32 items-center justify-center rounded-lg border border-outline-variant bg-surface-container-low">
                                    <div class="text-center">
                                        <span class="material-symbols-outlined mb-2 text-4xl text-primary">analytics</span>
                                        <p class="font-label-md text-on-surface-variant">Live Audit Stream</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -right-10 -top-10 h-64 w-64 rounded-full bg-primary-container/10 blur-3xl"></div>
                    <div class="absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-secondary-container/20 blur-2xl"></div>
                </div>
            </div>
        </section>

        <div class="border-y border-outline-variant/30 bg-surface-container-lowest py-8">
            <div class="mx-auto flex max-w-container-max flex-wrap items-center justify-between gap-gutter px-margin-desktop opacity-60">
                <span class="flex items-center gap-2 font-label-md text-on-surface"><span class="material-symbols-outlined">shield</span> BANK-LEVEL SECURITY</span>
                <span class="flex items-center gap-2 font-label-md text-on-surface"><span class="material-symbols-outlined">description</span> AUDIT-READY LOGS</span>
                <span class="flex items-center gap-2 font-label-md text-on-surface"><span class="material-symbols-outlined">sync</span> SOC2 TYPE II COMPLIANT</span>
                <span class="flex items-center gap-2 font-label-md text-on-surface"><span class="material-symbols-outlined">group</span> ROLE-BASED ACCESS</span>
            </div>
        </div>

        <section class="bg-white py-stack-xl" id="features">
            <div class="mx-auto grid max-w-container-max grid-cols-1 items-center gap-gutter px-margin-desktop lg:grid-cols-12">
                <div class="space-y-stack-md lg:col-span-5">
                    <div class="mb-2 inline-block rounded bg-secondary-container/30 px-3 py-1 font-label-md uppercase tracking-widest text-secondary">Collections Operations</div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">Designed for debtor portfolio follow-up without losing control of data visibility.</h2>
                    <p class="font-body-md leading-relaxed text-on-surface-variant">
                        SRS DailyCRM provides a centralized cockpit for managing complex recovery workflows while strictly enforcing "need-to-know" data visibility.
                    </p>
                    <div class="space-y-stack-md pt-stack-sm">
                        <div class="group flex items-start gap-4 rounded-xl border border-outline-variant p-4 transition-colors hover:border-primary">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container-high font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-white">1</div>
                            <div>
                                <h4 class="font-button text-on-surface">Portfolio and campaign management</h4>
                                <p class="font-body-sm text-on-surface-variant">Manage debtors, campaigns, and automated follow-ups from a unified governance console.</p>
                            </div>
                        </div>
                        <div class="group flex items-start gap-4 rounded-xl border border-outline-variant p-4 transition-colors hover:border-primary">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container-high font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-white">2</div>
                            <div>
                                <h4 class="font-button text-on-surface">Bank-aware segregation</h4>
                                <p class="font-body-sm text-on-surface-variant">Operational records and reports strictly respect bank scope and assigned organizational silos.</p>
                            </div>
                        </div>
                        <div class="group flex items-start gap-4 rounded-xl border border-outline-variant p-4 transition-colors hover:border-primary">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container-high font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-white">3</div>
                            <div>
                                <h4 class="font-button text-on-surface">Operational oversight</h4>
                                <p class="font-body-sm text-on-surface-variant">Compliance reviews and import approvals are natively woven into the live operating model.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative lg:col-span-7">
                    <div class="overflow-hidden rounded-2xl border border-outline-variant shadow-2xl">
                        <img class="aspect-[4/3] w-full object-cover" alt="Debt recovery dashboard interface" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC7XuL0c8ARqE1TiO2B_rISJ1dyKNJVQWN9dpi0pvI6k_DD9cB6Vu1bbRPf4LMyLi-s3X0IwX_W_pI-eCIVNG95a90-sX7v2CydtarBP2w2S7cm51LfXLw9X5X8X_DFysh-_EkI75AUigrS3Oc05Jl5MPM9rkwLHZIMeRpb5peNfclgBL6dQnJoiyOwz_llShWGDYGytu0TMDznYQr_5bBuajEPge_k9squu8O_g_8TKXmYAl1LZpcY"/>
                    </div>
                    <div class="glass-card absolute -bottom-8 -left-8 hidden max-w-xs rounded-xl p-6 shadow-lg md:block">
                        <div class="mb-2 flex items-center gap-3">
                            <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <span class="font-label-md text-on-surface">Compliance Verified</span>
                        </div>
                        <p class="font-body-sm text-on-surface-variant">"SRS DailyCRM unified our workflows without a single data breach in 24 months."</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-surface-container-low py-stack-xl">
            <div class="mx-auto max-w-container-max px-margin-desktop">
                <div class="grid grid-cols-1 items-center gap-gutter lg:grid-cols-12">
                    <div class="space-y-stack-md lg:order-2 lg:col-span-6">
                        <div class="mb-2 inline-block rounded bg-primary-container/10 px-3 py-1 font-label-md uppercase tracking-widest text-primary">Meta Direct Messaging</div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Approved WhatsApp messaging and inbound reply handling in a governed collections environment.</h2>
                        <div class="grid grid-cols-1 gap-stack-md pt-stack-md md:grid-cols-2">
                            <div class="rounded-xl border border-outline-variant/50 bg-surface p-6">
                                <span class="material-symbols-outlined mb-4 text-3xl text-primary">forum</span>
                                <h4 class="mb-2 font-button text-on-surface">Template-driven outbound</h4>
                                <p class="font-body-sm text-on-surface-variant">Only Meta-approved templates are accessible, ensuring brand safety and legal compliance on every message sent.</p>
                            </div>
                            <div class="rounded-xl border border-outline-variant/50 bg-surface p-6">
                                <span class="material-symbols-outlined mb-4 text-3xl text-primary">move_to_inbox</span>
                                <h4 class="mb-2 font-button text-on-surface">Inbound reply visibility</h4>
                                <p class="font-body-sm text-on-surface-variant">Full audit trail for incoming customer responses, linked directly to debtor records and compliance workflows.</p>
                            </div>
                            <div class="rounded-xl border border-outline-variant/50 bg-surface p-6 md:col-span-2">
                                <div class="flex items-center gap-4">
                                    <span class="material-symbols-outlined text-3xl text-primary">policy</span>
                                    <div>
                                        <h4 class="font-button text-on-surface">Public policy access</h4>
                                        <p class="font-body-sm text-on-surface-variant">Compliance and opt-out terms remain publicly accessible without requiring agent authentication.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="relative p-10 lg:order-1 lg:col-span-6">
                        <div class="absolute inset-0 rotate-1 rounded-3xl bg-secondary p-1 opacity-5"></div>
                        <div class="glass-card relative z-10 mx-auto aspect-[9/16] max-w-[320px] overflow-hidden rounded-[2.5rem] border-8 border-inverse-surface p-4 shadow-2xl">
                            <div class="flex h-full flex-col">
                                <div class="flex items-center gap-3 bg-surface-container-high p-4">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white">
                                        <span class="material-symbols-outlined">support_agent</span>
                                    </div>
                                    <div>
                                        <div class="text-xs font-button text-on-surface">SRS DailyCRM Verified</div>
                                        <div class="flex items-center gap-1 text-[10px] text-secondary">
                                            <span class="material-symbols-outlined text-[10px]">lock</span> Secured
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-grow space-y-4 overflow-y-auto bg-surface/50 p-4">
                                    <div class="max-w-[85%] rounded-2xl rounded-tl-none border border-outline-variant/30 bg-white p-3 text-xs text-on-surface-variant shadow-sm">
                                        Hello! This is a secure reminder regarding your account. Reply YES to continue.
                                    </div>
                                    <div class="ml-auto max-w-[85%] rounded-2xl rounded-tr-none bg-primary-container p-3 text-xs text-white shadow-sm">
                                        I need to setup a payment plan.
                                    </div>
                                    <div class="max-w-[85%] rounded-2xl rounded-tl-none border border-outline-variant/30 bg-white p-3 text-xs italic text-on-surface-variant shadow-sm">
                                        <span class="material-symbols-outlined align-middle text-[10px]">verified</span> Compliance approved template sent.
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 border-t border-outline-variant/30 bg-surface-container-low p-4">
                                    <div class="h-8 flex-grow rounded-full border border-outline-variant/50 bg-white"></div>
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white">
                                        <span class="material-symbols-outlined text-sm">send</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-stack-xl" id="security">
            <div class="mx-auto max-w-container-max px-margin-desktop">
                <div class="mb-stack-xl space-y-stack-sm text-center">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">Security and compliance built into the operating model.</h2>
                    <p class="mx-auto max-w-2xl font-body-lg text-on-surface-variant">The platform is structured around zero-trust access control, full reviewability, and governed data movement.</p>
                </div>
                <div class="grid grid-cols-1 gap-gutter md:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border-t-4 border-primary bg-surface-container-lowest p-8 shadow-sm transition-shadow hover:shadow-md">
                        <span class="material-symbols-outlined mb-6 text-4xl text-primary">vpn_key</span>
                        <h3 class="mb-4 font-headline-md text-[18px]">Access and authentication</h3>
                        <p class="font-body-sm text-on-surface-variant">Privileged-user MFA, account lockout, session timeout enforcement, and strictly enforced role-aware separation.</p>
                    </div>
                    <div class="rounded-xl border-t-4 border-secondary bg-surface-container-lowest p-8 shadow-sm transition-shadow hover:shadow-md">
                        <span class="material-symbols-outlined mb-6 text-4xl text-secondary">visibility_off</span>
                        <h3 class="mb-4 font-headline-md text-[18px]">Data visibility controls</h3>
                        <p class="font-body-sm text-on-surface-variant">Bank-aware segregation, portfolio-aware restrictions, and masked sensitive values in standard operational views.</p>
                    </div>
                    <div class="rounded-xl border-t-4 border-primary bg-surface-container-lowest p-8 shadow-sm transition-shadow hover:shadow-md">
                        <span class="material-symbols-outlined mb-6 text-4xl text-primary">history_edu</span>
                        <h3 class="mb-4 font-headline-md text-[18px]">Audit and approvals</h3>
                        <p class="font-body-sm text-on-surface-variant">Export request approval, activity logging, incident workflows, and governed review paths for sensitive operational actions.</p>
                    </div>
                    <div class="rounded-xl border-t-4 border-secondary bg-surface-container-lowest p-8 shadow-sm transition-shadow hover:shadow-md">
                        <span class="material-symbols-outlined mb-6 text-4xl text-secondary">security_update_good</span>
                        <h3 class="mb-4 font-headline-md text-[18px]">Platform validation</h3>
                        <p class="font-body-sm text-on-surface-variant">Meta permission checks, webhook controls, lawful-basis gating, suppression handling, and import safeguards.</p>
                    </div>
                </div>
                <div class="mt-stack-lg rounded-lg border border-outline-variant bg-surface-container-high p-6 text-center">
                    <p class="font-body-sm text-on-surface-variant">
                        <span class="font-bold text-on-surface">Important:</span> This public landing page is intended for app identification, policy visibility, and stakeholder review. Operational security controls remain enforced within the authenticated platform itself.
                    </p>
                </div>
            </div>
        </section>

        <section class="mb-stack-xl py-stack-xl">
            <div class="mx-auto max-w-container-max px-margin-desktop">
                <div class="relative overflow-hidden rounded-[2rem] bg-primary-container p-stack-lg text-center text-on-primary shadow-2xl md:p-stack-xl">
                    <div class="pointer-events-none absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
                    <div class="relative z-10 space-y-stack-md">
                        <h2 class="font-headline-lg text-headline-lg">Need the public policy and verification links?</h2>
                        <p class="mx-auto max-w-2xl font-body-lg text-on-primary-container">
                            Use the links below to access the public-facing Privacy Policy, Compliance Overview, and Terms pages directly. These pages are available without sign-in for external review and Meta platform verification.
                            Data deletion instructions are also available publicly.
                        </p>
                        <div class="flex flex-wrap justify-center gap-stack-md pt-stack-md">
                            <a class="flex items-center gap-2 rounded-xl bg-surface-container-lowest px-8 py-4 font-button text-primary shadow-lg transition-colors hover:bg-surface" href="{{ route('public.privacy') }}">
                                <span class="material-symbols-outlined">policy</span>
                                Privacy Policy
                            </a>
                            <a class="flex items-center gap-2 rounded-xl border border-on-primary/30 bg-primary px-8 py-4 font-button text-on-primary transition-colors hover:bg-primary/80" href="{{ route('public.compliance') }}">
                                <span class="material-symbols-outlined">gavel</span>
                                Compliance
                            </a>
                            <a class="flex items-center gap-2 rounded-xl border border-on-primary/30 bg-primary px-8 py-4 font-button text-on-primary transition-colors hover:bg-primary/80" href="{{ route('public.data-deletion') }}">
                                <span class="material-symbols-outlined">delete</span>
                                Data Deletion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observerOptions = { threshold: 0.1 };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('opacity-100', 'translate-y-0');
                        entry.target.classList.remove('opacity-0', 'translate-y-10');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.bg-surface-container-lowest').forEach((el) => {
                el.classList.add('transition-all', 'duration-700', 'opacity-0', 'translate-y-10');
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
