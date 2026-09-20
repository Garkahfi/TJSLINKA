<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PumkPinjaman extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_NONAKTIF = 'nonaktif';

    protected $table = 'pumk_pinjaman';

    protected $fillable = [
        'mitra_id',
        'no_urut_sumber',
        'spj_awal',
        'reschedule_ke1',
        'reschedule_ke2',
        'reschedule_ke3',
        'reschedule_ke4',
        'jenis_jaminan',
        'jaminan_no_pol',
        'jaminan_no_bpkb',
        'jaminan_merk',
        'jaminan_type',
        'jaminan_tahun_kendaraan',
        'jaminan_no_sertifikat',
        'jaminan_luas',
        'jaminan_atas_nama',
        'jaminan_alamat',
        'berkas_spj_path',
        'berkas_jaminan_path',
        'tanggal_pencairan',
        'tahun_pencairan',
        'mulai_angsuran',
        'selesai_angsuran',
        'pinjaman_pokok',
        'persen_bunga',
        'pinjaman_bunga',
        'nilai_angsuran_bulanan',
        'bulan_tunggakan',
        'nilai_tunggakan',
        'kolektibilitas',
        'sisa_pokok',
        'sisa_bunga',
        'total_sisa',
        'source_key',
        'is_active',
        'status',
        'lunas_at',
        'lunas_by',
        'lunas_note',
        'source_updated_at',
        'baseline_sumber',
        'calculated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pencairan' => 'date',
            'tahun_pencairan' => 'integer',
            'mulai_angsuran' => 'date',
            'selesai_angsuran' => 'date',
            'pinjaman_pokok' => 'decimal:2',
            'persen_bunga' => 'decimal:4',
            'pinjaman_bunga' => 'decimal:2',
            'total_pinjaman' => 'decimal:2',
            'nilai_angsuran_bulanan' => 'decimal:2',
            'nilai_tunggakan' => 'decimal:2',
            'sisa_pokok' => 'decimal:2',
            'sisa_bunga' => 'decimal:2',
            'total_sisa' => 'decimal:2',
            'is_active' => 'boolean',
            'lunas_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'baseline_sumber' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PumkPinjaman $pinjaman): void {
            // Tahun dari kolom AB tetap boleh menjadi fallback ketika tanggal
            // pencairan sumber kosong/tidak valid. Jangan hapus fallback itu
            // dengan menimpa tahun menjadi null pada setiap save.
            if ($pinjaman->tanggal_pencairan !== null) {
                $pinjaman->tahun_pencairan = $pinjaman->tanggal_pencairan->year;
            }

            if ($pinjaman->pinjaman_pokok !== null || $pinjaman->pinjaman_bunga !== null) {
                $pinjaman->total_pinjaman = (float) ($pinjaman->pinjaman_pokok ?? 0)
                    + (float) ($pinjaman->pinjaman_bunga ?? 0);
            } else {
                $pinjaman->total_pinjaman = null;
            }
        });
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(PumkMitra::class, 'mitra_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pelunas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lunas_by');
    }

    public function saldoAwal(): HasOne
    {
        return $this->hasOne(PumkSaldoAwal::class, 'pinjaman_id');
    }

    public function angsuran(): HasMany
    {
        return $this->hasMany(PumkAngsuran::class, 'pinjaman_id');
    }

    public function dokumenKontrak(): HasMany
    {
        return $this->hasMany(PumkPinjamanDokumen::class, 'pinjaman_id');
    }

}
