<?php

namespace Tests\Benchmarks;

use App\Models\PumkPinjaman;
use App\Models\User;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkCollectibilityFormula;
use App\Services\Pumk\PumkCollectibilitySummaryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Run explicitly: php vendor/bin/phpunit tests/Benchmarks/TjslinkaPerformanceBenchmark.php
 * Synthetic data and the TestCase guard keep this entirely in testing SQLite :memory:.
 */
class TjslinkaPerformanceBenchmark extends TestCase
{
    use RefreshDatabase;

    private bool $recordQueries = false;

    /** @var list<array{sql:string,ms:float}> */
    private array $queries = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_synthetic_http_benchmark(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $users = [
            'pumk' => User::factory()->create(['role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]),
            'admin' => User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]),
        ];
        $this->seedSynthetic($users['admin']->id);
        $this->installCalculatorCounter();
        DB::listen(function (QueryExecuted $event): void {
            if ($this->recordQueries) {
                $this->queries[] = ['sql' => preg_replace('/\s+/', ' ', $event->sql), 'ms' => $event->time];
            }
        });

        $scenarios = [
            'card_active_400' => ['pumk', route('pumk-admin.mitra.show', ['mitra' => 1, 'pinjaman' => 1])],
            'partners_active_400' => ['pumk', route('pumk-admin.mitra.index')],
            'partners_category_400' => ['pumk', route('pumk-admin.mitra.index', ['kolektibilitas' => 'lancar'])],
            'inka_400' => ['admin', route('monitoring.inka', ['year' => 2026])],
            'bri_100_facilities_800_snapshots' => ['admin', route('monitoring.bri', ['pumk_year' => 2026])],
            'programs_100' => ['admin', route('admin.programs.index')],
            'assistance_100' => ['admin', route('admin.assistance.index')],
        ];
        foreach ($scenarios as $label => [$role, $url]) {
            $this->actingAs($users[$role], $role === 'pumk' ? 'pumk' : 'web');
            $runs = [];
            for ($iteration = 0; $iteration < 9; $iteration++) {
                $this->queries = [];
                $this->recordQueries = true;
                $calculator = app(PiutangCalculator::class);
                $beforeCalls = $calculator->calls;
                $start = hrtime(true);
                $response = $this->get($url);
                $wallMs = (hrtime(true) - $start) / 1_000_000;
                $this->recordQueries = false;
                $response->assertOk();
                if ($iteration < 2) {
                    continue;
                }
                $runs[] = [
                    'wall_ms' => round($wallMs, 3),
                    'query_count' => count($this->queries),
                    'sql_ms' => round(array_sum(array_column($this->queries, 'ms')), 3),
                    'bytes' => strlen($response->getContent()),
                    'calculator_calls' => $calculator->calls - $beforeCalls,
                    'fingerprints' => array_count_values(array_map(
                        fn (array $query): string => hash('sha256', $query['sql']), $this->queries,
                    )),
                ];
            }
            $times = array_column($runs, 'wall_ms');
            sort($times);
            $fingerprints = $runs[0]['fingerprints'];
            arsort($fingerprints);
            fwrite(STDERR, 'BENCH '.json_encode([
                'scenario' => $label,
                'runs' => count($runs),
                'warmups' => 2,
                'query_count' => $runs[0]['query_count'],
                'sql_ms_p50' => $this->median(array_column($runs, 'sql_ms')),
                'wall_ms_p50' => $times[3],
                'wall_ms_min' => $times[0],
                'wall_ms_max' => $times[6],
                'bytes' => $runs[0]['bytes'],
                'calculator_calls' => $runs[0]['calculator_calls'],
                'top_sql' => array_slice($fingerprints, 0, 3, true),
            ], JSON_UNESCAPED_SLASHES).PHP_EOL);
        }
        $this->assertTrue(true);
    }

