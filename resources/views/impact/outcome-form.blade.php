@extends('layouts.app')

@section('title', $outcome ? 'Edit Outcome' : 'Tambah Outcome')

@section('content')
@php
    $aksi = $outcome ? route('impact.outcome.update', $outcome) : route('impact.outcome.store', $program);
    $nilai = fn (string $k, $bawaan = null) => old($k, $outcome->{$k} ?? $bawaan);
    $kelasInput = 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';
    $sumberTerpilih = $nilai('sumber_nilai', \App\Models\ImpactOutcome::SUMBER_OTOMATIS);

    // Tri-state displacement: '' belum ditentukan, '1' relevan, '0' tidak.
    $relevanLama = old('displacement_relevan', $outcome->displacement_relevan ?? null);
    $triStateDisplacement = $relevanLama === null || $relevanLama === '' ? '' : ($relevanLama ? '1' : '0');
@endphp

<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">{{ $outcome ? 'Edit Outcome' : 'Tambah Outcome' }}</h2>
    <p class="text-xs text-text-secondary mb-5">Program: {{ $program->nama }}</p>

    @if ($proxies->isEmpty())
        {{-- Tanpa proxy, outcome apa pun tidak akan punya nilai rupiah dan
             SROI tetap menolak dihitung. Lebih baik dikatakan sekarang. --}}
        <div class="rounded-xl bg-gold-light border border-status-warning/40 px-4 py-3 mb-5">
            <p class="text-sm font-semibold text-cahaya-primary">Belum ada proxy finansial</p>
            <p class="text-xs text-text-secondary mt-1">
                Outcome tanpa proxy tidak punya nilai rupiah, dan SROI akan tetap menolak dihitung.
                <a href="{{ route('impact.proxy.create') }}" class="text-cahaya-secondary hover:underline">Tambahkan proxy dulu →</a>
            </p>
        </div>
    @endif

    <form method="POST" action="{{ $aksi }}" class="space-y-6">
        @csrf
        @if ($outcome) @method('PUT') @endif

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Nama Outcome</label>
            <input type="text" name="nama" required maxlength="255" value="{{ $nilai('nama') }}"
                   placeholder="Mis. Pendapatan usaha peserta meningkat" class="{{ $kelasInput }}">
            @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- ============ SUMBER ANGKA ============ --}}
        <div>
            <h3 class="text-sm font-semibold text-text-main mb-3">Sumber Angka Perubahan</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Sumber</label>
                    <select name="sumber_nilai" required class="{{ $kelasInput }}" data-outcome="sumber">
                        @foreach (\App\Models\ImpactOutcome::SUMBER_OPTIONS as $kode => $label)
                            <option value="{{ $kode }}" @selected($sumberTerpilih === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('sumber_nilai') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div data-outcome="blok-otomatis">
                    <label class="block text-xs font-medium text-text-main mb-1">Indikator Pengukuran</label>
                    <select name="indikator" class="{{ $kelasInput }}">
                        <option value="">— pilih indikator —</option>
                        @foreach ($indikatorPilihan as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('indikator') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-text-secondary mt-1">
                        Diambil otomatis dari baseline &amp; endline peserta. Indikator keuangan disetahunkan
                        lebih dulu, karena proxy hampir selalu dinyatakan per tahun.
                    </p>
                    @error('indikator') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div data-outcome="blok-manual">
                    <label class="block text-xs font-medium text-text-main mb-1">Nilai Perubahan</label>
                    <input type="number" name="nilai_perubahan_manual" value="{{ $nilai('nilai_perubahan_manual') }}"
                           class="{{ $kelasInput }}">
                    @error('nilai_perubahan_manual') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div data-outcome="blok-manual">
                    <label class="block text-xs font-medium text-text-main mb-1">Jumlah Penerima</label>
                    <input type="number" name="jumlah_penerima_manual" min="0" value="{{ $nilai('jumlah_penerima_manual') }}"
                           class="{{ $kelasInput }}">
                    @error('jumlah_penerima_manual') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-text-main mb-1">Proxy Finansial</label>
                    <select name="financial_proxy_id" class="{{ $kelasInput }}">
                        <option value="">— belum ditentukan —</option>
                        @foreach ($proxies as $px)
                            <option value="{{ $px->id }}" @selected((int) $nilai('financial_proxy_id') === $px->id)>
                                {{ $px->nama }} — {{ $px->ringkas() }}
                            </option>
                        @endforeach
                    </select>
                    @error('financial_proxy_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ============ FAKTOR PENYESUAI ============ --}}
        <div>
            <h3 class="text-sm font-semibold text-text-main mb-1">Faktor Penyesuai</h3>
            <p class="text-[11px] text-text-secondary mb-3">
                Inti kejujuran SROI. Tanpa keempatnya, seluruh perubahan diklaim milik program —
                padahal sebagian akan tetap terjadi tanpa program, sebagian milik pihak lain yang juga
                mendampingi, dan sebagian hanya memindahkan manfaat dari usaha sebelah.
            </p>

            {{-- DEADWEIGHT --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Deadweight (%)</label>
                    <input type="number" name="deadweight_persen" required min="0" max="100" step="0.01"
                           value="{{ $nilai('deadweight_persen', 0) }}" class="{{ $kelasInput }}">
                    <p class="text-[11px] text-text-secondary mt-1">
                        Berapa persen perubahan ini tetap terjadi seandainya program tidak ada.
                    </p>
                    @error('deadweight_persen') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-text-main mb-1">
                        Dasar angka deadweight <span class="text-text-secondary font-normal">(disarankan)</span>
                    </label>
                    <textarea name="deadweight_catatan" rows="2" class="{{ $kelasInput }}"
                              placeholder="Dari mana asumsi ini diambil? Mis. tren UMKM sejenis, data pembanding, penilaian pendamping.">{{ $nilai('deadweight_catatan') }}</textarea>
                    @error('deadweight_catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ATTRIBUTION --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Attribution (%)</label>
                    <input type="number" name="attribution_persen" required min="0" max="100" step="0.01"
                           value="{{ $nilai('attribution_persen', 100) }}" class="{{ $kelasInput }}">
                    <p class="text-[11px] text-text-secondary mt-1">
                        Berapa persen perubahan yang benar-benar milik program ini.
                    </p>
                    @error('attribution_persen') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-text-main mb-1">
                        Siapa lagi yang berkontribusi?
                        <span class="text-text-secondary font-normal">(wajib bila di bawah 100%)</span>
                    </label>
                    <textarea name="attribution_catatan" rows="2" class="{{ $kelasInput }}"
                              placeholder="Mis. usaha peserta sendiri, dukungan keluarga, program lain, kondisi pasar yang membaik.">{{ $nilai('attribution_catatan') }}</textarea>
                    <p class="text-[11px] text-text-secondary mt-1">
                        Attribution 70% berarti 30% diakui milik pihak lain. Tanpa menyebut pihak lain itu siapa,
                        angka 70 tidak bisa diperiksa siapa pun.
                    </p>
                    @error('attribution_catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{--
                DISPLACEMENT — tiga keadaan, bukan dua.

                Angka 0 saja memuat tiga arti yang sangat berbeda: sudah
                diperiksa dan memang nol, tidak relevan untuk outcome jenis
                ini, atau belum pernah dipikirkan. Yang ketiga yang berbahaya,
                dan justru yang paling mungkin terjadi.
            --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Displacement</label>
                    <select name="displacement_relevan" class="{{ $kelasInput }}" data-outcome="displacement">
                        <option value="" @selected($triStateDisplacement === '')>Belum ditentukan</option>
                        <option value="1" @selected($triStateDisplacement === '1')>Relevan — ada perpindahan manfaat</option>
                        <option value="0" @selected($triStateDisplacement === '0')>Tidak relevan</option>
                    </select>
                    @error('displacement_relevan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div data-outcome="displacement-persen">
                    <label class="block text-xs font-medium text-text-main mb-1">Displacement (%)</label>
                    <input type="number" name="displacement_persen" required min="0" max="100" step="0.01"
                           value="{{ $nilai('displacement_persen', 0) }}" class="{{ $kelasInput }}">
                    <p class="text-[11px] text-text-secondary mt-1">
                        Berapa persen yang hanya memindahkan manfaat dari pihak lain.
                    </p>
                    @error('displacement_persen') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">
                        Alasan <span class="text-text-secondary font-normal">(wajib bila tidak relevan)</span>
                    </label>
                    <textarea name="displacement_catatan" rows="2" class="{{ $kelasInput }}"
                              placeholder="Mis. produk peserta tidak bersaing dengan usaha lain di wilayah yang sama.">{{ $nilai('displacement_catatan') }}</textarea>
                    @error('displacement_catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- DURASI & DROP-OFF --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Durasi Manfaat (tahun)</label>
                    <input type="number" name="durasi_tahun" required min="1" max="10"
                           value="{{ $nilai('durasi_tahun', 1) }}" class="{{ $kelasInput }}" data-outcome="durasi">
                    <p class="text-[11px] text-text-secondary mt-1">1 = hanya tahun berjalan.</p>
                    @error('durasi_tahun') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div data-outcome="blok-dropoff">
                    <label class="block text-xs font-medium text-text-main mb-1">Drop-off (%) per tahun</label>
                    <input type="number" name="drop_off_persen" required min="0" max="100" step="0.01"
                           value="{{ $nilai('drop_off_persen', 0) }}" class="{{ $kelasInput }}">
                    <p class="text-[11px] text-text-secondary mt-1">
                        Berapa persen manfaat luruh tiap tahun berikutnya. Tahun pertama tidak terkena.
                    </p>
                    @error('drop_off_persen') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Satuan <span class="text-text-secondary font-normal">(opsional)</span></label>
                    <input type="text" name="satuan" maxlength="255" value="{{ $nilai('satuan') }}" class="{{ $kelasInput }}">
                    @error('satuan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ============ BUKTI ============ --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Bukti / Sumber Angka</label>
                <textarea name="bukti" rows="3" class="{{ $kelasInput }}"
                          placeholder="Mis. laporan omzet peserta periode Jan–Des, hasil monitoring lapangan">{{ $nilai('bukti') }}</textarea>
                <p class="text-[11px] text-text-secondary mt-1">
                    Wajib untuk outcome manual — angka yang tidak berasal dari data operasional sistem
                    harus bisa ditelusuri, atau ia tidak layak masuk laporan dampak.
                </p>
                @error('bukti') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Deskripsi <span class="text-text-secondary font-normal">(opsional)</span></label>
                <textarea name="deskripsi" rows="3" class="{{ $kelasInput }}">{{ $nilai('deskripsi') }}</textarea>
                @error('deskripsi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2" class="{{ $kelasInput }}">{{ $nilai('catatan') }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('impact.show', $program) }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">Batal</a>
        </div>
    </form>
</div>

{{--
    Menyembunyikan blok yang tidak berlaku untuk mode yang dipilih.

    Ini SEMATA-MATA kerapian tampilan. Aturan sebenarnya ada di
    ImpactOutcomeController::validasi, yang mewajibkan indikator untuk mode
    otomatis dan nilai+bukti untuk mode manual, lalu MENGOSONGKAN kolom mode
    sebelahnya. Kalau JavaScript mati, seluruh kolom terlihat dan formnya
    tetap tersimpan benar.
--}}
<script>
(function () {
    const sumber = document.querySelector('[data-outcome="sumber"]');
    if (!sumber) return;

    function segarkan() {
        const otomatis = sumber.value === 'otomatis';
        document.querySelectorAll('[data-outcome="blok-otomatis"]')
            .forEach((el) => el.classList.toggle('hidden', !otomatis));
        document.querySelectorAll('[data-outcome="blok-manual"]')
            .forEach((el) => el.classList.toggle('hidden', otomatis));
    }

    sumber.addEventListener('change', segarkan);
    segarkan();
})();

/**
 * Menyembunyikan kolom yang TIDAK BERLAKU untuk pilihan yang dibuat.
 *
 * Murni kerapian tampilan. Server tetap menolak kombinasi yang tidak sah dan
 * menolkan drop-off untuk durasi satu tahun — jadi kalau JavaScript mati,
 * seluruh kolom terlihat dan formnya tetap tersimpan benar.
 */
(function () {
    const displacement = document.querySelector('[data-outcome="displacement"]');
    const durasi = document.querySelector('[data-outcome="durasi"]');
    if (!displacement || !durasi) return;

    const blokPersen = document.querySelector('[data-outcome="displacement-persen"]');
    const blokDropOff = document.querySelector('[data-outcome="blok-dropoff"]');

    function segarkan() {
        // Persentase displacement hanya berarti kalau displacement-nya relevan.
        blokPersen.classList.toggle('hidden', displacement.value === '0');
        // Drop-off hanya berarti untuk manfaat lebih dari satu tahun.
        blokDropOff.classList.toggle('hidden', (parseInt(durasi.value, 10) || 1) <= 1);
    }

    displacement.addEventListener('change', segarkan);
    durasi.addEventListener('input', segarkan);
    segarkan();
})();
</script>
@endsection
