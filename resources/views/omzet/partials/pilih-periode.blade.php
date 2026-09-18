{{--
    PEMILIH PERIODE — Bulanan / Mingguan / Harian.

    Dipakai BERSAMA form internal dan form publik, sehingga aturan yang
    terlihat di layar tidak pernah berbeda dari aturan yang dijalankan server
    (MenentukanPeriodeOmzet).

    Parameter:
      $jenisOptions  daftar mode
      $publik        true -> gaya input besar untuk ponsel
--}}
@php
    $publik = $publik ?? false;

    $kelasInput = $publik
        ? 'w-full rounded-xl border border-gray-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-cahaya-secondary'
        : 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';

    $kelasLabel = $publik
        ? 'block text-sm font-semibold text-gray-700 mb-1'
        : 'block text-xs font-medium text-text-main mb-1';

    $kelasBantuan = $publik ? 'text-xs text-gray-400 mt-1' : 'text-[11px] text-text-secondary mt-1';
    $kelasError = $publik ? 'text-xs text-red-600 mt-1' : 'text-xs text-status-danger mt-1';

    $jenisTerpilih = old('jenis_periode', \App\Models\OmzetReport::BULANAN);
@endphp

<div>
    <label class="{{ $kelasLabel }}">Cara Pencatatan</label>
    <select name="jenis_periode" required class="{{ $kelasInput }}" data-periode="jenis">
        @foreach ($jenisOptions as $kode => $label)
            <option value="{{ $kode }}" @selected($jenisTerpilih === $kode)>{{ $label }}</option>
        @endforeach
    </select>
    {{--
        Aturan satu-bulan-satu-mode dijelaskan DI SINI, di tempat pilihannya
        dibuat — bukan hanya sebagai pesan kesalahan setelah formnya ditolak.
        Peserta yang sudah mengetik seluruh angkanya lalu ditolak akan
        menyerah; yang membacanya lebih dulu tidak akan salah pilih.
    --}}
    <p class="{{ $kelasBantuan }}">
        Satu bulan hanya boleh memakai satu cara pencatatan, supaya omzetnya tidak terhitung dua kali.
    </p>
    @error('jenis_periode') <p class="{{ $kelasError }}">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="{{ $kelasLabel }}">Bulan</label>
        <select name="periode_bulan" required class="{{ $kelasInput }}" data-periode="bulan">
            @foreach ($bulanOptions as $nomor => $nama)
                <option value="{{ $nomor }}" @selected((int) old('periode_bulan', now()->month) === $nomor)>{{ $nama }}</option>
            @endforeach
        </select>
        @error('periode_bulan') <p class="{{ $kelasError }}">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $kelasLabel }}">Tahun</label>
        @if (isset($tahunOptions))
            <select name="periode_tahun" required class="{{ $kelasInput }}" data-periode="tahun">
                @foreach ($tahunOptions as $tahun)
                    <option value="{{ $tahun }}" @selected((int) old('periode_tahun', now()->year) === $tahun)>{{ $tahun }}</option>
                @endforeach
            </select>
        @else
            <input type="number" name="periode_tahun" required min="2000" max="{{ now()->year + 1 }}"
                   value="{{ old('periode_tahun', now()->year) }}" class="{{ $kelasInput }}" data-periode="tahun">
        @endif
        @error('periode_tahun') <p class="{{ $kelasError }}">{{ $message }}</p> @enderror
    </div>
</div>

{{--
    Nomor periode. Disembunyikan untuk mode Bulanan — bulan utuh tidak punya
    nomor urut di dalamnya. Server tetap memaksa nilainya 0 untuk mode itu,
    jadi menyembunyikannya di sini murni kerapian, bukan pengaman.
--}}
<div data-periode="blok-urut" class="{{ $jenisTerpilih === \App\Models\OmzetReport::BULANAN ? 'hidden' : '' }}">
    <label class="{{ $kelasLabel }}" data-periode="label-urut">Periode</label>
    <select name="periode_urut" class="{{ $kelasInput }}" data-periode="urut"
            data-terpilih="{{ old('periode_urut', 1) }}">
        {{-- Diisi skrip di bawah sesuai mode & jumlah hari bulan terpilih. --}}
    </select>
    <p class="{{ $kelasBantuan }}" data-periode="bantuan-urut"></p>
    @error('periode_urut') <p class="{{ $kelasError }}">{{ $message }}</p> @enderror
</div>

<script>
/**
 * Pilihan nomor periode mengikuti mode DAN bulan yang dipilih.
 *
 * Februari 2027 tidak punya tanggal 29 dan tidak punya minggu ke-5, jadi
 * daftarnya disusun ulang setiap kali mode/bulan/tahun berubah. Ini cerminan
 * dari aturan yang sama di server (OmzetReport::urutMaksimal) — kalau
 * JavaScript mati, server tetap menolak nilai yang di luar jangkauan.
 */
(function () {
    const jenisEl = document.querySelector('[data-periode="jenis"]');
    if (!jenisEl) return;

    const form = jenisEl.closest('form');
    const el = (n) => form.querySelector('[data-periode="' + n + '"]');
    const urutEl = el('urut');
    const blokEl = el('blok-urut');
    const NAMA_BULAN = @json(array_values($bulanOptions));

    function jumlahHari(bulan, tahun) {
        // Hari ke-0 bulan berikutnya = hari terakhir bulan ini. Ikut
        // memperhitungkan tahun kabisat tanpa aturan tambahan.
        return new Date(tahun, bulan, 0).getDate();
    }

    function segarkan() {
        const jenis = jenisEl.value;
        const bulan = parseInt(el('bulan').value, 10) || 1;
        const tahun = parseInt(el('tahun').value, 10) || new Date().getFullYear();
        const bulanan = jenis === 'Bulanan';

        blokEl.classList.toggle('hidden', bulanan);
        if (bulanan) return;

        const hari = jumlahHari(bulan, tahun);
        const harian = jenis === 'Harian';
        const maks = harian ? hari : Math.ceil(hari / 7);

        el('label-urut').textContent = harian ? 'Tanggal' : 'Minggu ke-';
        el('bantuan-urut').textContent = harian
            ? 'Pilih tanggal yang omzetnya dilaporkan.'
            : 'Minggu ke-1 adalah tanggal 1-7, minggu ke-2 tanggal 8-14, dan seterusnya.';

        // Pilihan sebelumnya dipertahankan kalau masih masuk akal di bulan
        // yang baru — mengubah bulan tidak seharusnya mereset tanggal.
        const sebelumnya = parseInt(urutEl.value || urutEl.dataset.terpilih, 10) || 1;
        urutEl.innerHTML = '';

        for (let i = 1; i <= maks; i++) {
            const opsi = document.createElement('option');
            opsi.value = i;
            opsi.textContent = harian
                ? String(i).padStart(2, '0') + ' ' + NAMA_BULAN[bulan - 1] + ' ' + tahun
                : 'Minggu ke-' + i + ' (' + ((i - 1) * 7 + 1) + '-' + Math.min(i * 7, hari) + ' ' + NAMA_BULAN[bulan - 1] + ')';
            urutEl.appendChild(opsi);
        }

        urutEl.value = sebelumnya <= maks ? sebelumnya : maks;
    }

    [jenisEl, el('bulan'), el('tahun')].forEach((i) => i && i.addEventListener('change', segarkan));
    segarkan();
})();
</script>
