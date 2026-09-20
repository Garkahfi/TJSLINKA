<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['username' => 'super-test', 'role' => 'super_admin', 'is_admin' => true, 'is_active' => true, 'must_change_password' => false]);
    }

    private function attachPhaseOneDocuments(Program $program): void
    {
        foreach (Program::PHASE_ONE_DOCUMENTS as $type => $label) {
            $program->documents()->create([
                'document_type' => $type,
                'nama_dokumen' => $label,
                'file_path' => "programs/{$program->id}/documents/{$type}.pdf",
                'uploaded_at' => now(),
            ]);
        }
    }

    public function test_super_admin_login_is_role_restricted(): void
    {
        $super = User::factory()->create(['username' => 'TJSLINKAMIN', 'password' => Hash::make('TJSLHPVICTUS15'), 'role' => 'super_admin', 'is_active' => true, 'must_change_password' => true]);
        $this->post(route('superadmin.login.store'), ['username' => 'TJSLINKAMIN', 'password' => 'TJSLHPVICTUS15'])->assertRedirect(route('superadmin.profile'));
        $this->assertAuthenticatedAs($super, 'superadmin');
    }

    public function test_super_admin_login_ignores_stale_admin_intended_url(): void
    {
        User::factory()->create([
            'username' => 'TJSLINKAMIN',
            'password' => Hash::make('TJSLHPVICTUS15'),
            'role' => 'super_admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->withSession(['url.intended' => route('admin.home')])
            ->post(route('superadmin.login.store'), [
                'username' => 'TJSLINKAMIN',
                'password' => 'TJSLHPVICTUS15',
            ])->assertRedirect(route('superadmin.home'))
            ->assertSessionMissing('url.intended');
    }

    public function test_primary_super_admin_pages_render(): void
    {
        $super = $this->superAdmin();
        foreach ([['Sosial', 'sosial', '#2563eb'], ['Ekonomi', 'ekonomi', '#f59e0b'], ['Lingkungan', 'lingkungan', '#16a34a'], ['Hukum & Tata Kelola', 'hukum-tata-kelola', '#dc2626']] as [$name,$slug,$color]) {
            Pillar::create(['name' => $name, 'slug' => $slug, 'color_hex' => $color]);
        }
        foreach (['superadmin.home', 'superadmin.programs.index', 'superadmin.assistance.index', 'superadmin.notifications', 'superadmin.users.index', 'superadmin.users.create', 'superadmin.profile'] as $route) {
            $this->actingAs($super, 'superadmin')->get(route($route))->assertOk();
        }
    }

    public function test_super_admin_can_approve_pending_program_and_creator_is_notified(): void
    {
        $super = $this->superAdmin();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $program = Program::create(['slug' => 'pending-test', 'pillar_id' => $pillar->id, 'nama_program' => 'Pending Test', 'deskripsi_program' => 'x', 'sasaran_program' => 'x', 'lokasi_program' => 'x', 'mitra_program' => 'x', 'rencana_anggaran' => 1, 'tujuan_program' => 'x', 'status' => 'pending_fase1', 'created_by' => $admin->id]);
        $this->attachPhaseOneDocuments($program);
        $this->actingAs($super, 'superadmin')->post(route('superadmin.programs.approve', $program))->assertRedirect();
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'status' => 'approved_fase1', 'fase1_reviewed_by' => $super->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'related_type' => 'program', 'related_id' => $program->id]);
    }

    public function test_reject_requires_reason(): void
    {
        $super = $this->superAdmin();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $program = Program::create(['slug' => 'reject-test', 'pillar_id' => $pillar->id, 'nama_program' => 'Reject Test', 'deskripsi_program' => 'x', 'sasaran_program' => 'x', 'lokasi_program' => 'x', 'mitra_program' => 'x', 'rencana_anggaran' => 1, 'tujuan_program' => 'x', 'status' => 'pending_fase1', 'created_by' => $admin->id]);
        $this->actingAs($super, 'superadmin')->from(route('superadmin.programs.show', $program))->post(route('superadmin.programs.reject', $program), ['rejected_reason' => ''])->assertSessionHasErrors('rejected_reason');
        $this->assertSame('pending_fase1', $program->fresh()->status);
    }

    public function test_unread_count_endpoint_returns_json(): void
    {
        $super = $this->superAdmin();
        AdminNotification::create(['user_id' => $super->id, 'title' => 'Baru', 'message' => 'Pengajuan baru', 'related_type' => 'program', 'related_id' => 1]);
        $this->actingAs($super, 'superadmin')->getJson(route('superadmin.notifications.unread'))->assertOk()->assertJson(['count' => 1]);
    }

    public function test_super_admin_sidebar_has_only_four_labeled_primary_menus_and_defaults_expanded(): void
    {
        $response = $this->actingAs($this->superAdmin(), 'superadmin')->get(route('superadmin.home'));
        $response->assertOk()->assertSeeInOrder(['SuperAdmin', 'Home', 'Overview Program TJSL', 'Overview Bantuan TJSL', 'User']);
        $response->assertSee('var savedSidebarState=true?false:', false);
        $response->assertDontSee('superadmin.logout');
    }

    public function test_super_admin_session_does_not_authenticate_admin_area(): void
    {
        $this->actingAs($this->superAdmin(), 'superadmin')->get(route('admin.home'))->assertRedirect(route('admin.login'));
    }

    public function test_opening_super_admin_login_always_shows_login_form(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super, 'superadmin')->get(route('superadmin.login'))->assertOk()->assertSee('Super Admin');
        $this->assertGuest('superadmin');
    }

    public function test_super_admin_cannot_create_program_or_assistance(): void
    {
        $super = $this->superAdmin();

        $this->assertFalse(Route::has('superadmin.programs.create'));
        $this->assertFalse(Route::has('superadmin.programs.store'));
        $this->assertFalse(Route::has('superadmin.programs.update'));
        $this->assertFalse(Route::has('superadmin.program-documents.destroy'));
        $this->assertFalse(Route::has('superadmin.assistance.create'));
        $this->assertFalse(Route::has('superadmin.assistance.store'));
        $this->assertFalse(Route::has('superadmin.assistance.update'));
        $this->assertFalse(Route::has('superadmin.assistance-documents.destroy'));

        $this->actingAs($super, 'superadmin')->get('/superadmin/program-tjsl/create')
            ->assertRedirect(route('superadmin.programs.index'));
        $this->get('/superadmin/bantuan-csr/create')
            ->assertRedirect(route('superadmin.assistance.index'));
        $this->post('/superadmin/program-tjsl')->assertStatus(405);
        $this->post('/superadmin/bantuan-csr')->assertStatus(405);

        $sidebar = $this->get(route('superadmin.home'));
        $sidebar->assertSee('Status Bantuan TJSL')
            ->assertDontSee('Buat Program Baru')
            ->assertDontSee('Buat Bantuan TJSL');
    }

    public function test_super_admin_cannot_edit_or_delete_pending_program_content(): void
    {
        $super = $this->superAdmin();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        $program = Program::create(['slug' => 'edit-pending', 'pillar_id' => $pillar->id, 'nama_program' => 'Sebelum Edit', 'deskripsi_program' => 'x', 'sasaran_program' => 'x', 'lokasi_program' => 'x', 'mitra_program' => 'x', 'rencana_anggaran' => 1, 'tujuan_program' => 'x', 'status' => 'pending_fase1', 'created_by' => $admin->id]);
        $this->attachPhaseOneDocuments($program);

        $response = $this->actingAs($super, 'superadmin')
            ->get(route('superadmin.programs.show', [$program, 'edit' => 1]));

        $response->assertOk()
            ->assertSee('readonly', false)
            ->assertSee('Approve Program')
            ->assertSee('Reject Program')
            ->assertDontSee('class="review-edit"', false)
            ->assertDontSee('Simpan Program')
            ->assertDontSee('type="file"', false);

        $this->put('/superadmin/program-tjsl/'.$program->id, [
            'pillar_id' => $pillar->id,
            'nama_program' => 'Sesudah Edit',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 1000,
            'realisasi_anggaran' => 0,
            'tujuan_program' => 'Tujuan',
            'action' => 'submit',
        ])->assertStatus(405);

        $document = $program->documents()->firstOrFail();
        $this->delete('/superadmin/program-documents/'.$document->id)
            ->assertStatus(405);

        $this->post('/superadmin/program-tjsl/'.$program->id.'/bast')
            ->assertNotFound();

        $this->assertSame('Sebelum Edit', $program->fresh()->nama_program);
        $this->assertSame('pending_fase1', $program->fresh()->status);
        $this->assertDatabaseHas('program_documents', ['id' => $document->id]);
    }

    public function test_super_admin_dashboard_groups_programs_by_pillar_and_filtered_list_shows_statuses(): void
    {
        $super = $this->superAdmin();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $hukum = Pillar::create(['name' => 'Hukum & Tata Kelola', 'slug' => 'hukum-tata-kelola', 'color_hex' => '#dc2626']);
        $sosial = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);

        foreach (['pending_fase1', 'approved_fase1', 'pending_fase2'] as $status) {
            Program::create([
                'slug' => 'program-hukum-'.$status,
                'pillar_id' => $hukum->id,
                'nama_program' => 'Program Hukum '.ucfirst($status),
                'deskripsi_program' => 'x',
                'sasaran_program' => 'x',
                'lokasi_program' => 'x',
                'mitra_program' => 'x',
                'rencana_anggaran' => 1,
                'tujuan_program' => 'x',
                'status' => $status,
                'created_by' => $admin->id,
            ]);
        }

        Program::create([
            'slug' => 'program-sosial-completed',
            'pillar_id' => $sosial->id,
            'nama_program' => 'Program Sosial Completed',
            'deskripsi_program' => 'x',
            'sasaran_program' => 'x',
            'lokasi_program' => 'x',
            'mitra_program' => 'x',
            'rencana_anggaran' => 1,
            'tujuan_program' => 'x',
            'status' => 'completed',
            'created_by' => $admin->id,
        ]);

        Program::create([
            'slug' => 'draft-admin-tersembunyi',
            'pillar_id' => $hukum->id,
            'nama_program' => 'Draft Admin Tersembunyi',
            'deskripsi_program' => 'x',
            'sasaran_program' => 'x',
            'lokasi_program' => 'x',
            'mitra_program' => 'x',
            'rencana_anggaran' => 1,
            'tujuan_program' => 'x',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $dashboard = $this->actingAs($super, 'superadmin')->get(route('superadmin.home'));
        $dashboard->assertOk()
            ->assertSee('data-pillar="hukum-tata-kelola"', false)
            ->assertSee('Program TJSL Hukum &amp; Tata Kelola', false)
            ->assertSee('3 Program');
        $this->assertSame(1, substr_count($dashboard->getContent(), 'data-pillar="hukum-tata-kelola"'));

        $list = $this->get(route('superadmin.programs.index', ['pillar' => 'hukum-tata-kelola']));
        $list->assertOk()
            ->assertSee('Kategori: Hukum &amp; Tata Kelola', false)
            ->assertSee('data-program-status="pending_fase1"', false)
            ->assertSee('data-program-status="approved_fase1"', false)
            ->assertSee('data-program-status="pending_fase2"', false)
            ->assertSee('background:#dc2626', false)
            ->assertSee('Waiting')
            ->assertSee('Approved')
            ->assertSee('Waiting Fase 2')
            ->assertDontSee('Fase 1')
            ->assertDontSee('Program Sosial Completed')
            ->assertDontSee('Draft Admin Tersembunyi');
    }
}
