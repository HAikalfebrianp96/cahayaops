<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\ImpactProgram;
use App\Models\KoperasiMember;
use App\Models\LegalitasRecord;
use App\Models\SystemSetting;
use App\Models\KoperasiSetting;
use App\Models\FinancialProxy;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Models\PendampinganRecord;
use App\Models\Pengeluaran;
use App\Models\SalesTransaction;
use App\Models\TrainingRecord;
use App\Observers\ActivityLogObserver;
use App\Policies\BusinessUnitPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ImpactPolicy;
use App\Policies\KoperasiMemberPolicy;
use App\Policies\MemberSavingPolicy;
use App\Policies\OmzetReportPolicy;
use App\Policies\ParticipantMeasurementPolicy;
use App\Policies\ParticipantPolicy;
use App\Policies\PendampinganRecordPolicy;
use App\Policies\PendampingTargetPolicy;
use App\Policies\TrainingRecordPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->batasiPercobaanLogin();

        // Activity Log — data/transaksi penting (generik lewat Observer).
        // Aktivitas KEAMANAN (login, logout, buat akun, reset/ganti password)
        // DICATAT EKSPLISIT langsung di controller terkait — bukan lewat
        // Observer — supaya deskripsinya jelas ("Reset password", bukan cuma
        // "Updated User") dan TIDAK dobel tercatat.
        Participant::observe(ActivityLogObserver::class);
        Pendamping::observe(ActivityLogObserver::class);
        // Riwayat pendampingan kini bisa diubah & dihapus, jadi perubahannya
        // harus ikut tercatat — lewat Observer yang SAMA, bukan sistem log kedua.
        PendampinganRecord::observe(ActivityLogObserver::class);
        PendampingTarget::observe(ActivityLogObserver::class);
        // Pelatihan, laporan omzet, unit usaha, dan simpanan kini bisa diubah
        // & dihapus dari banyak tempat, jadi perubahannya ikut dicatat — lewat
        // Observer yang SAMA, bukan sistem log kedua.
        TrainingRecord::observe(ActivityLogObserver::class);
        OmzetReport::observe(ActivityLogObserver::class);
        BusinessUnit::observe(ActivityLogObserver::class);
        MemberSaving::observe(ActivityLogObserver::class);
        KoperasiMember::observe(ActivityLogObserver::class);
        SalesTransaction::observe(ActivityLogObserver::class);
        Pengeluaran::observe(ActivityLogObserver::class);
        DanaSukarelaMasuk::observe(ActivityLogObserver::class);
        DanaSukarelaKeluar::observe(ActivityLogObserver::class);
        DanaHibahMasuk::observe(ActivityLogObserver::class);
        DanaHibahKeluar::observe(ActivityLogObserver::class);
        HppCalculation::observe(ActivityLogObserver::class);
        // Baseline/monitoring/endline adalah dasar SELURUH klaim perubahan
        // usaha dan seluruh perhitungan SROI di atasnya. Justru karena itu
        // perubahannya wajib berjejak — lewat Observer yang SAMA.
        ParticipantMeasurement::observe(ActivityLogObserver::class);

        // ============================================================
        // LEGALITAS — sebelumnya TIDAK tercatat sama sekali.
        //
        // Terhitung 84 dokumen legalitas tersimpan tanpa satu pun baris
        // jejak audit. Yang membuatnya lebih dari sekadar kelalaian
        // pembukuan: KPI legalitas menghitung dokumen berstatus
        // "Sudah Ada", jadi angka penilaian seorang pendamping bergantung
        // pada kolom yang bisa ia ubah sendiri.
        //
        // Selama perubahannya tidak tercatat, tidak ada cara membedakan
        // dokumen yang memang terbit dari status yang sekadar diubah — dan
        // tidak ada yang bisa menjelaskannya kalau suatu saat ditanya
        // pemberi dana. Berkasnya pun bisa diganti tanpa bekas.
        //
        // Pencatatan ini TIDAK mengubah perhitungan apa pun; ia hanya
        // meninggalkan jejak.
        // ============================================================
        LegalitasRecord::observe(ActivityLogObserver::class);

        // ============================================================
        // TOMBOL KEBIJAKAN — datanya diaudit, aturannya belum.
        //
        // Sampai sebelum ini, seluruh pencatatan jejak menyasar DATA:
        // peserta, catatan, omzet, simpanan, penjualan. Tidak satu pun
        // menyasar ATURAN yang menentukan arti data itu.
        //
        // Padahal menggeser followup_merah_hari dari 45 ke 90 mengubah
        // status seluruh peserta dan isi daftar kerja seluruh pendamping
        // seketika — tanpa satu baris data pun berubah. Begitu pula nilai
        // acuan rupiah, yang menentukan angka dampak yang dilaporkan ke
        // pemberi dana.
        //
        // Perubahan semacam itu justru yang paling perlu bisa ditelusuri,
        // karena akibatnya paling luas dan paling tidak kelihatan.
        // ============================================================
        SystemSetting::observe(ActivityLogObserver::class);
        KoperasiSetting::observe(ActivityLogObserver::class);
        FinancialProxy::observe(ActivityLogObserver::class);

        // ==========================================================
        // POLICY — otorisasi backend untuk Peserta/Binaan, Riwayat
        // Pendampingan, dan Keanggotaan Koperasi.
        //
        // Didaftarkan EKSPLISIT (bukan mengandalkan penemuan otomatis) supaya
        // terlihat jelas modul mana yang dijaga policy. Isi policy-nya sendiri
        // hanya meneruskan ke helper di model User yang sudah dipakai
        // controller & Blade — jadi tidak ada aturan izin yang tertulis dua
        // kali dan bisa berbeda.
        // ==========================================================
        Gate::policy(Participant::class, ParticipantPolicy::class);
        Gate::policy(PendampinganRecord::class, PendampinganRecordPolicy::class);
        Gate::policy(KoperasiMember::class, KoperasiMemberPolicy::class);
        Gate::policy(TrainingRecord::class, TrainingRecordPolicy::class);
        Gate::policy(OmzetReport::class, OmzetReportPolicy::class);
        Gate::policy(BusinessUnit::class, BusinessUnitPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(PendampingTarget::class, PendampingTargetPolicy::class);
        Gate::policy(MemberSaving::class, MemberSavingPolicy::class);
        Gate::policy(ParticipantMeasurement::class, ParticipantMeasurementPolicy::class);
        // SATU policy untuk SELURUH modul Dampak & SROI (program, investasi,
        // outcome, proxy, perhitungan). Keempatnya bagian dari satu keputusan
        // yang sama — siapa yang boleh menyusun & menerbitkan klaim dampak
        // lembaga — jadi memecahnya hanya membuka peluang salah satunya
        // kelak berbeda dari yang lain.
        Gate::policy(ImpactProgram::class, ImpactPolicy::class);
    }

    /**
     * PEMBATAS PERCOBAAN LOGIN.
     *
     * ==================================================================
     * KENAPA PERLU
     * ==================================================================
     *
     * Halaman Cek Simpanan publik sudah dibatasi (throttle:5,1), tapi
     * halaman login staf tidak dibatasi sama sekali — padahal justru akun
     * Admin-lah yang memegang Manajemen User, Backup, dan Restore Database.
     * Tanpa pembatas, kata sandi bisa ditebak berkali-kali tanpa henti.
     *
     * ==================================================================
     * DIBATASI PER EMAIL + IP, BUKAN PER IP SAJA
     * ==================================================================
     *
     * Seluruh staf YBM PLN UP3 Bengkulu bekerja dari satu kantor, jadi
     * kemungkinan besar berbagi satu alamat IP. Pembatas per-IP akan membuat
     * satu orang yang lupa kata sandinya mengunci SELURUH kantor dari
     * sistem — termasuk Admin yang seharusnya menolong.
     *
     * Dengan email ikut jadi kunci, yang terkunci hanya percobaan terhadap
     * akun yang sedang ditebak.
     */
    private function batasiPercobaanLogin(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $kunci = Str::lower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($kunci)->response(function () {
                return back()
                    ->withErrors([
                        'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam satu menit.',
                    ])
                    ->withInput(request()->except('password'));
            });
        });
    }
}
