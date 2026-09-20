@php
    $isSuperPanel = request()->routeIs('superadmin.*');
    $panelPrefix = $isSuperPanel ? 'superadmin' : 'admin';
    $isProgramFormPage = request()->routeIs(
        'admin.programs.create',
        'admin.programs.cooperation.form',
        'admin.programs.show',
        'admin.programs.phase2',
        'superadmin.programs.show'
    );
    $isAssistanceFormPage = request()->routeIs(
        'admin.assistance.create',
        'admin.assistance.show',
        'admin.assistance.phase2',
        'superadmin.assistance.show'
    );
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard Admin' }} — LENSA TJSL INKA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(request()->routeIs('admin.programs.create', 'admin.programs.cooperation.form', 'admin.programs.show', 'admin.programs.phase2', 'admin.assistance.create', 'admin.assistance.show', 'admin.assistance.phase2', 'superadmin.programs.show', 'superadmin.assistance.show'))
        @include('admin.partials.form-styles')
    @endif
    <style>
        /* Critical shell: keeps the admin layout correct even before Vite rebuilds. */
        .admin-shell{min-height:100vh;background:#f5f5f5;font-family:Poppins,sans-serif;color:#0f172a}
        .admin-header{position:fixed;top:0;left:0;right:0;z-index:50;height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;background:#fff;border-bottom:1px solid #e5e7eb}
        .admin-navbar-logo{display:block;height:40px;width:auto;max-width:180px;object-fit:contain}
        .admin-header>div{display:flex;align-items:center;gap:16px}.admin-header svg{display:block;width:24px;height:24px}.admin-header a[aria-label="Profil"] svg{width:32px;height:32px}
        .admin-greeting{display:inline-block;border:1px solid #0f172a;border-radius:999px;padding:6px 16px;font-size:14px;color:#0f172a;white-space:nowrap}
        .admin-sidebar{position:fixed;top:64px;left:0;bottom:0;z-index:40;width:256px;overflow-y:auto;background:#fff;border-right:1px solid #e5e7eb}
        .admin-sidebar nav{padding:16px}.admin-sidebar a,.admin-sidebar summary{box-sizing:border-box;text-decoration:none;color:#0f172a}.admin-sidebar svg{display:block;width:24px;height:24px;flex:none}
        .admin-sidebar button[data-sidebar-toggle]{display:flex;width:100%;align-items:center;justify-content:space-between;border:0;background:transparent;font:inherit;text-align:left;cursor:pointer}.admin-sidebar button[data-sidebar-toggle]>span:first-child{display:flex;align-items:center;gap:8px}.admin-sidebar #program-menu,.admin-sidebar #assistance-menu{padding-left:32px}.admin-sidebar #program-menu a,.admin-sidebar #assistance-menu a{display:block;padding:8px 12px}.admin-sidebar .hidden{display:none}
        .admin-main{min-height:100vh;margin-left:256px;padding:96px 32px 32px}
        .admin-mobile-toggle,.admin-overlay{display:none}.collapsed-nav-icon{display:none}
        .admin-shell,.admin-sidebar,.admin-main{transition:width .2s,margin-left .2s}.sidebar-collapsed .admin-sidebar{width:74px}.sidebar-collapsed .admin-main{margin-left:74px}.sidebar-collapsed .admin-sidebar nav{padding:16px 7px}.sidebar-collapsed .admin-title-text,.sidebar-collapsed .nav-label{display:none!important}.sidebar-collapsed .sidebar-title{height:42px;margin-bottom:7px;justify-content:center}.sidebar-collapsed .sidebar-title button{display:grid;width:58px;height:42px;place-items:center}.sidebar-collapsed .sidebar-title svg{width:30px;height:30px;stroke-width:1.8}.sidebar-collapsed .admin-sidebar button[data-sidebar-toggle]{display:grid!important;width:58px;height:52px;margin:0 auto 1px;place-items:center;padding:0!important;border-radius:0}.sidebar-collapsed .admin-sidebar button[data-sidebar-toggle]>span:first-child{display:grid;place-items:center}.sidebar-collapsed .admin-sidebar button[data-sidebar-toggle] [data-chevron]{display:none}.sidebar-collapsed .admin-sidebar button[data-sidebar-toggle] .text-xl{display:block;width:31px;height:31px;font-size:0;background-position:center;background-repeat:no-repeat;background-size:31px}.sidebar-collapsed .admin-sidebar nav>div:nth-of-type(2) .text-xl{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4.5 5.25h4.75A2.75 2.75 0 0 1 12 8v11a2.75 2.75 0 0 0-2.75-2.75H4.5v-11Zm15 0h-4.75A2.75 2.75 0 0 0 12 8v11a2.75 2.75 0 0 1 2.75-2.75h4.75v-11Z'/%3E%3Cpath d='M7 8h2M7 11h2M15 8h2M15 11h2'/%3E%3C/svg%3E")}.sidebar-collapsed .admin-sidebar nav>div:nth-of-type(3) .text-xl{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m12 2.75 8 4.5v9.5l-8 4.5-8-4.5v-9.5l8-4.5Zm0 9 8-4.5M12 11.75l-8-4.5M12 11.75v9.5M8 5l8 4.5'/%3E%3C/svg%3E")}.sidebar-collapsed #program-menu,.sidebar-collapsed #assistance-menu{display:block!important;padding-left:0}.sidebar-collapsed .admin-sidebar a{display:grid!important;width:58px;height:52px;margin:0 auto 1px;place-items:center;padding:0!important;border-radius:0;font-size:0}.sidebar-collapsed .admin-sidebar a>svg{display:none}.sidebar-collapsed .admin-sidebar a>.collapsed-nav-icon{display:block;width:31px;height:31px;stroke:#050505;stroke-width:1.8}.sidebar-collapsed .admin-sidebar .home-link>svg{display:block;width:31px;height:31px;stroke-width:1.8}.sidebar-collapsed .admin-sidebar a.is-active{background:#eeeeee}.sidebar-collapsed .logout-form{display:none}
        .sidebar-collapsed #teras-menu{display:block!important;padding-left:0}
        .sidebar-collapsed .admin-sidebar nav>div:nth-of-type(4) .text-xl{font-size:24px}
        @media(max-width:1023px){.admin-sidebar{transform:translateX(-100%);transition:transform .2s}.admin-sidebar:not(.-translate-x-full){transform:translateX(0)}.admin-main{margin-left:0;padding:96px 20px 32px}.admin-mobile-toggle{display:block;position:fixed;top:76px;left:16px;z-index:45;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px}.admin-overlay:not(.hidden){display:block;position:fixed;inset:0;z-index:30;background:rgba(0,0,0,.3)}}
        .sidebar-collapsed .admin-sidebar button[data-sidebar-toggle]{background:#eeeeee}
        .sidebar-collapsed #program-menu a:nth-child(1) .collapsed-nav-icon{stroke:transparent;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 3.75h12v16.5H6zM9 3.75v6l3-2 3 2v-6M8.5 17h7'/%3E%3C/svg%3E") center/31px no-repeat}
        .sidebar-collapsed #program-menu a:nth-child(2) .collapsed-nav-icon,.sidebar-collapsed #assistance-menu a:nth-child(2) .collapsed-nav-icon{stroke:transparent;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 15.5 9.7-9.7 4.5 4.5-9.7 9.7H4v-4.5Zm7.5-7.5 4.5 4.5M4 20h16'/%3E%3C/svg%3E") center/31px no-repeat}
        .sidebar-collapsed #assistance-menu a:nth-child(1) .collapsed-nav-icon{stroke:transparent;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 12.5 7 10l3 2.5 2-1.5 2 1.5 3-2.5 4 2.5-5 5h-3l-1 1-1-1H8l-5-5Zm4-2.5 2-2h3l2 2M17 10l-2-2h-3'/%3E%3C/svg%3E") center/31px no-repeat}
        .notification-link{position:relative}.notification-badge{position:absolute;right:-5px;top:-5px;display:grid;min-width:18px;height:18px;place-items:center;border-radius:999px;background:#ff0009;color:#fff;font-size:10px;font-weight:700}.notification-badge.hidden{display:none}
        .super-panel .super-menu-link,.super-panel .super-menu-button{box-sizing:border-box;display:flex;width:100%;min-height:43px;align-items:center;gap:10px;border:0;border-radius:8px;background:transparent;padding:9px 12px;color:#0f172a;font:inherit;text-align:left;text-decoration:none;cursor:pointer}.super-panel .super-menu-link:hover,.super-panel .super-menu-button:hover,.super-panel .super-menu-link.is-active{background:#f1f1f1}.super-panel .super-menu-link>svg,.super-panel .super-menu-button>span>svg{display:block;width:25px;height:25px;flex:0 0 25px;stroke-width:1.8}.super-panel .super-menu-button{justify-content:space-between}.super-panel .super-menu-button>span{display:flex;align-items:center;gap:10px}.super-panel .menu-chevron{width:16px;height:16px;transition:transform .2s}.super-panel .super-submenu{padding-left:35px}.super-panel .super-submenu a{display:block;border-radius:7px;padding:8px 10px;color:#0f172a;text-decoration:none}.super-panel .super-submenu a:hover{background:#f1f1f1}.sidebar-collapsed.super-panel .super-submenu{display:none!important}.sidebar-collapsed.super-panel .super-menu-link,.sidebar-collapsed.super-panel .super-menu-button{display:grid!important;width:58px;height:52px;margin:0 auto 3px;place-items:center;padding:0}.sidebar-collapsed.super-panel .super-menu-link>svg,.sidebar-collapsed.super-panel .super-menu-button>span>svg{display:block!important;width:31px;height:31px}.sidebar-collapsed.super-panel .super-menu-button>span{display:grid;place-items:center}.sidebar-collapsed.super-panel .super-menu-button>.menu-chevron{display:none}.sidebar-collapsed.super-panel .super-menu-link::before{content:none!important}
        .super-panel .admin-header{height:70px;padding:0 40px}.super-panel .admin-sidebar{top:70px;width:300px}.super-panel .admin-main{margin-left:300px;padding:88px 20px 40px}.super-panel .admin-sidebar nav{padding:16px 20px}.sidebar-collapsed.super-panel .admin-sidebar{width:63px}.sidebar-collapsed.super-panel .admin-main{margin-left:63px}.sidebar-collapsed.super-panel .admin-sidebar nav{padding:16px 2px}.sidebar-collapsed.super-panel .super-menu-link,.sidebar-collapsed.super-panel .super-menu-button{width:58px}.super-panel .admin-navbar-logo{height:46px;max-width:160px}
        @media(max-width:1023px){.super-panel .admin-main{margin-left:0;padding:96px 20px 32px}.super-panel .admin-sidebar{width:300px}}
    </style>
</head>
<body @class([
    'program-form-page' => $isProgramFormPage,
    'assistance-form-page' => $isAssistanceFormPage,
])>
<div
    @class([
        'admin-shell min-h-screen bg-[#f5f5f5]',
        'super-panel' => $isSuperPanel,
    ])
    data-admin-shell
>
    <header class="admin-header fixed left-0 right-0 top-0 z-50 flex h-16 items-center justify-between border-b bg-white px-6">
        <a href="{{ route($panelPrefix.'.home') }}" class="flex items-center gap-2" aria-label="Dashboard {{ $isSuperPanel?'Super Admin':'Admin' }}">
            <img src="{{ asset('images/logo/lensa-tjsl-inka-navbar.png') }}" class="admin-navbar-logo h-10 w-auto" alt="Lensa TJSL INKA">
        </a>
        <div class="flex items-center gap-4">
            <span class="admin-greeting">Hi, {{ $isSuperPanel ? 'Super Admin' : (auth()->user()->nama_depan ? auth()->user()->nama_depan.' User' : 'Admin User') }}</span>
            <a href="{{ route($panelPrefix.'.notifications') }}" aria-label="Notifikasi" class="notification-link rounded-full p-1 hover:bg-slate-100"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.86 17.08a24.8 24.8 0 0 0 5.45-1.31A8.97 8.97 0 0 1 18 9.75V9a6 6 0 0 0-12 0v.75a8.97 8.97 0 0 1-2.31 6.02c1.77.58 3.59 1.02 5.45 1.31m5.72 0a24.25 24.25 0 0 1-5.72 0m5.72 0a3 3 0 1 1-5.72 0"/></svg><span data-notification-badge class="notification-badge hidden">0</span></a>
            <a href="{{ route($panelPrefix.'.profile') }}" aria-label="Profil" class="rounded-full p-1 hover:bg-slate-100"><svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0"/></svg></a>
        </div>
    </header>

    <button class="admin-mobile-toggle" data-admin-menu-button aria-label="Buka navigasi"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>

    <aside class="admin-sidebar fixed bottom-0 left-0 top-16 z-40 w-64 overflow-y-auto border-r bg-white -translate-x-full lg:translate-x-0" data-admin-sidebar>
        <nav class="space-y-1 p-4 text-[15px] font-medium">
            @if($isSuperPanel)
            @include('superadmin.partials.sidebar')
            @else
            <div class="sidebar-title mb-4 flex items-center justify-between text-xl font-bold"><span class="admin-title-text">Admin</span><button type="button" data-sidebar-collapse aria-label="Perkecil sidebar"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button></div>
            <a href="{{ route('admin.home') }}" data-icon="⌂" class="home-link flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.home')?'is-active':'' }}"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="m3 11 9-8 9 8v10h-6v-6H9v6H3Z"/></svg><span class="nav-label">Home</span></a>
            <div>
                <button type="button" data-sidebar-toggle="program-menu" class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-100">
                    <span class="flex items-center gap-2"><span class="text-xl">▥</span><span class="nav-label">Overview Program TJSL</span></span>
                    <span data-chevron>⌄</span>
                </button>
                <div id="program-menu" class="space-y-1 pl-8 {{ request()->routeIs('admin.programs.*') ? '' : 'hidden' }}">
                    <a href="{{ route('admin.programs.index') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.programs.index','admin.programs.show','admin.programs.phase2')?'is-active':'' }}"><svg class="collapsed-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h4.75A2.75 2.75 0 0 1 12 8v11a2.75 2.75 0 0 0-2.75-2.75H4.5v-11Zm15 0h-4.75A2.75 2.75 0 0 0 12 8v11a2.75 2.75 0 0 1 2.75-2.75H4.5v-11ZM7 8h2M7 11h2M15 8h2M15 11h2"/></svg>Status Program</a>
                    <a href="{{ route('admin.programs.create') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.programs.create','admin.programs.cooperation.form')?'is-active':'' }}"><svg class="collapsed-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h12v16.5H6zM9 3.75v6l3-2 3 2v-6M8.5 17h7"/></svg>Buat Program Baru</a>
                </div>
            </div>
            <div>
                <button type="button" data-sidebar-toggle="assistance-menu" class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-100">
                    <span class="flex items-center gap-2"><span class="text-xl">◇</span><span class="nav-label">Overview Bantuan TJSL</span></span>
                    <span data-chevron>⌄</span>
                </button>
                <div id="assistance-menu" class="space-y-1 pl-8 {{ request()->routeIs('admin.assistance.*') ? '' : 'hidden' }}">
                    <a href="{{ route('admin.assistance.index') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.assistance.index','admin.assistance.show','admin.assistance.phase2')?'is-active':'' }}"><svg class="collapsed-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2.75 8 4.5v9.5l-8 4.5-8-4.5v-9.5l8-4.5Zm0 9 8-4.5M12 11.75l-8-4.5M12 11.75v9.5M8 5l8 4.5"/></svg>Status Bantuan TJSL</a>
                    <a href="{{ route('admin.assistance.create') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.assistance.create')?'is-active':'' }}"><svg class="collapsed-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12.5 7 10l3 2.5 2-1.5 2 1.5 3-2.5 4 2.5-5 5h-3l-1 1-1-1H8l-5-5Zm4-2.5 2-2h3l2 2M17 10l-2-2h-3"/></svg>Buat Bantuan TJSL</a>
                </div>
            </div>
            <div>
                <button type="button" data-sidebar-toggle="teras-menu" class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-100">
                    <span class="flex items-center gap-2"><span class="text-xl">▣</span><span class="nav-label">Teras TJSL</span></span>
                    <span data-chevron>⌄</span>
                </button>
                <div id="teras-menu" class="space-y-1 pl-8 {{ request()->routeIs('admin.teras.*') ? '' : 'hidden' }}">
                    <a href="{{ route('admin.teras.products.index') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.teras.products.*') ? 'is-active' : '' }}"><svg class="collapsed-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5h16v13H4zM8 9.5h.01M4 15l4-4 3 3 2-2 3 3"/></svg>Produk</a>
                    <a href="{{ route('admin.teras.packages.index') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('admin.teras.packages.*') ? 'is-active' : '' }}"><svg class="collapsed-nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"/></svg>Paket</a>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}" class="logout-form pt-4">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-red-600 hover:bg-red-50">Keluar</button></form>
            @endif
        </nav>
    </aside>
    <div class="admin-overlay hidden" data-admin-overlay></div>
    <main class="admin-main ml-64 min-h-screen p-8 pt-24">{{ $slot }}</main>
</div>
@if(session('approval_completed'))
    <x-approval-success-modal :approval="session('approval_completed')" />
@endif
<template
    id="admin-shell-config"
    data-is-super-panel="{{ $isSuperPanel ? 'true' : 'false' }}"
    data-sidebar-storage-key="{{ $isSuperPanel ? 'superadminSidebarCollapsedV2' : 'adminSidebarCollapsed' }}"
    data-notification-endpoint="{{ route($panelPrefix.'.notifications.unread') }}"
></template>
<script>
var adminConfig=document.getElementById('admin-shell-config');
var isSuperPanel=adminConfig?.dataset.isSuperPanel==='true';
var adminShell=document.querySelector('[data-admin-shell]');
var sidebarCollapseButton=document.querySelector('[data-sidebar-collapse]');
var desktopSidebarQuery=window.matchMedia('(min-width: 1024px)');
var sidebarStorageKey=adminConfig?.dataset.sidebarStorageKey||'adminSidebarCollapsed';

function setSidebarCollapsed(collapsed){
    if(!adminShell||!sidebarCollapseButton)return;
    adminShell.classList.toggle('sidebar-collapsed',collapsed);
    sidebarCollapseButton.setAttribute('aria-expanded',collapsed?'false':'true');
    sidebarCollapseButton.setAttribute('aria-label',collapsed?'Perbesar sidebar':'Perkecil sidebar');
}

if(adminShell&&sidebarCollapseButton){
    var storedSidebarState=localStorage.getItem(sidebarStorageKey);
    var savedSidebarState=true?false:(storedSidebarState===null?false:storedSidebarState==='true');
    if(!isSuperPanel){
        savedSidebarState=storedSidebarState===null?false:storedSidebarState==='true';
    }
    setSidebarCollapsed(desktopSidebarQuery.matches&&savedSidebarState);

    sidebarCollapseButton.addEventListener('click',function(){
        if(!desktopSidebarQuery.matches)return;
        var collapsed=!adminShell.classList.contains('sidebar-collapsed');
        setSidebarCollapsed(collapsed);
        if(!isSuperPanel)localStorage.setItem(sidebarStorageKey,String(collapsed));
    });

    desktopSidebarQuery.addEventListener('change',function(event){
        setSidebarCollapsed(event.matches&&!isSuperPanel&&localStorage.getItem(sidebarStorageKey)==='true');
    });
}

document.querySelectorAll('[data-sidebar-toggle]').forEach(function(button){
    button.addEventListener('click',function(){
        var menu=document.getElementById(button.dataset.sidebarToggle);
        if(menu){menu.classList.toggle('hidden');button.querySelector('[data-chevron]')?.classList.toggle('rotate-180');}
    });
});
var notificationEndpoint=adminConfig?.dataset.notificationEndpoint||'';
function refreshNotificationCount(){if(!notificationEndpoint)return;fetch(notificationEndpoint,{headers:{Accept:'application/json'}}).then(function(response){return response.json()}).then(function(data){var badge=document.querySelector('[data-notification-badge]');if(!badge)return;badge.textContent=data.count>99?'99+':data.count;badge.classList.toggle('hidden',!data.count)}).catch(function(){});}
refreshNotificationCount();setInterval(refreshNotificationCount,18000);
</script>
</body>
</html>
