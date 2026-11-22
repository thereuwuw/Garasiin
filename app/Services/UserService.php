<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\GeneralException;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Daftar sebagai penyewa. Role dan status diisi di sini (bukan dari form),
     * jadi pendaftar tidak bisa mengirim role=admin atau status=accepted.
     *
     * @param  array{nik: string, name: string, phone: string, email: string, password: string}  $data
     */
    public static function register(array $data): User
    {
        $user = new User([
            'nik' => $data['nik'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $user->forceFill([
            'role' => UserRole::Penyewa,
            'status' => UserStatus::Pending,
        ])->save();

        return $user;
    }

    /**
     * @return Collection<int, User>
     */
    public static function pendingTenants(): Collection
    {
        return User::query()
            ->where('role', UserRole::Penyewa)
            ->where('status', UserStatus::Pending)
            ->oldest()
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public static function verifiedTenants(): Collection
    {
        return User::query()
            ->where('role', UserRole::Penyewa)
            ->whereIn('status', [UserStatus::Accepted->value, UserStatus::Rejected->value])
            ->latest('verified_at')
            ->get();
    }

    /**
     * @throws GeneralException
     */
    public static function accept(User $tenant, User $admin): User
    {
        return self::verify($tenant, $admin, UserStatus::Accepted);
    }

    /**
     * @throws GeneralException
     */
    public static function reject(User $tenant, User $admin): User
    {
        return self::verify($tenant, $admin, UserStatus::Rejected);
    }

    /**
     * Nama route halaman awal sesuai role dan status.
     */
    public static function homeRoute(User $user): string
    {
        if ($user->role === UserRole::Admin) {
            return 'admin.garages.index';
        }

        return $user->status === UserStatus::Accepted ? 'tenant.rentals.index' : 'tenant.approval';
    }

    /**
     * @throws GeneralException
     */
    private static function verify(User $tenant, User $admin, UserStatus $status): User
    {
        if ($admin->role !== UserRole::Admin) {
            throw new GeneralException('Hanya admin yang bisa memverifikasi pendaftar.');
        }

        if ($tenant->role !== UserRole::Penyewa) {
            throw new GeneralException('Akun ini bukan penyewa.');
        }

        if ($tenant->status !== UserStatus::Pending) {
            throw new GeneralException('Pendaftar ini sudah diverifikasi sebelumnya.');
        }

        $tenant->forceFill([
            'status' => $status,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ])->save();

        return $tenant;
    }
}
