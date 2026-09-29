<x-dynamic-component :component="$isSuperadmin ? 'layouts.admin' : 'layouts.pumk-admin'" title="Rincian Data Monitoring PUMK">
    @php($reasonLabels = [
        'missing_principal' => 'Pokok belum diketahui; periksa data kontrak.',
        'missing_interest' => 'Bunga belum diketahui; periksa data kontrak.',
        'missing_import_baseline' => 'Saldo baseline impor tidak lengkap; periksa sumber.',
        'baseline_after_cutoff' => 'Baseline baru berlaku setelah tanggal posisi.',
        'opening_after_cutoff' => 'Saldo awal berlaku setelah tanggal posisi.',
        'classification_missing' => 'Kategori tidak tersedia pada sumber.',
        'classification_not_effective' => 'Kategori belum terbukti berlaku pada tanggal posisi.',
        'province_unmapped' => 'Wilayah ada, tetapi pemetaan provinsi belum tersedia.',
        'negative_balance_unreviewed' => 'Saldo negatif terbuka; periksa kelebihan bayar.',
        'mixed_component_balance' => 'Komponen pokok dan bunga berlawanan tanda; periksa alokasi.',
        'invalid_source_date' => 'Tanggal sumber perlu diverifikasi.',
    ])
    <div class="mx-auto max-w-6xl space-y-5 px-4 py-8 text-slate-900">
        <div>
            <h1 class="text-3xl font-bold">Rincian Data Monitoring PUMK</h1>
            <p class="mt-2 text-sm text-slate-600">Hanya kasus yang perlu ditinjau. Saldo kosong berarti belum dapat dihitung, bukan nol.</p>
        </div>
        <form method="GET" class="flex items-end gap-3">
            <label for="year" class="text-sm font-semibold">Tahun laporan</label>
            <select id="year" name="year" class="rounded border border-slate-400 bg-white px-3 py-2">
                @foreach($report['years'] as $year)
                    <option value="{{ $year }}" @selected($year === $report['year'])>{{ $year }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded bg-blue-700 px-4 py-2 font-semibold text-white">Lihat</button>
        </form>
        <p class="text-sm text-slate-600">Posisi {{ $report['as_of_date'] ?? 'belum tersedia' }} · {{ $rows->total() }} temuan</p>
        <div class="overflow-x-auto rounded border border-slate-300 bg-white">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-slate-900 text-left text-white"><tr>
                    <th class="p-3">Pinjaman</th><th class="p-3">Mitra</th><th class="p-3">Alasan</th>
                    <th class="p-3">Sumber / tanggal</th><th class="p-3">Saldo diketahui</th><th class="p-3">Kartu</th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-t border-slate-200">
                            <td class="p-3">#{{ $row['pinjaman_id'] }}</td>
                            <td class="p-3">{{ $row['nama_mitra'] ?: 'Belum ada nama' }}</td>
                            <td class="p-3"><code>{{ $row['reason_code'] }}</code>@if($row['attribute'])<br><span class="text-slate-600">{{ $row['attribute'] }}</span>@endif<br><span class="text-slate-600">{{ $reasonLabels[$row['reason_code']] ?? 'Periksa sumber terkait.' }}</span></td>
                            <td class="p-3">{{ $row['source_kind'] }}<br>{{ $row['source_date'] ?? 'Tanggal sumber tidak ada' }}</td>
                            <td class="p-3">{{ $row['total'] === null ? 'Belum diketahui' : 'Rp '.number_format((float) $row['total'], 2, ',', '.') }}</td>
                            <td class="p-3"><a class="font-semibold text-blue-700 underline" href="{{ $isSuperadmin ? route('superadmin.pumk.kartu', $row['mitra_id']) : route('pumk-admin.mitra.show', $row['mitra_id']) }}">Buka kartu</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-center text-slate-600">Tidak ada temuan pada posisi ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    </div>
</x-dynamic-component>
