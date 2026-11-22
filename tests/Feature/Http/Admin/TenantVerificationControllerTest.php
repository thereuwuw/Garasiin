<?php

namespace Tests\Feature\Http\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_halaman_verifikasi_menampilkan_pendaftar(): void
    {
        $pending = User::factory()->pending()->create(['name' => 'Citra Lestari']);
        $accepted = User::factory()->create(['name' => 'Bambang Santoso']);

        $this->get(route('admin.verifications.index'))
            ->assertOk()
            ->assertSee('Citra Lestari')
            ->assertViewHas('pending', fn ($users) => $users->pluck('id')->all() === [$pending->id])
            ->assertViewHas('verified', fn ($users) => $users->contains($accepted));
    }

    public function test_admin_menerima_pendaftar(): void
    {
        $tenant = User::factory()->pending()->create(['name' => 'Citra']);

        $this->patch(route('admin.verifications.accept', $tenant))
            ->assertRedirect()
            ->assertSessionHas('success', 'Pendaftaran Citra diterima.');

        $this->assertSame(UserStatus::Accepted, $tenant->fresh()->status);
        $this->assertSame($this->admin->id, $tenant->fresh()->verified_by);
    }

    public function test_admin_menolak_pendaftar(): void
    {
        $tenant = User::factory()->pending()->create(['name' => 'Dewi']);

        $this->patch(route('admin.verifications.reject', $tenant))
            ->assertSessionHas('success', 'Pendaftaran Dewi ditolak.');

        $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
    }

    public function test_pendaftar_yang_sudah_diverifikasi_tidak_bisa_diubah(): void
    {
        $tenant = User::factory()->rejected()->create();

        $this->patch(route('admin.verifications.accept', $tenant))
            ->assertSessionHasErrors(['verification' => 'Pendaftar ini sudah diverifikasi sebelumnya.']);

        $this->assertSame(UserStatus::Rejected, $tenant->fresh()->status);
    }
}
