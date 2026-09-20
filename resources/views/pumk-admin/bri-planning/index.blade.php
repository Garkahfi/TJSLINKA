<x-layouts.pumk-admin title="RKA & Realisasi PUMK BRI">
    <div class="pumk-page bri-plan">
        <div class="bri-plan-head">
            <div>
                <h1 class="pumk-page-title">RKA &amp; Realisasi PUMK BRI</h1>
                <p class="pumk-page-subtitle">Input resmi ini terpisah dari workbook snapshot outstanding.</p>
            </div>
            <form method="GET" action="{{ route('pumk-admin.bri-planning.index') }}">
                <label for="tahun">Tahun</label>
                <select id="tahun" name="tahun" onchange="this.form.submit()">
                    @foreach($years as $option)<option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>@endforeach
                </select>
            </form>
        </div>

        @if(session('success'))<div class="pumk-alert success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="pumk-alert error">{{ $errors->first() }}</div>@endif

        <div class="bri-summary">
            <div class="pumk-card"><span>RKA {{ $year }}</span><strong>{{ $rka ? 'Rp '.number_format($rka->nominal_rka, 0, ',', '.') : 'Belum tersedia' }}</strong></div>
            <div class="pumk-card"><span>Realisasi {{ $year }}</span><strong>Rp {{ number_format($actual, 0, ',', '.') }}</strong></div>
            <div class="pumk-card"><span>Progres</span><strong>{{ $progress === null ? 'Belum tersedia' : number_format($progress, 2, ',', '.').'%' }}</strong></div>
        </div>

        <section class="pumk-card bri-form-card">
            <h2>RKA Tahunan</h2>
            <form method="POST" action="{{ route('pumk-admin.bri-planning.rka.store') }}" class="bri-inline-form">
                @csrf<input type="hidden" name="tahun" value="{{ $year }}">
                <div><label for="nominal_rka">Nominal RKA (Rp)</label><input class="money-input" id="nominal_rka" name="nominal_rka" value="{{ $rka ? number_format($rka->nominal_rka, 0, ',', '.') : '' }}" placeholder="Contoh: 175.000.000" required></div>
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
                        <form method="POST" action="{{ route('pumk-admin.bri-planning.monthly.store') }}">
                            @csrf<input type="hidden" name="tahun" value="{{ $year }}"><input type="hidden" name="bulan" value="{{ $month }}">
                            <label>{{ $label }}</label>
                            <input class="money-input" name="nominal_penyaluran" value="{{ $entry ? number_format($entry->nominal_penyaluran, 0, ',', '.') : '' }}" placeholder="Belum diinput" required>
                            <button class="pumk-primary-button" type="submit">Simpan</button>
                        </form>
                        @if($entry)<form method="POST" action="{{ route('pumk-admin.bri-planning.monthly.destroy', $entry) }}">@csrf @method('DELETE')<button class="bri-delete" type="submit">Kosongkan</button></form>@endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    <style>
        .bri-plan-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.bri-plan-head form{display:flex;align-items:center;gap:10px}.bri-plan select,.bri-plan input{min-height:42px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;padding:8px 12px;font:500 14px Poppins,sans-serif}.bri-plan select{min-width:120px}.bri-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:18px}.bri-summary .pumk-card{display:grid;gap:7px;padding:18px}.bri-summary span{color:#64748b;font-size:13px}.bri-summary strong{color:#1746c7;font-size:22px}.bri-form-card{margin-bottom:18px;padding:22px}.bri-form-card h2{margin:0 0 14px;font-size:20px}.bri-inline-form{display:grid;grid-template-columns:1fr auto;align-items:end;gap:14px}.bri-inline-form div{display:grid;gap:7px}.bri-help{margin:-7px 0 15px;color:#64748b;font-size:13px}.bri-month-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.bri-month-row{display:grid;grid-template-columns:1fr auto;align-items:end;gap:7px;border:1px solid #e2e8f0;border-radius:8px;padding:12px}.bri-month-row>form:first-child{display:grid;grid-template-columns:120px 1fr auto;align-items:center;gap:8px}.bri-delete{min-height:40px;border:1px solid #dc2626;border-radius:6px;background:#fff;padding:7px 10px;color:#dc2626;cursor:pointer}@media(max-width:800px){.bri-plan-head{align-items:stretch;flex-direction:column}.bri-summary,.bri-month-grid{grid-template-columns:1fr}.bri-month-row>form:first-child{grid-template-columns:1fr}.bri-inline-form{grid-template-columns:1fr}}
    </style>
    <script>document.querySelectorAll('.money-input').forEach(function(input){input.addEventListener('input',function(){const negative=this.value.trim().startsWith('-');const digits=this.value.replace(/\D/g,'');this.value=(negative?'-':'')+(digits?new Intl.NumberFormat('id-ID').format(Number(digits)):'');});});</script>
</x-layouts.pumk-admin>
