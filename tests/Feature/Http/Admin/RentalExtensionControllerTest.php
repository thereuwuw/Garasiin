<?php

namespace Tests\Feature\Http\Admin;

use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class RentalExtensionControllerTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    private Rental $rental;
    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10 09:00:00');
        $this->disk = Storage::fake('public');

        $tenant = $this->makeTenant(['name' => 'Bambang']);
        $garage = $this->makeGarage(GarageType::Mobil, 1, 30000);
        $this->rental = $this->makeRental($tenant, $this->makeVehicle($tenant), $garage, '2026-11-08', '2026-11-12');

        $this->actingAs($this->makeAdmin());
    }

    public function test_daftar_garasi_yang_sedang_disewa_hari_ini(): void
    {
        $this->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee('C001')
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$this->rental->id])
            ->assertViewHas('currentEndDates', fn ($dates) => $dates[$this->rental->id]->toDateString() === '2026-11-12');
    }

    public function test_form_perpanjangan(): void
    {
        $this->get(route('admin.extensions.edit', $this->rental))
            ->assertOk()
            ->assertViewHas('minNewEnd', '2026-11-13')
            ->assertViewHas('currentEnd', fn ($date) => $date->toDateString() === '2026-11-12');
    }

    public function test_form_perpanjangan_dari_baris_extend_menampilkan_sewa_awal(): void
    {
        $extension = $this->makeExtension($this->rental, '2026-11-13', '2026-11-14');

        $this->get(route('admin.extensions.edit', $extension))
            ->assertOk()
            ->assertViewHas('rental', fn ($rental) => $rental->id === $this->rental->id)
            ->assertViewHas('minNewEnd', '2026-11-15');
    }

    public function test_form_perpanjangan_sewa_tanpa_parent_kembali_ke_daftar(): void
    {
        $orphan = $this->makeRental($this->rental->user, $this->rental->vehicle, $this->rental->garage, '2026-11-20', '2026-11-21', [
            'status' => RentalStatus::Extend,
        ]);

        $this->get(route('admin.extensions.edit', $orphan))
            ->assertRedirect(route('admin.extensions.index'))
            ->assertSessionHasErrors('extension');
    }

    public function test_preview_sisa_biaya(): void
    {
        $this->postJson(route('admin.extensions.preview', $this->rental), ['new_end_date' => '2026-11-15'])
            ->assertOk()
            ->assertJson([
                'current_end_date' => '2026-11-12',
                'start_date' => '2026-11-13',
                'end_date' => '2026-11-15',
                'extra_days' => 3,
                'rent_cost' => 90000,
            ]);
    }

    public function test_preview_tanggal_tidak_valid_422(): void
    {
        $this->postJson(route('admin.extensions.preview', $this->rental), ['new_end_date' => '2026-11-11'])
            ->assertStatus(422)
            ->assertJson(['message' => 'Tanggal selesai baru harus setelah 12-11-2026.']);

        $this->postJson(route('admin.extensions.preview', $this->rental), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('new_end_date');
    }

    public function test_admin_memperpanjang_sewa_dengan_bukti_bayar(): void
    {
        $this->post(route('admin.extensions.store', $this->rental), [
            'new_end_date' => '2026-11-15',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])
            ->assertRedirect(route('admin.extensions.index'))
            ->assertSessionHas('success', 'Sewa diperpanjang 3 hari sampai 15-11-2026.');

        $extension = Rental::where('status', RentalStatus::Extend)->first();
        $this->assertSame($this->rental->id, $extension->parent_id);
        $this->disk->assertExists($extension->payment_proof);
    }

    public function test_gagal_memperpanjang_file_bukti_dihapus_lagi(): void
    {
        $this->post(route('admin.extensions.store', $this->rental), [
            'new_end_date' => '2026-11-12',
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasErrors('extension');

        $this->assertSame([], $this->disk->allFiles());
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_bukti_bayar_wajib_berupa_gambar(): void
    {
        $this->post(route('admin.extensions.store', $this->rental), ['new_end_date' => '2026-11-15'])
            ->assertSessionHasErrors('payment_proof');

        $this->post(route('admin.extensions.store', $this->rental), [
            'new_end_date' => '2026-11-15',
            'payment_proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('payment_proof');
    }
}
