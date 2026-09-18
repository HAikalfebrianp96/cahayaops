@csrf

{{-- ============================================================
     FORM TARGET PENDAMPING — dipakai bersama Tambah & Edit.

     SELURUH indikator boleh dikosongkan. Yang kosong TIDAK ikut dihitung ke
     KPI — "tidak ditargetkan" berbeda dari "ditargetkan tapi belum tercapai".
     Memaksa semuanya terisi hanya membuat Admin mengarang angka supaya
     formnya bisa disimpan.
     ============================================================ --}}

<div class="space-y-6">

    <div>
        <h3 class="text-[13px] font-semibold text-text-main mb-3">Periode Target</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-text-main mb-1">Pendamping</label>
                <select name="pendamping_id" class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm">
                    <option value="">— Pilih Pendamping —</option>
                    @foreach ($pendampings as $p)
                        <option value="{{ $p->id }}"
                            @selected((string) old('pendamping_id', $target->pendamping_id ?? request('pendamping_id')) === (string) $p->id)>
                            {{ $p->nama }}
                        </option>
                    @endforeach
                </select>
                @error('pendamping_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main mb-1">Periode</label>
                <select name="periode" id="periodeTarget" class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm">
                    @foreach ($periodeOptions as $p)
                        <option value="{{ $p }}" @selected(old('periode', $target->periode ?? 'Bulanan') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
                @error('periode') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main mb-1">Tahun</label>
                <select name="tahun" class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm">
                    @foreach ($tahunOptions as $t)
                        <option value="{{ $t }}" @selected((int) old('tahun', $target->tahun ?? now()->year) === $t)>{{ $t }}</option>
                    @endforeach
                </select>
                @error('tahun') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Bulan disembunyikan saat periode Tahunan: mengisinya di sana
                 tidak punya arti dan justru membuat rentang periodenya salah. --}}
            <div id="kolomBulan">
                <label class="block text-sm font-medium text-text-main mb-1">Bulan</label>
                <select name="bulan" class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm">
                    @foreach (\App\Models\OmzetReport::BULAN as $nomor => $nama)
                        <option value="{{ $nomor }}" @selected((int) old('bulan', $target->bulan ?? now()->month) === $nomor)>{{ $nama }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-text-secondary mt-1" id="petunjukBulan"></p>
                @error('bulan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- ============================================================
         INDIKATOR TARGET — DIKELOMPOKKAN

         Delapan kotak isian yang berjajar sama rata membuat Admin menduga
         kedelapannya wajib diisi, dan pendamping menduga kedelapannya
         sama-sama dinilai. Padahal ketiga kelompok ini berbeda jenis, dan
         bedanya menentukan apakah seseorang dinilai atas pekerjaan yang
         memang tanggung jawabnya.

         Perhitungannya TIDAK berubah: indikator kosong tetap tidak dihitung,
         apa pun kelompoknya. Yang berubah hanya urutan, judul, dan
         penjelasannya.
         ============================================================ --}}
    <div class="border-t border-border-soft pt-5 space-y-6">
        <div>
            <h3 class="text-[13px] font-semibold text-text-main mb-1">Indikator Target</h3>
            <p class="text-xs text-text-secondary">
                Isi HANYA indikator yang memang menjadi tanggung jawab pendamping pada periode ini.
                Yang dikosongkan berstatus <span class="font-medium">Tidak ditargetkan</span> dan tidak ikut dihitung ke skor KPI.
            </p>
        </div>

        @foreach (\App\Models\PendampingTarget::KELOMPOK as $kelompok => $kolomKelompok)
            <div>
                <div class="flex flex-wrap items-baseline gap-2 mb-1">
                    <h4 class="text-[13px] font-semibold text-text-main">
                        {{ \App\Models\PendampingTarget::KELOMPOK_JUDUL[$kelompok] }}
                    </h4>
                    @if ($kelompok === \App\Models\PendampingTarget::KELOMPOK_BEBAN)
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-surface-bg text-text-secondary">
                            bukan penilaian kinerja
                        </span>
                    @endif
                </div>
                <p class="text-xs text-text-secondary mb-3">
                    {{ \App\Models\PendampingTarget::KELOMPOK_KETERANGAN[$kelompok] }}
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($kolomKelompok as $kolom)
                        @continue (! array_key_exists($kolom, $indikator))
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">{{ $indikator[$kolom] }}</label>
                            <input type="number" name="{{ $kolom }}" min="0" max="9999"
                                   value="{{ old($kolom, $target->{$kolom} ?? '') }}"
                                   placeholder="Tidak ditargetkan"
                                   class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                            @error($kolom) <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Penjaga tampilan: indikator yang belum terdaftar di KELOMPOK tetap
             muncul, supaya menambah indikator baru tidak membuatnya diam-diam
             hilang dari formulir. --}}
        @php
            $terkelompok = array_merge(...array_values(\App\Models\PendampingTarget::KELOMPOK));
            $belum = array_diff(array_keys($indikator), $terkelompok);
        @endphp
        @if ($belum !== [])
            <div>
                <h4 class="text-[13px] font-semibold text-text-main mb-3">Lainnya</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($belum as $kolom)
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">{{ $indikator[$kolom] }}</label>
                            <input type="number" name="{{ $kolom }}" min="0" max="9999"
                                   value="{{ old($kolom, $target->{$kolom} ?? '') }}"
                                   placeholder="Tidak ditargetkan"
                                   class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                            @error($kolom) <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="border-t border-border-soft pt-5">
        <label class="block text-sm font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
        <textarea name="catatan" rows="3"
                  placeholder="Contoh: target diturunkan karena pendamping merangkap tugas lain."
                  class="w-full rounded-xl border border-border-soft bg-white px-3 py-2.5 text-sm">{{ old('catatan', $target->catatan ?? '') }}</textarea>
        @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex gap-2">
    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
        Simpan Target
    </button>
    <a href="{{ route('target-pendamping.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
        Batal
    </a>
</div>

<script>
    (function () {
        const periode = document.getElementById('periodeTarget');
        const kolomBulan = document.getElementById('kolomBulan');
        const petunjuk = document.getElementById('petunjukBulan');
        if (!periode || !kolomBulan) return;

        function sesuaikan() {
            const nilai = periode.value;
            kolomBulan.classList.toggle('hidden', nilai === 'Tahunan');

            petunjuk.textContent = nilai === 'Triwulan'
                ? 'Pilih bulan mana pun dalam triwulannya — sistem menyimpannya sebagai bulan awal triwulan.'
                : '';
        }

        periode.addEventListener('change', sesuaikan);
        sesuaikan();
    })();
</script>
