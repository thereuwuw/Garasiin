<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\GeneralException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private function registerData(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3578000000000001',
            'name' => 'Bambang Santoso',
            'phone' => '081234567890',
            'email' => 'Bambang@Gmail.com',
            'password' => 'password',
        ], $overrides);
    }

    // ---------- register() ----------

    public function test_register_membuat_penyewa_berstatus_pending(): void
    {
        $user = UserService::register($this->registerData());

        $this->assertTrue($user->exists);
        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('bambang@gmail.com', $user->email); // email dibuat huruf kecil
        $this->assertSame('3578000000000001', $user->nik);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_register_tidak_bisa_memaksa_role_admin_atau_status_accepted(): void
    {
        $user = UserService::register($this->registerData([
            'role' => 'admin',
            'status' => 'accepted',
        ]));

        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    // ---------- pendingTenants() & verifiedTenants() ----------

    public function test_pending_tenants_hanya_penyewa_pending_terlama_dulu(): void
    {
        $this->travelTo('2026-11-01');
        $first = User::factory()->pending()->create();
        $this->travelTo('2026-11-02');
        $second = User::factory()->pending()->create();
        User::factory()->create();             // accepted
        User::factory()->admin()->create();    // admin

        $this->assertSame([$first->id, $second->id], UserService::pendingTenants()->pluck('id')->all());
    }

    public function test_verified_tenants_berisi_accepted_dan_rejected_terbaru_dulu(): void
    {
        $older = User::factory()->create(['verified_at' => null]);
        $older->forceFill(['verified_at' => '2026-11-01 10:00:00'])->save();
        $newer = User::factory()->rejected()->create();
        $newer->forceFill(['verified_at' => '2026-11-05 10:00:00'])->save();
        User::factory()->pending()->create();
        User::factory()->admin()->create();

        $this->assertSame([$newer->id, $older->id], UserService::verifiedTenants()->pluck('id')->all());
    }

    // ---------- accept() & reject() ----------

    public function test_admin_menerima_pendaftar(): void
    {
        $this->travelTo('2026-11-10 09:00:00');
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->pending()->create();

        UserService::accept($tenant, $admin);

        $tenant->refresh();
        $this->assertSame(UserStatus::Accepted, $tenant->status);
        $this->assertSame($admin->id, $tenant->verified_by);
        $this->assertSame('2026-11-10 09:00:00', $tenant->verified_at->toDateTimeString());
    }

    public function test_admin_menolak_pendaftar(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->pending()->create();

        UserService::reject($tenant, $admin);

        $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
        $this->assertSame($admin->id, $tenant->fresh()->verified_by);
    }

    public function test_hanya_admin_yang_bisa_memverifikasi(): void
    {
        $notAdmin = User::factory()->create();
        $tenant = User::factory()->pending()->create();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage('Hanya admin yang bisa memverifikasi pendaftar.');

        UserService::accept($tenant, $notAdmin);
    }

    public function test_akun_admin_tidak_bisa_diverifikasi(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->pending()->create();

        $this->expectExceptionMessage('Akun ini bukan penyewa.');

        UserService::accept($otherAdmin, $admin);
    }

    public function test_pendaftar_yang_sudah_diverifikasi_tidak_bisa_diverifikasi_lagi(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = User::factory()->rejected()->create();

        $this->expectExceptionMessage('Pendaftar ini sudah diverifikasi sebelumnya.');

        UserService::accept($tenant, $admin);
    }

    // ---------- homeRoute() ----------

    public function test_home_route_sesuai_role_dan_status(): void
    {
        $this->assertSame('admin.garages.index', UserService::homeRoute(User::factory()->admin()->make()));
        $this->assertSame('tenant.rentals.index', UserService::homeRoute(User::factory()->make()));
        $this->assertSame('tenant.approval', UserService::homeRoute(User::factory()->pending()->make()));
        $this->assertSame('tenant.approval', UserService::homeRoute(User::factory()->rejected()->make()));
    }
}
