<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman awal ("/") CahayaOS SELALU redirect ke halaman login untuk
     * pengunjung yang belum login (lihat routes/web.php) — jadi test ini
     * memverifikasi redirect yang MEMANG terjadi, bukan assertStatus(200)
     * yang tidak sesuai perilaku aplikasi.
     */
    public function test_guest_visiting_root_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
