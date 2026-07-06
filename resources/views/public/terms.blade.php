@extends('public.tailwind-layout')

@section('title', 'Terms of Service | SRS DailyCRM')
@section('meta_description', 'Terms of Service for SRS DailyCRM public information pages and authorised business use.')

@section('content')
    <section class="doc-gradient py-stack-xl">
        <div class="mx-auto max-w-container-max px-margin-desktop">
            <div class="mb-stack-lg space-y-stack-md">
                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-high px-3 py-1">
                    <span class="material-symbols-outlined text-[16px] text-primary" style="font-variation-settings: 'FILL' 1;">description</span>
                    <span class="font-label-md text-label-md uppercase tracking-wider text-primary">Terms of Service</span>
                </div>
                <h1 class="max-w-4xl font-headline-lg text-headline-lg text-on-surface md:text-display-lg">Public policy access and authorised business use of SRS DailyCRM.</h1>
                <p class="max-w-3xl font-body-lg text-on-surface-variant">
                    SRS DailyCRM is a business application used for authorised operational purposes by Strauss Recovery
                    Solutions and approved users. The public pages of this site are available for policy, identity,
                    and review purposes only.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
                <div class="space-y-gutter lg:col-span-8">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-6 shadow-sm">
                        <div class="flex flex-col gap-2 font-body-sm text-on-surface-variant md:flex-row md:items-center md:justify-between">
                            <div><strong class="text-on-surface">Last updated:</strong> July 4, 2026</div>
                            <div><strong class="text-on-surface">Public review context:</strong> Meta and stakeholder policy access for SRS DailyCRM</div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">1. Public information pages</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            The public pages of this site are provided to describe the application, its privacy notice,
                            compliance posture, and owning business context. Public access does not grant access to the
                            operational CRM platform.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">2. Authorised use</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Authenticated areas are intended only for approved business users</li>
                            <li>Users must not attempt to access data or functions outside their authorisation</li>
                            <li>Use of the platform is subject to internal business, security, and compliance controls</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">3. Meta and WhatsApp platform use</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>SRS DailyCRM may use Meta platform functionality and the WhatsApp Business Platform for authorised business messaging, delivery tracking, and reply handling</li>
                            <li>Users of the authenticated CRM must comply with applicable business, legal, privacy, and platform restrictions relevant to WhatsApp messaging and customer communications</li>
                            <li>Improper use of Meta or WhatsApp messaging capabilities, including misleading, unauthorised, or abusive use, is prohibited</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">4. Security and restrictions</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Monitoring, audit, and control mechanisms may apply to authenticated use</li>
                            <li>Operational use may be limited by bank, department, role, portfolio, or compliance scope</li>
                            <li>Improper or unauthorised use may result in access restriction or removal</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">5. Privacy and policy relationship</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            Use of SRS DailyCRM and its public pages is subject to the published <a class="text-primary underline" href="{{ route('public.privacy') }}">Privacy Policy</a>
                            and <a class="text-primary underline" href="{{ route('public.compliance') }}">Compliance Overview</a>.
                            Those pages explain how Strauss Recovery Solutions describes the app’s data handling,
                            governance controls, Meta messaging context, and public review information.
                        </p>
                        <p class="mt-4 font-body-md leading-relaxed text-on-surface-variant">
                            Data deletion and rights-request instructions are publicly available on the
                            <a class="text-primary underline" href="{{ route('public.data-deletion') }}">Data Deletion page</a>.
                        </p>
                    </div>
                </div>

                <div class="space-y-gutter lg:col-span-4">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Review purpose</h3>
                        <p class="font-body-sm leading-relaxed text-on-surface-variant">
                            These public pages support external stakeholder, partner, and platform review by making the
                            application identity and key policy pages accessible without redirecting users into the
                            private CRM environment.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Public review links</h3>
                        <div class="grid gap-3">
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.privacy') }}">Privacy Policy</a>
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.compliance') }}">Compliance</a>
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.data-deletion') }}">Data Deletion</a>
                            <a class="rounded-lg bg-primary px-5 py-3 font-button text-on-primary" href="/login">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
