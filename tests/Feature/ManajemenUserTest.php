<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Test untuk halaman Manajemen User — masih PLACEHOLDER ("Segera Hadir").
 * Yang diuji di sini cuma: route terdaftar, guard Admin jalan, dan
 * halamannya benar-benar bisa dibuka. Belum ada CRUD untuk diuji.
 */
class ManajemenUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin-manajemen-user@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
    }

    private function makePendamping(): User
    {
        return User::create([
            'name' => 'Pendamping Test',
            'email' => 'pendamping-manajemen-user@example.com',
            'password' => 'password123',
            'role' => 'pendamping',
        ]);
    }

    public function test_route_manajemen_user_is_registered(): void
    {
        $this->assertTrue(Route::has('manajemen-user.index'));
    }

    public function test_guest_cannot_access_manajemen_user(): void
    {
        $response = $this->get(route('manajemen-user.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_is_forbidden_from_manajemen_user(): void
    {
        $pendamping = $this->makePendamping();

        $response = $this->actingAs($pendamping)->get(route('manajemen-user.index'));

        $response->assertForbidden();
    }

    /**
     * Halaman ini dulu hanya placeholder "Segera Hadir". Sejak Tahap 3 sudah
     * jadi modul sungguhan, jadi yang dikunci sekarang isi nyatanya — dan
     * dipastikan teks placeholder-nya BENAR-BENAR sudah hilang.
     */
    public function test_admin_can_open_manajemen_user(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('manajemen-user.index'));

        $response->assertOk();
        $response->assertSee('Manajemen User');
        $response->assertSee('Kelola akun internal yang memiliki akses ke CahayaOS.');
        $response->assertSee('+ Tambah User');
        $response->assertDontSee('Segera Hadir');
    }
}
