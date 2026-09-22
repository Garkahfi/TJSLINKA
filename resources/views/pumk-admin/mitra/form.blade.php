<x-layouts.pumk-admin :title="$mitra->exists ? 'Edit Mitra Binaan' : 'Tambah Mitra Binaan'">
    @include('pumk-admin.partials.module-styles')

    @php
        $editing = $mitra->exists;
        $formAction = $editing ? route('pumk-admin.mitra.update', $mitra) : route('pumk-admin.mitra.store');
        $dateValue = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '';
    @endphp

    <div class="pumk-page">
        <div class="pumk-page-header">
            <div>
                <h1 class="pumk-page-title">{{ $editing ? 'Edit Mitra Binaan' : 'Tambah Mitra Binaan' }}</h1>
                <p class="pumk-page-subtitle">Data tersimpan langsung tanpa alur approval. Kolom bertanda * wajib diisi.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="pumk-alert error" role="alert">
                <strong>Periksa kembali isian:</strong>
                <ul style="margin:8px 0 0;padding-left:20px">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form id="pumk-mitra-form" method="POST" action="{{ $formAction }}" autocomplete="off" enctype="multipart/form-data">
            @csrf
            @if($editing) @method('PUT') @endif

            <section class="pumk-card pumk-section">
                <h2 class="pumk-section-title">Identitas Mitra</h2>
                <div class="pumk-form-grid">
                    <div class="pumk-field">
                        <label for="nama_mitra">Nama Mitra *</label>
                        <input id="nama_mitra" name="nama_mitra" class="pumk-input" value="{{ old('nama_mitra', $mitra->nama_mitra) }}" maxlength="255" required>
                        @error('nama_mitra')<span class="pumk-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="pumk-field">
                        <label for="jenis_usaha">Jenis Usaha</label>
                        <input id="jenis_usaha" name="jenis_usaha" class="pumk-input" value="{{ old('jenis_usaha', $mitra->jenis_usaha) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="sektor_usaha_id">Sektor Usaha</label>
                        <select id="sektor_usaha_id" name="sektor_usaha_id" class="pumk-select">
                            <option value="">Pilih sektor usaha</option>
                            @foreach($sektorList as $sektor)
                                <option value="{{ $sektor->id }}" @selected((string) old('sektor_usaha_id', $mitra->sektor_usaha_id) === (string) $sektor->id)>{{ $sektor->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pumk-field">
                        <label for="wilayah_id">Wilayah</label>
                        <select id="wilayah_id" name="wilayah_id" class="pumk-select">
                            <option value="">Pilih wilayah</option>
                            @foreach($wilayahList as $wilayah)
                                <option value="{{ $wilayah->id }}" @selected((string) old('wilayah_id', $mitra->wilayah_id) === (string) $wilayah->id)>{{ $wilayah->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pumk-field full">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" class="pumk-textarea" maxlength="3000">{{ old('alamat', $mitra->alamat) }}</textarea>
                    </div>
                    <div class="pumk-field">
                        <label for="nama_pemilik">Nama Pemilik</label>
                        <input id="nama_pemilik" name="nama_pemilik" class="pumk-input" value="{{ old('nama_pemilik', $mitra->nama_pemilik) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="no_ktp">Nomor KTP</label>
                        <input id="no_ktp" name="no_ktp" class="pumk-input" value="{{ old('no_ktp', $mitra->no_ktp_encrypted) }}" maxlength="64" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="pumk-field">
                        <label for="no_telepon">Nomor Telepon</label>
                        <input id="no_telepon" name="no_telepon" class="pumk-input" value="{{ old('no_telepon', $mitra->no_telepon_encrypted) }}" maxlength="64" inputmode="tel" autocomplete="off">
                    </div>
                    <div class="pumk-field">
                        <label for="no_rekening">Nomor Rekening</label>
                        <input id="no_rekening" name="no_rekening" class="pumk-input" value="{{ old('no_rekening', $mitra->no_rekening_encrypted) }}" maxlength="64" inputmode="numeric" autocomplete="off">
                    </div>
                </div>
                <p class="pumk-note">Nomor KTP, telepon, dan rekening disimpan dalam bentuk terenkripsi serta tidak ditampilkan pada halaman daftar. Status Mitra ditentukan otomatis dari pinjamannya; Mitra arsip yang disimpan dari halaman ini akan memperoleh fasilitas pinjaman aktif baru tanpa menghapus histori lama.</p>
            </section>

            <section class="pumk-card pumk-section">
                <h2 class="pumk-section-title">Informasi Pinjaman</h2>
                <div class="pumk-form-grid">
                    <div class="pumk-field">
                        <label for="spj_awal">Nomor SPJ Awal</label>
                        <input id="spj_awal" name="spj_awal" class="pumk-input" value="{{ old('spj_awal', $pinjaman->spj_awal) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="reschedule_ke1">Kontrak Reschedule Ke-1</label>
                        <input id="reschedule_ke1" name="reschedule_ke1" class="pumk-input" value="{{ old('reschedule_ke1', $pinjaman->reschedule_ke1) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="reschedule_ke2">Kontrak Reschedule Ke-2</label>
                        <input id="reschedule_ke2" name="reschedule_ke2" class="pumk-input" value="{{ old('reschedule_ke2', $pinjaman->reschedule_ke2) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="reschedule_ke3">Kontrak Reschedule Ke-3</label>
                        <input id="reschedule_ke3" name="reschedule_ke3" class="pumk-input" value="{{ old('reschedule_ke3', $pinjaman->reschedule_ke3) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="reschedule_ke4">Kontrak Reschedule Ke-4</label>
                        <input id="reschedule_ke4" name="reschedule_ke4" class="pumk-input" value="{{ old('reschedule_ke4', $pinjaman->reschedule_ke4) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="tanggal_pencairan">Tanggal Pencairan</label>
                        <input id="tanggal_pencairan" type="date" name="tanggal_pencairan" class="pumk-input" value="{{ old('tanggal_pencairan', $dateValue($pinjaman->tanggal_pencairan)) }}">
                    </div>
                    <div class="pumk-field">
                        <label for="mulai_angsuran">Mulai Angsuran</label>
                        <input id="mulai_angsuran" type="date" name="mulai_angsuran" class="pumk-input" value="{{ old('mulai_angsuran', $dateValue($pinjaman->mulai_angsuran)) }}">
                    </div>
                    <div class="pumk-field">
                        <label for="selesai_angsuran">Selesai Angsuran</label>
                        <input id="selesai_angsuran" type="date" name="selesai_angsuran" class="pumk-input" value="{{ old('selesai_angsuran', $dateValue($pinjaman->selesai_angsuran)) }}">
                    </div>
                    <div class="pumk-field">
                        <label for="pinjaman_pokok">Pinjaman Pokok (Rp)</label>
                        <input id="pinjaman_pokok" type="number" min="0" step="0.01" name="pinjaman_pokok" class="pumk-input" value="{{ old('pinjaman_pokok', $pinjaman->pinjaman_pokok) }}">
                    </div>
                    <div class="pumk-field">
                        <label for="persen_bunga">Persentase Bunga</label>
                        <input id="persen_bunga" type="number" min="0" step="0.0001" name="persen_bunga" class="pumk-input" value="{{ old('persen_bunga', $pinjaman->persen_bunga) }}">
                    </div>
                    <div class="pumk-field">
                        <label for="pinjaman_bunga">Nilai Bunga (Rp)</label>
                        <input id="pinjaman_bunga" type="number" min="0" step="0.01" name="pinjaman_bunga" class="pumk-input" value="{{ old('pinjaman_bunga', $pinjaman->pinjaman_bunga) }}">
                    </div>
                    <div class="pumk-field">
                        <label for="nilai_angsuran_bulanan">Angsuran Bulanan (Rp)</label>
                        <input id="nilai_angsuran_bulanan" type="number" min="0" step="0.01" name="nilai_angsuran_bulanan" class="pumk-input" value="{{ old('nilai_angsuran_bulanan', $pinjaman->nilai_angsuran_bulanan) }}">
                    </div>
                </div>
            </section>

            <section class="pumk-card pumk-section">
                <h2 class="pumk-section-title">Dokumen SPJ dan Reschedule</h2>
                <div class="pumk-form-grid">
                    @foreach($documentTypes as $type => $label)
                        @php
                            $contractField = \App\Models\PumkPinjamanDokumen::CONTRACT_FIELDS[$type];
                            $contractNumber = old($contractField, $pinjaman->getAttribute($contractField));
                            $document = $contractDocuments->get($type);
                        @endphp
                        <div class="pumk-field" data-contract-document data-contract-input="{{ $contractField }}">
                            <label for="dokumen_{{ $type }}">{{ $label }}</label>
                            <input id="dokumen_{{ $type }}" type="file" name="dokumen_{{ $type }}" class="pumk-input" accept=".pdf,.jpg,.jpeg,.png" data-existing-document="{{ $document ? '1' : '0' }}" @disabled(blank($contractNumber))>
                            <small class="pumk-note" data-document-help>
                                @if(blank($contractNumber))
                                    Isi nomor kontrak terlebih dahulu untuk mengaktifkan upload.
                                @elseif($document)
                                    Tersimpan: {{ $document->nama_file_asli }}
                                @else
                                    Belum ada dokumen.
                                @endif
                            </small>
                            @error("dokumen_{$type}")<span class="pumk-field-error">{{ $message }}</span>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="pumk-card pumk-section">
                <h2 class="pumk-section-title">Informasi Jaminan / Agunan</h2>
                <div class="pumk-form-grid">
                    <div class="pumk-field full">
                        <label for="jenis_jaminan">Jenis Jaminan</label>
                        <textarea id="jenis_jaminan" name="jenis_jaminan" class="pumk-textarea" maxlength="3000">{{ old('jenis_jaminan', $pinjaman->jenis_jaminan) }}</textarea>
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_no_pol">Nomor Polisi</label>
                        <input id="jaminan_no_pol" name="jaminan_no_pol" class="pumk-input" value="{{ old('jaminan_no_pol', $pinjaman->jaminan_no_pol) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_no_bpkb">Nomor BPKB</label>
                        <input id="jaminan_no_bpkb" name="jaminan_no_bpkb" class="pumk-input" value="{{ old('jaminan_no_bpkb', $pinjaman->jaminan_no_bpkb) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_merk">Jenis / Merek Kendaraan</label>
                        <input id="jaminan_merk" name="jaminan_merk" class="pumk-input" value="{{ old('jaminan_merk', $pinjaman->jaminan_merk) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_type">Tipe Kendaraan</label>
                        <input id="jaminan_type" name="jaminan_type" class="pumk-input" value="{{ old('jaminan_type', $pinjaman->jaminan_type) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_tahun_kendaraan">Tahun Kendaraan</label>
                        <input id="jaminan_tahun_kendaraan" name="jaminan_tahun_kendaraan" class="pumk-input" value="{{ old('jaminan_tahun_kendaraan', $pinjaman->jaminan_tahun_kendaraan) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_no_sertifikat">Nomor Sertifikat</label>
                        <input id="jaminan_no_sertifikat" name="jaminan_no_sertifikat" class="pumk-input" value="{{ old('jaminan_no_sertifikat', $pinjaman->jaminan_no_sertifikat) }}" maxlength="255">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_luas">Luas</label>
                        <input id="jaminan_luas" name="jaminan_luas" class="pumk-input" value="{{ old('jaminan_luas', $pinjaman->jaminan_luas) }}" maxlength="255" placeholder="Contoh: 380 m²">
                    </div>
                    <div class="pumk-field">
                        <label for="jaminan_atas_nama">Atas Nama</label>
                        <input id="jaminan_atas_nama" name="jaminan_atas_nama" class="pumk-input" value="{{ old('jaminan_atas_nama', $pinjaman->jaminan_atas_nama) }}" maxlength="255">
                    </div>
                    <div class="pumk-field full">
                        <label for="jaminan_alamat">Alamat Jaminan</label>
                        <textarea id="jaminan_alamat" name="jaminan_alamat" class="pumk-textarea" maxlength="3000">{{ old('jaminan_alamat', $pinjaman->jaminan_alamat) }}</textarea>
                    </div>
                </div>
            </section>

            <div class="pumk-form-actions">
                <a href="{{ $editing ? route('pumk-admin.mitra.show', $mitra) : route('pumk-admin.mitra.index') }}" class="pumk-secondary-button">Batal</a>
                <button type="submit" class="pumk-primary-button">Simpan</button>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('pumk-mitra-form')?.addEventListener('submit', (event) => {
                const replacing = [...document.querySelectorAll('[data-existing-document="1"]')]
                    .some((file) => file.files.length > 0);
                if (replacing && !window.confirm('Dokumen lama pada jenis yang dipilih akan diganti. Lanjutkan?')) {
                    event.preventDefault();
                }
            });
            document.querySelectorAll('[data-contract-document]').forEach((wrapper) => {
                const contract = document.getElementById(wrapper.dataset.contractInput);
                const file = wrapper.querySelector('input[type="file"]');
                const help = wrapper.querySelector('[data-document-help]');
                if (!contract || !file) return;
                const sync = () => {
                    file.disabled = contract.value.trim() === '';
                    if (file.disabled && help) help.textContent = 'Isi nomor kontrak terlebih dahulu untuk mengaktifkan upload.';
                };
                contract.addEventListener('input', sync);
                sync();
            });
        });
    </script>
</x-layouts.pumk-admin>
