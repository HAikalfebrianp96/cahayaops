<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nama Unit Usaha</label>
        <input type="text" name="nama" value="{{ old('nama', $unit->nama ?? '') }}"
               placeholder="Contoh: Cahaya Mart"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Kode Unit</label>
        <input type="text" name="kode" value="{{ old('kode', $unit->kode ?? '') }}"
               placeholder="Contoh: MART"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('kode') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Jenis Usaha <span class="text-text-secondary">(boleh kosong)</span></label>
        <input type="text" name="jenis_usaha" value="{{ old('jenis_usaha', $unit->jenis_usaha ?? '') }}"
               placeholder="Contoh: Retail Sembako"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
        @error('jenis_usaha') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Tipe Unit</label>
        <select name="tipe" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
            <option value="">— Pilih Tipe —</option>
            @foreach ($tipeOptions as $tipe)
                <option value="{{ $tipe }}" @selected(old('tipe', $unit->tipe ?? '') === $tipe)>{{ $tipe }}</option>
            @endforeach
        </select>
        @error('tipe') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-text-main mb-1">Deskripsi <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="deskripsi" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('deskripsi', $unit->deskripsi ?? '') }}</textarea>
        @error('deskripsi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-text-main mb-1">Alamat <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="alamat" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('alamat', $unit->alamat ?? '') }}</textarea>
        @error('alamat') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nomor Kontak <span class="text-text-secondary">(boleh kosong)</span></label>
        <input type="text" name="nomor_kontak" value="{{ old('nomor_kontak', $unit->nomor_kontak ?? '') }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
        @error('nomor_kontak') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Tanggal Mulai <span class="text-text-secondary">(boleh kosong)</span></label>
        <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', isset($unit) && $unit->tanggal_mulai ? $unit->tanggal_mulai->format('Y-m-d') : '') }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
        @error('tanggal_mulai') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Status</label>
        <select name="status" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $unit->status ?? 'Aktif') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="catatan" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('catatan', $unit->catatan ?? '') }}</textarea>
        @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>
</div>
