<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Piutang {{ $mitra->nama_mitra }}</title>
    <style>
        @page{margin:18mm 11mm 14mm}*{box-sizing:border-box}body{margin:0;color:#111827;font-family:"DejaVu Sans",sans-serif;font-size:8px}.sheet{border:1.3px solid #111}.brand{width:100%;border-collapse:collapse;border-bottom:1.3px solid #111}.brand td{height:52px;padding:6px 10px;vertical-align:middle}.brand .logo{width:26%}.brand img{width:105px;max-height:38px}.brand .title{text-align:center;text-transform:uppercase;font-size:10px;font-weight:bold;line-height:1.25}.brand .title strong{display:block;font-size:14px}.brand .status{width:26%;text-align:right;font-weight:bold}.info{width:100%;border-collapse:collapse;border-bottom:1.3px solid #111}.info td{padding:3px 8px;vertical-align:top}.info .left{width:58%;padding:10px 13px}.info .right{width:42%;border-left:1.3px solid #111;padding:10px;text-align:center}.details{width:100%;border-collapse:collapse}.details td{padding:2px 0}.details .label{width:112px}.details .sep{width:10px}.card-title{display:inline-block;border:2px solid #111;padding:4px 15px;font-size:15px;font-weight:bold}.region{margin-top:5px;font-size:11px;font-weight:bold;text-transform:uppercase}.reschedule{min-height:12px;font-style:italic;font-weight:bold}.mini{width:100%;margin-top:5px;border-collapse:collapse;text-align:left}.mini td{border-bottom:.6px solid #777;padding:2px 5px}.warning{margin:8px;border:1px solid #d97706;background:#fffbeb;padding:6px;color:#92400e}.card-table{width:100%;border-collapse:collapse;table-layout:fixed;font-variant-numeric:tabular-nums}.card-table thead{display:table-header-group}.card-table tr{page-break-inside:avoid}.card-table th,.card-table td{border-right:.7px solid #111;border-bottom:.7px solid #111;padding:3px 3px;vertical-align:middle}.card-table th:last-child,.card-table td:last-child{border-right:0}.card-table th{background:#e5e7eb;text-align:center;font-weight:bold}.card-table .center{text-align:center}.card-table .money{text-align:right}.card-table .current{background:#fef3c7}.card-table .source{background:#eff6ff}.card-table .note{display:block;margin-top:2px;color:#475569;font-size:6px}.card-table tfoot td{font-size:9px;font-weight:bold;border-bottom:0}.card-table tfoot .shortage{background:#fde047;text-align:right}.muted{color:#64748b}
    </style>
</head>
<body>
@php
    $statusLabels = ['lancar' => 'Lancar', 'kurang_lancar' => 'Kurang Lancar', 'diragukan' => 'Diragukan', 'macet' => 'Macet'];
    $status = $kartu['calculation']['kolektibilitas'] ?? $pinjaman->kolektibilitas;
    $isRescheduled = filled($pinjaman->reschedule_ke1) || filled($pinjaman->reschedule_ke2) || filled($pinjaman->reschedule_ke3) || filled($pinjaman->reschedule_ke4);
    $angka = static fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.');
@endphp
<div class="sheet">
    <table class="brand"><tr>
        <td class="logo">@if($logoDataUri)<img src="{{ $logoDataUri }}" alt="INKA">@endif</td>
        <td class="title">Program<strong>Kemitraan dan Bina Lingkungan</strong></td>
        <td class="status">{{ $statusLabels[$status] ?? 'Belum dihitung' }}</td>
    </tr></table>
    <table class="info"><tr>
        <td class="left">
            <table class="details">
                <tr><td class="label">Nama Perusahaan</td><td class="sep">:</td><td><strong>{{ $mitra->nama_mitra }}</strong></td></tr>
                <tr><td class="label">Pemilik</td><td class="sep">:</td><td><strong>{{ $mitra->nama_pemilik ?: '-' }}</strong></td></tr>
                <tr><td class="label">Angsuran Pertama</td><td class="sep">:</td><td><strong>{{ $pinjaman->mulai_angsuran?->translatedFormat('F Y') ?? '-' }}</strong></td></tr>
                <tr><td class="label">Jatuh Tempo</td><td class="sep">:</td><td><strong>{{ $pinjaman->selesai_angsuran?->translatedFormat('F Y') ?? '-' }}{{ $kartu['tenor'] ? ' ('.$kartu['tenor'].'x)' : '' }}</strong></td></tr>
                <tr><td class="label">Jumlah Pinjaman</td><td class="sep">:</td><td><strong>Rp {{ $angka($pinjaman->pinjaman_pokok) }}</strong></td></tr>
            </table>
        </td>
        <td class="right">
            <div class="card-title">KARTU PIUTANG</div>
            <div class="region">{{ $mitra->wilayah?->nama ?? $mitra->wilayah_sumber ?? 'Wilayah belum diisi' }}</div>
            <div class="reschedule">{{ $isRescheduled ? 'Rescheduling' : '' }}</div>
            <table class="mini"><tr><td>Bunga</td><td><strong>Rp {{ $angka($pinjaman->pinjaman_bunga) }}</strong></td></tr><tr><td>Angs/bln</td><td><strong>Rp {{ $angka($pinjaman->nilai_angsuran_bulanan) }}</strong></td></tr></table>
        </td>
    </tr></table>
    <div class="warning">{{ $kartu['periode_label'] }}</div>
    @if($kartu['jadwal_error'])<div class="warning">{{ $kartu['jadwal_error'] }}</div>@endif
    <table class="card-table">
        <colgroup><col style="width:5%"><col style="width:11%"><col style="width:19%"><col style="width:11%"><col style="width:10%"><col style="width:11%"><col style="width:18%"><col style="width:15%"></colgroup>
        <thead>
            <tr><th rowspan="2">No</th><th rowspan="2">Tanggal</th><th rowspan="2">Nomor Bukti<br>Pembayaran</th><th colspan="3">Pembayaran Angsuran (Rp)</th><th colspan="2">Saldo Pinjaman</th></tr>
            <tr><th>Pokok</th><th>Bunga</th><th>Total</th><th>Pokok</th><th>Bunga</th></tr>
        </thead>
        <tbody>
        @forelse($kartu['jadwal'] as $row)
            <tr class="{{ $row['is_bulan_berjalan'] ? 'current' : (filled($row['catatan']) ? 'source' : '') }}">
                <td class="center">{{ $row['no'] ?? '-' }}</td><td class="center">{{ $row['tanggal']->format('d-M-y') }}</td>
                <td class="center">{{ filled($row['nomor_bukti']) ? $row['nomor_bukti'] : '-' }}@if(filled($row['catatan']))<span class="note">{{ $row['catatan'] }}</span>@endif</td>
                @foreach(['pokok_dibayar', 'bunga_dibayar', 'total_dibayar'] as $field)<td class="{{ $row['has_payment'] ? 'money' : 'center' }}">{{ $row['has_payment'] ? $angka($row[$field]) : '-' }}</td>@endforeach
                <td class="money">{{ $angka($row['saldo_pokok']) }}</td><td class="money">{{ $angka($row['saldo_bunga']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center">Belum ada jadwal yang dapat ditampilkan.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr><td colspan="7" class="money">Kekurangan</td><td class="shortage">{{ $angka($kartu['kekurangan']) }}</td></tr></tfoot>
    </table>
</div>
</body>
</html>
