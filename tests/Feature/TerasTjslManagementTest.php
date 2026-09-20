<?php

namespace Tests\Feature;

use App\Models\TerasPaket;
use App\Models\TerasProduk;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TerasTjslSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TerasTjslManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_legacy_products_and_packages_without_duplicates(): void
    {
        config()->set('bootstrap-users.admin_initial_password', 'TestAdminInitial2026');
        config()->set('bootstrap-users.superadmin_initial_password', 'TestSuperInitial2026');
        config()->set('pumk.admin.password', 'TestPumkInitial2026');
        $this->seed(DatabaseSeeder::class);
        $this->seed(TerasTjslSeeder::class);

        $this->assertSame(25, TerasProduk::query()->count());
        $this->assertSame(4, TerasPaket::query()->count());
        $this->assertDatabaseHas('teras_produk', [
            'nama_produk' => 'Chocotelo',
            'foto_path' => 'images/teras/products/chocotelo.png',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('teras_paket', [
            'nama_paket' => 'Paket D',
            'harga' => 1000000,
            'tipe_harga' => 'maksimal',
            'urutan' => 4,
            'is_active' => true,
        ]);

        $paketD = TerasPaket::query()->where('nama_paket', 'Paket D')->firstOrFail();
        $this->assertContains('Batik Kereta', $paketD->isi_paket);
        $this->assertSame('Maks Rp 1.000.000', $paketD->formatted_harga);
    }

    public function test_admin_can_publish_then_deactivate_a_product_without_deleting_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.teras.products.store'), [
                'nama_produk' => 'Keripik Testing Teras',
                'nama_umkm' => 'UMKM Testing',
                'foto' => UploadedFile::fake()->image('keripik.jpg', 800, 800),
                'deskripsi' => 'Produk uji integrasi Teras TJSL.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.teras.products.index'));

        $product = TerasProduk::query()->where('nama_produk', 'Keripik Testing Teras')->firstOrFail();
        Storage::disk('public')->assertExists($product->foto_path);

        $this->actingAs($admin, 'web')
            ->get('/teras-tjsl')
            ->assertOk()
            ->assertSee('Keripik Testing Teras')
            ->assertSee('UMKM Testing')
            ->assertSee(
                '/storage/'.$product->foto_path,
                escape: false,
            );

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.teras.products.toggle', $product))
            ->assertRedirect();

        $this->assertDatabaseHas('teras_produk', [
            'id' => $product->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin, 'web')
            ->get(route('teras'))
            ->assertOk()
            ->assertDontSee('Keripik Testing Teras');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.teras.products.index'))
            ->assertOk()
            ->assertSee('Keripik Testing Teras');
    }

    public function test_admin_can_create_a_maximum_price_package_and_edit_repeater_items(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.teras.packages.store'), [
                'nama_paket' => 'Paket Testing Maksimal',
                'foto' => UploadedFile::fake()->image('paket.jpg', 1000, 800),
                'harga' => 975000,
                'tipe_harga' => 'maksimal',
                'isi_paket' => [
                    'Batik Testing',
                    'Bluder Item Dihapus',
                    'Sambal Testing',
                    'Kopi Testing',
                ],
                'catatan_khusus' => 'Komposisi dapat disesuaikan.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.teras.packages.index'));

        $package = TerasPaket::query()->where('nama_paket', 'Paket Testing Maksimal')->firstOrFail();
        Storage::disk('public')->assertExists($package->foto_path);

        $this->actingAs($admin, 'web')
            ->get('/teras-tjsl')
            ->assertOk()
            ->assertSee('Paket Testing Maksimal')
            ->assertSee('Maks Rp 975.000')
            ->assertSee('Bluder Item Dihapus')
            ->assertSee(
                '/storage/'.$package->foto_path,
                escape: false,
            );

        $this->actingAs($admin, 'admin')
            ->put(route('admin.teras.packages.update', $package), [
                'nama_paket' => 'Paket Testing Maksimal',
                'harga' => 975000,
                'tipe_harga' => 'maksimal',
                'isi_paket' => [
                    'Batik Testing',
                    'Sambal Testing',
                    'Kopi Testing',
                ],
                'catatan_khusus' => 'Komposisi dapat disesuaikan.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.teras.packages.index'));

        $package->refresh();
        $this->assertSame([
            'Batik Testing',
            'Sambal Testing',
            'Kopi Testing',
        ], $package->isi_paket);

        $this->actingAs($admin, 'web')
            ->get(route('teras'))
            ->assertOk()
            ->assertSee('Batik Testing')
            ->assertDontSee('Bluder Item Dihapus');
    }

    public function test_admin_can_delete_products_and_packages_with_their_uploaded_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('teras/produk/hapus.jpg', 'product-image');
        Storage::disk('public')->put('teras/paket/hapus.jpg', 'package-image');
        $admin = $this->admin();
        $product = TerasProduk::query()->create([
            'nama_produk' => 'Produk Akan Dihapus',
            'nama_umkm' => 'UMKM Testing',
            'foto_path' => 'teras/produk/hapus.jpg',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $package = TerasPaket::query()->create([
            'nama_paket' => 'Paket Akan Dihapus',
            'foto_path' => 'teras/paket/hapus.jpg',
            'harga' => 100000,
            'tipe_harga' => 'tetap',
            'isi_paket' => ['Produk Testing'],
            'urutan' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.teras.products.destroy', $product))
            ->assertRedirect();
        $this->actingAs($admin, 'admin')
            ->delete(route('admin.teras.packages.destroy', $package))
            ->assertRedirect();

        $this->assertDatabaseMissing('teras_produk', ['id' => $product->id]);
        $this->assertDatabaseMissing('teras_paket', ['id' => $package->id]);
        Storage::disk('public')->assertMissing('teras/produk/hapus.jpg');
        Storage::disk('public')->assertMissing('teras/paket/hapus.jpg');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'username' => 'teras-admin',
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
