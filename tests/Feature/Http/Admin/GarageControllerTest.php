<?php

namespace Tests\Feature\Http\Admin;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class GarageControllerTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-10 09:00:00');
        $this->actingAs($this->makeAdmin());
    }

    public function test_daftar_garasi_bisa_dibuka_dan_difilter_per_tipe(): void
    {
        $this->makeGarage(GarageType::Sepeda, 1);
        $this->makeGarage(GarageType::Mobil, 1, 30000);

        $this->get(route('admin.garages.index'))
            ->assertOk()
            ->assertSee('S001')
            ->assertSee('C001');

        $this->get(route('admin.garages.index', ['type' => 'mobil']))
            ->assertOk()
            ->assertViewHas('selectedType', GarageType::Mobil)
            ->assertViewHas('garages', fn ($garages) => $garages->pluck('code')->all() === ['C001']);
    }

    public function test_membuka_daftar_garasi_menyinkronkan_status(): void
    {
        $garage = $this->makeGarage();
        $garage->forceFill(['status' => GarageStatus::Used])->save(); // tidak ada sewa hari ini

        $this->get(route('admin.garages.index'))->assertOk();

        $this->assertSame(GarageStatus::Available, $garage->fresh()->status);
    }

    public function test_form_tambah_garasi_menampilkan_sisa_kuota(): void
    {
        $this->makeGarage(GarageType::Sepeda, 1);
        $this->makeGarage(GarageType::Sepeda, 2);

        $this->get(route('admin.garages.create'))
            ->assertOk()
            ->assertViewHas('maxPerType', 999)
            ->assertViewHas('remaining', fn ($remaining) => $remaining->all() === [
                'sepeda' => 997,
                'mobil' => 999,
                'mobil_listrik' => 999,
            ]);
    }

    public function test_admin_membuat_garasi_berkelompok(): void
    {
        $this->post(route('admin.garages.store'), [
            'type' => 'sepeda',
            'quantity' => 3,
            'daily_price' => 5000,
        ])
            ->assertRedirect(route('admin.garages.index'))
            ->assertSessionHas('success', '3 garasi berhasil dibuat (S001 - S003).');

        $this->assertDatabaseCount('garages', 3);
    }

    public function test_pesan_sukses_untuk_satu_garasi(): void
    {
        $this->post(route('admin.garages.store'), ['type' => 'mobil', 'quantity' => 1, 'daily_price' => 30000])
            ->assertSessionHas('success', '1 garasi berhasil dibuat (C001).');
    }

    public function test_garasi_mobil_listrik_wajib_harga_listrik(): void
    {
        $this->post(route('admin.garages.store'), ['type' => 'mobil_listrik', 'quantity' => 1, 'daily_price' => 50000])
            ->assertSessionHasErrors('electricity_price_per_kwh');

        $this->post(route('admin.garages.store'), [
            'type' => 'mobil_listrik',
            'quantity' => 1,
            'daily_price' => 50000,
            'electricity_price_per_kwh' => 2500,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(2500, Garage::first()->electricity_price_per_kwh);
    }

    public function test_validasi_form_tambah_garasi(): void
    {
        $this->post(route('admin.garages.store'), [
            'type' => 'pesawat',
            'quantity' => 1000,
            'daily_price' => 0,
        ])->assertSessionHasErrors(['type', 'quantity', 'daily_price']);

        $this->assertDatabaseCount('garages', 0);
    }

    public function test_error_kuota_dari_service_dikembalikan_ke_form(): void
    {
        $this->makeGarage(GarageType::Sepeda, 999);

        $this->post(route('admin.garages.store'), ['type' => 'sepeda', 'quantity' => 1, 'daily_price' => 5000])
            ->assertSessionHasErrors(['garage' => 'Sisa kuota Garasi Sepeda tinggal 0.']);
    }
}
