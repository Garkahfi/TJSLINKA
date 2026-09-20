<div class="sidebar-title mb-4 flex items-center justify-between text-xl font-bold">
    <span class="admin-title-text">SuperAdmin</span>
    <button type="button" data-sidebar-collapse aria-label="Perkecil sidebar"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
</div>

<a href="{{ route('superadmin.home') }}" class="super-menu-link home-link {{ request()->routeIs('superadmin.home')?'is-active':'' }}">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3 11 9-8 9 8v10h-6v-6H9v6H3V11Z"/></svg><span class="nav-label">Home</span>
</a>

<div class="super-menu-group">
    <button type="button" data-sidebar-toggle="program-menu" class="super-menu-button">
        <span><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h4.75A2.75 2.75 0 0 1 12 8v11a2.75 2.75 0 0 0-2.75-2.75H4.5v-11Zm15 0h-4.75A2.75 2.75 0 0 0 12 8v11a2.75 2.75 0 0 1 2.75-2.75h4.75v-11Z"/></svg><span class="nav-label">Overview Program TJSL</span></span>
        <svg data-chevron class="menu-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
    </button>
    <div id="program-menu" class="super-submenu {{ request()->routeIs('superadmin.programs.*')?'':'hidden' }}">
        <a href="{{ route('superadmin.programs.index') }}">Status Program</a>
    </div>
</div>

<div class="super-menu-group">
    <button type="button" data-sidebar-toggle="assistance-menu" class="super-menu-button">
        <span><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2.75 8 4.5v9.5l-8 4.5-8-4.5v-9.5l8-4.5Zm0 9 8-4.5M12 11.75l-8-4.5M12 11.75v9.5"/></svg><span class="nav-label">Overview Bantuan TJSL</span></span>
        <svg data-chevron class="menu-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
    </button>
    <div id="assistance-menu" class="super-submenu {{ request()->routeIs('superadmin.assistance.*')?'':'hidden' }}">
        <a href="{{ route('superadmin.assistance.index') }}">Status Bantuan TJSL</a>
    </div>
</div>

<a href="{{ route('superadmin.users.index') }}" class="super-menu-link {{ request()->routeIs('superadmin.users.*')?'is-active':'' }}">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5 1a3 3 0 0 0 0-6M19 20v-1.5a4 4 0 0 0-2.5-3.7"/></svg><span class="nav-label">User</span>
</a>

<a href="{{ route('superadmin.pumk.index') }}" class="super-menu-link {{ request()->routeIs('superadmin.pumk.*')?'is-active':'' }}">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5h16v14H4Zm3-9h10M7 14h6"/></svg>
    <span class="nav-label">Monitoring Admin PUMK</span>
</a>
