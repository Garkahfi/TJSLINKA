<x-layouts.pumk-admin title="RKA & Realisasi PUMK BRI">
    @php($suggestedYear = $years->isEmpty() ? max(2026, now()->year) : min(2100, (int) $years->max() + 1))
    <div class="pumk-page bri-plan">
        <div class="bri-plan-head">
            <div>
                <h1 class="pumk-page-title">RKA &amp; Realisasi PUMK BRI</h1>
                <p class="pumk-page-subtitle">Input resmi ini terpisah dari workbook snapshot outstanding.</p>
            </div>
            <div class="bri-plan-actions">
                <form method="GET" action="{{ route('pumk-admin.bri-planning.index') }}" class="bri-year-form">
                    <label for="tahun">Tahun</label>
                    <select id="tahun" name="tahun" onchange="this.form.submit()" @disabled(!$hasAvailableYear)>
                        @forelse($years as $option)<option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>@empty<option>Belum ada tahun</option>@endforelse
                    </select>
                </form>
                <button class="pumk-secondary-button" type="button" data-open-year-dialog>+ Tambah Tahun</button>
            </div>
        </div>

        @if(session('success'))<div class="pumk-alert success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="pumk-alert error">{{ $errors->first() }}</div>@endif

        @if($hasAvailableYear)
        <div class="bri-summary">
            <div class="pumk-card"><span>RKA {{ $year }}</span><strong>{{ $rka?->nominal_rka !== null ? 'Rp '.number_format($rka->nominal_rka, 0, ',', '.') : 'Belum tersedia' }}</strong></div>
            <div class="pumk-card"><span>Realisasi {{ $year }}</span><strong>Rp {{ number_format($actual, 0, ',', '.') }}</strong></div>
            <div class="pumk-card"><span>Progres</span><strong>{{ $progress === null ? 'Belum tersedia' : number_format($progress, 2, ',', '.').'%' }}</strong></div>
        </div>

        <section class="pumk-card bri-form-card">
            <h2>RKA Tahunan</h2>
            <form method="POST" action="{{ route('pumk-admin.bri-planning.rka.store') }}" class="bri-rka-form-row">
                @csrf<input type="hidden" name="tahun" value="{{ $year }}">
                <div><label for="nominal_rka">Nominal RKA (Rp)</label><input class="money-input" id="nominal_rka" name="nominal_rka" value="{{ $rka?->nominal_rka !== null ? number_format($rka->nominal_rka, 0, ',', '.') : '' }}" placeholder="Contoh: 175.000.000" required></div>
                <button class="pumk-primary-button" type="submit">Simpan RKA</button>
            </form>
        </section>

        <section class="pumk-card bri-form-card">
            <h2>Realisasi Penyaluran Bulanan</h2>
            <p class="bri-help">Nilai 0 berarti sudah diinput dengan realisasi nol. Kolom kosong berarti belum diinput.</p>
            <div class="bri-month-grid">
                @foreach([1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $month => $label)
                    @php($entry = $monthly->get($month))
                    <div class="bri-month-row">
                        <form method="POST" action="{{ route('pumk-admin.bri-planning.monthly.store') }}" class="bri-month-save">
                            @csrf<input type="hidden" name="tahun" value="{{ $year }}"><input type="hidden" name="bulan" value="{{ $month }}">
                            <label>{{ $label }}</label>
                            <input class="money-input" name="nominal_penyaluran" value="{{ $entry ? number_format($entry->nominal_penyaluran, 0, ',', '.') : '' }}" placeholder="Belum diinput" required>
                            <button class="pumk-primary-button" type="submit">Simpan</button>
                        </form>
                        @if($entry)<form method="POST" action="{{ route('pumk-admin.bri-planning.monthly.destroy', $entry) }}" class="bri-month-clear" data-confirm-empty="Input realisasi {{ $label }} {{ $year }} akan dikosongkan.&#10;Status bulan kembali menjadi &quot;Belum diinput&quot;.&#10;&#10;Lanjutkan?">@csrf @method('DELETE')<button class="bri-delete" type="submit">Kosongkan</button></form>@endif
                    </div>
                @endforeach
            </div>
        </section>
        @else
            <section class="pumk-card bri-empty-year">
                <strong>Belum ada tahun PUMK BRI.</strong>
                <span>Tambahkan tahun terlebih dahulu untuk mengisi RKA dan realisasi bulanan.</span>
            </section>
        @endif

        <dialog class="bri-year-dialog" data-year-dialog>
            <form method="POST" action="{{ route('pumk-admin.bri-planning.year.store') }}" class="bri-year-dialog-card">
                @csrf
                <div class="bri-year-dialog-head"><h2>Tambah Tahun PUMK BRI</h2><button type="button" data-close-year-dialog aria-label="Tutup">&times;</button></div>
                <div><label for="tahun_baru">Tahun</label><input id="tahun_baru" name="tahun_baru" type="number" min="2026" max="2100" step="1" value="{{ old('tahun_baru', $suggestedYear) }}" required></div>
                <div class="bri-year-dialog-actions"><button class="pumk-secondary-button" type="button" data-close-year-dialog>Batal</button><button class="pumk-primary-button" type="submit">Tambah Tahun</button></div>
            </form>
        </dialog>
    </div>
    <style>
        .bri-plan,.bri-plan *{box-sizing:border-box}.bri-plan-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.bri-plan-actions,.bri-year-form{display:flex;align-items:center;gap:10px;min-width:0}.bri-plan select,.bri-plan input,.bri-year-dialog input{width:100%;min-width:0;min-height:42px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;padding:8px 12px;font:500 14px Poppins,sans-serif}.bri-plan select{min-width:120px}.bri-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:18px}.bri-summary .pumk-card{display:grid;min-width:0;gap:7px;padding:18px}.bri-summary span{color:#64748b;font-size:13px}.bri-summary strong{color:#1746c7;font-size:22px;overflow-wrap:anywhere}.bri-form-card{max-width:100%;min-width:0;margin-bottom:18px;padding:22px}.bri-form-card h2{margin:0 0 14px;font-size:20px}.bri-rka-form-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:12px}.bri-rka-form-row>div{display:grid;min-width:0;gap:7px}.bri-help{margin:-7px 0 15px;color:#64748b;font-size:13px}.bri-month-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.bri-month-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;min-width:0;max-width:100%;gap:8px;border:1px solid #e2e8f0;border-radius:8px;padding:12px}.bri-month-save{display:grid;grid-template-columns:90px minmax(0,1fr) auto;align-items:center;min-width:0;gap:8px}.bri-month-save label{font-weight:600}.bri-month-save input{width:100%;min-width:0}.bri-month-clear{min-width:0}.bri-month-row button{white-space:nowrap}.bri-delete{width:100%;min-height:40px;border:1px solid #dc2626;border-radius:6px;background:#fff;padding:7px 10px;color:#dc2626;cursor:pointer}.bri-empty-year{display:grid;gap:6px;padding:24px;color:#475569}.bri-year-dialog{width:min(470px,calc(100% - 32px));border:0;border-radius:12px;padding:0;box-shadow:0 24px 70px rgba(15,23,42,.3)}.bri-year-dialog::backdrop{background:rgba(15,23,42,.56)}.bri-year-dialog-card{display:grid;gap:18px;padding:22px}.bri-year-dialog-card>div:not(.bri-year-dialog-head):not(.bri-year-dialog-actions){display:grid;gap:7px}.bri-year-dialog-head,.bri-year-dialog-actions{display:flex;align-items:center;justify-content:space-between;gap:12px}.bri-year-dialog-head h2{margin:0;font-size:20px}.bri-year-dialog-head button{border:0;background:transparent;font-size:26px;cursor:pointer}.bri-year-dialog-actions{justify-content:flex-end}@media(max-width:1100px){.bri-month-grid{grid-template-columns:1fr}}@media(max-width:992px){.bri-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:800px){.bri-plan-head{align-items:stretch;flex-direction:column}.bri-plan-actions{justify-content:space-between;flex-wrap:wrap}.bri-year-form{flex:1}.bri-year-form select{flex:1}}@media(max-width:640px){.bri-summary,.bri-rka-form-row{grid-template-columns:1fr}.bri-plan-actions,.bri-year-form{align-items:stretch;flex-direction:column}.bri-plan-actions button,.bri-rka-form-row button{width:100%}.bri-month-row,.bri-month-save{grid-template-columns:1fr}.bri-month-row button{width:100%}.bri-form-card{padding:16px}.bri-year-dialog-actions{display:grid;grid-template-columns:1fr}.bri-year-dialog-actions button{width:100%}}
    </style>
    <script>
        document.querySelectorAll('.money-input').forEach(function(input){input.addEventListener('input',function(){const negative=this.value.trim().startsWith('-');const digits=this.value.replace(/\D/g,'');this.value=(negative?'-':'')+(digits?new Intl.NumberFormat('id-ID').format(Number(digits)):'');});});
        document.querySelectorAll('[data-confirm-empty]').forEach(function(form){form.addEventListener('submit',function(event){if(!window.confirm(form.dataset.confirmEmpty)){event.preventDefault();}});});
        const yearDialog=document.querySelector('[data-year-dialog]');
        document.querySelector('[data-open-year-dialog]')?.addEventListener('click',function(){yearDialog?.showModal();});
        document.querySelectorAll('[data-close-year-dialog]').forEach(function(button){button.addEventListener('click',function(){yearDialog?.close();});});
        yearDialog?.addEventListener('click',function(event){if(event.target===yearDialog){yearDialog.close();}});
        @if($errors->has('tahun_baru')) yearDialog?.showModal(); @endif
    </script>
</x-layouts.pumk-admin>
