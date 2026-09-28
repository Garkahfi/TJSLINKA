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
Schedule::command('pumk:monitoring-capture --reconcile')
    ->monthlyOn(1, '01:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(120);
