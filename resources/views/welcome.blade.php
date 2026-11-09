<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Selamat datang'])
        <meta name="description" content="MADIKA — Madrasah Digital Kita. Absensi, persuratan, dan administrasi madrasah dalam satu aplikasi.">
        <style>
            .madika-welcome { min-height: 100svh; margin: 0; background: #f1faf6; color: #07583f; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
            .madika-shell { width: min(100% - 40px, 1080px); margin: auto; }
            .madika-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 28px 0; }
            .madika-header .madika-school-logo { width: 48px; height: 48px; object-fit: contain; flex-shrink: 0; }
            .madika-identity { display: flex; align-items: center; gap: 12px; }
            .madika-header img { width: 210px; height: auto; }
            .madika-header a { color: #07583f; font-weight: 600; }
            .madika-main { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 64px; padding: 48px 0 72px; }
            .madika-hero-brands { display: flex; align-items: center; justify-content: center; gap: 24px; min-width: 0; }
            .madika-hero-brands .madika-logo { width: 55%; margin: 0; }
            .madika-hero-school { display: block; width: 39%; height: auto; object-fit: contain; }
            .madika-logo { display: block; width: min(100%, 420px); height: auto; margin: auto; }
            .madika-eyebrow { font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
            .madika-main h1 { font-size: clamp(32px, 4vw, 48px); line-height: 1.15; letter-spacing: -.03em; font-weight: 700; margin: 18px 0; }
            .madika-copy { color: #4b665d; font-size: 17px; line-height: 1.7; margin: 0 0 24px; }
            .madika-services { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 30px; padding: 0; list-style: none; }
            .madika-services li { background: white; border: 1px solid #d5e8de; border-radius: 999px; padding: 8px 14px; font-size: 14px; font-weight: 600; }
            .madika-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 12px 24px; border-radius: 10px; background: #07583f; color: white; font-weight: 600; text-decoration: none; }
            .madika-button:hover { background: #064630; }
            .madika-welcome a:focus-visible { outline: 3px solid #f5bf20; outline-offset: 5px; }
            .madika-footer { border-top: 1px solid #d5e8de; padding: 24px 0; color: #4b665d; font-size: 13px; }
            @media (max-width: 720px) { .madika-header { padding: 20px 0; } .madika-header img { width: 175px; } .madika-main { grid-template-columns: 1fr; gap: 36px; padding: 24px 0 48px; } .madika-logo { width: min(100%, 320px); } }
            @media (max-width: 720px) { .madika-hero-brands { width: min(100%, 500px); margin: auto; gap: 16px; } }
            @media (max-width: 400px) { .madika-header img { width: 135px; } .madika-header { gap: 12px; } }
        </style>
    </head>
    <body class="madika-welcome">
        <div class="madika-shell">
            <header class="madika-header">
                <a href="{{ route('home') }}" class="madika-identity" aria-label="Beranda MADIKA">
                    <img src="{{ asset('brand/school/logo-maarif.png') }}" alt="Logo MA Ma'arif NU 1 Kemranjen" width="1254" height="1250" class="madika-school-logo" />
                    <img src="{{ asset('brand/madika/logos/madika-compact.svg') }}" width="840" height="224" alt="MADIKA — Madrasah Digital Kita">
                </a>
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}">Masuk</a>
                    @endif
                @endauth
            </header>
            <main class="madika-main">
                <div class="madika-hero-brands">
                <img class="madika-logo" src="{{ asset('brand/madika/logos/madika-stacked.svg') }}"
                    width="820" height="630" alt="MADIKA — Madrasah Digital Kita">
                <img class="madika-hero-school" src="{{ asset('brand/school/logo-maarif.png') }}" width="1254" height="1250" alt="Logo MA Ma'arif NU 1 Kemranjen">
                </div>
                <section>
                    <p class="madika-eyebrow">Madrasah Digital Kita</p>
                    <h1>Satu Aplikasi, Semua Urusan Madrasah.</h1>
                    <p class="madika-copy">Kelola absensi, persuratan, dan administrasi madrasah dalam satu tempat.</p>
                    <ul class="madika-services" aria-label="Layanan MADIKA">
                        <li>Absensi</li><li>Persuratan</li><li>Administrasi</li>
                    </ul>
                    @auth
                        <a class="madika-button" href="{{ route('dashboard') }}">Buka dashboard</a>
                    @else
                        @if (Route::has('login'))
                            <a class="madika-button" href="{{ route('login') }}">Masuk ke MADIKA</a>
                        @endif
                    @endauth
                </section>
            </main>
            <footer class="madika-footer">&copy; {{ date('Y') }} MADIKA · Madrasah Digital Kita</footer>
        </div>
        @fluxScripts
    </body>
</html>
