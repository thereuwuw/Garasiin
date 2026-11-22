<?php

namespace Tests\Unit\Enums;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\RentalStatus;
use App\Enums\RentalType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_nilai_enum_sesuai_kolom_enum_di_database(): void
    {
        $this->assertSame(['available', 'used'], array_column(GarageStatus::cases(), 'value'));
        $this->assertSame(['sepeda', 'mobil', 'mobil_listrik'], array_column(GarageType::cases(), 'value'));
        $this->assertSame(['new', 'extend'], array_column(RentalStatus::cases(), 'value'));
        $this->assertSame(['harian', 'bulanan'], array_column(RentalType::cases(), 'value'));
        $this->assertSame(['admin', 'penyewa'], array_column(UserRole::cases(), 'value'));
        $this->assertSame(['pending', 'accepted', 'rejected'], array_column(UserStatus::cases(), 'value'));
        $this->assertSame(['sepeda', 'sepeda_motor', 'mobil', 'mobil_listrik'], array_column(VehicleType::cases(), 'value'));
    }

    public static function garageTypeProvider(): array
    {
        return [
            'sepeda' => [GarageType::Sepeda, 'S', 'Garasi Sepeda'],
            'mobil' => [GarageType::Mobil, 'C', 'Garasi Mobil'],
            'mobil listrik' => [GarageType::MobilListrik, 'E', 'Garasi Mobil Listrik'],
        ];
    }

    #[DataProvider('garageTypeProvider')]
    public function test_garage_type_punya_prefix_kode_dan_label(GarageType $type, string $prefix, string $label): void
    {
        $this->assertSame($prefix, $type->prefix());
        $this->assertSame($label, $type->label());
    }

    public static function vehicleTypeProvider(): array
    {
        return [
            'sepeda hanya di garasi sepeda' => [VehicleType::Sepeda, [GarageType::Sepeda], false, 'Sepeda'],
            'sepeda motor hanya di garasi sepeda' => [VehicleType::SepedaMotor, [GarageType::Sepeda], true, 'Sepeda Motor'],
            'mobil hanya di garasi mobil' => [VehicleType::Mobil, [GarageType::Mobil], true, 'Mobil'],
            'mobil listrik di garasi mobil atau mobil listrik' => [VehicleType::MobilListrik, [GarageType::Mobil, GarageType::MobilListrik], true, 'Mobil Listrik'],
        ];
    }

    #[DataProvider('vehicleTypeProvider')]
    public function test_vehicle_type_aturan_garasi_plat_dan_label(VehicleType $type, array $garageTypes, bool $needsPlate, string $label): void
    {
        $this->assertSame($garageTypes, $type->allowedGarageTypes());
        $this->assertSame($needsPlate, $type->needsPlate());
        $this->assertSame($label, $type->label());
    }

    public function test_label_rental_status(): void
    {
        $this->assertSame('Sewa Baru', RentalStatus::New->label());
        $this->assertSame('Perpanjangan', RentalStatus::Extend->label());
    }

    public function test_label_rental_type(): void
    {
        $this->assertSame('Harian', RentalType::Harian->label());
        $this->assertSame('Bulanan', RentalType::Bulanan->label());
    }

    public function test_label_user_status(): void
    {
        $this->assertSame('Menunggu Persetujuan', UserStatus::Pending->label());
        $this->assertSame('Diterima', UserStatus::Accepted->label());
        $this->assertSame('Ditolak', UserStatus::Rejected->label());
    }
}
