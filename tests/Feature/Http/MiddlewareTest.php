<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantAccepted;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function requestAs(?User $user): Request
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function next(): \Closure
    {
        return fn () => new Response('lolos');
    }

    // ---------- EnsureRole (dipanggil langsung) ----------

    public function test_ensure_role_meloloskan_role_yang_cocok(): void
    {
        $response = (new EnsureRole)->handle($this->requestAs(User::factory()->admin()->make()), $this->next(), 'admin');

        $this->assertSame('lolos', $response->getContent());
    }

    public function test_ensure_role_menolak_role_lain_dengan_403(): void
    {
        try {
            (new EnsureRole)->handle($this->requestAs(User::factory()->make()), $this->next(), 'admin');
            $this->fail('Seharusnya 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_ensure_role_menolak_tamu_dengan_403(): void
    {
        $this->expectException(HttpException::class);

        (new EnsureRole)->handle($this->requestAs(null), $this->next(), 'penyewa');
    }

    // ---------- EnsureTenantAccepted (dipanggil langsung) ----------

    public function test_ensure_tenant_accepted_meloloskan_penyewa_accepted(): void
    {
        $response = (new EnsureTenantAccepted)->handle($this->requestAs(User::factory()->make()), $this->next());

        $this->assertSame('lolos', $response->getContent());
    }

    public function test_ensure_tenant_accepted_mengarahkan_pending_dan_rejected(): void
    {
        foreach ([User::factory()->pending()->make(), User::factory()->rejected()->make()] as $user) {
            $response = (new EnsureTenantAccepted)->handle($this->requestAs($user), $this->next());

            $this->assertTrue($response->isRedirect(route('tenant.approval')));
        }
    }

    // ---------- Lewat route sungguhan ----------

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.garages.index'))->assertRedirect(route('login'));
        $this->get(route('tenant.rentals.index'))->assertRedirect(route('login'));
    }

    public function test_penyewa_tidak_bisa_membuka_halaman_admin(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.garages.index'))->assertForbidden();
        $this->get(route('admin.verifications.index'))->assertForbidden();
        $this->get(route('admin.extensions.index'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_admin_tidak_bisa_membuka_halaman_penyewa(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('tenant.rentals.index'))->assertForbidden();
        $this->get(route('tenant.approval'))->assertForbidden();
    }

    public function test_penyewa_pending_tidak_bisa_membuka_halaman_sewa(): void
    {
        $this->actingAs(User::factory()->pending()->create());

        $this->get(route('tenant.rentals.index'))->assertRedirect(route('tenant.approval'));
        $this->get(route('tenant.rentals.create'))->assertRedirect(route('tenant.approval'));
        $this->post(route('tenant.rentals.store'))->assertRedirect(route('tenant.approval'));
    }
}
