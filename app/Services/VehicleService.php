<?php

namespace App\Services;

use App\Enums\VehicleType;
use App\Exceptions\GeneralException;
use App\Models\User;
use App\Models\Vehicle;

class VehicleService
{
    /**
     * Ambil kendaraan penyewa berdasarkan nomor plat, atau buat baru kalau belum ada.
     * Satu penyewa bisa punya banyak kendaraan. Sepeda tidak punya plat, jadi selalu dibuat baru.
     *
     * Keys: type, plate_number, brand, model, max_battery_capacity_kwh.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws GeneralException
     */
    public static function resolveForUser(User $user, array $data): Vehicle
    {
        $type = ($data['type'] ?? null) instanceof VehicleType
            ? $data['type']
            : VehicleType::tryFrom((string) ($data['type'] ?? ''));

        if ($type === null) {
            throw new GeneralException('Tipe kendaraan tidak valid.');
        }

        $plate = $type->needsPlate() ? self::normalizePlate($data['plate_number'] ?? null) : null;

        if ($type->needsPlate() && $plate === null) {
            throw new GeneralException('Nomor plat wajib diisi, kecuali untuk sepeda.');
        }

        $brand = trim((string) ($data['brand'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));

        if ($brand === '' || $model === '') {
            throw new GeneralException('Merek dan tipe kendaraan wajib diisi.');
        }

        $capacity = null;

        if ($type === VehicleType::MobilListrik) {
            $capacity = (float) ($data['max_battery_capacity_kwh'] ?? 0);

            if ($capacity <= 0) {
                throw new GeneralException('Kapasitas baterai wajib diisi untuk mobil listrik.');
            }
        }

        if ($plate !== null) {
            $existing = Vehicle::query()->where('plate_number', $plate)->first();

            if ($existing !== null) {
                if ($existing->user_id !== $user->id) {
                    throw new GeneralException('Nomor plat ini sudah terdaftar atas penyewa lain.');
                }

                if ($existing->type !== $type) {
                    throw new GeneralException('Nomor plat ini sudah terdaftar sebagai tipe kendaraan lain.');
                }

                $existing->update([
                    'brand' => $brand,
                    'model' => $model,
                    'max_battery_capacity_kwh' => $capacity,
                ]);

                return $existing;
            }
        }

        return $user->vehicles()->create([
            'type' => $type,
            'plate_number' => $plate,
            'brand' => $brand,
            'model' => $model,
            'max_battery_capacity_kwh' => $capacity,
        ]);
    }

    /**
     * "l 1234  ab" menjadi "L 1234 AB".
     */
    public static function normalizePlate(mixed $plate): ?string
    {
        if (! is_string($plate)) {
            return null;
        }

        $plate = strtoupper(trim((string) preg_replace('/\s+/', ' ', $plate)));

        return $plate === '' ? null : $plate;
    }
}
