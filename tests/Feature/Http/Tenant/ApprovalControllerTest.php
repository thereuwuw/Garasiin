<?php

namespace Tests\Feature\Http\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_penyewa_pending_melihat_halaman_status(): void
    {
        $this->actingAs(User::factory()->pending()->create(['name' => 'Citra']))
            ->get(route('tenant.approval'))
            ->assertOk()
            ->assertSee('Citra');
    }

    public function test_penyewa_rejected_melihat_halaman_status(): void
    {
        $this->actingAs(User::factory()->rejected()->create())
            ->get(route('tenant.approval'))
            ->assertOk();
    }

    public function test_penyewa_accepted_diarahkan_ke_daftar_sewa(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tenant.approval'))
            ->assertRedirect(route('tenant.rentals.index'));
    }
}
