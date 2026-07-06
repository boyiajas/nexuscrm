@extends('public.tailwind-layout')

@section('title', 'Privacy Policy | SRS DailyCRM')
@section('meta_description', 'Privacy Policy for SRS DailyCRM and Strauss Recovery Solutions collections CRM operations.')

@section('content')
    <section class="doc-gradient py-stack-xl">
        <div class="mx-auto max-w-container-max px-margin-desktop">
            <div class="mb-stack-lg space-y-stack-md">
                <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/50 bg-surface-container-high px-3 py-1">
                    <span class="material-symbols-outlined text-[16px] text-primary" style="font-variation-settings: 'FILL' 1;">policy</span>
                    <span class="font-label-md text-label-md uppercase tracking-wider text-primary">Privacy Policy</span>
                </div>
                <h1 class="max-w-4xl font-headline-lg text-headline-lg text-on-surface md:text-display-lg">How SRS DailyCRM processes and protects collections data.</h1>
                <p class="max-w-3xl font-body-lg text-on-surface-variant">
                    This Privacy Policy explains how SRS DailyCRM is used by Strauss Recovery Solutions to process, protect,
                    and govern information linked to authorised collections, debtor follow-up, and Meta WhatsApp
                    Business engagement.
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
                        <h2 class="mb-4 font-headline-md text-on-surface">1. Business identity and scope</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            This Privacy Policy applies to <strong>SRS DailyCRM</strong>, a collections operations platform
                            used by <strong>Strauss Recovery Solutions</strong> for authorised debtor follow-up,
                            campaign management, complaint handling, audit workflows, and communications through the
                            Meta WhatsApp Business Platform. This public policy page is published for customers,
                            banking partners, regulators, and Meta platform reviewers so that the owning business,
                            the application purpose, and the information-handling practices can be identified without
                            requiring login access.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">2. Information processed</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Contact information such as names, email addresses, and phone numbers</li>
                            <li>Debtor or account-reference data supplied by authorised banking clients</li>
                            <li>Communication history, including campaign, email, SMS, and WhatsApp records</li>
                            <li>User account, login, audit, and compliance workflow information</li>
                            <li>Import, export, incident, and review metadata required for governance and traceability</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">3. Purpose of processing</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>Portfolio management and collections follow-up</li>
                            <li>Operational communication with authorised debtors</li>
                            <li>Audit, security, compliance, and incident tracking</li>
                            <li>Performance of lawful and contractually authorised servicing activities</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">4. Meta and WhatsApp Business communications</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            Business-initiated WhatsApp messaging is sent through the Meta WhatsApp Business Platform
                            using approved templates and governed sender controls. Message and reply records are
                            retained for service continuity, auditability, and operational follow-up. SRS DailyCRM uses
                            Meta platform functionality to send business messages, receive replies, track delivery or
                            failure events, and associate those events with authorised collections workflows.
                        </p>
                        <p class="mt-4 font-body-md leading-relaxed text-on-surface-variant">
                            Where required, WhatsApp outreach is limited by lawful-basis controls, opt-out or
                            suppression handling, and approved template restrictions. Recipients may stop future
                            WhatsApp contact by following the opt-out instructions provided in the relevant channel or
                            by contacting the authorised business or bank associated with the account.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">5. How information may be shared</h2>
                        <ul class="space-y-3 font-body-md text-on-surface-variant">
                            <li>With authorised banking clients or account owners that instruct Strauss Recovery Solutions to manage collections activity</li>
                            <li>With Meta Platforms technologies where WhatsApp Business messaging, delivery, replies, or platform events are required</li>
                            <li>With infrastructure, hosting, logging, or support providers that are used to operate the platform under confidentiality and security controls</li>
                            <li>Where required by applicable law, regulatory process, lawful instruction, or formal dispute handling</li>
                        </ul>
                        <p class="mt-4 font-body-md leading-relaxed text-on-surface-variant">
                            Personal information processed through SRS DailyCRM is not published for public use and is not
                            sold as part of the platform’s ordinary collections operation.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">6. Data retention, deletion, and rights requests</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            Records are retained according to contractual, operational, regulatory, and dispute-handling
                            requirements applicable to the relevant collections instruction. Where deletion, correction,
                            objection, opt-out, or access requests are received, Strauss Recovery Solutions may route
                            the request to the responsible banking client or authorised compliance contact where that
                            party is the responsible data owner.
                        </p>
                        <ul class="mt-4 space-y-3 font-body-md text-on-surface-variant">
                            <li>Data access or correction requests may be handled through the responsible bank or authorised compliance channel</li>
                            <li>WhatsApp opt-out or suppression requests may be recorded and enforced in the platform</li>
                            <li>Deletion requests are evaluated against lawful retention, audit, and dispute obligations</li>
                        </ul>
                        <p class="mt-4 font-body-md leading-relaxed text-on-surface-variant">
                            Public data deletion instructions are available on the
                            <a class="text-primary underline" href="{{ route('public.data-deletion') }}">Data Deletion page</a>.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">7. Security safeguards</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            SRS DailyCRM includes access-control, review, and governance mechanisms intended to protect
                            business and customer information. These safeguards may include role-based access controls,
                            bank-aware segregation, masked views of sensitive values, audit logging, governed export
                            workflows, incident tracking, session controls, and protected configuration for Meta
                            integrations.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h2 class="mb-4 font-headline-md text-on-surface">8. Contact and ownership context</h2>
                        <p class="font-body-md leading-relaxed text-on-surface-variant">
                            This application and these public policy pages belong to <strong>Strauss Recovery
                            Solutions</strong>. If you need to raise a privacy concern, opt-out concern, or policy
                            question related to SRS DailyCRM or the associated authorised communications, you should use the
                            official Strauss Recovery Solutions business contact channel or the relevant authorised bank
                            or account owner associated with the underlying matter.
                        </p>
                        @if(!empty($publicSettings?->support_email) || !empty($publicSettings?->support_phone))
                            <div class="mt-4 rounded-xl border border-outline-variant/40 bg-surface-container-low p-4 font-body-sm text-on-surface-variant">
                                @if(!empty($publicSettings?->support_email))
                                    <div><strong class="text-on-surface">Support email:</strong> {{ $publicSettings->support_email }}</div>
                                @endif
                                @if(!empty($publicSettings?->support_phone))
                                    <div><strong class="text-on-surface">Support phone:</strong> {{ $publicSettings->support_phone }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-gutter lg:col-span-4">
                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-lowest p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Meta-facing policy checklist</h3>
                        <ul class="space-y-3 font-body-sm text-on-surface-variant">
                            <li>Publicly accessible privacy policy page</li>
                            <li>Owning business and app purpose clearly identified</li>
                            <li>Meta WhatsApp Business usage expressly described</li>
                            <li>Opt-out and rights-handling context described</li>
                            <li>Information sharing, retention, security safeguards, and deletion instructions described</li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/50 bg-surface-container-high p-8 shadow-sm">
                        <h3 class="mb-4 font-headline-md text-on-surface">Public review purpose</h3>
                        <p class="font-body-sm leading-relaxed text-on-surface-variant">
                            This page is published so external reviewers, partners, and platform providers can confirm
                            that the application has a publicly accessible privacy policy linked to the business and app context.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a class="rounded-lg bg-primary px-5 py-3 font-button text-on-primary" href="{{ route('public.compliance') }}">Compliance</a>
                            <a class="rounded-lg border border-outline px-5 py-3 font-button text-on-surface" href="/login">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
