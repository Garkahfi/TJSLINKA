@php
    $publicNavbarUser = auth('web')->user();
    $publicNavbarName = trim(($publicNavbarUser?->nama_depan ?? '').' '.($publicNavbarUser?->nama_belakang ?? ''));
    $myDashboardRoute = $publicNavbarUser ? app(\App\Services\Auth\LoginDestination::class)->routeFor($publicNavbarUser) : null;
    $monitoringTjslActive = request()->routeIs('home', 'monitoring.tjsl');
    $monitoringBriActive = request()->routeIs('monitoring.bri');
    $monitoringInkaActive = request()->routeIs('monitoring.inka');
    $monitoringActive = $monitoringTjslActive || $monitoringBriActive || $monitoringInkaActive;
@endphp

<style>
    [data-account-picker],[data-language-picker]{position:relative}
    [data-account-button]{min-width:190px;border:2px solid #101b36;border-radius:999px;background:#fff;padding:10px 26px;color:#101b36;font:500 15px Poppins,sans-serif;cursor:pointer;transition:background-color .15s,color .15s}
    [data-account-button]:hover,[data-account-button][aria-expanded="true"]{background:#101b36;color:#fff}
    [data-account-menu]{position:absolute;right:0;top:58px;z-index:75;width:260px;border:1px solid #e5e7eb;background:#fff;box-shadow:0 10px 22px rgba(15,23,42,.13);transition:opacity .15s}
    [data-account-menu].invisible,[data-language-menu].invisible{visibility:hidden}
    [data-account-menu].opacity-0,[data-language-menu].opacity-0{opacity:0}
    .account-menu-link{display:block;width:100%;border:0;background:#fff;padding:18px 16px;color:#111827;font:500 17px Poppins,sans-serif;text-align:left;text-decoration:none;cursor:pointer}
    .account-menu-link:hover{background:#f1f5f9}
    [data-language-button]{display:grid;width:44px;height:44px;place-items:center;border:0;border-radius:999px;background:transparent;color:#101b36;cursor:pointer}
    [data-language-button]:hover{background:#f1f5f9}
    [data-language-button] svg{width:28px;height:28px}
    [data-language-menu]{position:absolute;right:-25px;top:56px;z-index:70;width:208px;padding:4px 0;border:1px solid #e2e8f0;background:#fff;box-shadow:0 10px 22px rgba(15,23,42,.13);transition:opacity .15s}
    [data-language-option]{font-family:inherit}
    [data-language-menu] [data-language-option]{display:flex;width:100%;align-items:center;justify-content:space-between;padding:16px;border:0;background:#fff;font-size:17px;text-align:left;cursor:pointer}
    [data-language-menu] [data-language-option]:hover{background:#f1f5f9}
    [data-language-check].invisible{visibility:hidden}
    .navbar-brand-group{display:flex;align-items:center;gap:14px}
    .navbar-corporate-logo{display:block;height:40px;width:auto;object-fit:contain}
    .navbar-disclosure{position:relative;display:flex;height:100%;align-items:center}
    .navbar-disclosure-button{display:inline-flex;min-height:44px;align-items:center;gap:5px;border:0;background:transparent;padding:0;font-family:inherit;cursor:pointer}
    .navbar-disclosure-button svg,.mobile-monitoring-button svg{width:14px;height:14px;transition:transform .15s}
    .navbar-disclosure-button[aria-expanded="true"] svg,.mobile-monitoring-button[aria-expanded="true"] svg{transform:rotate(180deg)}
    .navbar-disclosure-menu{position:absolute;top:calc(100% - 5px);left:0;z-index:60;min-width:230px;max-width:calc(100vw - 40px);border:1px solid #e2e8f0;border-radius:6px;background:#fff;padding:5px;box-shadow:0 8px 18px rgba(15,23,42,.12)}
    .navbar-disclosure-menu[hidden],.mobile-monitoring-submenu[hidden]{display:none}
    .navbar-program-menu{left:50%;width:288px;transform:translateX(-50%)}
    .navbar-disclosure-link{display:flex;min-height:44px;align-items:center;justify-content:space-between;gap:12px;border-radius:4px;padding:10px 12px;color:#18243d;font-size:14px;text-decoration:none}
    .navbar-disclosure-link:hover,.navbar-disclosure-link:focus-visible{background:#f1f5f9}
    .navbar-disclosure-link[aria-current="page"]{background:#fef2f2;color:#b91c1c;font-weight:700}
    .navbar-disclosure-link svg{width:15px;height:15px;flex:none}
    .mobile-monitoring-button{display:flex;width:100%;min-height:44px;align-items:center;justify-content:space-between;border:0;background:transparent;padding:0;text-align:left;cursor:pointer}
    .mobile-monitoring-submenu{margin:0 0 2px 10px;border-left:1px solid #e2e8f0;padding-left:10px}
    [data-mobile-menu]{max-height:calc(100dvh - 80px);overflow-y:auto}

    @media(max-width:640px){
        .navbar-brand-group{gap:10px}
        .navbar-corporate-logo{height:30px}
    }
</style>

<header class="sticky top-0 z-50 bg-white shadow-sm">
    <nav class="container-site flex h-20 items-center justify-between" aria-label="Navigasi utama">
        <a href="{{ route('home') }}" class="navbar-brand-group">
            <img
                src="{{ asset('images/logo/danantara.png') }}"
                alt="Danantara Indonesia"
                class="navbar-corporate-logo"
            >
            <img
                src="{{ asset('images/logo/inka.png') }}"
                alt="PT Industri Kereta Api (Persero)"
                class="navbar-corporate-logo"
            >
        </a>

        <div class="hidden items-center gap-10 lg:flex">
            <div class="navbar-disclosure" data-nav-disclosure>
                <button class="nav-link navbar-disclosure-button {{ $monitoringActive ? 'text-inka-red' : '' }}" type="button" data-nav-disclosure-button aria-expanded="false" aria-controls="monitoring-desktop-menu">
                    Monitoring
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
                </button>
                <div id="monitoring-desktop-menu" class="navbar-disclosure-menu" data-nav-disclosure-menu hidden>
                    <a href="{{ route('monitoring.tjsl') }}" class="navbar-disclosure-link" @if($monitoringTjslActive) aria-current="page" @endif>Realisasi TJSL @if($monitoringTjslActive)<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>@endif</a>
                    <a href="{{ route('monitoring.bri') }}" class="navbar-disclosure-link" @if($monitoringBriActive) aria-current="page" @endif>PUMK BRI @if($monitoringBriActive)<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>@endif</a>
                    <a href="{{ route('monitoring.inka') }}" class="navbar-disclosure-link" @if($monitoringInkaActive) aria-current="page" @endif>PUMK PT INKA @if($monitoringInkaActive)<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>@endif</a>
                </div>
            </div>
            <a class="nav-link {{ request()->routeIs('teras') ? 'text-inka-red' : '' }}" href="{{ route('teras') }}">Teras TJSL</a>
            <div class="navbar-disclosure" data-nav-disclosure>
                <button class="nav-link navbar-disclosure-button" type="button" data-nav-disclosure-button aria-expanded="false" aria-controls="program-desktop-menu">
                    Program TJSL <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
                </button>
                <div id="program-desktop-menu" class="navbar-disclosure-menu navbar-program-menu" data-nav-disclosure-menu hidden>
                    <a href="{{ route('program.overview') }}" class="navbar-disclosure-link">Overview Program TJSL INKA</a>
                    <a href="{{ route('program.rincian') }}" class="navbar-disclosure-link">Realisasi Program TJSL INKA</a>
                </div>
            </div>
        </div>

        <div class="hidden items-center gap-8 md:flex">
            <div data-account-picker>
                <button
                    type="button"
                    data-account-button
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    Hi, {{ $publicNavbarName !== '' ? $publicNavbarName : 'User Admin' }}
                </button>
                <div
                    data-account-menu
                    class="invisible opacity-0"
                    role="menu"
                    aria-label="Menu akun"
                >
                    @if($myDashboardRoute)
                    <a href="{{ route($myDashboardRoute) }}" class="account-menu-link" role="menuitem">Dashboard Saya</a>
                    @endif
                    <a href="{{ route('public.profile') }}" class="account-menu-link" role="menuitem">
                        Profil
                    </a>
                    <form action="{{ route('public.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="account-menu-link" role="menuitem">
                            Logout
                        </button>
                    </form>
                </div>
            </div>

            <div data-language-picker>
                <button
                    type="button"
                    data-language-button
                    aria-label="Pilih bahasa"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" d="M3 12h18M12 3c2.2 2.45 3.35 5.45 3.35 9S14.2 18.55 12 21M12 3C9.8 5.45 8.65 8.45 8.65 12S9.8 18.55 12 21"/>
                    </svg>
                </button>
                <div data-language-menu class="invisible opacity-0" role="menu" aria-label="Pilihan bahasa">
                    <button type="button" data-language-option="id" role="menuitem">
                        Bahasa Indonesia <span data-language-check="id" aria-hidden="true">✓</span>
                    </button>
                    <button type="button" data-language-option="en" role="menuitem">
                        English <span data-language-check="en" class="invisible" aria-hidden="true">✓</span>
                    </button>
                </div>
            </div>
        </div>

        <button data-menu-button class="rounded-md p-2 text-2xl lg:hidden" aria-label="Buka menu">☰</button>
    </nav>

    <div data-mobile-menu class="hidden border-t bg-white px-5 py-4 lg:hidden">
        <div class="flex flex-col gap-4">
            <div>
                <button class="nav-link mobile-monitoring-button {{ $monitoringActive ? 'text-inka-red' : '' }}" type="button" data-mobile-monitoring-button aria-expanded="false" aria-controls="monitoring-mobile-menu">
                    Monitoring <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
                </button>
                <div id="monitoring-mobile-menu" class="mobile-monitoring-submenu" data-mobile-monitoring-menu hidden>
                    <a href="{{ route('monitoring.tjsl') }}" class="navbar-disclosure-link" @if($monitoringTjslActive) aria-current="page" @endif>Realisasi TJSL @if($monitoringTjslActive)<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>@endif</a>
                    <a href="{{ route('monitoring.bri') }}" class="navbar-disclosure-link" @if($monitoringBriActive) aria-current="page" @endif>PUMK BRI @if($monitoringBriActive)<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>@endif</a>
                    <a href="{{ route('monitoring.inka') }}" class="navbar-disclosure-link" @if($monitoringInkaActive) aria-current="page" @endif>PUMK PT INKA @if($monitoringInkaActive)<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>@endif</a>
                </div>
            </div>
            <a class="nav-link" href="{{ route('teras') }}">Teras TJSL</a>
            <a class="nav-link" href="{{ route('program.overview') }}">Overview Program</a>
            <a class="nav-link" href="{{ route('program.rincian') }}">Realisasi Program</a>
            @if($myDashboardRoute)
            <a class="nav-link" href="{{ route($myDashboardRoute) }}">Dashboard Saya</a>
            @endif
            <a class="nav-link" href="{{ route('public.profile') }}">Profil</a>
            <form action="{{ route('public.logout') }}" method="POST">
                @csrf
                <button type="submit" class="nav-link border-0 bg-transparent p-0 text-left">Logout</button>
            </form>
            <div class="flex gap-2" data-mobile-languages>
                <button type="button" data-language-option="id" class="rounded border px-3 py-2">Bahasa Indonesia</button>
                <button type="button" data-language-option="en" class="rounded border px-3 py-2">English</button>
            </div>
        </div>
    </div>
</header>

<script>
(function(){
    var accountPicker=document.querySelector('[data-account-picker]');
    var languagePicker=document.querySelector('[data-language-picker]');
    var accountButton=accountPicker?.querySelector('[data-account-button]');
    var accountMenu=accountPicker?.querySelector('[data-account-menu]');
    var languageButton=languagePicker?.querySelector('[data-language-button]');
    var languageMenu=languagePicker?.querySelector('[data-language-menu]');
    var navDisclosures=Array.from(document.querySelectorAll('[data-nav-disclosure]'));
    var mobileMonitoringButton=document.querySelector('[data-mobile-monitoring-button]');
    var mobileMonitoringMenu=document.querySelector('[data-mobile-monitoring-menu]');

    function closeMenu(button,menu){
        if(!button||!menu)return;
        menu.classList.add('invisible','opacity-0');
        button.setAttribute('aria-expanded','false');
    }
    function openMenu(button,menu){
        if(!button||!menu)return;
        menu.classList.remove('invisible','opacity-0');
        button.setAttribute('aria-expanded','true');
    }
    function closeNavDisclosure(disclosure){
        disclosure.querySelector('[data-nav-disclosure-menu]').hidden=true;
        disclosure.querySelector('[data-nav-disclosure-button]').setAttribute('aria-expanded','false');
    }
    function closeMobileMonitoring(){
        if(!mobileMonitoringButton||!mobileMonitoringMenu)return;
        mobileMonitoringMenu.hidden=true;
        mobileMonitoringButton.setAttribute('aria-expanded','false');
    }
    navDisclosures.forEach(function(disclosure){
        var button=disclosure.querySelector('[data-nav-disclosure-button]');
        var menu=disclosure.querySelector('[data-nav-disclosure-menu]');
        button.addEventListener('click',function(){
            var shouldOpen=menu.hidden;
            navDisclosures.forEach(closeNavDisclosure);
            closeMenu(accountButton,accountMenu);
            closeMenu(languageButton,languageMenu);
            if(shouldOpen){menu.hidden=false;button.setAttribute('aria-expanded','true');}
        });
    });
    mobileMonitoringButton?.addEventListener('click',function(){
        var shouldOpen=mobileMonitoringMenu.hidden;
        closeMobileMonitoring();
        if(shouldOpen){mobileMonitoringMenu.hidden=false;mobileMonitoringButton.setAttribute('aria-expanded','true');}
    });
    function setLanguage(language){
        var selected=language==='en'?'en':'id';
        document.documentElement.lang=selected;
        localStorage.setItem('lensaLanguage',selected);
        document.querySelectorAll('[data-language-check]').forEach(function(check){
            check.classList.toggle('invisible',check.dataset.languageCheck!==selected);
        });
        if(languageButton)languageButton.title=selected==='id'?'Bahasa Indonesia':'English';
        closeMenu(languageButton,languageMenu);
    }

    setLanguage(localStorage.getItem('lensaLanguage')||'id');

    accountButton?.addEventListener('click',function(event){
        event.stopPropagation();
        var shouldOpen=accountMenu.classList.contains('invisible');
        closeMenu(languageButton,languageMenu);
        navDisclosures.forEach(closeNavDisclosure);
        shouldOpen?openMenu(accountButton,accountMenu):closeMenu(accountButton,accountMenu);
    });
    languageButton?.addEventListener('click',function(event){
        event.stopPropagation();
        var shouldOpen=languageMenu.classList.contains('invisible');
        closeMenu(accountButton,accountMenu);
        navDisclosures.forEach(closeNavDisclosure);
        shouldOpen?openMenu(languageButton,languageMenu):closeMenu(languageButton,languageMenu);
    });
    document.querySelectorAll('[data-language-option]').forEach(function(option){
        option.addEventListener('click',function(){setLanguage(option.dataset.languageOption);});
    });
    document.addEventListener('click',function(event){
        if(accountPicker&&!accountPicker.contains(event.target))closeMenu(accountButton,accountMenu);
        if(languagePicker&&!languagePicker.contains(event.target))closeMenu(languageButton,languageMenu);
        navDisclosures.forEach(function(disclosure){if(!disclosure.contains(event.target))closeNavDisclosure(disclosure);});
        if(mobileMonitoringButton&&!mobileMonitoringButton.parentElement.contains(event.target))closeMobileMonitoring();
    });
    document.addEventListener('keydown',function(event){
        if(event.key==='Escape'){
            var openDisclosure=navDisclosures.find(function(disclosure){return !disclosure.querySelector('[data-nav-disclosure-menu]').hidden;});
            if(openDisclosure){openDisclosure.querySelector('[data-nav-disclosure-button]').focus();event.preventDefault();}
            else if(mobileMonitoringMenu&&!mobileMonitoringMenu.hidden){mobileMonitoringButton.focus();event.preventDefault();}
            navDisclosures.forEach(closeNavDisclosure);
            closeMobileMonitoring();
            closeMenu(accountButton,accountMenu);
            closeMenu(languageButton,languageMenu);
        }
    });
})();
</script>