    public function test_synthetic_contract_signature(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $pumk = User::factory()->create(['role' => 'pumk_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->seedSynthetic($admin->id);

        $this->actingAs($pumk, 'pumk');
        $card = $this->get(route('pumk-admin.mitra.show', ['mitra' => 1, 'pinjaman' => 1]))->viewData('kartu');
        $list = $this->get(route('pumk-admin.mitra.index', ['kolektibilitas' => 'lancar']));
        $this->actingAs($admin, 'web');
        $inka = $this->get(route('monitoring.inka', ['year' => 2026]))->viewData('pumkLiveDashboard');
        $bri = $this->get(route('monitoring.bri', ['pumk_year' => 2026]))->viewData('pumkBriDashboard');
        $programs = $this->get(route('admin.programs.index'))->viewData('programs');
        $assistance = $this->get(route('admin.assistance.index'))->viewData('bantuanPerPilar');

        $signature = [
            'card_calculation' => $card['calculation'],
            'card_years' => $card['tahun_tersedia'],
            'card_rows' => count($card['jadwal']),
            'list_partner_ids' => $list->viewData('mitraList')->pluck('id')->all(),
            'list_summary' => $list->viewData('collectibilitySummary'),
            'inka' => collect($inka)->except(['updated_at'])->all(),
            'bri' => collect($bri)->except(['updated_at'])->all(),
            'program_ids' => $programs->pluck('id')->all(),
            'assistance_ids' => $assistance->flatMap(fn ($pillar) => $pillar->bantuanCsr->pluck('id'))->all(),
        ];
        fwrite(STDERR, 'SIGNATURE '.hash('sha256', json_encode($signature, JSON_THROW_ON_ERROR)).PHP_EOL);
        fwrite(STDERR, 'SIGNATURE_COUNTS '.json_encode([
            'selected_partner_rows' => $list->viewData('mitraList')->count(),
            'selected_category_total' => $list->viewData('collectibilitySummary')['subtotal'],
            'inka_known_loans' => $inka['known_loans'],
            'bri_latest_month' => $bri['latest_month'],
        ]).PHP_EOL);
        $this->assertCount(100, $programs);
    }

    public function test_paired_category_scan_benchmark(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->seedSynthetic($admin->id);
        $this->installCalculatorCounter();
        $summary = app(PumkCollectibilitySummaryService::class);
        DB::listen(function (QueryExecuted $event): void {
            if ($this->recordQueries) {
                $this->queries[] = ['sql' => $event->sql, 'ms' => $event->time];
            }
        });

        $category = $summary->positionForLoan(PumkPinjaman::query()->with(['saldoAwal', 'angsuran'])->firstOrFail())['category'];
        $methods = [
            'two_pass' => fn (): array => [
                'ids' => $summary->matchingLoanIds([], $category),
                'summary' => $summary->summarize([], $category),
            ],
            'single_pass' => fn (): array => $summary->matchingLoanIdsWithSummary([], $category),
        ];
        $measurements = ['two_pass' => [], 'single_pass' => []];
        for ($iteration = 0; $iteration < 9; $iteration++) {
            $order = $iteration % 2 === 0 ? ['two_pass', 'single_pass'] : ['single_pass', 'two_pass'];
            foreach ($order as $method) {
                $this->queries = [];
                $this->recordQueries = true;
                $beforeCalls = app(PiutangCalculator::class)->calls;
                memory_reset_peak_usage();
                $start = hrtime(true);
                $result = $methods[$method]();
                $wall = (hrtime(true) - $start) / 1_000_000;
                $peakBytes = memory_get_peak_usage(true);
                $this->recordQueries = false;
                if ($method === 'two_pass') {
                    $expected = $result;
                } else {
                    $this->assertSame($expected, $result);
                }
                if ($iteration >= 2) {
                    $measurements[$method][] = [
                        'wall_ms' => round($wall, 3),
                        'queries' => count($this->queries),
                        'sql_ms' => round(array_sum(array_column($this->queries, 'ms')), 3),
                        'peak_bytes' => $peakBytes,
                        'calculator_calls' => app(PiutangCalculator::class)->calls - $beforeCalls,
                    ];
                }
            }
        }
        foreach ($measurements as $method => $runs) {
            fwrite(STDERR, 'PAIRED '.json_encode([
                'method' => $method,
                'category' => $category,
                'matched_loans' => count($expected['ids']),
                'runs' => count($runs),
                'query_count' => $runs[0]['queries'],
                'calculator_calls' => $runs[0]['calculator_calls'],
                'sql_ms_p50' => $this->median(array_column($runs, 'sql_ms')),
                'wall_ms_p50' => $this->median(array_column($runs, 'wall_ms')),
                'peak_bytes_p50' => $this->median(array_column($runs, 'peak_bytes')),
            ]).PHP_EOL);
        }
    }

    private function median(array $values): float
    {
        sort($values);

        return $values[3];
    }

    private function installCalculatorCounter(): void
    {
        $calculator = new class(app(PumkCollectibilityFormula::class)) extends PiutangCalculator
        {
            public int $calls = 0;

            public function hitungUntukPinjaman(PumkPinjaman $pinjaman, ?CarbonInterface $tanggalAcuan = null): array
            {
                $this->calls++;

                return parent::hitungUntukPinjaman($pinjaman, $tanggalAcuan);
            }
        };
        app()->instance(PiutangCalculator::class, $calculator);
    }

    private function seedSynthetic(int $adminId): void
    {
        $now = '2026-10-05 00:00:00';
        DB::table('pillars')->insert([
            ['id' => 1, 'name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Ekonomi', 'slug' => 'ekonomi', 'color_hex' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $mitra = $loans = $payments = $briMitra = $briFacilities = $briSnapshots = $programs = $assistance = [];
        for ($i = 1; $i <= 400; $i++) {
            if ($i <= 200) {
                $mitra[] = [
                    'id' => $i, 'nama_mitra' => sprintf('Synthetic Partner %04d', $i),
                    'source_key' => hash('sha256', 'bench-mitra-'.$i), 'is_active' => 1,
                    'sektor_sumber' => $i % 2 ? 'Perdagangan' : 'Pertanian',
                    'wilayah_sumber' => $i % 2 ? 'Kota Madiun' : 'Magetan',
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            $loans[] = [
                'id' => $i, 'mitra_id' => (int) ceil($i / 2),
                'source_key' => hash('sha256', 'bench-loan-'.$i),
                'tanggal_pencairan' => '2025-01-01', 'mulai_angsuran' => '2025-02-01',
                'selesai_angsuran' => '2027-01-01', 'pinjaman_pokok' => 10000000,
                'pinjaman_bunga' => 300000, 'total_pinjaman' => 10300000,
                'nilai_angsuran_bulanan' => 430000, 'status' => 'aktif',
                'is_active' => 1, 'kolektibilitas' => 'lancar',
                'created_at' => $now, 'updated_at' => $now,
            ];
            foreach ([1, 4, 7, 9] as $month) {
                $payments[] = [
                    'pinjaman_id' => $i, 'periode' => sprintf('2026-%02d-01', $month),
                    'pokok' => 100000, 'bunga' => 3000, 'denda' => 0, 'total' => 103000,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            if ($i <= 100) {
                $briMitra[] = [
                    'id' => $i, 'nama_mitra' => sprintf('Synthetic BRI %04d', $i),
                    'wilayah' => 'Madiun', 'sektor_usaha' => 'Perdagangan',
                    'pinjaman' => 10000000, 'source_key' => hash('sha256', 'bench-bri-'.$i),
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $programs[] = [
                    'slug' => 'bench-program-'.$i, 'pillar_id' => $i % 2 + 1,
                    'nama_program' => 'Synthetic Program '.$i, 'deskripsi_program' => 'Benchmark',
                    'sasaran_program' => 'Benchmark', 'lokasi_program' => 'Benchmark',
                    'mitra_program' => 'Benchmark', 'tujuan_program' => 'Benchmark',
                    'status' => 'completed', 'created_by' => $adminId,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $assistance[] = [
                    'nama_program_bantuan' => 'Synthetic Assistance '.$i,
                    'pillar_id' => $i % 2 + 1, 'deskripsi_bantuan' => 'Benchmark',
                    'status' => 'completed', 'created_by' => $adminId,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('pumk_mitra')->insert($mitra);
        foreach (array_chunk($loans, 100) as $chunk) {
            DB::table('pumk_pinjaman')->insert($chunk);
        }
        foreach (array_chunk($payments, 100) as $chunk) {
            DB::table('pumk_angsuran')->insert($chunk);
        }
        DB::table('pumk_bri_mitra')->insert($briMitra);
        for ($i = 1; $i <= 100; $i++) {
            $briFacilities[] = [
                'id' => $i, 'mitra_id' => $i,
                'reference_key' => sprintf('00000000-0000-0000-0000-%012d', $i),
                'pinjaman' => 10000000, 'tanggal_pencairan' => '2025-01-01',
                'created_at' => $now, 'updated_at' => $now,
            ];
            foreach (range(1, 8) as $month) {
                $briSnapshots[] = [
                    'mitra_id' => $i, 'fasilitas_id' => $i, 'bulan' => $month, 'tahun' => 2026,
                    'saldo_piutang' => 10000000 - $month * 100000,
                    'kolektibilitas_kode' => ['L', 'KL', 'D', 'M'][$i % 4],
                    'source_sheet' => 'Synthetic', 'source_row' => $i + 2,
                    'wilayah_sumber' => 'Madiun', 'sektor_usaha_sumber' => 'Perdagangan',
                    'pinjaman_sumber' => 10000000, 'profil_sumber_terverifikasi' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('pumk_bri_fasilitas')->insert($briFacilities);
        foreach (array_chunk($briSnapshots, 100) as $chunk) {
            DB::table('pumk_bri_snapshot_bulanan')->insert($chunk);
        }
        DB::table('programs')->insert($programs);
        DB::table('bantuan_csr')->insert($assistance);
    }
}
