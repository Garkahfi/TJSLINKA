<style>
    .public-footer-shell{box-sizing:border-box;display:grid;width:100%;gap:32px;padding-inline:clamp(28px,3vw,52px)}
    .footer-inka-logo{display:block;width:min(320px,100%);height:auto}
    @media(min-width:768px){
        .public-footer-shell{grid-template-columns:58% 17% 25%;align-items:center;gap:0}
    }
</style>
<footer class="bg-[#1d222b] py-14 text-white">
    <div class="public-footer-shell">
        <div class="flex items-center">
            <img
                class="footer-inka-logo"
                src="{{ asset('images/logo/inka-footer-white.png') }}"
                alt="PT Industri Kereta Api (Persero)"
            >
        </div>

        <div>
            <h2 class="mb-3 text-lg font-bold">RESOURCES</h2>
            <nav class="space-y-2 text-slate-200" aria-label="Navigasi footer">
                <a class="block hover:text-white" href="{{ route('home') }}">Home</a>
                <a class="block hover:text-white" href="{{ route('teras') }}">Teras TJSL</a>
                <a class="block hover:text-white" href="{{ route('program.overview') }}">Program TJSL</a>
            </nav>
        </div>

        <address class="not-italic text-slate-200">
            <h2 class="mb-3 text-lg font-bold text-white">CONTACT</h2>
            <p class="flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5h4l2 5-2.5 1.8a14 14 0 0 0 4.7 4.7l1.8-2.5 5 2v4a2 2 0 0 1-2 2C9.2 21.5 2.5 14.8 2.5 6.5a2 2 0 0 1 2-2Z"/>
                </svg>
                <span>(+62) 811-310-8585</span>
            </p>
            <p class="mt-1 flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/>
                </svg>
                <span>tjsl@inka.co.id</span>
            </p>
            <p class="mt-1 flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="5"/>
                    <circle cx="12" cy="12" r="4"/>
                    <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
                </svg>
                <span>@tjsl.inka</span>
            </p>
        </address>
    </div>
</footer>
