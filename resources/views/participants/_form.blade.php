@csrf

{{-- ============================================================
     FORM PESERTA / BINAAN — dipakai bersama oleh Tambah & Edit.

     Field Pendamping hanya dirender untuk yang berhak menentukannya
     ($bolehPilihPendamping). Ini SEMATA-MATA tampilan: pengamannya ada di
     ParticipantController::validated(), yang membuang nilai pendamping_id
     dari request siapa pun yang tidak berhak — jadi request manual pun
     tidak bisa memindahkan binaan.
     ============================================================ --}}

<div class="space-y-6">

    {{-- DATA DIRI --}}
    <div>
        <h3 class="text-[13px] font-semibold text-gray-700 mb-3">Data Diri</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Peserta</label>
                <input type="text" name="nama_peserta" value="{{ old('nama_peserta', $participant->nama_peserta ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('nama_peserta') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Identitas (NIK/KTP) <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="text" name="nomor_identitas" value="{{ old('nomor_identitas', $participant->nomor_identitas ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('nomor_identitas') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor HP</label>
                <input type="text" name="nomor_hp" value="{{ old('nomor_hp', $participant->nomor_hp ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('nomor_hp') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="email" name="email" value="{{ old('email', $participant->email ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="alamat" rows="2"
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">{{ old('alamat', $participant->alamat ?? '') }}</textarea>
                @error('alamat') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kecamatan <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="text" name="kecamatan" value="{{ old('kecamatan', $participant->kecamatan ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('kecamatan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kelurahan/Desa <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="text" name="kelurahan" value="{{ old('kelurahan', $participant->kelurahan ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('kelurahan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- DATA USAHA --}}
    <div class="border-t border-gray-100 pt-5">
        <h3 class="text-[13px] font-semibold text-gray-700 mb-3">Data Usaha</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Usaha</label>
                <input type="text" name="nama_usaha" value="{{ old('nama_usaha', $participant->nama_usaha ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('nama_usaha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Usaha <span class="text-gray-400">(boleh kosong, isi kalau berbeda dari alamat tinggal)</span></label>
                <textarea name="alamat_usaha" rows="2"
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">{{ old('alamat_usaha', $participant->alamat_usaha ?? '') }}</textarea>
                @error('alamat_usaha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Usaha</label>
                <select name="kategori_usaha" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">— Pilih Jenis Usaha —</option>
                    @foreach ($kategoriOptions as $kategori)
                        <option value="{{ $kategori }}" @selected(old('kategori_usaha', $participant->kategori_usaha ?? '') === $kategori)>{{ $kategori }}</option>
                    @endforeach
                </select>
                @error('kategori_usaha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Produk <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="text" name="produk" value="{{ old('produk', $participant->produk ?? '') }}"
                       placeholder="Contoh: Keripik pisang, kue kering"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('produk') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Berdiri <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="number" name="tahun_berdiri" min="1900" max="{{ now()->year + 1 }}"
                       value="{{ old('tahun_berdiri', $participant->tahun_berdiri ?? '') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('tahun_berdiri') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status Usaha <span class="text-gray-400">(boleh kosong)</span></label>
                <select name="status_usaha" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">— Belum ditentukan —</option>
                    @foreach ($statusUsahaOptions as $statusUsaha)
                        <option value="{{ $statusUsaha }}" @selected(old('status_usaha', $participant->status_usaha ?? '') === $statusUsaha)>{{ $statusUsaha }}</option>
                    @endforeach
                </select>
                @error('status_usaha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- DATA PROGRAM --}}
    <div class="border-t border-gray-100 pt-5">
        <h3 class="text-[13px] font-semibold text-gray-700 mb-3">Data Program</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai Program</label>
                <input type="date" name="tanggal_mulai_program"
                       value="{{ old('tanggal_mulai_program', isset($participant) ? $participant->tanggal_mulai_program->format('Y-m-d') : now()->format('Y-m-d')) }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('tanggal_mulai_program') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status Peserta</label>
                <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">— Pilih Status —</option>
                    @foreach ($statusOptions as $status)
                        {{-- Nilai status LAMA ('Graduasi'/'Tidak Aktif') ikut
                             dirender kalau peserta ini masih memakainya, supaya
                             membuka form Edit tidak diam-diam mengubah statusnya.
                             Padanan barunya ditulis sebagai keterangan. --}}
                        <option value="{{ $status }}" @selected(old('status', $participant->status ?? 'Terdaftar') === $status)>
                            {{ $status }}@isset($statusLegacy[$status]) (lama — setara "{{ $statusLegacy[$status] }}")@endisset
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Perjalanan peserta: Terdaftar &rarr; Aktif &rarr; Berkembang &rarr; Mandiri &rarr; Alumni.
                    Mengubah status TIDAK menghapus riwayat apa pun.
                </p>
                @error('status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Catatan Internal <span class="text-gray-400">(boleh kosong)</span>
                </label>
                <textarea name="catatan_internal" rows="3"
                          placeholder="Catatan kerja yang membantu pendampingan. Hanya terlihat oleh Admin dan Pendamping peserta ini."
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">{{ old('catatan_internal', $participant->catatan_internal ?? '') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">
                    Tidak pernah tampil di halaman publik maupun laporan yang dibagikan.
                </p>
                @error('catatan_internal') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @if ($bolehPilihPendamping ?? false)
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pendamping <span class="text-gray-400">(boleh dikosongkan)</span></label>
                    <select name="pendamping_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Tanpa Pendamping —</option>
                        @foreach ($pendampings as $p)
                            <option value="{{ $p->id }}" @selected((string) old('pendamping_id', $participant->pendamping_id ?? '') === (string) $p->id)>{{ $p->nama }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Mengganti pilihan di sini akan memindahkan binaan ke Pendamping lain.</p>
                    @error('pendamping_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @else
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pendamping</label>
                    <p class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                        {{ $participant->pendamping->nama ?? auth()->user()->pendamping->nama ?? '—' }}
                        <span class="text-xs text-gray-400 block mt-0.5">Penugasan Pendamping hanya bisa diubah Admin.</span>
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="mt-6 flex gap-2">
    <button type="submit"
            class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
        Simpan
    </button>
    <a href="{{ isset($participant) ? route('participants.show', $participant) : (auth()->user()->isAdmin() ? route('participants.index') : route('binaan.index')) }}"
       class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
        Batal
    </a>
</div>
