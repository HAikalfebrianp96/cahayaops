{{-- PANEL: PENGATURAN KOPERASI CAHAYA — dipindahkan apa adanya dari
     koperasi/pengaturan/index.blade.php. Form ini TETAP submit ke
     endpoint aslinya (koperasi.pengaturan.update -> KoperasiSetting);
     tidak ada field, validasi, atau logic yang diubah. --}}
    <form method="POST" action="{{ route('koperasi.pengaturan.update') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        {{-- IDENTITAS --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-4">Identitas Koperasi</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama</label>
                    <input type="text" name="nama" value="{{ old('nama', $setting->nama) }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Subtitle</label>
                    <input type="text" name="subtitle" value="{{ old('subtitle', $setting->subtitle) }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Tagline</label>
                    <input type="text" name="tagline" value="{{ old('tagline', $setting->tagline) }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                </div>
            </div>
        </div>

        {{-- LOGO --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-4">Logo</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach ([
                    'logo_koperasi' => 'Logo Koperasi Cahaya',
                    'logo_cahayaos' => 'Logo CahayaOS',
                    'logo_ybm_pln' => 'Logo YBM PLN',
                ] as $field => $label)
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-2">{{ $label }}</label>
                        <div class="h-20 w-20 border border-border-soft rounded-xl flex items-center justify-center mb-2 bg-surface-bg">
                            @if ($setting->{$field})
                                <img src="{{ asset('images/koperasi/' . $setting->{$field}) }}" class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-xs text-text-secondary">Belum ada</span>
                            @endif
                        </div>
                        <input type="file" name="{{ $field }}" accept="image/*" class="text-xs w-full">
                        @error($field) <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        {{-- DOKUMEN: TTD & STEMPEL --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-4">Dokumen — Tanda Tangan &amp; Stempel</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-2">Tanda Tangan</label>
                    <div class="h-20 w-32 border border-border-soft rounded-xl flex items-center justify-center mb-2 bg-surface-bg">
                        @if ($setting->ttd_koperasi)
                            <img src="{{ asset('images/koperasi/' . $setting->ttd_koperasi) }}" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-xs text-text-secondary">Belum ada</span>
                        @endif
                    </div>
                    <input type="file" name="ttd_koperasi" accept="image/*" class="text-xs w-full">
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-2">Stempel</label>
                    <div class="h-20 w-32 border border-border-soft rounded-xl flex items-center justify-center mb-2 bg-surface-bg">
                        @if ($setting->stempel_koperasi)
                            <img src="{{ asset('images/koperasi/' . $setting->stempel_koperasi) }}" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-xs text-text-secondary">Belum ada</span>
                        @endif
                    </div>
                    <input type="file" name="stempel_koperasi" accept="image/*" class="text-xs w-full">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama Penandatangan</label>
                    <input type="text" name="nama_penandatangan" value="{{ old('nama_penandatangan', $setting->nama_penandatangan) }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Jabatan</label>
                    <input type="text" name="jabatan_penandatangan" value="{{ old('jabatan_penandatangan', $setting->jabatan_penandatangan) }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                </div>
            </div>
        </div>

        {{-- INVOICE --}}
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-sm font-semibold text-text-main mb-4">Invoice</h3>
            <div class="grid grid-cols-1 gap-4">
                <div class="max-w-xs">
                    <label class="block text-sm font-medium text-text-main mb-1">Prefix Nomor Invoice</label>
                    <input type="text" name="prefix_invoice" value="{{ old('prefix_invoice', $setting->prefix_invoice) }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    <p class="text-xs text-text-secondary mt-1">Contoh: INV → jadi INV-202608-000001</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Footer Invoice</label>
                    <textarea name="footer_invoice" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('footer_invoice', $setting->footer_invoice) }}</textarea>
                </div>
            </div>
        </div>

        {{-- koperasi.pengaturan.update TETAP Admin-only. Untuk non-Admin
             tombolnya dihilangkan supaya tidak jadi tombol yang selalu 403,
             dan diberi keterangan singkat agar isian di atas tidak disangka
             bisa disimpan. Otorisasi backend TIDAK bergantung pada ini —
             ia tetap menolak walau tombolnya dipaksa muncul.
             Default true supaya partial ini aman kalau di-include tanpa
             variabel tersebut. --}}
        @if ($bolehUbahKoperasi ?? true)
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan Pengaturan
            </button>
        @else
            <p class="text-xs text-text-secondary">
                Hanya dapat dilihat. Perubahan pengaturan Koperasi dilakukan oleh Admin.
            </p>
        @endif
    </form>
