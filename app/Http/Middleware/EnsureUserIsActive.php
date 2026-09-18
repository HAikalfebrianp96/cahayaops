<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup celah keamanan: sesi yang SUDAH berjalan milik akun yang kemudian
 * dinonaktifkan.
 *
 * Tahap 2 sudah mencegah akun nonaktif LOGIN BARU (LoginController), tapi
 * pengguna yang sudah terlanjur login tetap bisa memakai sesi lamanya sampai
 * sesi itu kedaluwarsa. Middleware ini memeriksanya di SETIAP request, jadi
 * begitu Admin menonaktifkan sebuah akun, request berikutnya dari akun itu
 * langsung ditolak.
 *
 * SATU TITIK PENGAMANAN: dipasang di grup middleware 'web' (lihat
 * bootstrap/app.php), bukan ditempel satu per satu ke puluhan controller.
 *
 * TIDAK mengganggu tamu: kalau belum login, middleware ini langsung
 * meneruskan request apa adanya — halaman login, form lapor omzet publik,
 * dan cek simpanan anggota tetap bisa diakses seperti biasa.
 *
 * TIDAK menyebabkan infinite redirect: pengguna sudah di-logout SEBELUM
 * diarahkan ke /login, jadi saat sampai di sana dia sudah berstatus tamu dan
 * middleware 'guest' tidak memantulkannya balik.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Tamu, atau akun yang masih aktif -> perilaku existing, tanpa perubahan.
        if (! $user || $user->isActive()) {
            return $next($request);
        }

        $userId = $user->id;
        $userName = $user->name;

        // Bunuh sesi yang sedang berjalan. Password, role, pendamping_id,
        // dan seluruh data akun TIDAK disentuh — hanya sesinya yang dibatalkan.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Aktivitas keamanan — memakai ActivityLog::record() yang sudah ada,
        // bukan mekanisme baru. userId diisi eksplisit karena setelah logout
        // Auth::id() sudah null. Hanya tercatat sekali per sesi: request
        // berikutnya sudah berstatus tamu.
        ActivityLog::record(
            action: 'session_blocked',
            modelType: 'Auth',
            modelId: $userId,
            description: 'Sesi dihentikan, akun sudah dinonaktifkan: ' . $userName,
            userId: $userId,
        );

        $pesan = 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.';

        // Permintaan AJAX/JSON tidak boleh dikirimi halaman login sebagai
        // jawaban — kembalikan 401 supaya sisi klien bisa menanganinya.
        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()
            ->route('login')
            ->withErrors(['akun' => $pesan]);
    }
}
