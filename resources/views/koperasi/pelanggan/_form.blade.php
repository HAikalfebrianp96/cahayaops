@csrf

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nama Pelanggan</label>
        <input type="text" name="nama" value="{{ old('nama', $customer->nama ?? '') }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nomor HP <span class="text-text-secondary">(boleh kosong)</span></label>
        <input type="text" name="nomor_hp" value="{{ old('nomor_hp', $customer->nomor_hp ?? '') }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('nomor_hp') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Email <span class="text-text-secondary">(boleh kosong)</span></label>
        <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('email') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">
            Anggota Koperasi <span class="text-text-secondary">(boleh kosong)</span>
        </label>
        <select name="koperasi_member_id"
                class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
            <option value="">Bukan anggota</option>
            @foreach ($anggotaPilihan ?? [] as $a)
                <option value="{{ $a->id }}" @selected((int) old('koperasi_member_id', $customer->koperasi_member_id ?? null) === $a->id)>
                    {{ $a->nomor_anggota }} — {{ $a->nama }}
                </option>
            @endforeach
        </select>
        {{-- Kenapa kolom ini ada dijelaskan di tempatnya, supaya tidak terisi
             asal-asalan hanya karena tersedia. --}}
        <p class="text-[11px] text-text-secondary mt-1">
            Diisi kalau pelanggan ini adalah anggota koperasi. Nilai transaksinya lalu ikut
            diperhitungkan sebagai jasa usaha saat pembagian SHU.
        </p>
        @error('koperasi_member_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-text-main mb-1">Alamat <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="alamat" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">{{ old('alamat', $customer->alamat ?? '') }}</textarea>
        @error('alamat') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="catatan" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">{{ old('catatan', $customer->catatan ?? '') }}</textarea>
        @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="pt-4 flex gap-2">
    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
        Simpan
    </button>
    <a href="{{ route('koperasi.pelanggan.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
        Batal
    </a>
</div>
