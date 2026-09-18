@extends('layouts.app')

@section('title', $periode ? 'Edit Penetapan SHU' : 'Penetapan SHU Baru')

@section('content')
@php
    $aksi = $periode ? route('koperasi.shu.update', $periode) : route('koperasi.shu.store');
    $kelasInput = 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';

    // Baris komponen: input lama (kalau validasi gagal) menang atas data.
    $baris = old('komponen', $komponenAwal);
@endphp

<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">
        {{ $periode ? 'Edit Penetapan SHU ' . $periode->tahun : 'Penetapan SHU Baru' }}
    </h2>
    <p class="text-xs text-text-secondary mb-5">
        Persentase pembagian ditetapkan Rapat Anggota dan disimpan per periode — mengubahnya di sini
        tidak mengubah penetapan tahun-tahun sebelumnya.
    </p>

    <form method="POST" action="{{ $aksi }}" class="space-y-6">
        @csrf
        @if ($periode) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tahun Buku</label>
                <input type="number" name="tahun" required min="2000" max="{{ now()->year + 1 }}"
                       value="{{ old('tahun', $tahun) }}"
                       @if ($periode) readonly @endif
                       class="{{ $kelasInput }} @if ($periode) bg-surface-bg @endif">
                @if ($periode)
                    {{-- Tahun tidak boleh dipindahkan: alokasinya sudah dihitung
                         dari simpanan & transaksi tahun tersebut. --}}
                    <p class="text-[11px] text-text-secondary mt-1">Tahun buku tidak dapat diubah.</p>
                @endif
                @error('tahun') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tanggal Penetapan</label>
                <input type="date" name="tanggal_penetapan" required
                       value="{{ old('tanggal_penetapan', $periode?->tanggal_penetapan?->toDateString() ?? now()->toDateString()) }}"
                       class="{{ $kelasInput }}">
                @error('tanggal_penetapan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Sumber Angka</label>
                <select name="sumber" required class="{{ $kelasInput }}">
                    @foreach (\App\Models\ShuPeriod::SUMBER_OPTIONS as $kode => $label)
                        <option value="{{ $kode }}" @selected(old('sumber', $periode->sumber ?? 'laba_rugi') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('sumber') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Total SHU (Rp)</label>
            <input type="number" name="total_shu" required min="0" step="1000"
                   value="{{ old('total_shu', $periode->total_shu ?? $usulanTotal) }}"
                   class="{{ $kelasInput }}">
            {{-- Angka Laba Rugi ditawarkan sebagai USULAN, bukan dipaksakan.
                 Total SHU final adalah keputusan Rapat Anggota. --}}
            <p class="text-[11px] text-text-secondary mt-1">
                Laba bersih koperasi tahun {{ $tahun }} menurut sistem: <span class="font-medium">{{ rupiah($usulanTotal) }}</span>.
                Angka ini usulan — silakan sesuaikan dengan keputusan Rapat Anggota.
            </p>
            @error('total_shu') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- ============ KOMPONEN ============ --}}
        <div>
            <h3 class="text-sm font-semibold text-text-main mb-1">Komponen Pembagian</h3>
            <p class="text-[11px] text-text-secondary mb-3">
                Komponen yang ditandai &ldquo;dibagikan ke anggota&rdquo; dibagi proporsional menurut dasarnya.
                Simpanan sukarela sengaja TIDAK ikut menjadi dasar jasa modal — sifatnya titipan yang bisa
                ditarik sewaktu-waktu, bukan modal yang disertakan ke koperasi.
            </p>

            <div id="barisKomponen" class="space-y-2">
                @foreach ($baris as $i => $k)
                    <div class="grid grid-cols-12 gap-2 items-start" data-baris>
                        <input type="text" name="komponen[{{ $i }}][nama]" required maxlength="255"
                               value="{{ $k['nama'] ?? '' }}" placeholder="Nama komponen"
                               class="col-span-5 rounded-lg border border-border-soft px-3 py-2 text-sm">
                        <input type="number" name="komponen[{{ $i }}][persen]" required min="0" max="100" step="0.01"
                               value="{{ $k['persen'] ?? 0 }}" placeholder="%"
                               class="col-span-2 rounded-lg border border-border-soft px-3 py-2 text-sm">
                        <select name="komponen[{{ $i }}][dasar]" class="col-span-4 rounded-lg border border-border-soft px-3 py-2 text-sm">
                            <option value="" @selected(empty($k['ke_anggota']))>Dana koperasi</option>
                            <option value="simpanan" @selected(! empty($k['ke_anggota']) && ($k['dasar'] ?? null) === 'simpanan')>
                                Anggota — dasar simpanan
                            </option>
                            <option value="transaksi" @selected(! empty($k['ke_anggota']) && ($k['dasar'] ?? null) === 'transaksi')>
                                Anggota — dasar transaksi
                            </option>
                        </select>
                        {{-- ke_anggota diturunkan dari dasar yang dipilih, supaya
                             tidak mungkin ada komponen "ke anggota" tanpa dasar
                             pembagian — kombinasi yang akan menghasilkan pool
                             yang tidak pernah terbagi ke siapa pun. --}}
                        <input type="hidden" name="komponen[{{ $i }}][ke_anggota]"
                               value="{{ ! empty($k['ke_anggota']) ? 1 : 0 }}" data-ke-anggota>
                        <button type="button" class="col-span-1 text-status-danger text-sm py-2" data-hapus>&times;</button>
                    </div>
                @endforeach
            </div>

            @error('komponen') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            @foreach ($errors->get('komponen.*') as $pesan)
                <p class="text-xs text-status-danger mt-1">{{ $pesan[0] }}</p>
            @endforeach

            <div class="flex items-center justify-between mt-3">
                <button type="button" id="tambahKomponen" class="text-sm text-cahaya-secondary hover:underline">+ Tambah komponen</button>
                <p class="text-xs text-text-secondary">Total: <span id="totalPersen" class="font-medium">0</span>%</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2" class="{{ $kelasInput }}">{{ old('catatan', $periode->catatan ?? '') }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan &amp; Susun Alokasi
            </button>
            <a href="{{ $periode ? route('koperasi.shu.show', $periode) : route('koperasi.shu.index') }}"
               class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">Batal</a>
        </div>
    </form>
