<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function editPassword()
    {
        return view('account.password');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $user->password = $validated['password']; // otomatis di-hash lewat cast 'hashed' di User model
        $user->save();

        // Activity Log — HANYA catat bahwa password diubah, TIDAK PERNAH
        // menyimpan password lama maupun baru di log manapun.
        ActivityLog::record(
            action: 'password_change',
            modelType: 'User',
            modelId: $user->id,
            description: 'Ganti password sendiri: ' . $user->name,
        );

        return redirect()
            ->route('account.password.edit')
            ->with('success', 'Password berhasil diubah. Gunakan password baru Anda untuk login berikutnya.');
    }
}
