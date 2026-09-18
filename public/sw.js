/* ============================================================
 * SERVICE WORKER CAHAYAOS
 *
 * SATU ATURAN YANG MENENTUKAN SELURUH ISI FILE INI:
 * HALAMAN DAN DATA TIDAK PERNAH DILAYANI DARI CACHE.
 *
 * CahayaOS adalah sistem pencatatan operasional. Pendamping yang membuka
 * daftar binaan lalu melihat data kemarin — tanpa tahu itu data kemarin —
 * akan mengambil keputusan lapangan yang salah. Halaman basi di aplikasi
 * seperti ini lebih berbahaya daripada halaman yang lambat.
 *
 * Maka pembagiannya tegas:
 *
 *   HTML, API, dan SEMUA permintaan non-GET  -> langsung ke jaringan,
 *                                               tidak pernah disentuh cache.
 *   Aset statis (logo, ikon, font, CSS/JS)   -> boleh dari cache, karena
 *                                               isinya tidak berubah tanpa
 *                                               nama file ikut berubah.
 *
 * Yang didapat: aplikasi bisa dipasang di Android dan aset dasarnya termuat
 * cepat. Yang TIDAK dijanjikan: mode offline. Menjanjikannya sekarang berarti
 * menampilkan data lama tanpa penanda — persis yang harus dihindari.
 * ============================================================ */

const VERSI = 'cahayaos-v1';
const CACHE_ASET = `${VERSI}-aset`;

/* Aset yang aman di-cache sejak awal. Sengaja sedikit: hanya yang pasti ada
 * dan pasti tidak berubah diam-diam. */
const ASET_AWAL = [
    '/images/logo-cahayaos.png',
    '/images/favicon-cahayaos.png',
    '/apple-touch-icon.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_ASET)
            // addAll gagal total kalau SATU file tidak ada. Ditambahkan
            // satu-satu supaya aset yang hilang tidak menggagalkan
            // pemasangan service worker secara keseluruhan.
            .then((cache) => Promise.all(
                ASET_AWAL.map((url) => cache.add(url).catch(() => null))
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((kunci) => Promise.all(
                kunci.filter((k) => !k.startsWith(VERSI)).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/* Apakah permintaan ini aset statis yang aman di-cache? */
function asetStatis(url) {
    return /\.(?:png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot|css|js)$/i.test(url.pathname)
        || url.pathname === '/manifest.webmanifest';
}

self.addEventListener('fetch', (event) => {
    const permintaan = event.request;

    /* Non-GET (POST/PUT/DELETE — seluruh penyimpanan data) tidak pernah
     * disentuh sama sekali. */
    if (permintaan.method !== 'GET') return;

    const url = new URL(permintaan.url);

    /* Permintaan ke domain lain dibiarkan apa adanya. */
    if (url.origin !== self.location.origin) return;

    /* HALAMAN & DATA -> selalu jaringan. Tidak ada fallback ke cache:
     * lebih baik pengguna melihat halaman error browser yang jujur
     * daripada data lama yang tampak baru. */
    const memintaHtml = permintaan.mode === 'navigate'
        || (permintaan.headers.get('accept') || '').includes('text/html');

    if (memintaHtml || !asetStatis(url)) return;

    /* ASET STATIS -> cache dulu, jaringan kalau belum ada (stale-while-
     * revalidate ringan). Aman karena isinya tidak berubah tanpa nama
     * filenya ikut berubah. */
    event.respondWith(
        caches.match(permintaan).then((tersimpan) => {
            const dariJaringan = fetch(permintaan)
                .then((respons) => {
                    if (respons && respons.status === 200 && respons.type === 'basic') {
                        const salinan = respons.clone();
                        caches.open(CACHE_ASET).then((cache) => cache.put(permintaan, salinan));
                    }
                    return respons;
                })
                .catch(() => tersimpan);

            return tersimpan || dariJaringan;
        })
    );
});
