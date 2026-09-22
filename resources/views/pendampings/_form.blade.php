@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
        <input type="text" name="nama" value="{{ old('nama', $pendamping->nama ?? '') }}"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
        @error('nama') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor HP</label>
        <input type="text" name="nomor_hp" value="{{ old('nomor_hp', $pendamping->nomor_hp ?? '') }}"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
        @error('nomor_hp') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-gray-400">(boleh kosong)</span></label>
        <input type="email" name="email" value="{{ old('email', $pendamping->email ?? '') }}"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
        @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">— Pilih Status —</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $pendamping->status ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>

{{-- ================= AKUN LOGIN CAHAYAOS ================= --}}
@php
    $sudahPunyaAkun = isset($pendamping) && $pendamping->user;
@endphp

<div class="mt-8 pt-6 border-t border-gray-200">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Akun Login CahayaOS</h3>

    @if ($sudahPunyaAkun)
        @if (session('password_baru'))
            <div class="mb-3 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm">
                <p class="text-amber-800 font-medium mb-1">Password Sementara: <span class="text-base tracking-wide font-bold">{{ session('password_baru') }}</span></p>
                <p class="text-xs text-amber-700">Password ini hanya ditampilkan sekarang. Simpan/berikan ke Pendamping — password lama sudah tidak berlaku.</p>
            </div>
        @endif
        <p class="text-sm text-green-700 bg-green-50 border border-green-100 rounded-md px-3 py-2 inline-block">
            🟢 Akun Login: Aktif — <span class="font-medium">{{ $pendamping->user->email }}</span>
        </p>
        <p class="text-xs text-gray-400 mt-2 mb-3">Ubah email di atas untuk mengganti email login-nya juga (otomatis disinkronkan).</p>

        {{-- Tombol ini SENGAJA tidak pakai <form> terpisah (supaya tidak nested-form
        di dalam form Edit) — pakai formaction/formmethod, tetap kirim _method=PUT
        yang sudah ada di form utama, jadi nyambung ke route PUT reset-password. --}}
        <button type="submit"
                formaction="{{ route('pendampings.reset-password', $pendamping) }}"
                formmethod="POST"
                onclick="return confirm('Reset password Pendamping ini?\n\nPassword lama akan langsung tidak berlaku dan sistem akan membuat password sementara baru.');"
                class="text-xs bg-cahaya-secondary text-white font-medium px-3 py-1.5 rounded-md hover:opacity-90 transition">
            Reset Password
        </button>
    @else
        <label class="flex items-center gap-2 text-sm text-gray-700 mb-3">
            <input type="checkbox" name="buat_akun" value="1" id="buatAkunCheckbox"
                   onchange="document.getElementById('akunFields').style.display = this.checked ? 'grid' : 'none'"
                   {{ old('buat_akun') ? 'checked' : '' }}
                   class="rounded border-gray-300">
            Buat akun login untuk pendamping
        </label>

        <div id="akunFields" style="display: {{ old('buat_akun') ? 'grid' : 'none' }};" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 rounded-md p-4">
            <p class="text-xs text-gray-500 md:col-span-2 -mt-1 mb-1">
                Pendamping dengan akun login dapat masuk ke CahayaOS dan hanya mengakses data binaannya. Email di atas akan menjadi email login.
            </p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password Sementara</label>
                <input type="password" name="password" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>
    @endif
</div>

<div class="mt-6 flex gap-2">
    <button type="submit"
            class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
        Simpan
    </button>
    <a href="{{ route('pendampings.index') }}"
       class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
        Batal
    </a>
</div>
