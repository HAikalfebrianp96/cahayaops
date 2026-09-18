/*
 * CHART.JS — dibundel, bukan diambil dari CDN.
 *
 * ============================================================================
 * KENAPA BERKAS TERPISAH, BUKAN DIGABUNG KE app.js
 * ============================================================================
 *
 * Hanya empat halaman yang menggambar grafik: Dashboard Cahaya Preneur, KPI
 * Pendamping, Detail Peserta, dan halaman Cek Simpanan anggota. Menggabungkan
 * Chart.js ke app.js berarti seluruh halaman lain — termasuk halaman login dan
 * form omzet yang dibuka peserta lewat ponsel — ikut mengunduh ratusan
 * kilobyte yang tidak pernah mereka pakai.
 *
 * ============================================================================
 * KENAPA DITEMPELKAN KE window
 * ============================================================================
 *
 * Konfigurasi tiap grafik disusun Blade di dalam halamannya masing-masing
 * (label bulan, angka omzet, warna), jadi kodenya berupa <script> biasa di
 * tengah halaman, bukan modul. Agar kode itu tetap bisa memanggil `new
 * Chart(...)` seperti sebelumnya, pustakanya dipasang sebagai variabel global
 * — persis seperti yang dilakukan versi CDN-nya.
 *
 * CATATAN PENTING soal urutan jalan:
 * Vite memuat berkas ini sebagai <script type="module">, dan modul selalu
 * DITUNDA sampai halaman selesai diurai. Artinya <script> biasa di tengah
 * halaman berjalan LEBIH DULU daripada berkas ini. Karena itu setiap
 * pemanggilan `new Chart(...)` di Blade dibungkus DOMContentLoaded — peristiwa
 * itu terjadi setelah seluruh modul dijalankan, jadi window.Chart sudah pasti
 * tersedia. Melepas pembungkus itu akan membuat grafiknya diam-diam hilang.
 */
import Chart from 'chart.js/auto';

window.Chart = Chart;
