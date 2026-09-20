{{-- Canonical admin shell is the anonymous component used by all admin pages. --}}
<x-layouts.admin :title="$title ?? 'Dashboard Admin'">
    @yield('content')
</x-layouts.admin>
