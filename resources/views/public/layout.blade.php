<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SRS DailyCRM')</title>
    <meta name="description" content="@yield('meta_description', 'SRS DailyCRM is a secure collections CRM for Strauss Recovery Solutions with WhatsApp, audit, and compliance controls.')">
    <style>
        :root {
            --ink: #172033;
            --muted: #5f6b85;
            --line: #d7dfef;
            --panel: #ffffff;
            --bg: #eef5ff;
            --navy: #1f275e;
            --blue: #2563eb;
            --blue-2: #dbeafe;
            --green: #0f766e;
            --green-2: #ccfbf1;
            --gold: #b45309;
            --gold-2: #fef3c7;
            --red: #b91c1c;
            --shadow: 0 18px 40px rgba(31, 39, 94, 0.12);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--ink);
        }

        a { color: inherit; text-decoration: none; }
        .shell { width: min(1180px, calc(100% - 32px)); margin: 0 auto; }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(14px);
            background: rgba(248, 251, 255, 0.82);
            border-bottom: 1px solid rgba(215, 223, 239, 0.8);
        }

        .site-header__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand__mark {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            color: #fff;
            font-weight: 800;
            box-shadow: var(--shadow);
        }

        .brand__text strong {
            display: block;
            font-size: 1.05rem;
            letter-spacing: 0.02em;
        }

        .brand__text span {
            display: block;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .site-nav {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .site-nav a:not(.btn) {
            color: var(--muted);
            font-weight: 600;
            padding-bottom: 4px;
            border-bottom: 2px solid transparent;
        }

        .site-nav a:hover { color: var(--ink); }
        .site-nav a.is-active:not(.btn) {
            color: var(--navy);
            border-bottom-color: var(--blue);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 10px 18px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s ease;
        }

        .btn--primary {
            background: #1d4ed8;
            color: #fff;
            box-shadow: 0 4px 12px rgba(29, 78, 216, 0.2);
        }

        .btn--primary:hover { background: #1e40af; transform: translateY(-1px); }
        .btn--ghost {
            border-color: var(--line);
            background: #fff;
            color: var(--ink);
        }
        .btn--ghost:hover { background: #f8fafc; }

        .hero {
            position: relative;
            padding: 0 0 34px;
            overflow: hidden;
        }

        .hero--fullbleed {
            min-height: 760px;
            background:
                linear-gradient(115deg, rgba(10, 18, 42, 0.86) 0%, rgba(18, 36, 94, 0.76) 45%, rgba(13, 110, 128, 0.66) 100%),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1600 900'%3E%3Cdefs%3E%3ClinearGradient id='g1' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop stop-color='%23111f4d' offset='0'/%3E%3Cstop stop-color='%231f4a92' offset='0.45'/%3E%3Cstop stop-color='%230c7489' offset='1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='1600' height='900' fill='url(%23g1)'/%3E%3Cg opacity='0.18'%3E%3Ccircle cx='1240' cy='160' r='210' fill='%23ffffff'/%3E%3Ccircle cx='1390' cy='240' r='120' fill='%23dbeafe'/%3E%3Ccircle cx='210' cy='180' r='165' fill='%23ccfbf1'/%3E%3Ccircle cx='370' cy='280' r='90' fill='%23ffffff'/%3E%3C/g%3E%3Cg opacity='0.16' stroke='%23ffffff' stroke-width='2'%3E%3Cpath d='M110 650C310 470 530 510 730 370C930 230 1160 310 1490 130'/%3E%3Cpath d='M-40 780C240 620 440 640 690 520C980 380 1180 470 1660 250'/%3E%3C/g%3E%3Cg opacity='0.28'%3E%3Crect x='920' y='180' width='420' height='520' rx='34' fill='%23091a3b'/%3E%3Crect x='980' y='240' width='300' height='18' rx='9' fill='%23ffffff'/%3E%3Crect x='980' y='286' width='210' height='12' rx='6' fill='%23cbd5e1'/%3E%3Crect x='980' y='330' width='240' height='90' rx='18' fill='%231d4ed8'/%3E%3Crect x='980' y='448' width='260' height='14' rx='7' fill='%23ffffff'/%3E%3Crect x='980' y='476' width='220' height='14' rx='7' fill='%23bfdbfe'/%3E%3Crect x='980' y='520' width='160' height='58' rx='16' fill='%2314b8a6'/%3E%3C/g%3E%3Cg opacity='0.22'%3E%3Crect x='180' y='420' width='320' height='210' rx='28' fill='%23ffffff'/%3E%3Crect x='220' y='468' width='220' height='16' rx='8' fill='%231d4ed8'/%3E%3Crect x='220' y='506' width='170' height='12' rx='6' fill='%2394a3b8'/%3E%3Crect x='220' y='548' width='110' height='44' rx='14' fill='%230f766e'/%3E%3C/g%3E%3C/svg%3E");
            background-size: cover;
            background-position: center;
        }

        .hero--fullbleed::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 18% 22%, rgba(255,255,255,0.18), transparent 24%),
                radial-gradient(circle at 82% 18%, rgba(255,255,255,0.14), transparent 20%),
                linear-gradient(180deg, rgba(7, 12, 26, 0.12), rgba(7, 12, 26, 0.42));
            pointer-events: none;
        }

        .hero__inner {
            position: relative;
            z-index: 1;
            padding: 68px 0 42px;
        }

        .hero__grid {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
            gap: 28px;
            align-items: center;
        }

        .hero__card, .panel {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .hero__card {
            padding: 34px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(255,255,255,0.97) 0%, rgba(242,247,255,0.94) 100%);
        }

        .hero__slider {
            position: relative;
            min-height: 600px;
        }

        .hero__slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transform: translateX(24px);
            pointer-events: none;
            transition: opacity 0.55s ease, transform 0.55s ease;
            display: flex;
            flex-direction: column;
        }

        .hero__slide.is-active {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }

        .hero__slide::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -90px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.16), transparent 68%);
            pointer-events: none;
        }

        .hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--blue-2);
            color: #1d4ed8;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.84rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        h1, h2, h3 { margin: 0 0 14px; line-height: 1.08; }
        h1 { font-size: clamp(2.3rem, 5vw, 4rem); max-width: 12ch; }
        h2 { font-size: clamp(1.65rem, 2.7vw, 2.4rem); }
        h3 { font-size: 1.1rem; }
        p { margin: 0 0 14px; color: var(--muted); line-height: 1.68; }

        .hero--fullbleed .section-kicker,
        .hero--fullbleed .hero__stack h3,
        .hero--fullbleed .hero__stack p {
            color: #eff6ff;
        }

        .hero--fullbleed .hero__stack p {
            color: rgba(239, 246, 255, 0.86);
        }

        .hero__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .hero__meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 32px;
        }

        .hero__highlights {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 24px;
        }

        .hero__highlight {
            border-radius: 18px;
            border: 1px solid var(--line);
            padding: 16px;
            background: rgba(247, 250, 255, 0.9);
        }

        .hero__highlight strong {
            display: block;
            color: var(--navy);
            margin-bottom: 6px;
            font-size: 0.98rem;
        }

        .hero__controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: auto;
            padding-top: 28px;
        }

        .hero__dots {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hero__dot {
            width: 13px;
            height: 13px;
            border-radius: 50%;
            border: 0;
            padding: 0;
            background: #bfd0f7;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .hero__dot.is-active {
            background: var(--blue);
            transform: scale(1.16);
        }

        .hero__nav {
            display: flex;
            gap: 10px;
        }

        .hero__nav button {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.92);
            color: var(--navy);
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
        }

        .hero__nav button:hover { background: #fff; }

        .metric {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255,255,255,0.74);
            padding: 16px;
        }

        .metric strong {
            display: block;
            font-size: 1.35rem;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .hero__stack {
            display: grid;
            gap: 18px;
            align-content: start;
        }

        .hero__visual {
            position: relative;
            min-height: 640px;
        }

        .hero__visual-main,
        .hero__visual-side {
            position: absolute;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 28px 54px rgba(7, 12, 26, 0.28);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .hero__visual-main {
            inset: 24px 28px 120px 0;
            background:
                linear-gradient(180deg, rgba(6, 12, 28, 0.16), rgba(6, 12, 28, 0.56)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 900 700'%3E%3Cdefs%3E%3ClinearGradient id='heroA' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop stop-color='%23091c3f' offset='0'/%3E%3Cstop stop-color='%231d4ed8' offset='0.54'/%3E%3Cstop stop-color='%2314b8a6' offset='1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='900' height='700' fill='url(%23heroA)'/%3E%3Cg opacity='0.24'%3E%3Ccircle cx='740' cy='120' r='130' fill='%23ffffff'/%3E%3Ccircle cx='180' cy='170' r='95' fill='%23ccfbf1'/%3E%3C/g%3E%3Crect x='118' y='128' width='660' height='420' rx='28' fill='%23f8fbff' opacity='0.96'/%3E%3Crect x='152' y='168' width='196' height='16' rx='8' fill='%231f275e'/%3E%3Crect x='152' y='204' width='308' height='12' rx='6' fill='%236b7280'/%3E%3Crect x='152' y='246' width='246' height='166' rx='20' fill='%231d4ed8'/%3E%3Crect x='426' y='246' width='316' height='70' rx='20' fill='%23e5eefc'/%3E%3Crect x='450' y='272' width='180' height='12' rx='6' fill='%231f275e'/%3E%3Crect x='450' y='296' width='224' height='10' rx='5' fill='%2394a3b8'/%3E%3Crect x='426' y='338' width='316' height='74' rx='20' fill='%23eff6ff'/%3E%3Crect x='450' y='364' width='200' height='12' rx='6' fill='%231f275e'/%3E%3Crect x='450' y='388' width='182' height='10' rx='5' fill='%2394a3b8'/%3E%3Crect x='152' y='440' width='590' height='74' rx='20' fill='%23f4f8fe'/%3E%3Crect x='184' y='466' width='208' height='12' rx='6' fill='%230f766e'/%3E%3Crect x='184' y='490' width='326' height='10' rx='5' fill='%2394a3b8'/%3E%3C/svg%3E");
            background-size: cover;
            background-position: center;
        }

        .hero__visual-side {
            right: 0;
            bottom: 32px;
            width: 290px;
            height: 240px;
            background:
                linear-gradient(180deg, rgba(6, 12, 28, 0.1), rgba(6, 12, 28, 0.34)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 520 420'%3E%3Cdefs%3E%3ClinearGradient id='heroB' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop stop-color='%230a1634' offset='0'/%3E%3Cstop stop-color='%230f766e' offset='1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='520' height='420' fill='url(%23heroB)'/%3E%3Crect x='82' y='60' width='356' height='284' rx='28' fill='%23ffffff' opacity='0.96'/%3E%3Ccircle cx='134' cy='110' r='24' fill='%2314b8a6'/%3E%3Crect x='174' y='96' width='180' height='12' rx='6' fill='%231f275e'/%3E%3Crect x='174' y='122' width='120' height='10' rx='5' fill='%2394a3b8'/%3E%3Crect x='118' y='162' width='280' height='56' rx='18' fill='%23eff6ff'/%3E%3Crect x='144' y='184' width='190' height='10' rx='5' fill='%230f766e'/%3E%3Crect x='164' y='246' width='234' height='58' rx='18' fill='%231d4ed8'/%3E%3Crect x='186' y='270' width='112' height='10' rx='5' fill='%23ffffff'/%3E%3C/svg%3E");
            background-size: cover;
            background-position: center;
        }

        .hero__visual-caption {
            position: absolute;
            left: 28px;
            bottom: 0;
            max-width: 270px;
            border-radius: 24px;
            padding: 20px;
            background: rgba(255,255,255,0.96);
            box-shadow: 0 20px 36px rgba(7, 12, 26, 0.18);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .hero__visual-caption strong {
            display: block;
            color: var(--navy);
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .panel {
            padding: 24px;
            background: rgba(8, 16, 38, 0.42);
            border-color: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(10px);
        }
        .panel--accent { background: rgba(255, 255, 255, 0.14); }

        .badge-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 9px 12px;
            font-size: 0.88rem;
            font-weight: 700;
        }

        .badge--green { background: var(--green-2); color: var(--green); }
        .badge--gold { background: var(--gold-2); color: var(--gold); }
        .badge--blue { background: var(--blue-2); color: #1d4ed8; }
        .badge--red { background: #fee2e2; color: #b91c1c; }

        .hero--fullbleed .badge--blue {
            background: rgba(219, 234, 254, 0.94);
        }

        .hero--fullbleed .badge--green {
            background: rgba(204, 251, 241, 0.94);
        }

        .hero--fullbleed .badge--gold {
            background: rgba(254, 243, 199, 0.94);
        }

        section.page-section { padding: 26px 0 14px; }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }
        
        .section-head--center {
            justify-content: center;
            margin-bottom: 30px;
        }

        .grid-3, .grid-2 {
            display: grid;
            gap: 18px;
        }

        .grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        .feature-card, .content-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(215, 223, 239, 0.92);
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 10px 28px rgba(31, 39, 94, 0.08);
        }

        .feature-card ul, .content-card ul {
            margin: 12px 0 0 18px;
            padding: 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .callout {
            border-left: 4px solid var(--blue);
            padding: 18px 20px;
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--line);
        }

        .content-card table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.95rem;
        }

        .content-card th, .content-card td {
            text-align: left;
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        .site-footer {
            margin-top: 48px;
            padding: 28px 0 42px;
            border-top: 1px solid rgba(215, 223, 239, 0.9);
            background: rgba(255,255,255,0.72);
        }

        .site-footer__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }

        .site-footer__links {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            color: var(--muted);
            font-weight: 600;
        }

        .trust-strip {
            margin-top: 18px;
            margin-bottom: 30px;
        }

        .trust-strip__panel {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            padding: 0;
            background: transparent;
            border: none;
            box-shadow: none;
            max-width: 900px;
            margin: 0 auto;
        }

        .trust-strip__logos,
        .trust-strip__stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .trust-chip,
        .stat-chip {
            border-radius: 999px;
            padding: 12px 16px;
            background: transparent;
            border: none;
            box-shadow: none;
            font-weight: 700;
            color: var(--muted);
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .trust-chip {
            font-weight: 700;
            color: var(--muted);
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .trust-chip svg {
            color: var(--muted);
        }

        .stat-chip strong {
            display: block;
            color: var(--navy);
            font-size: 1rem;
        }

        .stat-chip span {
            color: var(--muted);
            font-size: 0.85rem;
        }

        .feature-band {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(320px, 1.05fr);
            gap: 28px;
            align-items: center;
            margin-bottom: 24px;
        }

        .feature-band--reverse {
            grid-template-columns: minmax(320px, 1.05fr) minmax(0, 0.95fr);
        }

        .feature-band--reverse .feature-band__body { order: 2; }
        .feature-band--reverse .feature-band__media { order: 1; }

        .feature-band__body {
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(215, 223, 239, 0.92);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 14px 28px rgba(31, 39, 94, 0.08);
        }

        .feature-band__eyebrow {
            display: inline-flex;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 14px;
        }
        
        .eyebrow--green { background: var(--green-2); color: var(--green); }
        .eyebrow--blue { background: var(--blue-2); color: #1d4ed8; }

        .feature-list {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .feature-list__item {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 14px;
            align-items: start;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: #f9fbff;
        }

        .feature-list__icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #1d4ed8;
            font-weight: 800;
            background: #dbeafe;
        }

        .feature-list__item strong {
            display: block;
            margin-bottom: 4px;
        }

        .feature-band__media {
            min-height: 480px;
            border-radius: 30px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 18px 36px rgba(31, 39, 94, 0.14);
            border: 1px solid rgba(215,223,239,0.92);
        }

        .feature-band__media--ops {
            background:
                linear-gradient(180deg, rgba(8, 16, 38, 0.12), rgba(8, 16, 38, 0.46)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 900 760'%3E%3Cdefs%3E%3ClinearGradient id='ops' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop stop-color='%23111f4d' offset='0'/%3E%3Cstop stop-color='%232563eb' offset='0.5'/%3E%3Cstop stop-color='%230f766e' offset='1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='900' height='760' fill='url(%23ops)'/%3E%3Ccircle cx='740' cy='122' r='104' fill='%23ffffff' opacity='0.18'/%3E%3Crect x='104' y='110' width='692' height='500' rx='34' fill='%23f8fbff' opacity='0.96'/%3E%3Crect x='144' y='150' width='230' height='16' rx='8' fill='%231f275e'/%3E%3Crect x='144' y='186' width='310' height='12' rx='6' fill='%236b7280'/%3E%3Crect x='144' y='232' width='286' height='186' rx='24' fill='%231d4ed8'/%3E%3Crect x='466' y='232' width='286' height='76' rx='18' fill='%23e8f0fe'/%3E%3Crect x='492' y='258' width='160' height='10' rx='5' fill='%231f275e'/%3E%3Crect x='492' y='280' width='188' height='10' rx='5' fill='%2394a3b8'/%3E%3Crect x='466' y='332' width='286' height='86' rx='18' fill='%23eff6ff'/%3E%3Crect x='492' y='360' width='172' height='10' rx='5' fill='%231f275e'/%3E%3Crect x='492' y='382' width='204' height='10' rx='5' fill='%2394a3b8'/%3E%3Crect x='144' y='454' width='608' height='118' rx='26' fill='%23f4f8fe'/%3E%3Crect x='176' y='488' width='200' height='12' rx='6' fill='%230f766e'/%3E%3Crect x='176' y='514' width='330' height='12' rx='6' fill='%2394a3b8'/%3E%3Crect x='176' y='538' width='292' height='12' rx='6' fill='%2394a3b8'/%3E%3C/svg%3E");
            background-size: cover;
            background-position: center;
        }

        .feature-band__media--whatsapp {
            background:
                linear-gradient(180deg, rgba(8, 16, 38, 0.12), rgba(8, 16, 38, 0.44)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 900 760'%3E%3Cdefs%3E%3ClinearGradient id='wa' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop stop-color='%23061a2a' offset='0'/%3E%3Cstop stop-color='%230f766e' offset='0.52'/%3E%3Cstop stop-color='%2314b8a6' offset='1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='900' height='760' fill='url(%23wa)'/%3E%3Crect x='280' y='92' width='352' height='574' rx='42' fill='%23ffffff' opacity='0.97'/%3E%3Crect x='326' y='146' width='182' height='14' rx='7' fill='%231f275e'/%3E%3Crect x='326' y='176' width='122' height='10' rx='5' fill='%2394a3b8'/%3E%3Crect x='326' y='226' width='244' height='108' rx='24' fill='%23dcfce7'/%3E%3Crect x='348' y='258' width='160' height='10' rx='5' fill='%230f766e'/%3E%3Crect x='348' y='282' width='186' height='10' rx='5' fill='%234b5563'/%3E%3Crect x='370' y='366' width='200' height='108' rx='24' fill='%23dbeafe'/%3E%3Crect x='392' y='398' width='120' height='10' rx='5' fill='%231d4ed8'/%3E%3Crect x='392' y='422' width='150' height='10' rx='5' fill='%234b5563'/%3E%3Crect x='326' y='514' width='246' height='62' rx='18' fill='%230f766e'/%3E%3Crect x='392' y='538' width='112' height='12' rx='6' fill='%23ffffff'/%3E%3C/svg%3E");
            background-size: cover;
            background-position: center;
        }

        .media-float-card {
            position: absolute;
            left: 24px;
            bottom: 24px;
            max-width: 280px;
            border-radius: 24px;
            padding: 20px;
            background: rgba(255,255,255,0.96);
            box-shadow: 0 18px 34px rgba(7,12,26,0.18);
        }

        .media-float-card strong {
            display: block;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .security-pillars {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .pillar-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(215,223,239,0.92);
            border-radius: 24px;
            padding: 22px;
            box-shadow: 0 12px 24px rgba(31,39,94,0.04);
            border-top: 4px solid #dbeafe;
        }
        .pillar-card:nth-child(2) { border-top-color: #ccfbf1; }
        .pillar-card:nth-child(3) { border-top-color: #dbeafe; }
        .pillar-card:nth-child(4) { border-top-color: #ccfbf1; }

        .pillar-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: transparent;
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }

        .pillar-card strong {
            display: block;
            margin-bottom: 10px;
            color: var(--navy);
            font-size: 1.05rem;
        }
        
        .pillar-card p {
            font-size: 0.9rem;
        }

        .callout--center { text-align: center; }
        .callout--important {
            background: #f1f5f9;
            border-color: transparent;
            border-left: none;
            color: #475569;
            padding: 16px 24px;
            font-size: 0.9rem;
            border-radius: 12px;
        }
        .callout--important strong { color: #0f172a; }

        .cta-banner {
            margin-top: 28px;
            background: #1e3a8a;
            color: #fff;
            border-radius: 24px;
            padding: 48px 34px;
            box-shadow: 0 20px 40px rgba(31,39,94,0.16);
            text-align: center;
        }

        .cta-banner p { color: rgba(255,255,255,0.84); max-width: 600px; margin: 16px auto 32px; }
        .cta-actions { justify-content: center; margin-top: 0; }
        .cta-btn--light { background: #fff; color: #1e3a8a; border: none; }
        .cta-btn--dark { background: #1e40af; border: 1px solid #3b82f6; }
        .cta-btn--dark:hover { background: #1d4ed8; }

        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.65s ease, transform 0.65s ease;
        }

        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .page-copy {
            padding: 54px 0 18px;
        }

        .page-copy__card {
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(215, 223, 239, 0.92);
            border-radius: 28px;
            box-shadow: var(--shadow);
            padding: 32px;
        }

        .page-copy__card h2 { margin-top: 28px; }
        .page-copy__card h2:first-of-type { margin-top: 0; }
        .page-copy__card ul, .page-copy__card ol {
            margin: 12px 0 18px 20px;
            color: var(--muted);
            line-height: 1.72;
        }

        .page-copy__card li + li { margin-top: 6px; }

        .section-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 14px;
        }

        .landing-hero {
            padding: 40px 0 18px;
        }

        .landing-hero .shell {
            width: min(1060px, calc(100% - 32px));
        }

        .landing-hero__grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            gap: 36px;
            align-items: center;
        }

        .landing-hero__copy h1 {
            max-width: 14ch;
            font-size: clamp(2.35rem, 4.6vw, 3.55rem);
            line-height: 1.05;
            letter-spacing: -0.02em;
            margin-bottom: 18px;
        }

        .landing-hero__copy p {
            max-width: 42ch;
            font-size: 0.95rem;
        }

        .landing-hero__visual {
            position: relative;
            display: flex;
            justify-content: center;
        }

        .browser-mockup {
            width: min(100%, 548px);
            background: linear-gradient(180deg, #ffffff 0%, #f5f8ff 100%);
            border: 1px solid rgba(215, 223, 239, 0.92);
            border-radius: 24px;
            box-shadow: 0 24px 44px rgba(31, 39, 94, 0.12);
            overflow: hidden;
        }

        .browser-mockup__bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(215, 223, 239, 0.92);
            background: rgba(248, 251, 255, 0.94);
        }

        .browser-mockup__dots {
            display: flex;
            gap: 6px;
        }

        .browser-mockup__dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #c5d0ec;
        }

        .browser-mockup__label {
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .browser-mockup__body {
            padding: 18px;
        }

        .browser-mockup__title {
            height: 16px;
            width: 32%;
            border-radius: 999px;
            background: #dbe4fb;
            margin-bottom: 18px;
        }

        .browser-mockup__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .browser-mockup__tile {
            min-height: 108px;
            border-radius: 18px;
            background: linear-gradient(180deg, #eef3ff 0%, #e1eafc 100%);
            border: 1px solid #d7dfef;
        }

        .browser-mockup__stream {
            min-height: 120px;
            border-radius: 18px;
            border: 1px solid #d7dfef;
            background: linear-gradient(180deg, #f9fbff 0%, #edf3ff 100%);
            display: grid;
            place-items: center;
            color: var(--navy);
            font-weight: 700;
        }

        .operating-band {
            margin-top: 24px;
        }

        .dashboard-frame {
            position: relative;
            min-height: 420px;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(31,39,94,0.12);
            background: #fff;
            border: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .dashboard-frame-inner {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .dashboard-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
            background: #f8fafc;
        }
        
        .dh-title {
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 16px;
        }
        
        .dh-stats {
            display: flex;
            gap: 16px;
        }
        
        .dh-stat {
            flex: 1;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px;
        }
        
        .dh-stat span { display: block; font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; }
        .dh-stat strong { display: block; font-size: 1.1rem; color: var(--navy); }
        
        .dashboard-body {
            padding: 20px;
            display: flex;
            gap: 20px;
            flex: 1;
        }
        
        .db-chart {
            flex: 1.5;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 6px;
            background-image: linear-gradient(transparent 90%, #e2e8f0 90%);
            background-size: 100% 20px;
        }
        
        .db-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .db-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid var(--line);
            font-size: 0.75rem;
        }
        
        .db-name { font-weight: 600; color: var(--navy); }

        .dashboard-note {
            position: absolute;
            left: -12px;
            bottom: 22px;
            max-width: 250px;
            padding: 16px 18px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 18px 34px rgba(31,39,94,0.14);
            border: 1px solid rgba(215,223,239,0.92);
        }
        
        .dashboard-note span {
            display: block;
            margin-top: 6px;
            font-size: 0.85rem;
            color: var(--muted);
            font-style: italic;
        }

        .device-panel {
            position: relative;
            min-height: 460px;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
            display: grid;
            place-items: center;
        }

        .phone-mockup {
            width: 248px;
            height: 472px;
            border-radius: 34px;
            background: #0f172a;
            padding: 14px;
            box-shadow: 0 22px 42px rgba(31,39,94,0.2);
        }

        .phone-mockup__screen {
            height: 100%;
            border-radius: 26px;
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            overflow: hidden;
            border: 1px solid rgba(215,223,239,0.92);
        }

        .phone-mockup__header {
            padding: 14px 16px;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            color: #fff;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .phone-mockup__chat {
            padding: 18px 14px;
            display: grid;
            gap: 12px;
        }

        .phone-mockup__input {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: #fff;
            border-top: 1px solid var(--line);
        }
        
        .phone-mockup__input div {
            flex: 1;
            height: 32px;
            border-radius: 16px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        
        .phone-mockup__input span {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1d4ed8;
            color: #fff;
            display: grid;
            place-items: center;
        }

        .bubble {
            border-radius: 16px;
            padding: 12px 14px;
            font-size: 0.85rem;
            line-height: 1.45;
            max-width: 88%;
        }

        .bubble--in {
            background: #ffffff;
            border: 1px solid #d7dfef;
            color: var(--ink);
        }

        .bubble--out {
            background: #dbeafe;
            color: #153fb8;
            margin-left: auto;
        }

        .stack-cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .stack-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(215,223,239,0.92);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 10px 18px rgba(31,39,94,0.06);
        }

        .stack-card--wide {
            grid-column: 1 / -1;
        }

        .stack-card strong {
            display: block;
            margin-bottom: 8px;
            color: var(--navy);
        }

        .policy-hero {
            padding: 44px 0 12px;
        }

        .policy-hero__grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.8fr);
            gap: 24px;
            align-items: stretch;
        }

        .policy-hero__card,
        .policy-side-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(215,223,239,0.92);
            border-radius: 26px;
            box-shadow: 0 14px 28px rgba(31,39,94,0.08);
        }

        .policy-hero__card {
            padding: 28px;
        }

        .policy-side-card {
            padding: 22px;
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .policy-side-card strong {
            color: var(--navy);
            font-size: 1rem;
        }

        .policy-side-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .policy-side-list li {
            padding: 12px 14px;
            border-radius: 16px;
            background: #f7faff;
            border: 1px solid var(--line);
            color: var(--muted);
            line-height: 1.55;
        }

        .policy-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 22px;
        }

        .policy-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(215,223,239,0.92);
            border-radius: 24px;
            box-shadow: 0 14px 28px rgba(31,39,94,0.08);
            padding: 24px;
        }

        .policy-card + .policy-card {
            margin-top: 18px;
        }

        .policy-card h3 {
            margin-bottom: 10px;
            color: var(--navy);
        }

        .policy-card ul, .policy-card ol {
            margin: 12px 0 0 18px;
            color: var(--muted);
            line-height: 1.7;
        }

        .policy-card li + li {
            margin-top: 6px;
        }

        @media (max-width: 980px) {
            .hero__grid,
            .grid-3,
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .trust-strip__panel,
            .feature-band,
            .feature-band--reverse,
            .security-pillars,
            .landing-hero__grid,
            .policy-hero__grid,
            .policy-grid {
                grid-template-columns: 1fr;
            }

            .hero__meta { grid-template-columns: 1fr; }
            .hero__highlights { grid-template-columns: 1fr; }
            .hero__slider { min-height: 720px; }
            .hero--fullbleed { min-height: auto; }
            .hero__visual {
                min-height: 480px;
            }
            .feature-band--reverse .feature-band__body,
            .feature-band--reverse .feature-band__media {
                order: initial;
            }
            .stack-cards {
                grid-template-columns: 1fr;
            }
            .stack-card--wide {
                grid-column: auto;
            }
            .landing-hero .shell {
                width: min(1180px, calc(100% - 32px));
            }
        }

        @media (max-width: 720px) {
            .site-header__row,
            .section-head,
            .site-footer__row {
                align-items: flex-start;
                flex-direction: column;
            }

            .hero__inner { padding-top: 42px; }
            .hero__card, .panel, .page-copy__card { padding: 22px; }
            .hero__controls {
                align-items: flex-start;
                flex-direction: column;
            }
            .hero__slider { min-height: 820px; }
            .hero__visual {
                min-height: 360px;
            }
            .hero__visual-main {
                inset: 12px 0 98px 0;
            }
            .hero__visual-side {
                width: 220px;
                height: 180px;
                right: 12px;
                bottom: 20px;
            }
            .hero__visual-caption {
                left: 12px;
                right: 84px;
                max-width: none;
            }
            .cta-banner__row {
                align-items: flex-start;
                flex-direction: column;
            }
            .landing-hero__copy h1 {
                max-width: 11ch;
                font-size: clamp(2rem, 8.5vw, 3rem);
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="shell site-header__row" style="display: flex; align-items: center;">
            <a href="{{ route('public.landing') }}" class="brand" style="flex: 1;">
                <span class="brand__mark" style="border-radius: 8px;">NC</span>
                <span class="brand__text">
                    <strong style="font-size: 1.1rem; color: var(--navy);">SRS DailyCRM</strong>
                </span>
            </a>
            <nav class="site-nav" aria-label="Public navigation" style="display: flex; justify-content: center; gap: 24px;">
                <a href="{{ route('public.landing') }}#features" class="js-hash-link" data-section="features">Features</a>
                <a href="{{ route('public.landing') }}#security" class="js-hash-link" data-section="security">Security</a>
                <a href="{{ route('public.compliance') }}" class="{{ request()->routeIs('public.compliance') ? 'is-active' : '' }}">Compliance</a>
                <a href="{{ route('public.privacy') }}" class="{{ request()->routeIs('public.privacy') ? 'is-active' : '' }}">Privacy Policy</a>
                <a href="{{ route('public.terms') }}" class="{{ request()->routeIs('public.terms') ? 'is-active' : '' }}">Terms</a>
            </nav>
            <div style="flex: 1; display: flex; justify-content: flex-end;">
                <a href="/login" class="btn btn--primary">Login</a>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="shell site-footer__row">
            <div>
                <strong>SRS DailyCRM</strong><br>
                <span style="color: var(--muted);">Collections workflow, WhatsApp engagement, audit, and compliance tooling for Strauss Recovery Solutions.</span>
            </div>
            <div class="site-footer__links">
                <a href="{{ route('public.privacy') }}">Privacy Policy</a>
                <a href="{{ route('public.compliance') }}">Compliance</a>
                <a href="{{ route('public.terms') }}">Terms of Service</a>
                <a href="/login">Login</a>
            </div>
        </div>
    </footer>
    <script>
        (function () {
            const hashLinks = Array.from(document.querySelectorAll('.js-hash-link'));
            if (!hashLinks.length || window.location.pathname !== '{{ route('public.landing', [], false) }}') return;

            const setActive = (sectionId) => {
                hashLinks.forEach((link) => {
                    link.classList.toggle('is-active', link.dataset.section === sectionId);
                });
            };

            const sections = ['features', 'security']
                .map((id) => document.getElementById(id))
                .filter(Boolean);

            const updateFromHash = () => {
                const sectionId = window.location.hash ? window.location.hash.replace('#', '') : 'features';
                setActive(sectionId);
            };

            if ('IntersectionObserver' in window && sections.length) {
                const observer = new IntersectionObserver((entries) => {
                    const visible = entries
                        .filter((entry) => entry.isIntersecting)
                        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

                    if (visible?.target?.id) {
                        setActive(visible.target.id);
                    }
                }, { threshold: 0.3 });

                sections.forEach((section) => observer.observe(section));
            } else {
                updateFromHash();
            }

            window.addEventListener('hashchange', updateFromHash);
            updateFromHash();
        })();
    </script>
    @stack('scripts')
</body>
</html>
