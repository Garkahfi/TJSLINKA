@props(['title' => 'Dashboard Admin PUMK'])

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — LENSA TJSL INKA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{--pumk-primary:#2653ff;--pumk-ink:#0f172a;--pumk-bg:#f5f5f5;--pumk-border:#e5e7eb}
        *{box-sizing:border-box}.pumk-shell{min-height:100vh;background:var(--pumk-bg);color:var(--pumk-ink);font-family:Poppins,sans-serif}.pumk-header{position:fixed;inset:0 0 auto;z-index:50;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--pumk-border);background:#fff;padding:0 24px}.pumk-logo{display:block;height:40px;width:auto;max-width:180px;object-fit:contain}.pumk-identity{display:flex;align-items:center;gap:15px}.pumk-greeting{display:inline-block;border:1px solid var(--pumk-ink);border-radius:999px;padding:6px 16px;font-size:14px;white-space:nowrap}.pumk-user-icon{display:grid;width:36px;height:36px;place-items:center}.pumk-user-icon svg{width:31px;height:31px}.pumk-sidebar{position:fixed;top:64px;bottom:0;left:0;z-index:40;width:256px;overflow-y:auto;border-right:1px solid var(--pumk-border);background:#fff;transition:width .2s}.pumk-sidebar nav{padding:16px}.pumk-sidebar a,.pumk-sidebar button{box-sizing:border-box;color:var(--pumk-ink);font:500 15px Poppins,sans-serif;text-decoration:none}.pumk-sidebar a:hover,.pumk-sidebar button:hover,.pumk-sidebar .is-active{background:#f1f1f1}.pumk-sidebar-title{display:flex;height:42px;align-items:center;justify-content:space-between;margin-bottom:7px;font-size:20px;font-weight:700}.pumk-collapse{display:grid;width:42px;height:42px;place-items:center;border:0;background:transparent;cursor:pointer}.pumk-collapse svg,.pumk-nav-link svg,.pumk-menu-button svg{width:24px;height:24px;flex:none}.pumk-nav-link,.pumk-menu-button{display:flex;width:100%;min-height:44px;align-items:center;gap:10px;border:0;border-radius:8px;background:transparent;padding:9px 12px;text-align:left;cursor:pointer}.pumk-menu-button{justify-content:space-between}.pumk-menu-button>span{display:flex;align-items:center;gap:10px}.pumk-chevron{width:16px!important;height:16px!important;transition:transform .2s}.pumk-menu-button[aria-expanded=true] .pumk-chevron{transform:rotate(180deg)}.pumk-submenu{padding-left:34px}.pumk-submenu[hidden]{display:none}.pumk-submenu a{display:block;border-radius:7px;padding:8px 10px}.pumk-logout{margin-top:18px}.pumk-logout button{width:100%;border:0;border-radius:8px;background:transparent;padding:10px 12px;color:#dc2626;text-align:left;cursor:pointer}.pumk-main{min-height:100vh;margin-left:256px;padding:96px 32px 40px;transition:margin-left .2s}.pumk-mobile-toggle,.pumk-overlay{display:none}
        .pumk-shell.is-collapsed .pumk-sidebar{width:74px}.pumk-shell.is-collapsed .pumk-main{margin-left:74px}.pumk-shell.is-collapsed .pumk-sidebar nav{padding:16px 7px}.pumk-shell.is-collapsed .pumk-sidebar-title{justify-content:center}.pumk-shell.is-collapsed .pumk-title-text,.pumk-shell.is-collapsed .pumk-nav-label,.pumk-shell.is-collapsed .pumk-chevron,.pumk-shell.is-collapsed .pumk-submenu,.pumk-shell.is-collapsed .pumk-logout{display:none!important}.pumk-shell.is-collapsed .pumk-nav-link,.pumk-shell.is-collapsed .pumk-menu-button{display:grid;width:58px;height:52px;margin:0 auto 2px;place-items:center;padding:0}.pumk-shell.is-collapsed .pumk-menu-button>span{display:grid;place-items:center}.pumk-shell.is-collapsed .pumk-nav-link svg,.pumk-shell.is-collapsed .pumk-menu-button svg{width:31px;height:31px}
        .pumk-alert{margin-bottom:18px;border-radius:8px;padding:12px 15px;font-size:13px}.pumk-alert.success{background:#dcfce7;color:#166534}.pumk-alert.error{background:#fee2e2;color:#991b1b}.pumk-page{width:100%;max-width:1100px;margin:0 auto}.pumk-page-title{margin:0;color:#09132a;font-size:34px;line-height:1.2;font-weight:700}.pumk-page-subtitle{margin:7px 0 0;color:#64748b;font-size:14px}.pumk-card{border:1px solid var(--pumk-border);border-radius:11px;background:#fff;box-shadow:0 3px 4px rgba(0,0,0,.14)}.pumk-primary-button,.pumk-secondary-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;border:0;border-radius:6px;padding:10px 18px;font:600 14px Poppins,sans-serif;text-decoration:none;cursor:pointer}.pumk-primary-button{background:var(--pumk-primary);color:#fff}.pumk-primary-button:hover{background:#163ed8}.pumk-secondary-button{background:#7fa4e4;color:#fff}.pumk-empty{padding:36px 20px;color:#64748b;text-align:center}
        @media(max-width:1023px){.pumk-sidebar{transform:translateX(-100%);transition:transform .2s}.pumk-sidebar.is-open{transform:translateX(0)}.pumk-main,.pumk-shell.is-collapsed .pumk-main{margin-left:0;padding:96px 20px 32px}.pumk-mobile-toggle{display:grid;position:fixed;top:76px;left:16px;z-index:45;width:42px;height:42px;place-items:center;border:1px solid var(--pumk-border);border-radius:8px;background:#fff}.pumk-overlay.is-open{display:block;position:fixed;inset:0;z-index:30;background:rgba(0,0,0,.3)}}
        @media(max-width:620px){.pumk-header{padding:0 16px}.pumk-logo{height:34px}.pumk-greeting{display:none}.pumk-main,.pumk-shell.is-collapsed .pumk-main{padding:92px 14px 28px}.pumk-page-title{font-size:27px}}
    </style>
</head>
<body>
@php($mustChangePassword = auth('pumk')->user()->must_change_password)
<div class="pumk-shell" data-pumk-shell>
    <header class="pumk-header">
        <a href="{{ route($mustChangePassword ? 'pumk-admin.profile' : 'pumk-admin.home') }}" aria-label="Dashboard Admin PUMK">
            <img src="{{ asset('images/logo/lensa-tjsl-inka-navbar.png') }}" class="pumk-logo" alt="LENSA TJSL INKA">
        </a>
        <div class="pumk-identity">
            <span class="pumk-greeting">Hi, {{ auth('pumk')->user()->nama_depan ?: auth('pumk')->user()->name }}{{ $mustChangePassword ? ' · Ganti Password' : '' }}</span>
            <a href="{{ route('pumk-admin.profile') }}" class="pumk-user-icon" aria-label="Profil" title="Profil">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0"/></svg>
            </a>
        </div>
    </header>

    <button type="button" class="pumk-mobile-toggle" data-pumk-mobile-toggle aria-label="Buka navigasi">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <aside class="pumk-sidebar" data-pumk-sidebar>
        <nav>
            <div class="pumk-sidebar-title">
                <span class="pumk-title-text">Admin PUMK</span>
                <button type="button" class="pumk-collapse" data-pumk-collapse aria-label="Perkecil sidebar">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>

            @unless($mustChangePassword)
            <a href="{{ route('pumk-admin.home') }}" class="pumk-nav-link {{ request()->routeIs('pumk-admin.home') ? 'is-active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linejoin="round" d="m3 11 9-8 9 8v10h-6v-6H9v6H3Z"/></svg>
                <span class="pumk-nav-label">Home</span>
            </a>

            <div>
                <button type="button" class="pumk-menu-button" data-pumk-menu aria-expanded="{{ request()->routeIs('pumk-admin.mitra.*') ? 'true' : 'false' }}">
                    <span>
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path d="M5 3.5h14v17H5zM8 7h8M8 11h8M8 15h5"/><path d="M16.5 14v4m-2-2h4"/></svg>
                        <span class="pumk-nav-label">Kartu Piutang</span>
                    </span>
                    <svg class="pumk-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="pumk-submenu" data-pumk-submenu @if(! request()->routeIs('pumk-admin.mitra.*')) hidden @endif>
                    <a href="{{ route('pumk-admin.mitra.index') }}" class="{{ request()->routeIs('pumk-admin.mitra.index', 'pumk-admin.mitra.show', 'pumk-admin.mitra.edit') ? 'is-active' : '' }}">Daftar Mitra Binaan</a>
                    <a href="{{ route('pumk-admin.mitra.create') }}" class="{{ request()->routeIs('pumk-admin.mitra.create') ? 'is-active' : '' }}">Tambah Mitra Binaan</a>
                </div>
            </div>

            <a href="{{ route('pumk-admin.monitoring.upload') }}" class="pumk-nav-link {{ request()->routeIs('pumk-admin.monitoring.*') ? 'is-active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 13v6h14v-6"/></svg>
                <span class="pumk-nav-label">Upload Monitoring</span>
            </a>

            <a href="{{ route('pumk-admin.bri-planning.index') }}" class="pumk-nav-link {{ request()->routeIs('pumk-admin.bri-planning.*') ? 'is-active' : '' }}">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8V6"/></svg>
                <span class="pumk-nav-label">RKA &amp; Realisasi BRI</span>
            </a>
            @endunless

            <form method="POST" action="{{ route('pumk-admin.logout') }}" class="pumk-logout">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </nav>
    </aside>

    <div class="pumk-overlay" data-pumk-overlay></div>
    <main class="pumk-main">{{ $slot }}</main>
</div>

<script>
    (function () {
        const shell = document.querySelector('[data-pumk-shell]');
        const sidebar = document.querySelector('[data-pumk-sidebar]');
        const overlay = document.querySelector('[data-pumk-overlay]');
        const stored = localStorage.getItem('pumkAdminSidebarCollapsed');
        if (stored === 'true') shell?.classList.add('is-collapsed');

        document.querySelector('[data-pumk-collapse]')?.addEventListener('click', function () {
            shell.classList.toggle('is-collapsed');
            localStorage.setItem('pumkAdminSidebarCollapsed', shell.classList.contains('is-collapsed') ? 'true' : 'false');
        });

        document.querySelector('[data-pumk-menu]')?.addEventListener('click', function () {
            const submenu = document.querySelector('[data-pumk-submenu]');
            const nextExpanded = this.getAttribute('aria-expanded') !== 'true';
            this.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
            submenu.hidden = ! nextExpanded;
        });

        function closeMobile() {
            sidebar?.classList.remove('is-open');
            overlay?.classList.remove('is-open');
        }
        document.querySelector('[data-pumk-mobile-toggle]')?.addEventListener('click', function () {
            sidebar?.classList.add('is-open');
            overlay?.classList.add('is-open');
        });
        overlay?.addEventListener('click', closeMobile);
    })();
</script>
</body>
</html>
