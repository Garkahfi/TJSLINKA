<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminBantuanCsrController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\AdminProgramController;
use App\Http\Controllers\AdminTerasPaketController;
use App\Http\Controllers\AdminTerasProdukController;
use App\Http\Controllers\MonitoringUploadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicAuthController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\PumkAdminAuthController;
use App\Http\Controllers\PumkAdminDashboardController;
use App\Http\Controllers\PumkBriPlanningController;
use App\Http\Controllers\PumkMitraController;
use App\Http\Controllers\SuperAdminAuthController;
use App\Http\Controllers\SuperAdminBantuanCsrController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdminNotificationController;
use App\Http\Controllers\SuperAdminProgramController;
use App\Http\Controllers\SuperAdminPumkMonitoringController;
use App\Http\Controllers\SuperAdminUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/login', [PublicAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [PublicAuthController::class, 'login'])->name('login.store');

Route::middleware('auth:web')->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::post('/logout', [PublicAuthController::class, 'logout'])->name('public.logout');
    Route::get('/profil', [PublicProfileController::class, 'edit'])->name('public.profile');
    Route::put('/profil', [PublicProfileController::class, 'update'])->name('public.profile.update');
    Route::put('/profil/password', [PublicProfileController::class, 'updatePassword'])->name('public.password.update');
    Route::get('/teras-tjsl', [PageController::class, 'teras'])->name('teras');
    Route::get('/program-tjsl/overview', [PageController::class, 'overview'])->name('program.overview');
    Route::get('/program-tjsl/overview/monitoring', [PageController::class, 'overviewMonitoring'])
        ->name('program.overview.monitoring');
    Route::get('/program-tjsl/rincian', [PageController::class, 'rincian'])->name('program.rincian');
    Route::get('/program-tjsl/rincian/bantuan-csr/{bantuanCsr}', [PageController::class, 'csrDetail'])
        ->name('program.csr.detail');
    Route::get('/program-tjsl/rincian/bantuan-csr/{bantuanCsr}/dokumen/{document}/lihat', [PageController::class, 'viewCsrDocument'])
        ->name('program.csr.documents.view');
    Route::get('/program-tjsl/rincian/bantuan-csr/{bantuanCsr}/dokumen/{document}/unduh', [PageController::class, 'downloadCsrDocument'])
        ->name('program.csr.documents.download');
    Route::get('/program-tjsl/rincian/{program:slug}/dokumen/{document}/lihat', [PageController::class, 'viewProgramDocument'])
        ->name('program.documents.view');
    Route::get('/program-tjsl/rincian/{program:slug}/dokumen/{document}/unduh', [PageController::class, 'downloadProgramDocument'])
        ->name('program.documents.download');
    Route::get('/program-tjsl/rincian/{slug}', [PageController::class, 'detail'])->name('program.detail');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');

    Route::middleware(['auth:admin', 'role:admin', 'admin.password.changed'])->group(function () {
        Route::get('/ganti-password', [AdminAuthController::class, 'showChangePassword'])->name('password.edit');
        Route::put('/ganti-password', [AdminAuthController::class, 'changePassword'])->name('password.update');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        Route::redirect('/', '/admin/home')->name('dashboard');
        Route::get('/home', AdminDashboardController::class)->name('home');
        Route::get('/program-documents/{document}', [AdminProgramController::class, 'downloadDocument'])->name('program-documents.download');
        Route::delete('/program-documents/{document}', [AdminProgramController::class, 'destroyDocument'])->name('program-documents.destroy');
        Route::post('/program-tjsl/{program}/cancel', [AdminProgramController::class, 'cancel'])->name('programs.cancel');
        Route::get('/program-tjsl/{program}/bast-dokumentasi', [AdminProgramController::class, 'phaseTwo'])->name('programs.phase2');
        Route::post('/program-tjsl/{program}/bast', [AdminProgramController::class, 'uploadBast'])->name('programs.bast.store');
        Route::get('/program-tjsl/create/{jenisKerjasama}', [AdminProgramController::class, 'createCooperationForm'])
            ->where('jenisKerjasama', 'pks|non-pks')
            ->name('programs.cooperation.form');
        Route::resource('program-tjsl', AdminProgramController::class)->parameters(['program-tjsl' => 'program'])->names('programs')->except(['destroy', 'edit']);
        Route::get('/bantuan-documents/{document}', [AdminBantuanCsrController::class, 'downloadDocument'])->name('assistance-documents.download');
        Route::delete('/bantuan-documents/{document}', [AdminBantuanCsrController::class, 'destroyDocument'])->name('assistance-documents.destroy');
        Route::post('/bantuan-csr/{bantuanCsr}/cancel', [AdminBantuanCsrController::class, 'cancel'])->name('assistance.cancel');
        Route::get('/bantuan-csr/{bantuanCsr}/bast', [AdminBantuanCsrController::class, 'phaseTwo'])->name('assistance.phase2');
        Route::post('/bantuan-csr/{bantuanCsr}/bast', [AdminBantuanCsrController::class, 'uploadBast'])->name('assistance.bast.store');
        Route::resource('bantuan-csr', AdminBantuanCsrController::class)->parameters(['bantuan-csr' => 'bantuanCsr'])->names('assistance')->except(['destroy', 'edit']);
        Route::patch('/teras-produk/{terasProduk}/toggle', [AdminTerasProdukController::class, 'toggle'])->name('teras.products.toggle');
        Route::resource('teras-produk', AdminTerasProdukController::class)->parameters(['teras-produk' => 'terasProduk'])->names('teras.products')->except(['show']);
        Route::patch('/teras-paket/{terasPaket}/toggle', [AdminTerasPaketController::class, 'toggle'])->name('teras.packages.toggle');
        Route::resource('teras-paket', AdminTerasPaketController::class)->parameters(['teras-paket' => 'terasPaket'])->names('teras.packages')->except(['show']);
        Route::get('/notifikasi', AdminNotificationController::class)->name('notifications');
        Route::get('/notifikasi/unread-count', fn (Request $request) => response()->json(['count' => $request->user()->adminNotifications()->where('is_read', false)->count()]))->name('notifications.unread');
        Route::get('/profil', [AdminProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [AdminProfileController::class, 'update'])->name('profile.update');
    });
});