</div>

{{--
    Baris komponen dinamis + total persen hidup.

    Semuanya urusan tampilan. Server tetap memvalidasi tiap baris sendiri,
    dan total persen yang bukan 100 SENGAJA tidak ditolak — pembagian SHU
    adalah keputusan Rapat Anggota, dan sistem hanya memperingatkan
    (lihat ShuService::peringatan) supaya yang mengesahkan melihat apa yang
    sedang ia sahkan.
--}}
<script>
(function () {
    const wadah = document.getElementById('barisKomponen');
    const tombolTambah = document.getElementById('tambahKomponen');
    const totalEl = document.getElementById('totalPersen');
    if (!wadah) return;

    let urut = wadah.querySelectorAll('[data-baris]').length;

    function hitungTotal() {
        let total = 0;
        wadah.querySelectorAll('input[name$="[persen]"]').forEach((i) => {
            total += parseFloat(i.value || '0') || 0;
        });
        totalEl.textContent = Number(total.toFixed(2)).toLocaleString('id-ID');
        totalEl.classList.toggle('text-status-danger', Math.abs(total - 100) > 0.01);
    }

    // ke_anggota selalu mengikuti dasar yang dipilih — tidak ada kombinasi
    // "ke anggota tanpa dasar" yang bisa lolos dari form ini.
    function selaraskan(baris) {
        const dasar = baris.querySelector('select');
        const tersembunyi = baris.querySelector('[data-ke-anggota]');
        if (dasar && tersembunyi) tersembunyi.value = dasar.value ? 1 : 0;
    }

    wadah.addEventListener('input', hitungTotal);
    wadah.addEventListener('change', (e) => {
        const baris = e.target.closest('[data-baris]');
        if (baris) selaraskan(baris);
    });
    wadah.addEventListener('click', (e) => {
        if (!e.target.matches('[data-hapus]')) return;
        // Baris terakhir tidak boleh dihapus: validasi server mewajibkan
        // minimal satu komponen, dan form kosong hanya akan ditolak.
        if (wadah.querySelectorAll('[data-baris]').length <= 1) return;
        e.target.closest('[data-baris]').remove();
        hitungTotal();
    });

    tombolTambah.addEventListener('click', () => {
        const contoh = wadah.querySelector('[data-baris]');
        const baru = contoh.cloneNode(true);
        baru.querySelectorAll('input, select').forEach((el) => {
            el.name = el.name.replace(/komponen\[\d+\]/, 'komponen[' + urut + ']');
            if (el.tagName === 'SELECT') el.value = '';
            else if (el.type === 'hidden') el.value = 0;
            else el.value = el.name.endsWith('[persen]') ? 0 : '';
        });
        urut++;
        wadah.appendChild(baru);
        hitungTotal();
    });

    wadah.querySelectorAll('[data-baris]').forEach(selaraskan);
    hitungTotal();
})();
</script>
@endsection
