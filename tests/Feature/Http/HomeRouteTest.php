<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    public function test_halaman_utama_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
