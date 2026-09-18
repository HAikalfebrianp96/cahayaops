<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-cahayaos.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title', 'Dashboard') — CahayaOS</title>

    {{-- ============================================================
         PWA — aplikasi dapat dipasang di Android.

         Service worker-nya SENGAJA tidak menyimpan halaman maupun data di
         cache (lihat public/sw.js): CahayaOS adalah sistem pencatatan
         operasional, dan halaman basi yang tampak baru lebih berbahaya
         daripada halaman yang lambat. Yang di-cache hanya aset statis.
         ============================================================ --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#03586D">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CahayaOS">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-bg text-text-main">
    <div class="min-h-screen flex">

        @php
            $isAdmin = auth()->user()?->isAdmin();
            $isPimpinan = auth()->user()?->isPimpinan();
            $isPendamping = auth()->user()?->isPendamping();
            $isTimKoperasi = auth()->user()?->isTimKoperasi();
            // CATATAN: $diAreaKoperasi sudah DIHAPUS — penentuan wilayah untuk
            // breadcrumb topbar kini terpusat di $konteksBreadcrumb di bawah.
            $kopSetting = \App\Models\KoperasiSetting::current();
            $preneurSetting = \App\Models\SystemSetting::current();

            // ==========================================================
            // SIDEBAR — struktur Admin (urutan WAJIB seperti ini):
            //   1. Dashboard        : link TUNGGAL flat paling atas
            //   2. label "KEGIATAN"
            //        - Cahaya Preneur  : grup — label link + chevron toggle
            //        - Koperasi Cahaya : grup — label link + chevron toggle
            //   3. label "SISTEM"
            //        - Pengaturan, Manajemen User, Backup Database (flat)
            //
            // HEADER GRUP = 2 elemen terpisah, BUKAN satu tombol:
            //   - LABEL (<a>)       : navigasi LANGSUNG ke halaman daftar modul —
            //                         CP -> participants.index (Peserta),
            //                         Koperasi -> koperasi.anggota.index (Anggota).
            //                         SENGAJA tidak menuju Dashboard: satu-satunya
            //                         jalan ke Dashboard adalah link flat di atas.
            //   - CHEVRON (<button>): HANYA buka/tutup submenu, tidak navigasi.
            // Kedua grup toggle INDEPENDEN — bisa terbuka bersamaan (lihat
            // toggleSidebarGroup() di bawah halaman).
            //
            // CATATAN "Dashboard" — TIDAK ADA lagi di kedua submenu:
            //   - 'dashboard' dihapus dari grup CP: route-nya PERSIS SAMA dengan
            //     link Dashboard tunggal paling atas — dulu duplikat.
            //   - 'koperasi.dashboard' dihapus dari grup Koperasi: Dashboard
            //     Koperasi sekarang jadi PANEL di Dashboard gabungan, diakses
            //     lewat link "Dashboard" di atas lalu switcher tab.
            //
            // CATATAN AUDIT: "Pelanggan" kini SUDAH punya halaman sendiri
            // (koperasi.pelanggan.index) dan karenanya masuk menu — sebelumnya
            // pelanggan hanya bisa dibuat lewat quickStore AJAX dari form
            // Penjualan, tanpa cara melihat atau memperbaikinya.
            //
            // "Produk & Jasa" TETAP tidak dibuatkan link tersendiri: produk
            // memang selalu milik satu Unit Usaha dan dikelola dari dalam
            // Detail Unit Usaha, jadi daftar global tanpa konteks unitnya
            // justru menyesatkan.
            // ==========================================================
            // 'dashboard' TIDAK lagi masuk $rutePeserta — supaya saat di halaman
            // Dashboard yang menyala link tunggal di atas, bukan accordion CP.
            $rutePeserta = ['participants.*', 'pendampings.*', 'omzet.*', 'pendampingan.*', 'laporan', 'laporan.*'];
            $ruteKoperasi = ['koperasi.*'];
            // Dashboard sekarang SATU halaman dengan switcher ?modul=koperasi,
            // jadi "sedang di mana" ditentukan route + query param itu.
            $diDashboard = request()->routeIs('dashboard');
            $modulKoperasiAktif = $diDashboard && request()->query('modul') === 'koperasi';

            $cpActive = $isAdmin && request()->routeIs($rutePeserta);
            // Grup Koperasi ikut terbuka saat panel Koperasi di Dashboard aktif,
            // supaya konteksnya tetap terlihat walau yang menyala link Dashboard.
            $kopActive = $isAdmin && (request()->routeIs($ruteKoperasi) || $modulKoperasiAktif);

            // "Dashboard" adalah SATU-SATUNYA jalan masuk ke halaman Dashboard,
            // jadi link ini menyala untuk KEDUA tab switcher (Cahaya Preneur
            // maupun Koperasi). Label accordion di bawahnya TIDAK lagi menuju
            // Dashboard — masing-masing langsung ke halaman daftar modulnya —
            // sehingga tidak ada lagi dua item yang menyala bersamaan.
            $dashboardActive = $isAdmin && $diDashboard;

            // ==========================================================
            // KONTEKS BREADCRUMB TOPBAR — "wilayah" halaman yang sedang dibuka,
            // ditampilkan sebagai "CahayaOS · <konteks>". null = tanpa konteks.
            //
            // Ditulis EKSPLISIT per wilayah, bukan "kalau bukan Koperasi berarti
            // Cahaya Preneur" seperti sebelumnya — pola biner itu bikin halaman
            // di section SISTEM (Monitoring & Keamanan, Manajemen User, Backup
            // Database) salah berlabel "Cahaya Preneur", padahal tidak ada
            // hubungannya dengan modul itu. Wilayah baru yang ditambahkan nanti
            // cukup didaftarkan di sini.
            // ==========================================================
            $konteksBreadcrumb = match (true) {
                // Dashboard = halaman GABUNGAN dua modul -> netral, tanpa konteks.
                $diDashboard => null,
                request()->routeIs('koperasi.*') => $kopSetting->nama ?? 'Koperasi Cahaya',
                request()->routeIs('pengaturan.*', 'pengaturan-sistem.*', 'manajemen-user.*', 'backup-database.*') => 'Sistem',
                // Halaman akun pribadi — dipakai SEMUA peran (Admin, Pimpinan,
                // Pendamping) dan letaknya di footer sidebar, bukan di bawah
                // modul manapun. Sebelumnya salah berlabel "Cahaya Preneur",
                // yang bahkan menyesatkan bagi Pimpinan/Pendamping.
                request()->routeIs('account.*') => 'Akun',
                default => 'Cahaya Preneur',
            };

            // CATATAN: kedua LABEL accordion sengaja TIDAK diberi penanda aktif
            // (bg-primary-light). Tujuan kliknya — participants.index dan
            // koperasi.anggota.index — sudah diwakili item "Peserta" dan
            // "Anggota" di submenunya masing-masing; kalau label ikut disorot,
            // akan ada dua item menyala di halaman yang sama.

            // Menu section SISTEM — 3 halaman terpisah, semuanya khusus Admin.
            $menuSistem = [
                ['route' => 'pengaturan.index', 'label' => 'Pengaturan', 'active' => ['pengaturan.*']],
                // Label 'Monitoring & Keamanan' — halaman ini BUKAN halaman
                // konfigurasi, isinya System Health, System Information, dan
                // Activity Log. Nama lamanya ('Pengaturan Sistem') terlalu mirip
                // dengan menu 'Pengaturan' di atasnya dan membingungkan.
                // NAMA ROUTE-nya sengaja TIDAK diubah (tetap pengaturan-sistem.*)
                // supaya tidak ada referensi/bookmark yang putus — yang berubah
                // hanya label yang dilihat pengguna.
                ['route' => 'pengaturan-sistem.index', 'label' => 'Monitoring & Keamanan', 'active' => ['pengaturan-sistem.*']],
                ['route' => 'manajemen-user.index', 'label' => 'Manajemen User', 'active' => ['manajemen-user.*']],
                // Arsip Data ditaruh di section SISTEM, bukan di dalam modul:
                // isinya lintas modul (peserta, pendamping, catatan, koperasi),
                // jadi menaruhnya di salah satu modul justru menyesatkan.
                ['route' => 'arsip.index', 'label' => 'Arsip Data', 'active' => ['arsip.*']],
                ['route' => 'backup-database.index', 'label' => 'Backup Database', 'active' => ['backup-database.*']],
            ];

            $menuCahayaPreneur = [
                ['route' => 'participants.index', 'label' => 'Peserta', 'active' => ['participants.*']],
                ['route' => 'pendampings.index', 'label' => 'Pendamping', 'active' => ['pendampings.*']],
                ['route' => 'omzet.index', 'label' => 'Monitoring Omzet', 'active' => ['omzet.*']],
                ['route' => 'pendampingan.index', 'label' => 'Monitoring Pendampingan', 'active' => ['pendampingan.*']],
                ['route' => 'follow-up.index', 'label' => 'Follow Up', 'active' => ['follow-up.*']],
                ['route' => 'prioritas.index', 'label' => 'Prioritas', 'active' => ['prioritas.*']],
                ['route' => 'kpi-pendamping.index', 'label' => 'KPI Pendamping', 'active' => ['kpi-pendamping.*']],
                ['route' => 'target-pendamping.index', 'label' => 'Target Pendamping', 'active' => ['target-pendamping.*']],
                ['route' => 'laporan', 'label' => 'Laporan', 'active' => ['laporan', 'laporan.*']],
                // Dampak & SROI ditaruh PALING BAWAH di modul Cahaya Preneur,
                // bukan di section SISTEM: isinya menafsirkan data program
                // (peserta, pendampingan, baseline/endline), jadi tempatnya
                // memang di ujung alur modul ini — bukan di administrasi.
                ['route' => 'impact.index', 'label' => 'Dampak Program', 'active' => ['impact.*']],
            ];

            // "Dashboard" SENGAJA TIDAK ADA di sini — Dashboard Koperasi sekarang
            // jadi PANEL di halaman Dashboard (switcher ?modul=koperasi), bukan
            // halaman menu tersendiri.
            $menuKoperasi = [
                // "Tugas Koperasi" ditaruh PALING ATAS di modul Koperasi,
                // sejajar dengan "Tugas Saya" milik Pendamping: pertanyaan
                // pertama Tim Koperasi tiap pagi juga "apa pekerjaan saya",
                // bukan "menu mana yang harus dibuka".
                ['route' => 'koperasi.tugas.index', 'label' => 'Tugas Koperasi', 'active' => ['koperasi.tugas.*']],
                ['route' => 'koperasi.anggota.index', 'label' => 'Anggota', 'active' => ['koperasi.anggota.*']],
                ['route' => 'koperasi.unit-usaha.index', 'label' => 'Unit Usaha', 'active' => ['koperasi.unit-usaha.*', 'koperasi.produk.*']],
                ['route' => 'koperasi.penjualan.index', 'label' => 'Penjualan', 'active' => ['koperasi.penjualan.*', 'koperasi.invoice.*']],
                ['route' => 'koperasi.pengeluaran.index', 'label' => 'Pengeluaran', 'active' => ['koperasi.pengeluaran.*']],
                ['route' => 'koperasi.keuangan.index', 'label' => 'Keuangan', 'active' => ['koperasi.keuangan.*', 'koperasi.dana-sukarela.*']],
                ['route' => 'koperasi.shu.index', 'label' => 'SHU', 'active' => ['koperasi.shu.*']],
                ['route' => 'koperasi.laporan.index', 'label' => 'Laporan', 'active' => ['koperasi.laporan.*']],
            ];

            // Pimpinan & Pendamping TIDAK punya 2 modul untuk ditoggle (menu
            // mereka memang sudah ringkas/lintas-modul) — jadi tetap flat,
            // TIDAK dibuat accordion. Accordion HANYA berlaku utk Admin.
            if ($isPimpinan) {
                // Sidebar Pimpinan sengaja dibuat SATU titik masuk saja.
                // "Penjualan Koperasi" dilepas dari sini BUKAN karena fiturnya
                // dihapus - route, controller, view, dan otorisasinya semua
                // tetap utuh - melainkan supaya Pimpinan tidak perlu mencari
                // modul di sidebar. Kedua ekosistem dipilih langsung dari
                // kartu di Dashboard Pimpinan.
                $menuFlat = [
                    ['route' => 'dashboard', 'label' => 'Dashboard Pimpinan', 'active' => ['dashboard']],
                    // SATU tambahan untuk Pimpinan, dan disengaja: menyetujui
                    // hasil SROI sebagai Final adalah kewenangannya, dan
                    // kewenangan yang tidak punya pintu di sidebar tidak akan
                    // pernah dipakai.
                    ['route' => 'impact.index', 'label' => 'Dampak Program', 'active' => ['impact.*']],
                ];
            } elseif ($isTimKoperasi) {
                // TIM KOPERASI - satu ekosistem saja, jadi tetap menu flat
                // (bukan accordion). Kunci 'section' memberi label pemisah di
                // atas item, memakai gaya yang sama dengan section SISTEM milik
                // Admin. Seluruhnya route EXISTING; tidak ada route baru.
                //
                // TIDAK ADA menu Pengaturan di sini. Halaman Pengaturan memuat
                // konfigurasi Cahaya Preneur sekaligus Koperasi — termasuk
                // identitas resmi, tanda tangan, stempel, dan prefix invoice —
                // dan sesuai keputusan hak akses terakhir ia kembali menjadi
                // menu Admin saja. Backend-nya pun menolak Tim Koperasi (403),
                // jadi menu ini dihilangkan bukan sekadar disembunyikan.
                $menuFlat = [
                    ['route' => 'dashboard', 'label' => 'Dashboard Koperasi', 'active' => ['dashboard']],
                    // "Tugas Koperasi" tepat setelah Dashboard — sejajar dengan
                    // "Tugas Saya" milik Pendamping. Pertanyaan pertama Tim
                    // Koperasi tiap pagi juga "apa pekerjaan saya hari ini",
                    // bukan "menu mana yang harus dibuka".
                    ['route' => 'koperasi.tugas.index', 'label' => 'Tugas Koperasi', 'active' => ['koperasi.tugas.*']],
                    ['route' => 'koperasi.anggota.index', 'label' => 'Anggota', 'active' => ['koperasi.anggota.*'], 'section' => 'Koperasi'],
                    ['route' => 'koperasi.unit-usaha.index', 'label' => 'Unit Usaha', 'active' => ['koperasi.unit-usaha.*', 'koperasi.produk.*']],
                    ['route' => 'koperasi.penjualan.index', 'label' => 'Penjualan', 'active' => ['koperasi.penjualan.*', 'koperasi.invoice.*']],
                    ['route' => 'koperasi.pelanggan.index', 'label' => 'Pelanggan', 'active' => ['koperasi.pelanggan.*']],
                    ['route' => 'koperasi.pengeluaran.index', 'label' => 'Pengeluaran', 'active' => ['koperasi.pengeluaran.*', 'koperasi.kategori-pengeluaran.*']],
                    ['route' => 'koperasi.keuangan.index', 'label' => 'Keuangan', 'active' => ['koperasi.keuangan.*']],
                    ['route' => 'koperasi.shu.index', 'label' => 'SHU', 'active' => ['koperasi.shu.*']],
                    ['route' => 'koperasi.laporan.index', 'label' => 'Laporan', 'active' => ['koperasi.laporan.*']],
                    // Arsip Tim Koperasi hanya memuat modul koperasi.
                    ['route' => 'arsip.index', 'label' => 'Arsip Data', 'active' => ['arsip.*']],
                ];
            } elseif ($isPendamping) {
                // "Binaan Saya" dan "Laporan" HANYA muncul untuk Pendamping
                // yang akunnya benar-benar tertaut ke data Pendamping.
                // Syaratnya disamakan persis dengan guard di controllernya
                // masing-masing — BinaanSayaController dan
                // ReportController::index(), keduanya
                // (isPendamping() && pendamping_id). Tanpa ini, akun Pendamping
                // yang pendamping_id-nya kosong (mis. dibuat lewat Manajemen
                // User, atau tautannya dilepas) melihat menu yang begitu diklik
                // justru menghasilkan 403.
                //
                // Yang diselaraskan HANYA tampilan menunya. Otorisasi di
                // controller TIDAK diubah dan tetap jadi pengaman terakhir.
                $punyaDataBinaan = (bool) auth()->user()->pendamping_id;

                $menuFlat = array_values(array_filter([
                    ['route' => 'dashboard', 'label' => 'Dashboard', 'active' => ['dashboard']],
                    // Menu ini juga menyala saat Pendamping sedang membuka
                    // detail, menambah, atau mengedit binaannya — ketiganya
                    // adalah bagian dari alur "Binaan Saya", bukan halaman
                    // lain. Daftar peserta GLOBAL (participants.index) tetap
                    // Admin-only dan tidak pernah dijangkau menu ini.
                    // "Tugas Saya" adalah pusat kerja harian pendamping dan
                    // ditaruh PALING ATAS setelah Dashboard — pertanyaan
                    // pertamanya tiap pagi adalah "apa pekerjaan saya hari
                    // ini", bukan "menu mana yang harus saya buka".
                    //
                    // Route-nya tetap follow-up.index: halaman, controller,
                    // dan datanya sama — yang berubah hanya susunan tampilan
                    // dan namanya. Tidak ada modul kedua yang dibuat.
                    $punyaDataBinaan
                        ? ['route' => 'follow-up.index', 'label' => 'Tugas Saya', 'active' => ['follow-up.*']]
                        : null,
                    $punyaDataBinaan
                        ? ['route' => 'binaan.index', 'label' => 'Binaan Saya', 'active' => ['binaan.*', 'participants.show', 'participants.create', 'participants.edit', 'pendampingan.edit']]
                        : null,

                    // "Prioritas Usaha" DILEPAS dari menu Pendamping.
                    //
                    // Pembedaan namanya dulu dimaksudkan supaya jelas bahwa
                    // yang satu soal JADWAL dan yang lain soal KEADAAN USAHA.
                    // Niatnya benar, tapi kodenya tidak pernah menepatinya:
                    // peringkat Prioritas justru paling ditentukan sinyal
                    // waktu — belum pernah didampingi, tanpa aktivitas sekian
                    // hari, follow up terlambat. Akibatnya kedua halaman
                    // menampilkan orang yang sama, dan pendamping harus
                    // menebak mana yang "benar".
                    //
                    // Sekarang alasan keadaan usaha ikut tampil di kartu
                    // Tugas Saya, jadi tidak ada diagnosis yang hilang dengan
                    // melepas menu ini — yang hilang hanya kewajiban membuka
                    // dua daftar untuk satu pertanyaan.
                    //
                    // Route, controller, dan halamannya SENGAJA tidak dihapus:
                    // Admin masih memakainya untuk melihat peringkat lintas
                    // pendamping, dan pendamping yang menyimpan tautannya
                    // tetap bisa membukanya.
                    // KPI Saya HANYA berisi kinerja dirinya sendiri —
                    // ArsipController/KpiPendampingController yang menyaringnya,
                    // dan filter dari URL diabaikan untuk non-Admin.
                    $punyaDataBinaan
                        ? ['route' => 'kpi-pendamping.index', 'label' => 'KPI Saya', 'active' => ['kpi-pendamping.*']]
                        : null,
                    $punyaDataBinaan
                        ? ['route' => 'laporan', 'label' => 'Laporan', 'active' => ['laporan', 'laporan.*']]
                        : null,
                    // "Arsip Data" DILEPAS dari menu Pendamping.
                    //
                    // Arsipnya memang sudah tersaring ke binaannya sendiri dan
                    // tidak pernah bisa menghapus permanen — jadi ini bukan
                    // soal keamanan, melainkan soal beban. Sidebar Pendamping
                    // adalah daftar pekerjaan lapangan; mengelola arsip bukan
                    // salah satunya.
                    //
                    // Route, controller, dan penyaringannya SENGAJA tidak
                    // diubah: Pendamping yang perlu memulihkan catatannya
                    // sendiri tetap bisa dibukakan jalannya oleh Admin, dan
                    // tautan yang terlanjur disimpan tetap berfungsi.
                ]));
            } else {
                $menuFlat = [];
            }

            // Label diambil dari ROLE_OPTIONS supaya role baru tidak lagi
            // salah dilabeli. Sebelumnya ternary ini memaksa role apa pun
            // selain Admin/Pimpinan menjadi 'Pendamping'.
            $roleLabel = $isAdmin
                ? 'Admin YBM'
                : (\App\Models\User::ROLE_OPTIONS[auth()->user()->role] ?? 'Pengguna');
            $namaUser = auth()->user()->name ?? '';
            $inisial = collect(explode(' ', trim($namaUser)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
        @endphp

        {{-- SIDEBAR — SATU identitas CahayaOS. Admin: Dashboard tunggal + 2 accordion modul + section SISTEM. Pimpinan/Pendamping: flat. --}}
        {{-- Latar gelap saat drawer terbuka di ponsel. Mengklik di luar
             sidebar menutupnya — perilaku yang diharapkan pengguna. --}}
        <div id="sidebarOverlay"
             class="fixed inset-0 bg-black/40 z-30 hidden md:hidden no-print"
             onclick="tutupSidebar()"></div>

        <aside id="sidebar"
               class="w-64 bg-cahaya-primary text-white flex-shrink-0 flex flex-col no-print
                      fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200
                      md:static md:translate-x-0 md:transition-none overflow-y-auto">

            <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-white flex items-center justify-center overflow-hidden p-1 flex-shrink-0">
                    <img src="{{ asset('images/logo-cahayaos.jpg') }}" class="h-full w-full object-contain">
                </div>
                <div>
                    <p class="text-[15px] font-bold leading-tight tracking-wide">CahayaOS</p>
                    <p class="text-[11px] text-white/55">YBM PLN UP3 Bengkulu</p>
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-1">
                @if ($isAdmin)
                    {{-- DASHBOARD — link TUNGGAL paling atas, di luar accordion manapun --}}
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-[13px] font-semibold transition relative
                              {{ $dashboardActive ? 'bg-primary-light text-cahaya-primary' : 'text-white/85 hover:bg-white/10' }}">
                        @if ($dashboardActive)
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-full bg-cahaya-accent"></span>
                        @endif
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10" />
                        </svg>
                        Dashboard
                    </a>

                    {{-- SECTION: KEGIATAN — 2 modul operasional --}}
                    <p class="px-3 pt-5 pb-1.5 text-[10.5px] font-semibold tracking-[0.12em] text-white/40 uppercase">
                        Kegiatan
                    </p>

                    {{-- PARENT: CAHAYA PRENEUR — LABEL = link navigasi, CHEVRON = tombol expand/collapse TERPISAH --}}
                    <div class="flex items-center">
                        <a href="{{ route('participants.index') }}"
                           class="flex-1 min-w-0 flex items-center gap-2 px-3 py-2.5 rounded-lg text-[13px] font-semibold transition
                                  {{ $cpActive ? 'text-white' : 'text-white/85 hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                            </svg>
                            Cahaya Preneur
                        </a>
                        <button type="button" onclick="toggleSidebarGroup('cp')"
                                id="toggle-cp" aria-controls="group-cp" aria-expanded="{{ $cpActive ? 'true' : 'false' }}"
                                aria-label="Buka/tutup submenu Cahaya Preneur"
                                class="flex-shrink-0 p-2 rounded-lg transition {{ $cpActive ? 'text-white' : 'text-white/85' }} hover:bg-white/10">
                            <svg id="chevron-cp"
                                 class="w-3.5 h-3.5 flex-shrink-0 transition-transform {{ $cpActive ? 'rotate-90' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    {{-- 'mb-2' sengaja TIDAK dipakai di sini: <nav> memakai
                         space-y-1 yang menimpa margin-bottom anaknya, jadi
                         kelas itu tidak berefek dan cuma bikin grup ini
                         terlihat beda aturan dari group-kop. --}}
                    <div id="group-cp" class="{{ $cpActive ? '' : 'hidden' }} ml-4 pl-3 mt-1 space-y-0.5 border-l border-white/10">
                        @foreach ($menuCahayaPreneur as $item)
                            @php $isActive = request()->routeIs($item['active']); @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] font-medium transition relative
                                      {{ $isActive ? 'bg-primary-light text-cahaya-primary' : 'text-white/75 hover:bg-white/10' }}">
                                @if ($isActive)
                                    <span class="absolute -left-3 top-1.5 bottom-1.5 w-[3px] rounded-full bg-cahaya-accent"></span>
                                @endif
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>

                    {{-- PARENT: KOPERASI CAHAYA — LABEL = link LANGSUNG ke daftar Anggota
                         (koperasi.anggota.index), CHEVRON = tombol expand/collapse TERPISAH. --}}
                    <div class="flex items-center">
                        <a href="{{ route('koperasi.anggota.index') }}"
                           class="flex-1 min-w-0 flex items-center gap-2 px-3 py-2.5 rounded-lg text-[13px] font-semibold transition
                                  {{ $kopActive ? 'text-white' : 'text-white/85 hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 12h.01M9 15h.01" />
                            </svg>
                            Koperasi Cahaya
                        </a>
                        <button type="button" onclick="toggleSidebarGroup('kop')"
                                id="toggle-kop" aria-controls="group-kop" aria-expanded="{{ $kopActive ? 'true' : 'false' }}"
                                aria-label="Buka/tutup submenu Koperasi Cahaya"
                                class="flex-shrink-0 p-2 rounded-lg transition {{ $kopActive ? 'text-white' : 'text-white/85' }} hover:bg-white/10">
                            <svg id="chevron-kop"
                                 class="w-3.5 h-3.5 flex-shrink-0 transition-transform {{ $kopActive ? 'rotate-90' : '' }}"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    <div id="group-kop" class="{{ $kopActive ? '' : 'hidden' }} ml-4 pl-3 mt-1 space-y-0.5 border-l border-white/10">
                        @foreach ($menuKoperasi as $item)
                            @php $isActive = request()->routeIs($item['active']); @endphp
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] font-medium transition relative
                                      {{ $isActive ? 'bg-primary-light text-cahaya-primary' : 'text-white/75 hover:bg-white/10' }}">
                                @if ($isActive)
                                    <span class="absolute -left-3 top-1.5 bottom-1.5 w-[3px] rounded-full bg-cahaya-accent"></span>
                                @endif
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>

                    {{-- SECTION: SISTEM — 3 link FLAT (bukan accordion), khusus Admin --}}
                    <p class="px-3 pt-5 pb-1.5 text-[10.5px] font-semibold tracking-[0.12em] text-white/40 uppercase">
                        Sistem
                    </p>
                    @foreach ($menuSistem as $item)
                        @php $isActive = request()->routeIs($item['active']); @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] font-medium transition relative
                                  {{ $isActive ? 'bg-primary-light text-cahaya-primary' : 'text-white/85 hover:bg-white/10' }}">
                            @if ($isActive)
                                <span class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-full bg-cahaya-accent"></span>
                            @endif
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @else
                    {{-- Pimpinan / Pendamping — menu flat sederhana, tidak perlu accordion.
                         Ukuran font disamakan dengan menu Admin (13px) supaya tidak ada
                         perbedaan ukuran teks antar peran tanpa alasan. --}}
                    @foreach ($menuFlat as $item)
                        @php $isActive = request()->routeIs($item['active']); @endphp
                        @isset($item['section'])
                            <p class="px-3 pt-5 pb-1.5 text-[10.5px] font-semibold tracking-[0.12em] text-white/40 uppercase">
                                {{ $item['section'] }}
                            </p>
                        @endisset
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] font-medium transition relative
                                  {{ $isActive ? 'bg-primary-light text-cahaya-primary' : 'text-white/85 hover:bg-white/10' }}">
                            @if ($isActive)
                                <span class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-full bg-cahaya-accent"></span>
                            @endif
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif
            </nav>

            <div class="px-3 py-4 border-t border-white/10 space-y-0.5">
                <div class="px-3 py-2 flex items-center gap-2">
                    <div class="h-7 w-7 rounded-full bg-cahaya-accent text-cahaya-primary-dark flex items-center justify-center text-[11px] font-bold flex-shrink-0">
                        {{ $inisial ?: '?' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[12.5px] font-medium text-white truncate">{{ $namaUser }}</p>
                        <p class="text-[10.5px] text-white/50">{{ $roleLabel }}</p>
                    </div>
                </div>
                <a href="{{ route('account.password.edit') }}"
                   class="block px-3 py-2 rounded-lg text-[13px] font-medium {{ request()->routeIs('account.password.*') ? 'bg-white/15' : 'text-white/80 hover:bg-white/10' }}">
                    Ganti Password
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-[13px] font-medium text-white/80 hover:bg-white/10">
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        {{-- KONTEN --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- TOPBAR — bersih, ~64px, judul kiri, identitas user kanan --}}
            <header class="h-16 bg-white border-b border-border-soft flex items-center justify-between px-4 md:px-6 no-print flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Hanya muncul di layar kecil; di desktop sidebar
                         memang selalu terlihat sehingga tombolnya tak perlu. --}}
                    <button type="button" onclick="bukaSidebar()" aria-label="Buka menu"
                            class="md:hidden h-10 w-10 -ml-2 rounded-lg flex items-center justify-center text-text-secondary hover:bg-surface-bg transition flex-shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <div class="min-w-0">
                    <p class="text-[11px] text-text-secondary font-medium leading-tight">
                        CahayaOS @if ($konteksBreadcrumb) &middot; {{ $konteksBreadcrumb }} @endif
                    </p>
                    <h1 class="text-[17px] font-semibold text-gray-800 leading-tight truncate">@yield('title', 'Dashboard')</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="text-right hidden sm:block">
                        <p class="text-[13px] font-medium text-gray-800 leading-tight">{{ $namaUser }}</p>
                        <p class="text-[11px] text-text-secondary leading-tight">{{ $roleLabel }}</p>
                    </div>
                    <div class="h-9 w-9 rounded-full bg-cahaya-primary text-white flex items-center justify-center text-[12px] font-bold">
                        {{ $inisial ?: '?' }}
                    </div>
                </div>
            </header>

            {{-- MAIN --}}
            <main class="flex-1 p-4 md:p-6">
                @if (session('success'))
                    <div class="mb-4 rounded-xl bg-primary-light border border-status-success/20 px-4 py-3 text-sm text-status-success no-print">
                        {{ session('success') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Toggle submenu sidebar — INDEPENDEN per grup. Yang diklik saja yang
        // berubah; grup lain TIDAK ikut ditutup, jadi Cahaya Preneur & Koperasi
        // Cahaya bisa terbuka BERSAMAAN. (Dulu mode accordion: buka satu =
        // tutup yang lain.)
        //
        // State AWAL saat halaman dimuat tetap ditentukan $cpActive/$kopActive
        // di Blade — fungsi ini cuma mengurus perilaku SETELAH user klik.
        function toggleSidebarGroup(id) {
            const panel = document.getElementById('group-' + id);
            if (!panel) return;

            const willOpen = panel.classList.toggle('hidden') === false;

            const chevron = document.getElementById('chevron-' + id);
            if (chevron) chevron.classList.toggle('rotate-90', willOpen);

            const toggle = document.getElementById('toggle-' + id);
            if (toggle) toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
    </script>
    {{-- Kontrol drawer sidebar di layar kecil. Sengaja tanpa library:
         yang dibutuhkan hanya menggeser satu elemen dan menampilkan latar. --}}
    <script>
        function bukaSidebar() {
            document.getElementById('sidebar')?.classList.remove('-translate-x-full');
            document.getElementById('sidebarOverlay')?.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function tutupSidebar() {
            document.getElementById('sidebar')?.classList.add('-translate-x-full');
            document.getElementById('sidebarOverlay')?.classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Menekan sebuah menu harus ikut menutup drawer — kalau tidak, halaman
        // baru terbuka di belakang sidebar yang masih menutupi layar.
        document.getElementById('sidebar')?.addEventListener('click', function (e) {
            if (e.target.closest('a') && window.innerWidth < 768) tutupSidebar();
        });

        // Esc menutup drawer, dan kembali ke lebar desktop merapikan keadaan
        // supaya sidebar tidak tertinggal dalam posisi tergeser.
        document.addEventListener('keydown', (e) => e.key === 'Escape' && tutupSidebar());
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) {
                document.getElementById('sidebarOverlay')?.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    </script>

    {{-- Pendaftaran service worker. Dibungkus pemeriksaan dukungan +
         try/catch: browser lama atau koneksi tanpa HTTPS tidak boleh
         menjatuhkan halaman hanya karena PWA tidak tersedia. --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {
                    // Diam — PWA adalah penambah kenyamanan, bukan syarat
                    // aplikasi bisa dipakai.
                });
            });
        }
    </script>
</body>
</html>
