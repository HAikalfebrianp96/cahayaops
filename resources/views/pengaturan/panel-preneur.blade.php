{{-- PANEL: PENGATURAN CAHAYA PRENEUR — dipindahkan apa adanya dari
     pengaturan/index.blade.php lama. Form ini TETAP submit ke endpoint
     aslinya (pengaturan.update -> SystemSetting); tidak ada field,
     validasi, atau logic yang diubah. --}}
    <form method="POST" action="{{ route('pengaturan.update') }}" enctype="multipart/form-data" class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 space-y-6">
        @csrf

        <div>
            <h3 class="text-sm font-semibold text-text-main mb-4">1. Profil Sistem</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama Sistem</label>
                    <input type="text" name="nama_sistem" value="{{ old('nama_sistem', $setting->nama_sistem) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('nama_sistem') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama Lembaga</label>
                    <input type="text" name="nama_lembaga" value="{{ old('nama_lembaga', $setting->nama_lembaga) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('nama_lembaga') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Alamat <span class="text-text-secondary">(boleh kosong)</span></label>
                    <textarea name="alamat" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('alamat', $setting->alamat) }}</textarea>
                    @error('alamat') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Kontak <span class="text-text-secondary">(boleh kosong)</span></label>
                    <input type="text" name="kontak" value="{{ old('kontak', $setting->kontak) }}" placeholder="Nomor telepon / email"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('kontak') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Logo Sistem (Umum) <span class="text-text-secondary">(JPG/PNG, boleh kosong)</span></label>
                    @if ($setting->logo_path)
                        <p class="text-xs text-text-secondary mb-1">
                            File saat ini: {{ $setting->logo_asli }} —
                            <a href="{{ route('pengaturan.logo') }}" class="text-status-info hover:underline">Lihat</a>
                        </p>
                    @endif
                    <input type="file" name="logo" accept=".jpg,.jpeg,.png" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('logo') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- LOGO PROGRAM & YBM PLN — tampil langsung di header, terpisah dari Logo Sistem di atas --}}
        <div class="border-t border-border-soft pt-6">
            <h3 class="text-sm font-semibold text-text-main mb-1">2. Logo Program</h3>
            <p class="text-xs text-text-secondary mb-4">Dua logo ini akan tampil langsung di bagian atas halaman Cahaya Preneur.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-2">Logo Cahaya Preneur</label>
                    <div class="h-20 w-20 border border-border-soft rounded-xl flex items-center justify-center mb-2 bg-surface-bg">
                        @if ($setting->logo_program)
                            <img src="{{ asset('images/preneur/' . $setting->logo_program) }}" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-xs text-text-secondary">Belum ada</span>
                        @endif
                    </div>
                    <input type="file" name="logo_program" accept="image/*" class="text-xs w-full">
                    @error('logo_program') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-2">Logo YBM PLN</label>
                    <div class="h-20 w-20 border border-border-soft rounded-xl flex items-center justify-center mb-2 bg-surface-bg">
                        @if ($setting->logo_ybm_pln)
                            <img src="{{ asset('images/preneur/' . $setting->logo_ybm_pln) }}" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-xs text-text-secondary">Belum ada</span>
                        @endif
                    </div>
                    <input type="file" name="logo_ybm_pln" accept="image/*" class="text-xs w-full">
                    @error('logo_ybm_pln') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="border-t border-border-soft pt-6">
            <h3 class="text-sm font-semibold text-text-main mb-4">3. Program</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama Program</label>
                    <input type="text" name="nama_program" value="{{ old('nama_program', $setting->nama_program) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('nama_program') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Periode Program <span class="text-text-secondary">(boleh kosong)</span></label>
                    <input type="text" name="periode_program" value="{{ old('periode_program', $setting->periode_program) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('periode_program') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Tahun <span class="text-text-secondary">(boleh kosong)</span></label>
                    <input type="text" name="tahun_program" value="{{ old('tahun_program', $setting->tahun_program) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('tahun_program') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ============================================================
             AMBANG FOLLOW UP.

             Kebijakan program, bukan aturan teknis: wajar berubah saat
             jumlah binaan per pendamping berubah, atau saat program masuk
             fase berbeda. Ditaruh di sini supaya perubahannya tidak butuh
             deploy. Dikosongkan = kembali ke nilai bawaan sistem.
             ============================================================ --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-1">Ambang Follow Up Pendampingan</h3>
            <p class="text-xs text-text-secondary mb-4">
                Menentukan kapan binaan berpindah dari Hijau &rarr; Kuning &rarr; Merah, dihitung dari
                tanggal pendampingan terakhir. Kosongkan untuk memakai nilai bawaan
                ({{ config('cahayaos.followup.kuning_hari') }} dan {{ config('cahayaos.followup.merah_hari') }} hari).
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">
                        Perlu Perhatian (Kuning) — hari
                    </label>
                    <input type="number" name="followup_kuning_hari" min="1" max="365"
                           value="{{ old('followup_kuning_hari', $setting->followup_kuning_hari) }}"
                           placeholder="{{ config('cahayaos.followup.kuning_hari') }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('followup_kuning_hari') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">
                        Terlambat (Merah) — hari
                    </label>
                    <input type="number" name="followup_merah_hari" min="2" max="365"
                           value="{{ old('followup_merah_hari', $setting->followup_merah_hari) }}"
                           placeholder="{{ config('cahayaos.followup.merah_hari') }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('followup_merah_hari') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ============================================================
             TARGET DEFAULT PENDAMPING.

             Dipakai kalau seorang pendamping BELUM punya target khusus untuk
             periode berjalan. Target individual SELALU menang; ini hanya
             jaring pengaman supaya KPI tidak kosong di awal program.
             ============================================================ --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-1">Target Default Pendamping</h3>
            <p class="text-xs text-text-secondary mb-4">
                Dipakai kalau pendamping belum punya target khusus. Kosongkan untuk memakai
                nilai bawaan sistem. Target individual selalu lebih diutamakan.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([
                    'target_default_binaan' => ['Jumlah Binaan', config('cahayaos.target_default.binaan')],
                    'target_default_kunjungan' => ['Kunjungan Usaha', config('cahayaos.target_default.kunjungan')],
                    'target_default_pendampingan' => ['Pendampingan', config('cahayaos.target_default.pendampingan')],
                    'target_default_follow_up' => ['Follow Up', config('cahayaos.target_default.follow_up')],
                ] as $kolom => [$label, $bawaan])
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">{{ $label }}</label>
                        <input type="number" name="{{ $kolom }}" min="0" max="9999"
                               value="{{ old($kolom, $setting->{$kolom}) }}" placeholder="{{ $bawaan }}"
                               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        @error($kolom) <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ============================================================
             AMBANG KEAKTIFAN PESERTA & BEBAN PENDAMPING.

             Keaktifan peserta SENGAJA terpisah dari ambang follow up
             pendamping di atas: yang itu mengukur kinerja PENDAMPING,
             yang ini mengukur kesehatan PESERTA.
             ============================================================ --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-1">Ambang Keaktifan Peserta &amp; Beban Pendamping</h3>
            <p class="text-xs text-text-secondary mb-4">
                Keaktifan peserta dihitung dari hari sejak aktivitas terakhir. Beban dihitung dari
                jumlah binaan, dipakai Admin untuk menimbang redistribusi peserta.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @foreach ([
                    'peserta_aktif_hari' => ['Aktif s/d (hari)', config('cahayaos.keaktifan_peserta.aktif_hari')],
                    'peserta_perlu_follow_up_hari' => ['Tidak Aktif di atas (hari)', config('cahayaos.keaktifan_peserta.perlu_follow_up_hari')],
                    'beban_ringan_maks' => ['Beban Ringan s/d', config('cahayaos.beban_pendamping.ringan_maks')],
                    'beban_normal_maks' => ['Beban Normal s/d', config('cahayaos.beban_pendamping.normal_maks')],
                    'beban_tinggi_maks' => ['Beban Tinggi s/d', config('cahayaos.beban_pendamping.tinggi_maks')],
                ] as $kolom => [$label, $bawaan])
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">{{ $label }}</label>
                        <input type="number" name="{{ $kolom }}" min="1" max="999"
                               value="{{ old($kolom, $setting->{$kolom}) }}" placeholder="{{ $bawaan }}"
                               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        @error($kolom) <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan Pengaturan
            </button>
        </div>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-4">4. Pengguna</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                        <th class="px-3 py-2.5 font-medium">Nama</th>
                        <th class="px-3 py-2.5 font-medium">Email</th>
                        <th class="px-3 py-2.5 font-medium">Role</th>
                        <th class="px-3 py-2.5 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                            <td class="px-3 py-2.5 font-medium text-text-main">{{ $user->name }}</td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $user->email }}</td>
                            <td class="px-3 py-2.5 text-text-secondary capitalize">{{ $user->role }}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-primary-light text-status-success">Aktif</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
