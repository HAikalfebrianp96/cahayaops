<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BinaanSayaController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BackupDatabaseController;
use App\Http\Controllers\ManajemenUserController;
use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AnggotaAksesController;
use App\Http\Controllers\DanaHibahController;
use App\Http\Controllers\DanaSukarelaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialProxyController;
use App\Http\Controllers\ImpactOutcomeController;
use App\Http\Controllers\ImpactProgramController;
use App\Http\Controllers\ProgramInvestmentController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KoperasiDashboardController;
use App\Http\Controllers\TugasKoperasiController;
use App\Http\Controllers\KpiPendampingController;
use App\Http\Controllers\KoperasiMemberController;
use App\Http\Controllers\KoperasiPlaceholderController;
use App\Http\Controllers\KoperasiSettingController;
use App\Http\Controllers\LabaRugiController;
use App\Http\Controllers\LaporanKoperasiController;
use App\Http\Controllers\LegalitasController;
use App\Http\Controllers\MemberSavingController;
use App\Http\Controllers\OmzetMonitoringController;
use App\Http\Controllers\OmzetReportPublicController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ParticipantMeasurementController;
use App\Http\Controllers\PendampinganController;
use App\Http\Controllers\PendampingController;
use App\Http\Controllers\PendampingTargetController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PrioritasPendampinganController;
use App\Http\Controllers\PengeluaranController;
use App\Http\Controllers\KategoriPengeluaranController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\HppCalculationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesTransactionController;
use App\Http\Controllers\ShuController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TrainingController;
use Illuminate\Support\Facades\Route;

// Halaman awal langsung arahkan ke login
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth (login sederhana, tanpa registrasi)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // throttle:login — 5 percobaan per menit per kombinasi email+IP.
    // Didaftarkan di AppServiceProvider; lihat alasan pemilihan kuncinya
    // di sana (kantor berbagi satu IP).
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Form laporan omzet PUBLIK (tanpa login, akses lewat token unik) — Tahap 3
Route::get('/lapor-omzet/{token}', [OmzetReportPublicController::class, 'show'])->name('omzet.public.show');
// Tanpa login, jadi dibatasi per IP: tautan yang bocor tidak boleh bisa
// dipakai membanjiri laporan omzet peserta.
Route::post('/lapor-omzet/{token}', [OmzetReportPublicController::class, 'store'])->middleware('throttle:20,1')->name('omzet.public.store');
Route::get('/cek-simpanan', [AnggotaAksesController::class, 'showLogin'])->name('anggota-akses.login');
Route::post('/cek-simpanan', [AnggotaAksesController::class, 'login'])->middleware('throttle:5,1')->name('anggota-akses.login.submit');
Route::get('/cek-simpanan/hasil', [AnggotaAksesController::class, 'hasil'])->name('anggota-akses.hasil');
Route::post('/cek-simpanan/keluar', [AnggotaAksesController::class, 'logout'])->name('anggota-akses.logout');

