<?php

namespace Tests\Feature\Http\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function registerForm(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3578000000000001',
            'name' => 'Bambang Santoso',
            'phone' => '081234567890',
            'email' => 'bambang@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    // ---------- Register ----------

    public function test_halaman_register_bisa_dibuka(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_register_membuat_penyewa_pending_dan_langsung_login(): void
    {
        $response = $this->post(route('register'), $this->registerForm());

        $response->assertRedirect(route('tenant.approval'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'bambang@gmail.com')->first();
        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_mengabaikan_role_dari_form(): void
    {
        $this->post(route('register'), $this->registerForm(['role' => 'admin', 'status' => 'accepted']));

        $user = User::first();
        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_register_validasi_input(): void
    {
        User::factory()->create(['email' => 'bambang@gmail.com', 'nik' => '3578000000000001']);

        $this->post(route('register'), $this->registerForm())
            ->assertSessionHasErrors(['email', 'nik']);

        $this->post(route('register'), $this->registerForm([
            'nik' => '123',
            'email' => 'bukan-email',
            'password_confirmation' => 'lain',
            'name' => '',
            'phone' => '',
        ]))->assertSessionHasErrors(['nik', 'email', 'password', 'name', 'phone']);

        $this->assertGuest();
    }

    public function test_nik_wajib_diisi(): void
    {
        $this->post(route('register'), $this->registerForm(['nik' => '']))
            ->assertSessionHasErrors('nik');

        $form = $this->registerForm();
        unset($form['nik']);

        $this->post(route('register'), $form)
            ->assertSessionHasErrors('nik');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    // ---------- Login & Logout ----------

    public function test_halaman_login_bisa_dibuka(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_login_admin_diarahkan_ke_halaman_garasi(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.garages.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_penyewa_accepted_diarahkan_ke_daftar_sewa(): void
    {
        $tenant = User::factory()->create();

        $this->post(route('login'), ['email' => $tenant->email, 'password' => 'password'])
            ->assertRedirect(route('tenant.rentals.index'));
    }

    public function test_login_penyewa_pending_diarahkan_ke_halaman_status(): void
    {
        $tenant = User::factory()->pending()->create();

        $this->post(route('login'), ['email' => $tenant->email, 'password' => 'password'])
            ->assertRedirect(route('tenant.approval'));
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        $tenant = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login'), ['email' => $tenant->email, 'password' => 'salah'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    public function test_login_dibatasi_5_kali_per_menit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => 'x@gmail.com', 'password' => 'salah']);
        }

        $this->post(route('login'), ['email' => 'x@gmail.com', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_yang_sudah_login_tidak_bisa_membuka_login_dan_register(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('login'))->assertRedirect();
        $this->get(route('register'))->assertRedirect();
    }

    // ---------- Halaman utama ----------

    public function test_halaman_utama_mengarahkan_sesuai_role(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertRedirect(route('admin.garages.index'));
    }
}
