@extends('public.tailwind-layout')

@section('title', 'Compliance Overview | SRS DailyCRM')
@section('meta_description', 'Compliance overview for SRS DailyCRM, Strauss Recovery Solutions, and collections communications workflows.')

@section('content')
    <section class="doc-gradient py-stack-xl">
        <div class="mx-auto max-w-container-max px-margin-desktop">
            <div class="mb-stack-lg space-y-stack-md">
                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-high px-3 py-1">
                    <span class="material-symbols-outlined text-[16px] text-primary" style="font-variation-settings: 'FILL' 1;">gavel</span>
                    <span class="font-label-md text-label-md uppercase tracking-wider text-primary">Compliance Overview</span>
                </div>
                <h1 class="max-w-4xl font-headline-lg text-headline-lg text-on-surface md:text-display-lg">Governance features built into live collections operations.</h1>
                <p class="max-w-3xl font-body-lg text-on-surface-variant">
                    SRS DailyCRM is designed to support regulated collections communication and operational oversight for
                    Strauss Recovery Solutions, with workflows for incidents, complaints, exports, imports, and review.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
                <div class="space-y-gutter lg:col-span-8">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-6 shadow-sm">
                        <div class="flex flex-col gap-2 font-body-sm text-on-surface-variant md:flex-row md:items-center md:justify-between">
                            <div><strong class="text-on-surface">Last updated:</strong> July 4, 2026</div>
                            <div><strong class="text-on-surface">Business context:</strong> Strauss Recovery Solutions collections operations using Meta WhatsApp Business</div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">1. Operational compliance scope</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Collections communication workflow management</li>
                            <li>Auditability of user actions and message activity</li>
                            <li>Security incident and complaint handling workflows</li>
                            <li>Data subject request and retention workflow support</li>
                            <li>Bank-scoped operational segregation</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">2. Platform and messaging controls</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Meta WhatsApp Business direct integration</li>
                            <li>Approved template usage</li>
                            <li>Webhook verification, signature checks, and reply processing</li>
                            <li>Lawful-basis and suppression gating for WhatsApp sends</li>
                            <li>Permission-health validation for production messaging</li>
                        </ul>
                        <p class="mt-4 font-body-md leading-relaxed text-on-surface-variant">
                            These controls exist to support business messaging that is governed, reviewable, and linked
                            to authorised operational processes rather than unmanaged or informal communication.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">3. Governance features available in the system</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Export approval and governed download workflow</li>
                            <li>Security incidents register and event timeline</li>
                            <li>Compliance console for requests, complaints, officers, retention, and transfers</li>
                            <li>Import upload tracking and malware-scan support</li>
                            <li>Role-based administrative separation across operational and review roles</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">4. Meta and WhatsApp compliance controls</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Use of approved business templates for business-initiated WhatsApp messaging</li>
                            <li>Restriction of outbound WhatsApp sends where lawful basis or suppression controls are not met</li>
                            <li>Tracking of inbound WhatsApp replies and message status events in the authorised business workflow</li>
                            <li>Publicly accessible privacy, compliance, and terms pages tied to the owning business and application</li>
                            <li>Operational controls intended to support authorised and non-deceptive business use of Meta platform functionality</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">5. Complaint, incident, and data-rights handling</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            The application includes workflow support for complaints, security incidents, exports,
                            imports, requests, retention activity, and review actions so that sensitive business activity
                            can be traced and escalated through an authorised compliance process.
                        </p>
                        <p class="mt-4 font-body-md leading-relaxed text-on-surface-variant">
                            Related public data deletion instructions are published on the
                            <a class="text-primary underline" href="{{ route('public.data-deletion') }}">Data Deletion page</a>.
                        </p>
                    </div>
                </div>

                <div class="space-y-gutter lg:col-span-4">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Public-facing purpose</h3>
                        <p class="font-body-sm leading-relaxed text-on-surface-variant">
                            This page is provided as a publicly accessible summary of the application’s governance and
                            compliance posture so external stakeholders and platform reviewers can verify the business and
                            app context without requiring authenticated CRM access.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Meta review readiness</h3>
                        <ul class="space-y-3 font-body-sm text-on-surface-variant">
                            <li>This page is public and does not require authentication</li>
                            <li>The page identifies Strauss Recovery Solutions as the owning business</li>
                            <li>The page explains how the app is used with Meta WhatsApp Business messaging</li>
                            <li>The related privacy and terms pages are linked directly below</li>
                        </ul>
                        <div class="mt-6 grid gap-3">
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.privacy') }}">Privacy Policy</a>
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.terms') }}">Terms of Service</a>
                            <a class="rounded-lg bg-primary px-5 py-3 font-button text-on-primary" href="/login">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
