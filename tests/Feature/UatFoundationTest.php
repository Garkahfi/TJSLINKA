<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UatFoundationTest extends TestCase
{
    public function test_all_critical_uat_routes_keep_their_role_boundaries(): void
    {
        $this->assertRoutesUseMiddleware([
            'home',
            'teras',
            'program.overview',
            'program.overview.monitoring',
            'program.rincian',
            'program.detail',
            'program.csr.detail',
            'program.documents.view',
            'program.documents.download',
            'program.csr.documents.view',
            'program.csr.documents.download',
        ], ['auth:web']);

        $this->assertRoutesUseMiddleware([
            'admin.home',
            'admin.profile',
            'admin.programs.index',
            'admin.programs.store',
            'admin.programs.bast.store',
            'admin.assistance.index',
            'admin.assistance.store',
            'admin.assistance.bast.store',
        ], ['auth:admin', 'role:admin', 'admin.password.changed']);

        $this->assertRoutesUseMiddleware([
            'superadmin.home',
            'superadmin.profile',
            'superadmin.programs.index',
            'superadmin.programs.approve',
            'superadmin.programs.reject',
            'superadmin.assistance.index',
            'superadmin.assistance.approve',
            'superadmin.assistance.reject',
            'superadmin.pumk.index',
        ], ['auth:superadmin', 'role:super_admin', 'admin.password.changed']);

        $this->assertRoutesUseMiddleware([
            'pumk-admin.home',
            'pumk-admin.profile',
            'pumk-admin.mitra.index',
            'pumk-admin.mitra.store',
            'pumk-admin.mitra.update',
            'pumk-admin.mitra.angsuran.store',
            'pumk-admin.mitra.dokumen.view',
            'pumk-admin.mitra.dokumen.download',
            'pumk-admin.mitra.kartu.excel',
            'pumk-admin.mitra.kartu.pdf',
        ], ['auth:pumk', 'role:pumk_admin', 'admin.password.changed']);
    }

    public function test_pumk_contract_documents_remain_private_and_allow_150_mb(): void
    {
        $localRoot = $this->normalizePath((string) config('filesystems.disks.local.root'));
        $privateRoot = $this->normalizePath(storage_path('app/private'));
        $publicRoot = $this->normalizePath(public_path());

        $this->assertSame('local', config('filesystems.disks.local.driver'));
        $this->assertSame($privateRoot, $localRoot);
        $this->assertFalse(
            str_starts_with(mb_strtolower($localRoot), mb_strtolower($publicRoot)),
            'Dokumen privat tidak boleh disimpan di bawah direktori public.',
        );
        $this->assertSame(153600, config('pumk.contract_document_max_kb'));
    }

    public function test_uat_database_is_isolated_in_memory(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_monthly_pumk_snapshot_schedule_is_registered_in_jakarta_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'pumk:snapshot-bulanan'));

        $this->assertNotNull($event, 'Jadwal pumk:snapshot-bulanan tidak terdaftar.');
        $this->assertSame('0 1 1 * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
    }

    public function test_superadmin_pumk_monitoring_exposes_no_mutation_routes(): void
    {
        foreach ([
            'superadmin.pumk.index',
            'superadmin.pumk.mitra',
            'superadmin.pumk.kartu',
            'superadmin.pumk.kartu.excel',
            'superadmin.pumk.kartu.pdf',
            'superadmin.pumk.dokumen.view',
            'superadmin.pumk.dokumen.download',
            'superadmin.pumk.angsuran.bukti.view',
            'superadmin.pumk.angsuran.bukti.download',
        ] as $routeName) {
            $route = $this->routeByName($routeName);
            $this->assertSame(['GET', 'HEAD'], $route->methods(), "Route {$routeName} harus baca-saja.");
        }

        foreach ([
            'superadmin.pumk.mitra.store',
            'superadmin.pumk.mitra.update',
            'superadmin.pumk.mitra.destroy',
            'superadmin.pumk.angsuran.store',
            'superadmin.pumk.angsuran.update',
            'superadmin.pumk.angsuran.destroy',
            'superadmin.pumk.pinjaman.lunas',
        ] as $forbiddenRouteName) {
            $this->assertFalse(Route::has($forbiddenRouteName));
        }
    }

    /**
     * @param  array<int, string>  $routeNames
     * @param  array<int, string>  $expectedMiddleware
     */
    private function assertRoutesUseMiddleware(array $routeNames, array $expectedMiddleware): void
    {
        foreach ($routeNames as $routeName) {
            $middleware = $this->routeByName($routeName)->gatherMiddleware();

            foreach ($expectedMiddleware as $expected) {
                $this->assertContains(
                    $expected,
                    $middleware,
                    "Route {$routeName} kehilangan middleware {$expected}.",
                );
            }
        }
    }

    private function routeByName(string $routeName): IlluminateRoute
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route {$routeName} tidak ditemukan.");

        return $route;
    }

    private function normalizePath(string $path): string
    {
        return mb_strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
    }
}
