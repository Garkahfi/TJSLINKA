<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pumk:snapshot-bulanan')
    ->monthlyOn(1, '01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120);

// Pada tanggal 1, catat posisi akhir bulan sebelumnya; jangan memberi key bulan baru.
// Rekonsiliasi periode lama tetap tindakan eksplisit setelah diagnosis;
// jadwal rutin hanya menangkap posisi akhir bulan yang baru.
Schedule::command('pumk:monitoring-capture')
    ->monthlyOn(1, '01:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120);
