<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Kode</label>
        <input type="text" name="kode" value="{{ old('kode', $produk->kode ?? '') }}"
               placeholder="Contoh: KP-001"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('kode') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nama</label>
        <input type="text" name="nama" value="{{ old('nama', $produk->nama ?? '') }}"
               placeholder="Contoh: Keripik Pisang"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Jenis</label>
        <select name="jenis" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
            <option value="">— Pilih —</option>
            @foreach ($jenisOptions as $jenis)
                <option value="{{ $jenis }}" @selected(old('jenis', $produk->jenis ?? '') === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
        @error('jenis') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Kategori <span class="text-text-secondary">(boleh kosong, bebas ketik)</span></label>
        <input type="text" name="kategori" list="saranKategori" value="{{ old('kategori', $produk->kategori ?? '') }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
        <datalist id="saranKategori">
            @foreach ($kategoriSaran as $kategori)
                <option value="{{ $kategori }}"></option>
            @endforeach
        </datalist>
        @error('kategori') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Satuan <span class="text-text-secondary">(boleh kosong)</span></label>
        <input type="text" name="satuan" value="{{ old('satuan', $produk->satuan ?? '') }}"
               placeholder="Contoh: pcs, kg, paket, jam"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
        @error('satuan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Status</label>
        <select name="status" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(old('status', $produk->status ?? 'Aktif') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">HPP / Harga Modal (Rp)</label>
        <input type="number" min="0" step="1" name="harga_modal" value="{{ old('harga_modal', $produk->harga_modal ?? 0) }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm font-medium">
        @error('harga_modal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Harga Jual (Rp)</label>
        <input type="number" min="0" step="1" name="harga_jual" value="{{ old('harga_jual', $produk->harga_jual ?? 0) }}"
               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm font-medium">
        @error('harga_jual') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-text-main mb-1">Deskripsi <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="deskripsi" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('deskripsi', $produk->deskripsi ?? '') }}</textarea>
        @error('deskripsi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="flex items-center gap-2 text-sm text-text-main">
            <input type="checkbox" name="stock_tracking" value="1" @checked(old('stock_tracking', $produk->stock_tracking ?? false))
                   class="rounded border-border-soft">
            <span>Lacak stok untuk produk/jasa ini <span class="text-text-secondary">(belum ada sistem stok — cuma penanda untuk tahap berikutnya)</span></span>
        </label>
    </div>
</div>
