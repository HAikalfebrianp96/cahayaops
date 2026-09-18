{{--
    PERKIRAAN LABA DI LAYAR — dipakai BERSAMA form internal & form publik.

    Ini murni bantuan hitung. TIDAK ADA input tersembunyi: yang dikirim ke
    server tetap hanya omzet, hpp, dan biaya_operasional. Laba dihitung ulang
    di server dari ketiganya (OmzetReport::profitBersih), jadi angka di layar
    tidak bisa dipakai menyelundupkan laba karangan.

    Ditulis polos tanpa kerangka kerja apa pun: form publik dibuka peserta di
    ponsel lapangan dengan jaringan seadanya, dan satu berkas JS tambahan
    adalah satu alasan lagi halamannya gagal terbuka.
--}}
<script>
(function () {
    const panel = document.querySelector('[data-laba="panel"]');
    if (!panel) return;
    const form = panel.closest('form');
    if (!form) return;

    const el = (n) => form.querySelector('[data-laba="' + n + '"]');
    const omzetEl = form.querySelector('[name="omzet"]');
    const hppEl = form.querySelector('[name="hpp"]');
    const biayaEl = form.querySelector('[name="biaya_operasional"]');
    const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    function hitung() {
        const omzet = parseInt(omzetEl && omzetEl.value, 10);
        const hppRaw = (hppEl && hppEl.value || '').trim();

        // HPP kosong berarti BELUM DILAPORKAN, bukan nol. Panelnya
        // disembunyikan daripada menampilkan laba yang seolah sama dengan
        // seluruh omzet — persis alasan kolomnya nullable di database.
        if (hppRaw === '' || isNaN(omzet)) { panel.classList.add('hidden'); return; }

        const hpp = parseInt(hppRaw, 10) || 0;
        const biaya = parseInt(biayaEl && biayaEl.value || '0', 10) || 0;
        const kotor = omzet - hpp;
        const bersih = kotor - biaya;

        panel.classList.remove('hidden');
        el('kotor').textContent = rupiah(kotor);
        el('bersih').textContent = rupiah(bersih);
        el('margin').textContent = omzet > 0 ? (bersih / omzet * 100).toFixed(1) + '%' : '-';
        el('bersih').classList.toggle('text-status-danger', bersih < 0);
        el('peringatan').textContent = bersih < 0
            ? 'Perhitungan ini menunjukkan usaha sedang rugi pada periode tersebut.'
            : '';
    }

    [omzetEl, hppEl, biayaEl].forEach((i) => i && i.addEventListener('input', hitung));
    hitung();
})();
</script>
