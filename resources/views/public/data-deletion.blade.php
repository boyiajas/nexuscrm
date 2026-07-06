@extends('public.tailwind-layout')

@section('title', 'Data Deletion Instructions | SRS DailyCRM')
@section('meta_description', 'Public data deletion and rights-request instructions for SRS DailyCRM and Strauss Recovery Solutions.')

@section('content')
    <section class="doc-gradient py-stack-xl">
        <div class="mx-auto max-w-container-max px-margin-desktop">
            <div class="mb-stack-lg space-y-stack-md">
                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-high px-3 py-1">
                    <span class="material-symbols-outlined text-[16px] text-primary" style="font-variation-settings: 'FILL' 1;">delete</span>
                    <span class="font-label-md text-label-md uppercase tracking-wider text-primary">Data Deletion Instructions</span>
                </div>
                <h1 class="max-w-4xl font-headline-lg text-headline-lg text-on-surface md:text-display-lg">Public instructions for deletion, access, correction, and opt-out requests.</h1>
                <p class="max-w-3xl font-body-lg text-on-surface-variant">
                    This page explains how requests relating to deletion, correction, access, and communication
                    suppression may be raised in relation to SRS DailyCRM, Strauss Recovery Solutions, and authorised
                    collections workflows that use Meta WhatsApp Business messaging.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-gutter lg:grid-cols-12">
                <div class="space-y-gutter lg:col-span-8">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-6 shadow-sm">
                        <div class="flex flex-col gap-2 font-body-sm text-on-surface-variant md:flex-row md:items-center md:justify-between">
                            <div><strong class="text-on-surface">Last updated:</strong> July 4, 2026</div>
                            <div><strong class="text-on-surface">Owning business:</strong> {{ $publicSettings?->company_name ?: 'Strauss Recovery Solutions' }}</div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">1. Types of requests supported</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Requests to access information held in relation to an authorised collections matter</li>
                            <li>Requests to correct inaccurate or incomplete information</li>
                            <li>Requests to object to certain communications, including WhatsApp contact where applicable</li>
                            <li>Requests for suppression or opt-out from future WhatsApp messaging</li>
                            <li>Requests for deletion where deletion is legally and operationally permitted</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">2. How requests are handled</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            SRS DailyCRM is used by Strauss Recovery Solutions as an authorised operating platform. In many
                            cases, the responsible banking client or account owner remains the primary controller of the
                            underlying debtor information. Because of that, deletion, access, correction, or objection
                            requests may be handled directly by Strauss Recovery Solutions, routed to the relevant bank,
                            or handled jointly with an authorised compliance contact.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">3. WhatsApp-specific deletion and opt-out requests</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            If you no longer wish to receive WhatsApp messages associated with an authorised collections
                            workflow, you may use the opt-out method provided in the relevant communication, reply using
                            a recognised stop instruction where supported, or raise the request through the business or
                            bank associated with the account.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">4. Deletion limitations</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            Deletion may be restricted where records must be retained for lawful servicing, dispute
                            handling, audit, fraud-prevention, regulatory, or contractual reasons. In those cases,
                            records may be restricted, archived, or suppressed instead of fully deleted.
                        </p>
                    </div>
                </div>

                <div class="space-y-gutter lg:col-span-4">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">How to contact us</h3>
                        <div class="space-y-3 font-body-sm text-on-surface-variant">
                            <div><strong class="text-on-surface">Business:</strong> {{ $publicSettings?->company_name ?: 'Strauss Recovery Solutions' }}</div>
                            @if(!empty($publicSettings?->support_email))
                                <div><strong class="text-on-surface">Support email:</strong> {{ $publicSettings->support_email }}</div>
                            @endif
                            @if(!empty($publicSettings?->support_phone))
                                <div><strong class="text-on-surface">Support phone:</strong> {{ $publicSettings->support_phone }}</div>
                            @endif
                            @if(empty($publicSettings?->support_email) && empty($publicSettings?->support_phone))
                                <div>Use the official Strauss Recovery Solutions contact channel or the relevant authorised bank contact linked to the account matter.</div>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Related public pages</h3>
                        <div class="grid gap-3">
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.privacy') }}">Privacy Policy</a>
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.compliance') }}">Compliance</a>
                            <a class="rounded-lg border border-outline bg-surface-container-lowest px-5 py-3 font-button text-on-surface" href="{{ route('public.terms') }}">Terms of Service</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
