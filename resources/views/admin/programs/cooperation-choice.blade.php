<x-layouts.admin title="Pilih Jenis Kerja Sama">
    <style>
        .cooperation-choice-page{max-width:1100px;margin:0 auto;font-family:Poppins,sans-serif;color:#0f172a}
        .cooperation-choice-heading{margin:0 0 12px;text-align:center;font-size:38px;line-height:1.2;font-weight:700;color:#000}
        .cooperation-choice-lead{max-width:720px;margin:0 auto 38px;color:#64748b;font-size:15px;line-height:1.7;text-align:center}
        .cooperation-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
        .cooperation-choice-card{position:relative;display:flex;min-height:270px;box-sizing:border-box;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;border:1px solid #dbe2ea;border-radius:14px;background:#fff;padding:32px;color:#0f172a;text-align:center;text-decoration:none;box-shadow:0 4px 12px rgba(15,23,42,.12);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
        .cooperation-choice-card::before{position:absolute;inset:0 0 auto;height:7px;background:var(--card-accent);content:""}
        .cooperation-choice-card:hover,.cooperation-choice-card:focus-visible{transform:translateY(-4px);border-color:var(--card-accent);box-shadow:0 12px 24px rgba(15,23,42,.16);outline:0}
        .cooperation-choice-card.pks{--card-accent:#2653ff}
        .cooperation-choice-card.non-pks{--card-accent:#6b7280}
        .cooperation-choice-icon{display:grid;width:72px;height:72px;margin-bottom:22px;place-items:center;border-radius:18px;background:color-mix(in srgb,var(--card-accent) 13%,white);color:var(--card-accent)}
        .cooperation-choice-icon svg{width:38px;height:38px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
        .cooperation-choice-card h2{margin:0;font-size:26px;line-height:1.25;font-weight:700}
        .cooperation-choice-action{margin-top:23px;border-radius:6px;background:var(--card-accent);padding:10px 24px;color:#fff;font-size:14px;font-weight:600}
        @media(max-width:720px){.cooperation-choice-heading{font-size:30px}.cooperation-choice-grid{grid-template-columns:1fr}.cooperation-choice-card{min-height:230px}}
    </style>

    <main class="cooperation-choice-page">
        <h1 class="cooperation-choice-heading">Pilih Jenis Kerja Sama Program TJSL</h1>
        <p class="cooperation-choice-lead">
            Pilih kebutuhan dokumen kerja sama sebelum melanjutkan ke form Program TJSL.
        </p>

        <div class="cooperation-choice-grid">
            <a
                class="cooperation-choice-card pks"
                href="{{ route('admin.programs.cooperation.form', ['jenisKerjasama' => 'pks']) }}"
            >
                <span class="cooperation-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M6 3.5h9l3 3v14H6zM15 3.5v4h3M9 12h6M9 16h6"/>
                        <path d="m9 8 1 1 2-2"/>
                    </svg>
                </span>
                <h2>PKS</h2>
                <span class="cooperation-choice-action">Pilih PKS</span>
            </a>

            <a
                class="cooperation-choice-card non-pks"
                href="{{ route('admin.programs.cooperation.form', ['jenisKerjasama' => 'non-pks']) }}"
            >
                <span class="cooperation-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M5 5h14v14H5zM8.5 9h7M8.5 12h7M8.5 15h4"/>
                    </svg>
                </span>
                <h2>NON-PKS</h2>
                <span class="cooperation-choice-action">Pilih NON-PKS</span>
            </a>
        </div>
    </main>
</x-layouts.admin>
