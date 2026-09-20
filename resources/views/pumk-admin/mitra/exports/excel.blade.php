@php
    // Susun processing instruction tanpa tanda pembuka XML utuh agar Intelephense
    // tidak salah menganggap deklarasi XML sebagai pembuka blok PHP.
    $xmlDeclaration = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';
    $excelDeclaration = '<'.'?mso-application progid="Excel.Sheet"?'.'>';
    $statusLabels = ['lancar' => 'Lancar', 'kurang_lancar' => 'Kurang Lancar', 'diragukan' => 'Diragukan', 'macet' => 'Macet'];
    $status = $kartu['calculation']['kolektibilitas'] ?? $pinjaman->kolektibilitas;
    $isRescheduled = filled($pinjaman->reschedule_ke1) || filled($pinjaman->reschedule_ke2) || filled($pinjaman->reschedule_ke3) || filled($pinjaman->reschedule_ke4);
@endphp
{!! $xmlDeclaration !!}
{!! $excelDeclaration !!}
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
    <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
        <Title>Kartu Piutang {{ $mitra->nama_mitra }}</Title>
        <Author>TJSL INKA</Author>
        <Created>{{ $generatedAt->toIso8601String() }}</Created>
    </DocumentProperties>
    <Styles>
        <Style ss:ID="Default" ss:Name="Normal">
            <Alignment ss:Vertical="Center"/>
            <Font ss:FontName="Arial" ss:Size="9"/>
        </Style>
        <Style ss:ID="Title"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="14" ss:Bold="1"/></Style>
        <Style ss:ID="Subtitle"><Alignment ss:Horizontal="Center"/><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/></Style>
        <Style ss:ID="Label"><Font ss:FontName="Arial" ss:Size="9" ss:Bold="1"/></Style>
        <Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders><Font ss:FontName="Arial" ss:Size="9" ss:Bold="1"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/></Style>
        <Style ss:ID="Cell"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
        <Style ss:ID="CellCenter"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>
        <Style ss:ID="Money"><Alignment ss:Horizontal="Right"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders><NumberFormat ss:Format="#,##0"/></Style>
        <Style ss:ID="MoneyCurrent"><Alignment ss:Horizontal="Right"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders><Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0"/></Style>
        <Style ss:ID="Shortage"><Alignment ss:Horizontal="Right"/><Font ss:Bold="1"/><Interior ss:Color="#FDE047" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0"/></Style>
    </Styles>
    <Worksheet ss:Name="Kartu Piutang">
        <Table>
            <Column ss:Width="32"/><Column ss:Width="75"/><Column ss:Width="125"/><Column ss:Width="85"/><Column ss:Width="75"/><Column ss:Width="85"/><Column ss:Width="95"/><Column ss:Width="85"/>
            <Row ss:Height="24"><Cell ss:MergeAcross="7" ss:StyleID="Subtitle"><Data ss:Type="String">PROGRAM KEMITRAAN DAN BINA LINGKUNGAN</Data></Cell></Row>
            <Row ss:Height="30"><Cell ss:MergeAcross="7" ss:StyleID="Title"><Data ss:Type="String">KARTU PIUTANG</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="7" ss:StyleID="Subtitle"><Data ss:Type="String">{{ $kartu['periode_label'] }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="1" ss:StyleID="Label"><Data ss:Type="String">Nama Perusahaan</Data></Cell><Cell ss:MergeAcross="5"><Data ss:Type="String">{{ $mitra->nama_mitra }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="1" ss:StyleID="Label"><Data ss:Type="String">Pemilik</Data></Cell><Cell ss:MergeAcross="5"><Data ss:Type="String">{{ $mitra->nama_pemilik ?: '-' }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="1" ss:StyleID="Label"><Data ss:Type="String">Wilayah</Data></Cell><Cell ss:MergeAcross="2"><Data ss:Type="String">{{ $mitra->wilayah?->nama ?? $mitra->wilayah_sumber ?? '-' }}</Data></Cell><Cell ss:StyleID="Label"><Data ss:Type="String">Status</Data></Cell><Cell ss:MergeAcross="1"><Data ss:Type="String">{{ $statusLabels[$status] ?? 'Belum dihitung' }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="1" ss:StyleID="Label"><Data ss:Type="String">Angsuran Pertama</Data></Cell><Cell ss:MergeAcross="2"><Data ss:Type="String">{{ $pinjaman->mulai_angsuran?->translatedFormat('F Y') ?? '-' }}</Data></Cell><Cell ss:StyleID="Label"><Data ss:Type="String">Bunga</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="Money"><Data ss:Type="Number">{{ (float) ($pinjaman->pinjaman_bunga ?? 0) }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="1" ss:StyleID="Label"><Data ss:Type="String">Jatuh Tempo</Data></Cell><Cell ss:MergeAcross="2"><Data ss:Type="String">{{ $pinjaman->selesai_angsuran?->translatedFormat('F Y') ?? '-' }}{{ $kartu['tenor'] ? ' ('.$kartu['tenor'].'x)' : '' }}</Data></Cell><Cell ss:StyleID="Label"><Data ss:Type="String">Angsuran/bulan</Data></Cell><Cell ss:MergeAcross="1" ss:StyleID="Money"><Data ss:Type="Number">{{ (float) ($pinjaman->nilai_angsuran_bulanan ?? 0) }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="1" ss:StyleID="Label"><Data ss:Type="String">Jumlah Pinjaman</Data></Cell><Cell ss:MergeAcross="2" ss:StyleID="Money"><Data ss:Type="Number">{{ (float) ($pinjaman->pinjaman_pokok ?? 0) }}</Data></Cell><Cell ss:MergeAcross="2"><Data ss:Type="String">{{ $isRescheduled ? 'Rescheduling' : '' }}</Data></Cell></Row>
            <Row/>
            <Row ss:Height="32">
                <Cell ss:StyleID="Header"><Data ss:Type="String">No</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Tanggal</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Nomor Bukti Pembayaran</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Pokok</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Bunga</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Total</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Saldo Pokok</Data></Cell><Cell ss:StyleID="Header"><Data ss:Type="String">Saldo Bunga</Data></Cell>
            </Row>
            @foreach($kartu['jadwal'] as $row)
                @php $moneyStyle = $row['is_bulan_berjalan'] ? 'MoneyCurrent' : 'Money'; @endphp
                <Row>
                    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $row['no'] ?? '-' }}</Data></Cell>
                    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ $row['tanggal']->format('d-M-Y') }}</Data></Cell>
                    <Cell ss:StyleID="CellCenter"><Data ss:Type="String">{{ filled($row['nomor_bukti']) ? $row['nomor_bukti'] : '-' }}{{ filled($row['catatan']) ? ' - '.$row['catatan'] : '' }}</Data></Cell>
                    @foreach(['pokok_dibayar', 'bunga_dibayar', 'total_dibayar'] as $field)
                        @if($row['has_payment'])<Cell ss:StyleID="{{ $moneyStyle }}"><Data ss:Type="Number">{{ (float) $row[$field] }}</Data></Cell>@else<Cell ss:StyleID="CellCenter"><Data ss:Type="String">-</Data></Cell>@endif
                    @endforeach
                    <Cell ss:StyleID="{{ $moneyStyle }}"><Data ss:Type="Number">{{ (float) $row['saldo_pokok'] }}</Data></Cell>
                    <Cell ss:StyleID="{{ $moneyStyle }}"><Data ss:Type="Number">{{ (float) $row['saldo_bunga'] }}</Data></Cell>
                </Row>
            @endforeach
            <Row><Cell ss:MergeAcross="6" ss:StyleID="Label"><Data ss:Type="String">Kekurangan</Data></Cell><Cell ss:StyleID="Shortage"><Data ss:Type="Number">{{ (float) $kartu['kekurangan'] }}</Data></Cell></Row>
            <Row><Cell ss:MergeAcross="7"><Data ss:Type="String">Dibuat {{ $generatedAt->translatedFormat('d F Y H:i') }} WIB</Data></Cell></Row>
        </Table>
        <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><Selected/><FreezePanes/><FrozenNoSplit/><SplitHorizontal>9</SplitHorizontal><TopRowBottomPane>9</TopRowBottomPane><ActivePane>2</ActivePane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions>
    </Worksheet>
</Workbook>
