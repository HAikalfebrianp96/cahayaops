@csrf
<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Tanggal</label>
        <input type="date" name="tanggal" value="{{ old('tanggal', isset($pengeluaran) ? $pengeluaran->tanggal->format('Y-m-d') : now()->format('Y-m-d')) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        @error('tanggal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Unit Usaha</label>
        <select name="business_unit_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="" {{ old('business_unit_id', $pengeluaran->business_unit_id ?? '') == '' ? 'selected' : '' }}>Operasional Koperasi (bukan milik unit tertentu)</option>
            @foreach ($units as $u)
                <option value="{{ $u->id }}" {{ old('business_unit_id', $pengeluaran->business_unit_id ?? '') == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
            @endforeach
        </select>
        @error('business_unit_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        {{-- Tautan "+ Tambah Kategori" dibuka di tab baru.
             Kategori pengeluaran memang sudah dinamis — daftarnya datang dari
             tabel master, bukan dari daftar tetap di kode. Yang kurang selama
             ini cuma jalannya: petugas yang butuh kategori baru di tengah
             pengisian form harus mencarinya sendiri lewat menu, dan isian
             yang sudah diketik hilang begitu ia berpindah halaman.

             Izinnya SAMA dengan halaman kategorinya sendiri
             (bolehKelolaKoperasi) — tautan ini tidak membuka pintu baru,
             hanya memperpendek jalan yang memang sudah terbuka. --}}
        <div class="flex items-baseline justify-between mb-1">
            <label class="block text-sm font-medium text-text-main">Kategori Pengeluaran</label>
            @if (auth()->user()?->bolehKelolaKoperasi())
                <a href="{{ route('koperasi.kategori-pengeluaran.index') }}" target="_blank" rel="noopener"
                   class="text-xs font-medium text-status-info hover:underline">+ Tambah Kategori</a>
            @endif
        </div>
        <select name="pengeluaran_category_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">— Pilih Kategori —</option>
            @foreach ($kategoriOptions as $k)
                <option value="{{ $k->id }}" {{ old('pengeluaran_category_id', $pengeluaran->pengeluaran_category_id ?? '') == $k->id ? 'selected' : '' }}>
                    {{ $k->nama }}{{ $k->status === 'Tidak Aktif' ? ' (nonaktif)' : '' }}
                </option>
            @endforeach
        </select>
        @if (isset($pengeluaran) && ! $pengeluaran->pengeluaran_category_id && $pengeluaran->kategori)
            <p class="text-xs text-text-secondary mt-1">Kategori lama (belum terhubung master): <span class="font-medium text-text-secondary">{{ $pengeluaran->kategori }}</span> — boleh dibiarkan, atau pilih kategori master di atas kalau mau diperbarui.</p>
        @endif
        @error('pengeluaran_category_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nominal (Rp)</label>
        <input type="number" min="0" step="1" name="nominal" value="{{ old('nominal', $pengeluaran->nominal ?? '') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        @error('nominal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Metode Pembayaran</label>
        <select name="metode_pembayaran" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @foreach ($metodeOptions as $m)
                <option value="{{ $m }}" {{ old('metode_pembayaran', $pengeluaran->metode_pembayaran ?? 'Cash') === $m ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>
        @error('metode_pembayaran') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="keterangan" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">{{ old('keterangan', $pengeluaran->keterangan ?? '') }}</textarea>
        @error('keterangan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Bukti Pengeluaran <span class="text-text-secondary">(JPG/PNG/PDF, boleh kosong)</span></label>
        @if (isset($pengeluaran) && $pengeluaran->bukti_path)
            <p class="text-xs text-text-secondary mb-1">
                File saat ini: {{ $pengeluaran->bukti_asli }} —
                <a href="{{ route('koperasi.pengeluaran.bukti', $pengeluaran) }}" target="_blank" class="text-koperasi-primary hover:underline">Lihat</a>
                (upload file baru untuk mengganti)
            </p>
        @endif
        <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.pdf" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        @error('bukti') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="pt-2">
        <button type="submit" class="bg-koperasi-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:opacity-90 transition">
            Simpan Pengeluaran
        </button>
    </div>
</div>