// Halaman yang butuh login (Admin YBM atau Pendamping — dibedakan di dalam controller)
Route::middleware('auth')->group(function () {
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/akun/ganti-password', [AccountController::class, 'editPassword'])->name('account.password.edit');
Route::put('/akun/ganti-password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    // Export daftar peserta — memakai FILTER YANG SAMA dengan halaman
    // daftar (ParticipantController::terapkanFilter), jadi isi file selalu
    // sama persis dengan yang terlihat di layar.
    Route::get('/participants-export', [ParticipantController::class, 'export'])->name('participants.export');
    Route::resource('participants', ParticipantController::class);
    Route::resource('pendampings', PendampingController::class);
    Route::put('/pendampings/{pendamping}/reset-password', [PendampingController::class, 'resetPassword'])->name('pendampings.reset-password');

    // Monitoring Omzet / perkembangan usaha (Tahap 3).
    // index: Admin, Pimpinan, Pendamping (ter-scope ke binaannya).
    // create/store/update/destroy: Admin & Pendamping pemilik binaan —
    // dijaga OmzetReportPolicy, Pimpinan tetap read-only.
    Route::get('/omzet', [OmzetMonitoringController::class, 'index'])->name('omzet.index');
    Route::get('/participants/{participant}/omzet/create', [OmzetMonitoringController::class, 'create'])->name('omzet.create');
    Route::post('/participants/{participant}/omzet', [OmzetMonitoringController::class, 'store'])->name('omzet.store');
    Route::get('/omzet/{omzetReport}', [OmzetMonitoringController::class, 'show'])->name('omzet.show');
    // Unduh bukti laporan omzet. Berkasnya ada di disk PRIVAT dan tidak
    // punya URL publik — route ini satu-satunya jalan keluarnya, dan izinnya
    // memakai Gate 'view' yang sama dengan halaman detail laporan.
    Route::get('/omzet/{omzetReport}/bukti', [OmzetMonitoringController::class, 'bukti'])->name('omzet.bukti');
    Route::get('/omzet/{omzetReport}/edit', [OmzetMonitoringController::class, 'edit'])->name('omzet.edit');
    Route::put('/omzet/{omzetReport}', [OmzetMonitoringController::class, 'update'])->name('omzet.update');
    Route::delete('/omzet/{omzetReport}', [OmzetMonitoringController::class, 'destroy'])->name('omzet.destroy');

    // ==========================================================
    // PERKEMBANGAN USAHA — Baseline / Monitoring / Endline
    //
    // Bentuk route-nya SAMA dengan modul catatan peserta lain (pendampingan,
    // pelatihan, omzet): bersarang di bawah peserta saat membuat, berdiri
    // sendiri saat mengubah/menghapus. Otorisasinya dijaga
    // ParticipantMeasurementPolicy — Pimpinan boleh melihat, tidak mengubah.
    // ==========================================================
    Route::get('/participants/{participant}/perkembangan', [ParticipantMeasurementController::class, 'index'])->name('perkembangan.index');
    Route::get('/participants/{participant}/perkembangan/create', [ParticipantMeasurementController::class, 'create'])->name('perkembangan.create');
    Route::post('/participants/{participant}/perkembangan', [ParticipantMeasurementController::class, 'store'])->name('perkembangan.store');
    Route::get('/perkembangan/{perkembangan}/edit', [ParticipantMeasurementController::class, 'edit'])->name('perkembangan.edit');
    Route::put('/perkembangan/{perkembangan}', [ParticipantMeasurementController::class, 'update'])->name('perkembangan.update');
    Route::delete('/perkembangan/{perkembangan}', [ParticipantMeasurementController::class, 'destroy'])->name('perkembangan.destroy');

    // ==========================================================
    // DAMPAK & SROI
    //
    // Seluruhnya dijaga ImpactPolicy: Admin menyusun & menghitung, Pimpinan
    // membaca & menyetujui, Pendamping dan Tim Koperasi tidak masuk sama
    // sekali. Route proxy SENGAJA didaftarkan SEBELUM impact/{impact},
    // supaya "proxy" tidak pernah terbaca sebagai id program.
    // ==========================================================
    Route::get('/impact/proxy', [FinancialProxyController::class, 'index'])->name('impact.proxy.index');
    Route::get('/impact/proxy/create', [FinancialProxyController::class, 'create'])->name('impact.proxy.create');
    Route::post('/impact/proxy', [FinancialProxyController::class, 'store'])->name('impact.proxy.store');
    Route::get('/impact/proxy/{proxy}/edit', [FinancialProxyController::class, 'edit'])->name('impact.proxy.edit');
    Route::put('/impact/proxy/{proxy}', [FinancialProxyController::class, 'update'])->name('impact.proxy.update');
    Route::post('/impact/proxy/{proxy}/toggle', [FinancialProxyController::class, 'toggle'])->name('impact.proxy.toggle');
    Route::delete('/impact/proxy/{proxy}', [FinancialProxyController::class, 'destroy'])->name('impact.proxy.destroy');

    Route::get('/impact', [ImpactProgramController::class, 'index'])->name('impact.index');
    Route::get('/impact/create', [ImpactProgramController::class, 'create'])->name('impact.create');
    Route::post('/impact', [ImpactProgramController::class, 'store'])->name('impact.store');
    Route::get('/impact/{impact}', [ImpactProgramController::class, 'show'])->name('impact.show');
    Route::get('/impact/{impact}/edit', [ImpactProgramController::class, 'edit'])->name('impact.edit');
    Route::put('/impact/{impact}', [ImpactProgramController::class, 'update'])->name('impact.update');
    Route::delete('/impact/{impact}', [ImpactProgramController::class, 'destroy'])->name('impact.destroy');

    // Menghitung dan menyetujui adalah dua tindakan yang SENGAJA dipisah —
    // menjalankan rumus dan menyatakan angkanya layak terbit bukan hal yang
    // sama, dan izinnya pun berbeda (lihat ImpactPolicy).
    // Export PDF laporan Dampak & SROI. Otorisasinya memakai Gate 'view'
    // yang SAMA dengan halaman programnya (Admin & Pimpinan) — bukan aturan
    // sendiri, supaya tombol yang tampil dan izin yang diperiksa tidak
    // pernah berbeda.
    Route::get('/impact/{impact}/export-pdf', [ImpactProgramController::class, 'exportPdf'])->name('impact.export-pdf');
    Route::post('/impact/{impact}/hitung', [ImpactProgramController::class, 'hitung'])->name('impact.hitung');
    Route::post('/impact/{impact}/setujui/{perhitungan}', [ImpactProgramController::class, 'setujui'])->name('impact.setujui');

    Route::get('/impact/{impact}/investasi/create', [ProgramInvestmentController::class, 'create'])->name('impact.investasi.create');
    Route::post('/impact/{impact}/investasi', [ProgramInvestmentController::class, 'store'])->name('impact.investasi.store');
    // Unduh bukti investasi. Berkasnya di disk PRIVAT dan tidak punya URL
    // publik — route ini satu-satunya jalan keluarnya, memakai Gate 'view'
    // pada programnya (Admin & Pimpinan).
    Route::get('/impact-investasi/{investasi}/bukti', [ProgramInvestmentController::class, 'bukti'])->name('impact.investasi.bukti');
    Route::get('/impact-investasi/{investasi}/edit', [ProgramInvestmentController::class, 'edit'])->name('impact.investasi.edit');
    Route::put('/impact-investasi/{investasi}', [ProgramInvestmentController::class, 'update'])->name('impact.investasi.update');
    Route::delete('/impact-investasi/{investasi}', [ProgramInvestmentController::class, 'destroy'])->name('impact.investasi.destroy');

    Route::get('/impact/{impact}/outcome/create', [ImpactOutcomeController::class, 'create'])->name('impact.outcome.create');
    Route::post('/impact/{impact}/outcome', [ImpactOutcomeController::class, 'store'])->name('impact.outcome.store');
    Route::get('/impact-outcome/{outcome}/edit', [ImpactOutcomeController::class, 'edit'])->name('impact.outcome.edit');
    Route::put('/impact-outcome/{outcome}', [ImpactOutcomeController::class, 'update'])->name('impact.outcome.update');
    Route::delete('/impact-outcome/{outcome}', [ImpactOutcomeController::class, 'destroy'])->name('impact.outcome.destroy');


    // ==========================================================
    // KPI & KINERJA PENDAMPING
    //
    // Target = kebijakan program -> Admin saja (PendampingTargetPolicy).
    // KPI    = alat ukur         -> Admin melihat semua, Pendamping hanya
    //          dirinya sendiri (di-scope di controller; filter dari URL
    //          diabaikan untuk non-Admin).
    // ==========================================================
    Route::get('/kpi-pendamping', [KpiPendampingController::class, 'index'])->name('kpi-pendamping.index');
    Route::get('/kpi-pendamping/export', [KpiPendampingController::class, 'export'])->name('kpi-pendamping.export');
    Route::get('/kpi-pendamping/export-pdf', [KpiPendampingController::class, 'exportPdf'])->name('kpi-pendamping.export-pdf');
    Route::get('/kpi-pendamping/{pendamping}', [KpiPendampingController::class, 'show'])->name('kpi-pendamping.show');

    Route::resource('target-pendamping', PendampingTargetController::class)
        ->parameters(['target-pendamping' => 'target'])
        ->except(['show']);

    // PRIORITAS PENDAMPINGAN — mengelompokkan berdasarkan KEADAAN peserta,
    // berbeda dari Follow Up yang mengelompokkan berdasarkan JADWAL.
    Route::get('/prioritas', [PrioritasPendampinganController::class, 'index'])->name('prioritas.index');

    // FOLLOW UP — reminder center. Pendamping melihat binaannya sendiri
    // (di-scope di query, filter dari URL diabaikan untuk non-Admin);
    // Admin melihat seluruh pendamping dengan filter.
    Route::get('/follow-up', [FollowUpController::class, 'index'])->name('follow-up.index');

    // Binaan Saya (khusus Pendamping)
    Route::get('/binaan-saya', [BinaanSayaController::class, 'index'])->name('binaan.index');

    // Pendampingan (tambah/ubah/hapus: Admin & Pendamping pemilik binaan;
    // monitoring: Admin). Seluruhnya dijaga PendampinganRecordPolicy — hak
    // atas satu catatan diturunkan dari PESERTANYA, bukan dari siapa yang
    // dulu menulisnya.
    Route::post('/participants/{participant}/pendampingan', [PendampinganController::class, 'store'])->name('pendampingan.store');
    Route::get('/pendampingan/{pendampinganRecord}/edit', [PendampinganController::class, 'edit'])->name('pendampingan.edit');
    Route::put('/pendampingan/{pendampinganRecord}', [PendampinganController::class, 'update'])->name('pendampingan.update');
    Route::delete('/pendampingan/{pendampinganRecord}', [PendampinganController::class, 'destroy'])->name('pendampingan.destroy');
    Route::get('/pendampingan', [PendampinganController::class, 'index'])->name('pendampingan.index');

    // Pelatihan — Admin & Pendamping pemilik binaan (TrainingRecordPolicy).
    // 'training.status' dipisah dari 'training.update' dengan sengaja:
    // dropdown status di Detail Peserta hanya mengirim satu field, jadi kalau
    // digabung, validasi form Edit lengkap akan menolaknya.
    Route::post('/participants/{participant}/training', [TrainingController::class, 'store'])->name('training.store');
    Route::get('/training/{trainingRecord}/edit', [TrainingController::class, 'edit'])->name('training.edit');
    Route::put('/training/{trainingRecord}', [TrainingController::class, 'update'])->name('training.update');
    Route::put('/training/{trainingRecord}/status', [TrainingController::class, 'updateStatus'])->name('training.status');
    Route::delete('/training/{trainingRecord}', [TrainingController::class, 'destroy'])->name('training.destroy');

    // Legalitas & Sertifikasi DINAMIS — Admin & Pendamping pemilik binaan, CRUD penuh
    Route::get('/participants/{participant}/legalitas/create', [LegalitasController::class, 'create'])->name('legalitas.create');
    Route::post('/participants/{participant}/legalitas', [LegalitasController::class, 'store'])->name('legalitas.store');
    Route::get('/legalitas/{legalitasRecord}/edit', [LegalitasController::class, 'edit'])->name('legalitas.edit');
    Route::put('/legalitas/{legalitasRecord}', [LegalitasController::class, 'update'])->name('legalitas.update');
    Route::delete('/legalitas/{legalitasRecord}', [LegalitasController::class, 'destroy'])->name('legalitas.destroy');
    Route::get('/legalitas/{legalitasRecord}/download', [LegalitasController::class, 'download'])->name('legalitas.download');

    // HPP Cahaya Preneur 
    Route::get('/participants/{participant}/hpp', [HppCalculationController::class, 'index'])->name('hpp.index');
    Route::get('/participants/{participant}/hpp/create', [HppCalculationController::class, 'create'])->name('hpp.create');
    Route::post('/participants/{participant}/hpp', [HppCalculationController::class, 'store'])->name('hpp.store');
    Route::get('/hpp/{hppCalculation}', [HppCalculationController::class, 'show'])->name('hpp.show');
    Route::get('/hpp/{hppCalculation}/edit', [HppCalculationController::class, 'edit'])->name('hpp.edit');
    Route::put('/hpp/{hppCalculation}', [HppCalculationController::class, 'update'])->name('hpp.update');
    Route::delete('/hpp/{hppCalculation}', [HppCalculationController::class, 'destroy'])->name('hpp.destroy');

    // TAHAP 5 — Laporan (Admin: semua data. Pendamping: hanya binaannya, di-scope di controller)
    Route::get('/laporan', [ReportController::class, 'index'])->name('laporan');
    Route::get('/laporan/peserta', [ReportController::class, 'peserta'])->name('laporan.peserta');
    Route::get('/laporan/peserta/export', [ReportController::class, 'pesertaExport'])->name('laporan.peserta.export');
    Route::get('/laporan/omzet', [ReportController::class, 'omzet'])->name('laporan.omzet');
    Route::get('/laporan/omzet/export', [ReportController::class, 'omzetExport'])->name('laporan.omzet.export');
    // LAPORAN KINERJA PENDAMPING — dari sisi pendamping, bukan peserta.
    // Pendamping hanya melihat barisnya sendiri (di-scope di controller).
    Route::get('/laporan/pendamping', [ReportController::class, 'pendamping'])->name('laporan.pendamping');
    Route::get('/laporan/pendamping/export', [ReportController::class, 'pendampingExport'])->name('laporan.pendamping.export');
    Route::get('/laporan/pendamping/export-pdf', [ReportController::class, 'pendampingExportPdf'])->name('laporan.pendamping.export-pdf');

    Route::get('/laporan/pendampingan', [ReportController::class, 'pendampingan'])->name('laporan.pendampingan');
    Route::get('/laporan/pendampingan/export', [ReportController::class, 'pendampinganExport'])->name('laporan.pendampingan.export');
    Route::get('/laporan/pelatihan', [ReportController::class, 'pelatihan'])->name('laporan.pelatihan');
    Route::get('/laporan/pelatihan/export', [ReportController::class, 'pelatihanExport'])->name('laporan.pelatihan.export');
    Route::get('/laporan/legalitas', [ReportController::class, 'legalitas'])->name('laporan.legalitas');
    Route::get('/laporan/legalitas/export', [ReportController::class, 'legalitasExport'])->name('laporan.legalitas.export');
    // Laporan Perkembangan Usaha — satu-satunya laporan yang isinya SELISIH
    // (baseline vs endline), bukan daftar kejadian. Angkanya diambil dari
    // PerbandinganService, sumber yang sama dengan halaman peserta dan mesin
    // SROI, supaya ketiganya tidak mungkin berbeda.
    Route::get('/laporan/perkembangan', [ReportController::class, 'perkembangan'])->name('laporan.perkembangan');
    Route::get('/laporan/perkembangan/export', [ReportController::class, 'perkembanganExport'])->name('laporan.perkembangan.export');
    Route::get('/laporan/rekap', [ReportController::class, 'rekap'])->name('laporan.rekap');
    Route::get('/laporan/download-semua', [ReportController::class, 'downloadAllPdf'])->name('laporan.download-semua');

    // Download PDF untuk 6 laporan
    Route::get('/laporan/peserta/export-pdf', [ReportController::class, 'pesertaExportPdf'])->name('laporan.peserta.export-pdf');
    Route::get('/laporan/omzet/export-pdf', [ReportController::class, 'omzetExportPdf'])->name('laporan.omzet.export-pdf');
    Route::get('/laporan/pendampingan/export-pdf', [ReportController::class, 'pendampinganExportPdf'])->name('laporan.pendampingan.export-pdf');
    Route::get('/laporan/pelatihan/export-pdf', [ReportController::class, 'pelatihanExportPdf'])->name('laporan.pelatihan.export-pdf');
    Route::get('/laporan/legalitas/export-pdf', [ReportController::class, 'legalitasExportPdf'])->name('laporan.legalitas.export-pdf');
    Route::get('/laporan/rekap/export-pdf', [ReportController::class, 'rekapExportPdf'])->name('laporan.rekap.export-pdf');

    // ==========================================================
    // PENGATURAN — SATU halaman dengan switcher tab:
    //   ?tab=semua (default) | ?tab=preneur | ?tab=koperasi
    //
    // Yang digabung HANYA tampilannya. Datanya tetap dua sistem terpisah:
    //   - Cahaya Preneur -> SystemSetting,  disimpan via pengaturan.update
    //   - Koperasi       -> KoperasiSetting, disimpan via koperasi.pengaturan.update
    // Masing-masing form tetap submit ke endpoint aslinya (lihat di bawah dan
    // di grup koperasi), jadi validasi & otorisasinya tidak bercampur.
    //
    // Admin saja — guard tetap di dalam masing-masing controller.
    // ==========================================================
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
    Route::post('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
    Route::get('/pengaturan/logo', [PengaturanController::class, 'logo'])->name('pengaturan.logo');

    // ==========================================================
    // KOPERASI CAHAYA — Guard admin/pimpinan ada di dalam masing-masing controller.
    // ==========================================================
    Route::prefix('koperasi')->name('koperasi.')->group(function () {
        Route::get('/dashboard', [KoperasiDashboardController::class, 'index'])->name('dashboard');

        // TUGAS KOPERASI — halaman kerja harian Tim Koperasi, pasangan dari
        // "Tugas Saya" milik Pendamping. Perhitungannya di
        // TugasKoperasiService, yang dipakai bersama ringkasan di Dashboard.
        Route::get('/tugas', [TugasKoperasiController::class, 'index'])->name('tugas.index');

        // Customer quick-add (dipakai dari halaman Penjualan)
        Route::post('/customer/quick', [CustomerController::class, 'quickStore'])->name('customer.quick-store');

        Route::resource('pelanggan', CustomerController::class)
            ->parameters(['pelanggan' => 'pelanggan'])
            ->except(['show']);

        // K1 — Keanggotaan + Simpanan
        //
        // 'pilih-peserta' HARUS didaftarkan SEBELUM resource('anggota'):
        // kalau tidak, GET /koperasi/anggota/pilih-peserta akan tertangkap
        // lebih dulu oleh route show (/anggota/{anggota}) dan berakhir 404.
        Route::get('/anggota/pilih-peserta', [KoperasiMemberController::class, 'pilihPeserta'])
            ->name('anggota.pilih-peserta');
        // Jalan pintas dari halaman Detail Peserta. Meneruskan ke create()
        // yang sama, jadi seluruh aturan anti-duplikasinya tetap berlaku.
        Route::get('/anggota/dari-peserta/{participant}', [KoperasiMemberController::class, 'jadikanAnggota'])
            ->name('anggota.jadikan');
        Route::resource('anggota', KoperasiMemberController::class)
            ->parameters(['anggota' => 'anggota']);
        Route::post('/anggota/{anggota}/simpanan', [MemberSavingController::class, 'store'])->name('savings.store');
        // KOREKSI SIMPANAN — TIDAK ADA route hapus di sini, dan itu disengaja:
        // baris simpanan tidak pernah dihapus. 'batalkan' menandai entri
        // sebagai Dibatalkan (barisnya tetap ada) dan boleh membuat entri
        // pengganti; 'kunci' menutup jalur edit langsung agar koreksi
        // berikutnya wajib berjejak.
        Route::get('/simpanan/{simpanan}/edit', [MemberSavingController::class, 'edit'])->name('savings.edit');
        Route::put('/simpanan/{simpanan}', [MemberSavingController::class, 'update'])->name('savings.update');
        Route::post('/simpanan/{simpanan}/batalkan', [MemberSavingController::class, 'batalkan'])->name('savings.batalkan');
        Route::post('/simpanan/{simpanan}/pulihkan', [MemberSavingController::class, 'pulihkan'])->name('savings.pulihkan');
        Route::post('/simpanan/{simpanan}/kunci', [MemberSavingController::class, 'kunci'])->name('savings.kunci');

        // HAPUS PERMANEN. Terpisah dari 'batalkan' dan memakai verb DELETE:
        // dua tindakan yang tidak bisa dibatalkan tidak boleh punya alamat
        // yang mirip. Syaratnya (harus sudah dibatalkan, bukan mata rantai
        // koreksi) dijaga PenghapusanPermanenService, bukan oleh rute.
        Route::delete('/simpanan/{simpanan}', [MemberSavingController::class, 'destroy'])->name('savings.destroy');
	Route::post('/anggota/{anggota}/generate-pin', [KoperasiMemberController::class, 'generatePin'])->name('anggota.generate-pin');

        Route::resource('unit-usaha', BusinessUnitController::class)
            ->parameters(['unit-usaha' => 'unit']);
        Route::get('/unit-usaha/{unit}/produk/create', [ProductServiceController::class, 'create'])->name('produk.create');
        Route::post('/unit-usaha/{unit}/produk', [ProductServiceController::class, 'store'])->name('produk.store');
        Route::post('/unit-usaha/{unit}/produk/quick', [ProductServiceController::class, 'quickStore'])->name('produk.quick-store');
        Route::get('/produk/{produk}/edit', [ProductServiceController::class, 'edit'])->name('produk.edit');
        Route::put('/produk/{produk}', [ProductServiceController::class, 'update'])->name('produk.update');
        Route::delete('/produk/{produk}', [ProductServiceController::class, 'destroy'])->name('produk.destroy');

        // K3 — Penjualan + Invoice (+ Edit Transaksi)
        Route::resource('penjualan', SalesTransactionController::class)
            ->parameters(['penjualan' => 'transaksi'])
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        // Pembatalan transaksi — TIDAK ADA route hapus, dan itu disengaja:
        // penjualan sudah masuk laba rugi dan mungkin invoice yang dicetak.
        Route::post('/penjualan/{transaksi}/batalkan', [SalesTransactionController::class, 'batalkan'])->name('penjualan.batalkan');
        // Jalan pulang untuk pembatalan yang keliru. Tanpa ini, satu-satunya
        // perbaikan adalah membuat transaksi baru — yang justru menambah baris
        // yang tidak pernah terjadi ke dalam laporan.
        Route::post('/penjualan/{transaksi}/pulihkan', [SalesTransactionController::class, 'pulihkan'])->name('penjualan.pulihkan');

        // Hapus permanen. Penjaganya menolak transaksi yang masih aktif —
        // harus dibatalkan (beserta alasannya) lebih dulu, supaya uang yang
        // hilang dari laporan selalu punya keterangan kenapa.
        Route::delete('/penjualan/{transaksi}', [SalesTransactionController::class, 'destroy'])->name('penjualan.destroy');
        Route::get('/penjualan/{transaksi}/invoice', [InvoiceController::class, 'show'])->name('invoice.show');
        Route::post('/penjualan/{transaksi}/invoice', [InvoiceController::class, 'store'])->name('invoice.store');
        Route::get('/penjualan/{transaksi}/invoice/pdf', [InvoiceController::class, 'pdf'])->name('invoice.pdf');

        // K4 — Pengeluaran (soft-cancel, TIDAK PERNAH hard delete)
        Route::get('/pengeluaran', [PengeluaranController::class, 'index'])->name('pengeluaran.index');
        Route::get('/pengeluaran/create', [PengeluaranController::class, 'create'])->name('pengeluaran.create');
        Route::post('/pengeluaran', [PengeluaranController::class, 'store'])->name('pengeluaran.store');
        Route::get('/pengeluaran/{pengeluaran}', [PengeluaranController::class, 'show'])->name('pengeluaran.show');
        Route::get('/pengeluaran/{pengeluaran}/edit', [PengeluaranController::class, 'edit'])->name('pengeluaran.edit');
        Route::put('/pengeluaran/{pengeluaran}', [PengeluaranController::class, 'update'])->name('pengeluaran.update');
        Route::post('/pengeluaran/{pengeluaran}/batalkan', [PengeluaranController::class, 'cancel'])->name('pengeluaran.cancel');
        Route::post('/pengeluaran/{pengeluaran}/pulihkan', [PengeluaranController::class, 'pulihkan'])->name('pengeluaran.pulihkan');

        // Hapus permanen. Penjaganya menolak pengeluaran yang masih aktif —
        // harus dibatalkan (beserta alasannya) lebih dulu, sama seperti
        // transaksi penjualan.
        Route::delete('/pengeluaran/{pengeluaran}', [PengeluaranController::class, 'destroy'])->name('pengeluaran.destroy');
        Route::get('/pengeluaran/{pengeluaran}/bukti', [PengeluaranController::class, 'bukti'])->name('pengeluaran.bukti');
	        // K8 — Kelola Kategori Pengeluaran
        Route::get('/kategori-pengeluaran', [KategoriPengeluaranController::class, 'index'])->name('kategori-pengeluaran.index');
        Route::get('/kategori-pengeluaran/create', [KategoriPengeluaranController::class, 'create'])->name('kategori-pengeluaran.create');
        Route::post('/kategori-pengeluaran', [KategoriPengeluaranController::class, 'store'])->name('kategori-pengeluaran.store');
        Route::get('/kategori-pengeluaran/{kategori}/edit', [KategoriPengeluaranController::class, 'edit'])->name('kategori-pengeluaran.edit');
        Route::put('/kategori-pengeluaran/{kategori}', [KategoriPengeluaranController::class, 'update'])->name('kategori-pengeluaran.update');
        Route::post('/kategori-pengeluaran/{kategori}/toggle', [KategoriPengeluaranController::class, 'toggleStatus'])->name('kategori-pengeluaran.toggle');
        Route::delete('/kategori-pengeluaran/{kategori}', [KategoriPengeluaranController::class, 'destroy'])->name('kategori-pengeluaran.destroy');

        // Keuangan — Dana Sukarela Sosial + Dana Hibah
        Route::get('/keuangan', [LabaRugiController::class, 'index'])->name('keuangan.index');
        Route::post('/keuangan/dana-sukarela/masuk', [DanaSukarelaController::class, 'storeMasuk'])->name('dana-sukarela.masuk');
        Route::post('/keuangan/dana-sukarela/keluar', [DanaSukarelaController::class, 'storeKeluar'])->name('dana-sukarela.keluar');
        Route::post('/keuangan/dana-hibah/masuk', [DanaHibahController::class, 'storeMasuk'])->name('dana-hibah.masuk');
        Route::post('/keuangan/dana-hibah/keluar', [DanaHibahController::class, 'storeKeluar'])->name('dana-hibah.keluar');

        // KOREKSI ARUS DANA — TIDAK ADA route hapus. Dana Sosial & Hibah
        // adalah titipan pihak ketiga; saldonya harus selalu bisa ditelusuri,
        // jadi kesalahan dikoreksi lewat perbaikan atau pembatalan berjejak.
        // {jenis} dibatasi 'masuk|keluar' di level route, lalu dipetakan ke
        // model lewat daftar putih di controller.
        Route::get('/keuangan/dana-sukarela/{jenis}/{id}/edit', [DanaSukarelaController::class, 'edit'])
            ->where('jenis', 'masuk|keluar')->name('dana-sukarela.edit');
        Route::get('/keuangan/dana-hibah/{jenis}/{id}/edit', [DanaHibahController::class, 'edit'])
            ->where('jenis', 'masuk|keluar')->name('dana-hibah.edit');
        Route::put('/keuangan/dana-sukarela/{jenis}/{id}', [DanaSukarelaController::class, 'perbarui'])
            ->where('jenis', 'masuk|keluar')->name('dana-sukarela.perbarui');
        Route::post('/keuangan/dana-sukarela/{jenis}/{id}/batalkan', [DanaSukarelaController::class, 'batalkan'])
            ->where('jenis', 'masuk|keluar')->name('dana-sukarela.batalkan');
        Route::put('/keuangan/dana-hibah/{jenis}/{id}', [DanaHibahController::class, 'perbarui'])
            ->where('jenis', 'masuk|keluar')->name('dana-hibah.perbarui');
        Route::post('/keuangan/dana-hibah/{jenis}/{id}/batalkan', [DanaHibahController::class, 'batalkan'])
            ->where('jenis', 'masuk|keluar')->name('dana-hibah.batalkan');

        // Hapus permanen arus dana — hanya untuk baris yang SUDAH dibatalkan.
        Route::delete('/keuangan/dana-sukarela/{jenis}/{id}', [DanaSukarelaController::class, 'destroy'])
            ->where('jenis', 'masuk|keluar')->name('dana-sukarela.destroy');
        Route::delete('/keuangan/dana-hibah/{jenis}/{id}', [DanaHibahController::class, 'destroy'])
            ->where('jenis', 'masuk|keluar')->name('dana-hibah.destroy');

        // Pengaturan Koperasi Cahaya (identitas, logo, TTD, stempel, invoice).
        // Tampilannya sekarang jadi salah satu TAB di /pengaturan, jadi route
        // GET ini DIPERTAHANKAN sebagai alias/redirect supaya bookmark & link
        // lama tidak jadi 404. Route POST-nya TIDAK berubah — form tab Koperasi
        // tetap submit ke sini, dengan validasi & data KoperasiSetting sendiri.
        // ==========================================================
        // SHU — SISA HASIL USAHA
        //
        // Otorisasinya di dalam controller memakai bolehKelolaKoperasi()
        // (Admin + Tim Koperasi) yang sudah dipakai seluruh modul Koperasi.
        // Yang khas hanya `sahkan`: mengunci angka penetapan dibatasi ke
        // Admin, sejalan dengan pemisahan yang sama di modul SROI.
        // ==========================================================
        Route::get('/shu', [ShuController::class, 'index'])->name('shu.index');
        Route::get('/shu/create', [ShuController::class, 'create'])->name('shu.create');
        Route::post('/shu', [ShuController::class, 'store'])->name('shu.store');
        Route::get('/shu/{shu}', [ShuController::class, 'show'])->name('shu.show');
        Route::get('/shu/{shu}/export-pdf', [ShuController::class, 'exportPdf'])->name('shu.export-pdf');
        Route::get('/shu/{shu}/edit', [ShuController::class, 'edit'])->name('shu.edit');
        Route::put('/shu/{shu}', [ShuController::class, 'update'])->name('shu.update');
        Route::post('/shu/{shu}/susun-ulang', [ShuController::class, 'susunUlang'])->name('shu.susun-ulang');
        Route::post('/shu/{shu}/sahkan', [ShuController::class, 'sahkan'])->name('shu.sahkan');
        Route::delete('/shu/{shu}', [ShuController::class, 'destroy'])->name('shu.destroy');

        Route::get('/pengaturan', [KoperasiSettingController::class, 'index'])->name('pengaturan.index');
        Route::post('/pengaturan', [KoperasiSettingController::class, 'update'])->name('pengaturan.update');
	Route::get('/laporan', [LaporanKoperasiController::class, 'index'])->name('laporan.index');
	Route::get('/laporan/ringkasan', [LaporanKoperasiController::class, 'ringkasan'])->name('laporan.ringkasan');
	Route::get('/laporan/ringkasan/pdf', [LaporanKoperasiController::class, 'ringkasanPdf'])->name('laporan.ringkasan.pdf');
	Route::get('/laporan/laba-rugi', [LaporanKoperasiController::class, 'labaRugi'])->name('laporan.laba-rugi'); 
	Route::get('/laporan/laba-rugi/pdf', [LaporanKoperasiController::class, 'labaRugiPdf'])->name('laporan.laba-rugi.pdf'); 
	Route::get('/laporan/penjualan', [LaporanKoperasiController::class, 'penjualan'])->name('laporan.penjualan');
	Route::get('/laporan/penjualan/pdf', [LaporanKoperasiController::class, 'penjualanPdf'])->name('laporan.penjualan.pdf');
	Route::get('/laporan/pengeluaran', [LaporanKoperasiController::class, 'pengeluaran'])->name('laporan.pengeluaran');
	Route::get('/laporan/pengeluaran/pdf', [LaporanKoperasiController::class, 'pengeluaranPdf'])->name('laporan.pengeluaran.pdf');
	Route::get('/laporan/unit-usaha', [LaporanKoperasiController::class, 'unitUsaha'])->name('laporan.unit-usaha');
	Route::get('/laporan/unit-usaha/pdf', [LaporanKoperasiController::class, 'unitUsahaPdf'])->name('laporan.unit-usaha.pdf');
	Route::get('/laporan/keanggotaan', [LaporanKoperasiController::class, 'keanggotaan'])->name('laporan.keanggotaan');
	Route::get('/laporan/keanggotaan/pdf', [LaporanKoperasiController::class, 'keanggotaanPdf'])->name('laporan.keanggotaan.pdf');
	Route::get('/laporan/simpanan', [LaporanKoperasiController::class, 'simpanan'])->name('laporan.simpanan');
	Route::get('/laporan/simpanan/pdf', [LaporanKoperasiController::class, 'simpananPdf'])->name('laporan.simpanan.pdf');
	Route::get('/laporan/dana-sosial', [LaporanKoperasiController::class, 'danaSosial'])->name('laporan.dana-sosial');
	Route::get('/laporan/dana-sosial/pdf', [LaporanKoperasiController::class, 'danaSosialPdf'])->name('laporan.dana-sosial.pdf');
	Route::get('/laporan/ringkasan/excel', [LaporanKoperasiController::class, 'ringkasanExcel'])->name('laporan.ringkasan.excel');
        Route::get('/laporan/laba-rugi/excel', [LaporanKoperasiController::class, 'labaRugiExcel'])->name('laporan.laba-rugi.excel');
        Route::get('/laporan/penjualan/excel', [LaporanKoperasiController::class, 'penjualanExcel'])->name('laporan.penjualan.excel');
        Route::get('/laporan/pengeluaran/excel', [LaporanKoperasiController::class, 'pengeluaranExcel'])->name('laporan.pengeluaran.excel');
        Route::get('/laporan/unit-usaha/excel', [LaporanKoperasiController::class, 'unitUsahaExcel'])->name('laporan.unit-usaha.excel');
        Route::get('/laporan/keanggotaan/excel', [LaporanKoperasiController::class, 'keanggotaanExcel'])->name('laporan.keanggotaan.excel');
        Route::get('/laporan/simpanan/excel', [LaporanKoperasiController::class, 'simpananExcel'])->name('laporan.simpanan.excel');
        Route::get('/laporan/dana-sosial/excel', [LaporanKoperasiController::class, 'danaSosialExcel'])->name('laporan.dana-sosial.excel');

               // Modul K5+ â€” placeholder dulu
        Route::get('/{modul}', [KoperasiPlaceholderController::class, 'show'])
            ->where('modul', 'tidak-ada')
            ->name('placeholder');
    });

    // ==========================================================
    // SISTEM — 3 halaman terpisah, semuanya khusus Admin (guard
    // abort_unless(isAdmin) ada di dalam masing-masing controller).
    // ==========================================================

    // Pengaturan Sistem — TINGGAL System Health, System Information, Activity Log.
    // Backup/Restore sudah pindah ke grup 'backup-database' di bawah.
    Route::prefix('pengaturan-sistem')->name('pengaturan-sistem.')->group(function () {
        Route::get('/', [SystemSettingsController::class, 'index'])->name('index');
    });

    // Manajemen User — akun internal.
    //
    // TIDAK ADA rute penghapusan akun, dan itu disengaja. Siklus akun hanya
    // punya dua arah, keduanya lewat 'status': dinonaktifkan (tidak bisa
    // login, data & riwayat tetap utuh) dan dipulihkan.
    //
    // Rute 'destroy' yang dulu ada memanggil forceDelete(). Seluruh foreign
    // key ke users ber-ON DELETE SET NULL, jadi membuang barisnya membuat
    // Activity Log dan data bisnisnya kehilangan pelaku sebagai relasi —
    // permanen, dan tidak bisa dibatalkan.
    Route::prefix('manajemen-user')->name('manajemen-user.')->group(function () {
        Route::get('/', [ManajemenUserController::class, 'index'])->name('index');
        Route::get('/create', [ManajemenUserController::class, 'create'])->name('create');
        Route::post('/', [ManajemenUserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [ManajemenUserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [ManajemenUserController::class, 'update'])->name('update');
        Route::post('/{user}/status', [ManajemenUserController::class, 'status'])->name('status');
        // Hapus permanen: Admin saja, dan ditolak bila akunnya sudah punya jejak.
        Route::delete('/{user}', [ManajemenUserController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/reset-password', [ManajemenUserController::class, 'resetPassword'])->name('reset-password');
    });

    // Backup Database — halaman sendiri (dipisah dari Pengaturan Sistem).
    Route::prefix('backup-database')->name('backup-database.')->group(function () {
        Route::get('/', [BackupDatabaseController::class, 'index'])->name('index');
        Route::post('/', [BackupDatabaseController::class, 'storeBackup'])->name('store');
        Route::get('/download/{filename}', [BackupDatabaseController::class, 'downloadBackup'])->name('download');
        Route::delete('/destroy/{filename}', [BackupDatabaseController::class, 'destroyBackup'])->name('destroy');
        Route::get('/restore', [BackupDatabaseController::class, 'showRestore'])->name('restore.show');
        Route::post('/restore', [BackupDatabaseController::class, 'restoreDatabase'])->name('restore.store');
    });
});