<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Tampilkan halaman login.
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Proses login.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // ==========================================================
            // BLOKIR AKUN NONAKTIF.
            //
            // Dicek SETELAH kredensial terbukti benar, dan SEBELUM sesi
            // di-regenerate — jadi akun nonaktif tidak pernah benar-benar
            // punya sesi login. Auth::logout() sekaligus membersihkan
            // cookie "remember me" kalau tadi dicentang.
            //
            // Password TIDAK diubah dan data TIDAK dihapus: yang dicabut
            // hanya aksesnya.
            //
            // Pesannya sengaja BERBEDA dari pesan "email/password salah".
            // Kalau disamakan, pemilik akun akan mengira dirinya lupa
            // password dan terus mencoba, padahal masalahnya bukan itu.
            // ==========================================================
            if (! $user->isActive()) {
                $userId = $user->id;
                $userName = $user->name;

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // userId diisi eksplisit — setelah logout, Auth::id() null.
                ActivityLog::record(
                    action: 'login_blocked',
                    modelType: 'Auth',
                    modelId: $userId,
                    description: 'Login ditolak, akun dinonaktifkan: ' . $userName,
                    userId: $userId,
                );

                return back()
                    ->withErrors([
                        'email' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi administrator.',
                    ])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();

            // Catat login terakhir. Ditulis langsung ke properti (bukan
            // mass-assignment) karena kedua kolom ini sengaja tidak masuk
            // $fillable — hanya alur login yang boleh mengisinya.
            $user->last_login_at = now();
            $user->last_login_ip = $request->ip();
            $user->save();

            // Activity Log — login berhasil. TIDAK mencatat percobaan gagal
            // (email/password salah tidak pernah tahu identitas user-nya).
            ActivityLog::record(
                action: 'login',
                modelType: 'Auth',
                modelId: Auth::id(),
                description: 'Login berhasil: ' . Auth::user()->name,
            );

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors([
                'email' => 'Email atau password yang Anda masukkan salah.',
            ])
            ->onlyInput('email');
    }

    /**
     * Proses logout.
     */
    public function destroy(Request $request)
    {
        // Ambil dulu identitas user SEBELUM logout — setelah Auth::logout()
        // dipanggil, Auth::user() sudah null dan tidak bisa dicatat lagi.
        $user = Auth::user();
        if ($user) {
            ActivityLog::record(
                action: 'logout',
                modelType: 'Auth',
                modelId: $user->id,
                description: 'Logout: ' . $user->name,
                userId: $user->id,
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
