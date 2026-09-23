@csrf
<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Nama Akun</label>
        <input type="text" name="nama" value="{{ old('nama', $akun->nama ?? '') }}" placeholder="mis. Kas Tunai, Bank BRI Koperasi"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Jenis</label>
        <select name="jenis" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @foreach (\App\Models\AkunKas::JENIS_OPTIONS as $j)
                <option value="{{ $j }}" {{ old('jenis', $akun->jenis ?? 'Kas') === $j ? 'selected' : '' }}>
                    {{ $j === 'Kas' ? 'Kas (uang tunai / laci)' : 'Bank (rekening)' }}
                </option>
            @endforeach
        </select>
        @error('jenis') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-text-main mb-1">Nomor Rekening <span class="text-text-secondary font-normal">(khusus Bank)</span></label>
            <input type="text" name="nomor_rekening" value="{{ old('nomor_rekening', $akun->nomor_rekening ?? '') }}"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @error('nomor_rekening') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-text-main mb-1">Atas Nama <span class="text-text-secondary font-normal">(khusus Bank)</span></label>
            <input type="text" name="atas_nama" value="{{ old('atas_nama', $akun->atas_nama ?? '') }}"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @error('atas_nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="rounded-lg border border-border-soft bg-gray-50 p-4 space-y-4">
        <div>
            <p class="text-sm font-semibold text-text-main">Saldo Awal</p>
            <p class="text-xs text-text-secondary mt-0.5">
                Isi dengan uang yang <strong>benar-benar ada</strong> di akun ini pada tanggal mulai pencatatan —
                hasil hitung isi laci, atau saldo di buku tabungan/mutasi rekening. Kosongkan tanggal bila belum dihitung.
                Saldo awal berlaku pada <strong>awal hari</strong> tanggal tersebut: transaksi di tanggal itu dan sesudahnya
                dihitung, transaksi sebelumnya dianggap sudah termasuk.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-text-main mb-1">Nominal (Rp)</label>
                <input type="number" min="0" step="1" name="saldo_awal"
                       value="{{ old('saldo_awal', isset($akun) ? (float) $akun->saldo_awal : '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('saldo_awal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-text-main mb-1">Per Tanggal</label>
                <input type="date" name="tanggal_saldo_awal" max="{{ now()->format('Y-m-d') }}"
                       value="{{ old('tanggal_saldo_awal', isset($akun) ? $akun->tanggal_saldo_awal?->format('Y-m-d') : '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('tanggal_saldo_awal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        @isset($akun)
            <p class="text-xs text-text-secondary">Setiap perubahan saldo awal dicatat di Activity Log beserta nilai sebelumnya.</p>
        @endisset
    </div>

    <div>
        <label class="block text-sm font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary font-normal">(opsional)</span></label>
        <textarea name="keterangan" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">{{ old('keterangan', $akun->keterangan ?? '') }}</textarea>
        @error('keterangan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-2">
        <button type="submit" class="bg-koperasi-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:opacity-90 transition">
            Simpan
        </button>
        <a href="{{ route('koperasi.kas-bank.index') }}" class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
            Batal
        </a>
    </div>
</div>
