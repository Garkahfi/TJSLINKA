<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $user = User::factory()->create();
        $pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
        Program::create(['slug' => 'program-jaminan-sosial-pekerja-rentan', 'pillar_id' => $pillar->id, 'nama_program' => 'Program Jaminan Sosial Pekerja Rentan', 'deskripsi_program' => 'Deskripsi', 'sasaran_program' => 'Sasaran', 'lokasi_program' => 'Madiun', 'mitra_program' => 'Mitra', 'rencana_anggaran' => 100, 'realisasi_anggaran' => 50, 'tujuan_program' => 'Tujuan', 'status' => 'completed', 'created_by' => $user->id]);
        $this->get('/login')->assertOk()->assertSee('USERNAME');
        $this->actingAs($user, 'web');
        $this->get('/')->assertOk()->assertSee('Realisasi Anggaran Program TJSL Tahun');
        $this->get('/teras-tjsl')->assertOk()->assertSee('Paket Produk Teras TJSL');
        $this->get('/program-tjsl/overview')
            ->assertOk()
            ->assertSee('Overview Program TJSL INKA')
            ->assertSeeInOrder([
                'A : Proposal Pengajuan Program',
                'B : Kelengkapan Survei',
                'C : Kajian Kelayakan Kerja Sama',
                'D : Kajian Risiko',
                'E : Perjanjian Kerja Sama',
                'F : Berita Acara Serah Terima',
            ]);
        $this->get('/program-tjsl/rincian')->assertOk()->assertSee('Realisasi Program TJSL INKA');
        $this->get('/program-tjsl/rincian/program-jaminan-sosial-pekerja-rentan')->assertOk()->assertSee('Kelengkapan Dokumen Program');
    }
}