Route::middleware(['auth:pumk', 'role:pumk_admin', 'admin.password.changed'])
    ->prefix('admin/monitoring')
    ->name('pumk-admin.monitoring.')
    ->group(function (): void {
        Route::get('/upload', [MonitoringUploadController::class, 'index'])->name('upload');
        Route::post('/upload', [MonitoringUploadController::class, 'store'])->name('upload.store');
    });

Route::prefix('admin-pumk')->name('pumk-admin.')->group(function () {
    Route::get('/login', [PumkAdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PumkAdminAuthController::class, 'login'])->name('login.store');

    Route::middleware(['auth:pumk', 'role:pumk_admin', 'admin.password.changed'])->group(function () {
        Route::get('/ganti-password', [PumkAdminAuthController::class, 'showChangePassword'])->name('password.edit');
        Route::put('/ganti-password', [PumkAdminAuthController::class, 'changePassword'])->name('password.update');
        Route::post('/logout', [PumkAdminAuthController::class, 'logout'])->name('logout');
        Route::redirect('/', '/admin-pumk/home')->name('dashboard');
        Route::get('/home', PumkAdminDashboardController::class)->name('home');
        Route::get('/profil', [AdminProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [AdminProfileController::class, 'update'])->name('profile.update');
        Route::get('/pumk-bri/rka-realisasi', [PumkBriPlanningController::class, 'index'])->name('bri-planning.index');
        Route::post('/pumk-bri/tahun', [PumkBriPlanningController::class, 'storeYear'])->name('bri-planning.year.store');
        Route::post('/pumk-bri/rka', [PumkBriPlanningController::class, 'storeRka'])->name('bri-planning.rka.store');
        Route::post('/pumk-bri/realisasi', [PumkBriPlanningController::class, 'storeMonthly'])->name('bri-planning.monthly.store');
        Route::delete('/pumk-bri/realisasi/{penyaluran}', [PumkBriPlanningController::class, 'destroyMonthly'])->name('bri-planning.monthly.destroy');
        Route::get('/mitra', [PumkMitraController::class, 'index'])->name('mitra.index');
        Route::get('/mitra/create', [PumkMitraController::class, 'create'])->name('mitra.create');
        Route::post('/mitra', [PumkMitraController::class, 'store'])->name('mitra.store');
        Route::get('/mitra/{mitra}', [PumkMitraController::class, 'show'])->name('mitra.show');
        Route::get('/mitra/{mitra}/edit', [PumkMitraController::class, 'edit'])->name('mitra.edit');
        Route::put('/mitra/{mitra}', [PumkMitraController::class, 'update'])->name('mitra.update');
        Route::post('/mitra/{mitra}/pinjaman/{pinjaman}/angsuran', [PumkMitraController::class, 'storeAngsuran'])
            ->name('mitra.angsuran.store');
        Route::patch('/mitra/{mitra}/pinjaman/{pinjaman}/angsuran/{angsuran}', [PumkMitraController::class, 'updateAngsuran'])
            ->name('mitra.angsuran.update');
        Route::get('/mitra/{mitra}/pinjaman/{pinjaman}/angsuran/{angsuran}/bukti/lihat', [PumkMitraController::class, 'viewPaymentProof'])
            ->name('mitra.angsuran.bukti.view');
        Route::get('/mitra/{mitra}/pinjaman/{pinjaman}/angsuran/{angsuran}/bukti/unduh', [PumkMitraController::class, 'downloadPaymentProof'])
            ->name('mitra.angsuran.bukti.download');
        Route::delete('/mitra/{mitra}/pinjaman/{pinjaman}/angsuran/{angsuran}/bukti', [PumkMitraController::class, 'destroyPaymentProof'])
            ->name('mitra.angsuran.bukti.destroy');
        Route::get('/mitra/{mitra}/pinjaman/{pinjaman}/dokumen/{document}/lihat', [PumkMitraController::class, 'viewDocument'])
            ->name('mitra.dokumen.view');
        Route::get('/mitra/{mitra}/pinjaman/{pinjaman}/dokumen/{document}/unduh', [PumkMitraController::class, 'downloadDocument'])
            ->name('mitra.dokumen.download');
        Route::delete('/mitra/{mitra}/pinjaman/{pinjaman}/dokumen/{document}', [PumkMitraController::class, 'destroyDocument'])
            ->name('mitra.dokumen.destroy');
        Route::post('/mitra/{mitra}/pinjaman/{pinjaman}/lunas', [PumkMitraController::class, 'markPaid'])
            ->name('mitra.pinjaman.lunas');
        Route::get('/mitra/{mitra}/pinjaman/{pinjaman}/kartu-piutang.xls', [PumkMitraController::class, 'exportExcel'])
            ->name('mitra.kartu.excel');
        Route::get('/mitra/{mitra}/pinjaman/{pinjaman}/kartu-piutang.pdf', [PumkMitraController::class, 'exportPdf'])
            ->name('mitra.kartu.pdf');
    });
});

Route::middleware(['auth:superadmin', 'role:super_admin', 'admin.password.changed'])->group(function () {
    Route::get('/superadmin/program-tjsl/create', fn () => redirect()->route('superadmin.programs.index'));
    Route::get('/superadmin/bantuan-csr/create', fn () => redirect()->route('superadmin.assistance.index'));
});

Route::prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/login', [SuperAdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [SuperAdminAuthController::class, 'login'])->name('login.store');
    Route::middleware(['auth:superadmin', 'role:super_admin', 'admin.password.changed'])->group(function () {
        Route::get('/ganti-password', [SuperAdminAuthController::class, 'showChangePassword'])->name('password.edit');
        Route::put('/ganti-password', [SuperAdminAuthController::class, 'changePassword'])->name('password.update');
        Route::post('/logout', [SuperAdminAuthController::class, 'logout'])->name('logout');
        Route::redirect('/', '/superadmin/home')->name('dashboard');
        Route::get('/home', SuperAdminDashboardController::class)->name('home');
        Route::get('/pumk', [SuperAdminPumkMonitoringController::class, 'index'])->name('pumk.index');
        Route::get('/pumk/mitra', [SuperAdminPumkMonitoringController::class, 'mitra'])->name('pumk.mitra');
        Route::get('/pumk/mitra/{mitra}', [SuperAdminPumkMonitoringController::class, 'kartu'])->name('pumk.kartu');
        Route::get('/pumk/mitra/{mitra}/pinjaman/{pinjaman}/kartu-piutang.xls', [PumkMitraController::class, 'exportExcel'])->name('pumk.kartu.excel');
        Route::get('/pumk/mitra/{mitra}/pinjaman/{pinjaman}/kartu-piutang.pdf', [PumkMitraController::class, 'exportPdf'])->name('pumk.kartu.pdf');
        Route::get('/pumk/mitra/{mitra}/pinjaman/{pinjaman}/dokumen/{document}/lihat', [PumkMitraController::class, 'viewDocument'])->name('pumk.dokumen.view');
        Route::get('/pumk/mitra/{mitra}/pinjaman/{pinjaman}/dokumen/{document}/unduh', [PumkMitraController::class, 'downloadDocument'])->name('pumk.dokumen.download');
        Route::get('/pumk/mitra/{mitra}/pinjaman/{pinjaman}/angsuran/{angsuran}/bukti/lihat', [PumkMitraController::class, 'viewPaymentProof'])->name('pumk.angsuran.bukti.view');
        Route::get('/pumk/mitra/{mitra}/pinjaman/{pinjaman}/angsuran/{angsuran}/bukti/unduh', [PumkMitraController::class, 'downloadPaymentProof'])->name('pumk.angsuran.bukti.download');

        Route::post('/program-tjsl/{program}/approve', [SuperAdminProgramController::class, 'approve'])->name('programs.approve');
        Route::post('/program-tjsl/{program}/reject', [SuperAdminProgramController::class, 'reject'])->name('programs.reject');
        Route::get('/program-documents/{document}', [SuperAdminProgramController::class, 'downloadDocument'])->name('program-documents.download');
        Route::resource('program-tjsl', SuperAdminProgramController::class)->parameters(['program-tjsl' => 'program'])->names('programs')->only(['index', 'show']);

        Route::post('/bantuan-csr/{bantuanCsr}/approve', [SuperAdminBantuanCsrController::class, 'approve'])->name('assistance.approve');
        Route::post('/bantuan-csr/{bantuanCsr}/reject', [SuperAdminBantuanCsrController::class, 'reject'])->name('assistance.reject');
        Route::get('/bantuan-documents/{document}', [SuperAdminBantuanCsrController::class, 'downloadDocument'])->name('assistance-documents.download');
        Route::resource('bantuan-csr', SuperAdminBantuanCsrController::class)->parameters(['bantuan-csr' => 'bantuanCsr'])->names('assistance')->only(['index', 'show']);

        Route::get('/notifikasi', [SuperAdminNotificationController::class, 'index'])->name('notifications');
        Route::get('/notifikasi/unread-count', [SuperAdminNotificationController::class, 'unread'])->name('notifications.unread');
        Route::resource('user', SuperAdminUserController::class)->parameters(['user' => 'user'])->names('users')->except(['show']);
        Route::get('/profil', [AdminProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [AdminProfileController::class, 'update'])->name('profile.update');
    });
});
