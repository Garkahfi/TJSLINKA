<?php

namespace Tests\Feature;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BantuanCsrPillarGroupingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    private User $superAdmin;

    /** @var array<string, Pillar> */
    private array $pillars = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin');
        $this->otherAdmin = $this->user('admin');
        $this->superAdmin = $this->user('super_admin');

        foreach ([
            ['Sosial', 'sosial', '#2563eb'],
            ['Ekonomi', 'ekonomi', '#f59e0b'],
            ['Lingkungan', 'lingkungan', '#16a34a'],
            ['Hukum & Tata Kelola', 'hukum-tata-kelola', '#dc2626'],
        ] as [$name, $slug, $color]) {
            $this->pillars[$slug] = Pillar::create([
                'name' => $name,
                'slug' => $slug,
                'color_hex' => $color,
            ]);
        }
    }

    public function test_admin_status_groups_own_active_bantuan_by_pillar(): void
    {
        $draft = $this->bantuan($this->admin, $this->pillars['sosial'], 'Draft Sosial', 'draft');
        $environment = $this->bantuan($this->admin, $this->pillars['lingkungan'], 'Bantuan Lingkungan', 'pending_fase1');
        $other = $this->bantuan($this->otherAdmin, $this->pillars['ekonomi'], 'Milik Admin Lain', 'pending_fase1');
        $archived = $this->bantuan($this->admin, $this->pillars['hukum-tata-kelola'], 'Sudah Diarsipkan', 'completed', true);
        $rejected = $this->bantuan($this->admin, $this->pillars['ekonomi'], 'Ditolak Final', 'rejected_fase1');

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.index'));

        $response->assertOk()
            ->assertSee('PILAR PEMBANGUNAN SOSIAL')
            ->assertSee('PILAR PEMBANGUNAN LINGKUNGAN')
            ->assertSee($draft->nama_program_bantuan)
            ->assertSee($environment->nama_program_bantuan)
            ->assertDontSee($other->nama_program_bantuan)
            ->assertDontSee($archived->nama_program_bantuan)
            ->assertDontSee($rejected->nama_program_bantuan)
            ->assertSee('background-color: #2563eb', false)
            ->assertSee('background-color: #16a34a', false);
    }

    public function test_superadmin_status_groups_all_submitted_bantuan_but_hides_drafts(): void
    {
        $social = $this->bantuan($this->admin, $this->pillars['sosial'], 'Pengajuan Sosial', 'pending_fase1');
        $economy = $this->bantuan($this->otherAdmin, $this->pillars['ekonomi'], 'Pengajuan Ekonomi', 'approved_fase1');
        $draft = $this->bantuan($this->admin, $this->pillars['lingkungan'], 'Draft Privat', 'draft');

        $response = $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.assistance.index'));

        $response->assertOk()
            ->assertSee('PILAR PEMBANGUNAN SOSIAL')
            ->assertSee('PILAR PEMBANGUNAN EKONOMI')
            ->assertSee($social->nama_program_bantuan)
            ->assertSee($economy->nama_program_bantuan)
            ->assertDontSee($draft->nama_program_bantuan)
            ->assertSee('Waiting')
            ->assertSee('Approved');
    }

    public function test_legacy_bantuan_without_pillar_is_kept_in_a_neutral_group(): void
    {
        $legacy = $this->bantuan($this->admin, null, 'Data Lama Tanpa Pilar', 'pending_fase1');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.index'))
            ->assertOk()
            ->assertSee('PILAR BELUM DITENTUKAN')
            ->assertSee($legacy->nama_program_bantuan);
    }

    public function test_archive_routes_and_sidebar_links_are_removed_from_both_panels(): void
    {
        $this->assertFalse(Route::has('admin.assistance.archive'));
        $this->assertFalse(Route::has('superadmin.assistance.archive'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.home'))
            ->assertOk()
            ->assertDontSee('Arsip Bantuan CSR');
        $this->get('/admin/bantuan-csr/arsip')->assertNotFound();

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.home'))
            ->assertOk()
            ->assertDontSee('Arsip Bantuan CSR');
        $this->get('/superadmin/bantuan-csr/arsip')->assertNotFound();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function bantuan(
        User $owner,
        ?Pillar $pillar,
        string $name,
        string $status,
        bool $archived = false,
    ): BantuanCsr {
        return BantuanCsr::create([
            'nama_program_bantuan' => $name,
            'deskripsi_bantuan' => 'Deskripsi '.$name,
            'pillar_id' => $pillar?->id,
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 500000,
            'status' => $status,
            'is_archived' => $archived,
            'created_by' => $owner->id,
            'submitted_at' => $status === 'draft' ? null : now(),
        ]);
    }
}
