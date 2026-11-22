<?php

namespace Tests\Feature\Services;

use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRentalData;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use CreatesRentalData, RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_merge([
            'type' => 'sepeda_motor',
            'plate_number' => 'l 5678  cd',
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'max_battery_capacity_kwh' => null,
        ], $overrides);
    }

    public function test_membuat_kendaraan_baru_untuk_penyewa(): void
    {
        $tenant = $this->makeTenant();

        $vehicle = VehicleService::resolveForUser($tenant, $this->data());

        $this->assertSame($tenant->id, $vehicle->user_id);
        $this->assertSame(VehicleType::SepedaMotor, $vehicle->type);
        $this->assertSame('L 5678 CD', $vehicle->plate_number);
        $this->assertNull($vehicle->max_battery_capacity_kwh);
    }

    public function test_tipe_boleh_dikirim_sebagai_enum(): void
    {
        $vehicle = VehicleService::resolveForUser($this->makeTenant(), $this->data(['type' => VehicleType::Mobil]));

        $this->assertSame(VehicleType::Mobil, $vehicle->type);
    }

    public function test_sepeda_tidak_punya_plat_dan_selalu_dibuat_baru(): void
    {
        $tenant = $this->makeTenant();
        $data = $this->data(['type' => 'sepeda', 'plate_number' => 'ABC', 'brand' => 'Polygon', 'model' => 'Xtrada']);

        $first = VehicleService::resolveForUser($tenant, $data);
        $second = VehicleService::resolveForUser($tenant, $data);

        $this->assertNull($first->plate_number);
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('vehicles', 2);
    }

    public function test_plat_yang_sudah_ada_milik_sendiri_dipakai_ulang_dan_diperbarui(): void
    {
        $tenant = $this->makeTenant();
        $first = VehicleService::resolveForUser($tenant, $this->data());

        $again = VehicleService::resolveForUser($tenant, $this->data([
            'plate_number' => 'L 5678 CD',
            'model' => 'Vario 125',
        ]));

        $this->assertSame($first->id, $again->id);
        $this->assertSame('Vario 125', $again->fresh()->model);
        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_plat_milik_penyewa_lain_ditolak(): void
    {
        VehicleService::resolveForUser($this->makeTenant(), $this->data());

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Nomor plat ini sudah terdaftar atas penyewa lain.');

        VehicleService::resolveForUser($this->makeTenant(), $this->data());
    }

    public function test_plat_sama_dengan_tipe_berbeda_ditolak(): void
    {
        $tenant = $this->makeTenant();
        VehicleService::resolveForUser($tenant, $this->data());

        $this->expectExceptionMessage('Nomor plat ini sudah terdaftar sebagai tipe kendaraan lain.');

        VehicleService::resolveForUser($tenant, $this->data(['type' => 'mobil']));
    }

    public function test_mobil_listrik_menyimpan_kapasitas_baterai(): void
    {
        $vehicle = VehicleService::resolveForUser($this->makeTenant(), $this->data([
            'type' => 'mobil_listrik',
            'max_battery_capacity_kwh' => 60,
        ]));

        $this->assertEquals(60, $vehicle->max_battery_capacity_kwh);
    }

    public function test_kapasitas_baterai_diabaikan_untuk_non_mobil_listrik(): void
    {
        $vehicle = VehicleService::resolveForUser($this->makeTenant(), $this->data([
            'type' => 'mobil',
            'max_battery_capacity_kwh' => 60,
        ]));

        $this->assertNull($vehicle->max_battery_capacity_kwh);
    }

    public function test_tipe_tidak_valid_ditolak(): void
    {
        $this->expectExceptionMessage('Tipe kendaraan tidak valid.');

        VehicleService::resolveForUser($this->makeTenant(), $this->data(['type' => 'truk']));
    }

    public function test_plat_wajib_kecuali_sepeda(): void
    {
        $this->expectExceptionMessage('Nomor plat wajib diisi, kecuali untuk sepeda.');

        VehicleService::resolveForUser($this->makeTenant(), $this->data(['plate_number' => '  ']));
    }

    public function test_merek_dan_tipe_wajib(): void
    {
        $this->expectExceptionMessage('Merek dan tipe kendaraan wajib diisi.');

        VehicleService::resolveForUser($this->makeTenant(), $this->data(['brand' => ' ']));
    }

    public function test_mobil_listrik_wajib_kapasitas_baterai(): void
    {
        $this->expectExceptionMessage('Kapasitas baterai wajib diisi untuk mobil listrik.');

        VehicleService::resolveForUser($this->makeTenant(), $this->data(['type' => 'mobil_listrik']));
    }
}
