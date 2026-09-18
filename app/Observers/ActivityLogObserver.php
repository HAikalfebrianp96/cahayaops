<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\FinancialProxy;
use App\Models\KoperasiMember;
use App\Models\KoperasiSetting;
use App\Models\LegalitasRecord;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\SystemSetting;
use App\Models\ParticipantMeasurement;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\TrainingRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogObserver
{
    /**
     * Nama aksi yang SPESIFIK per model.
     *
     * Model yang TIDAK terdaftar di sini tetap memakai nama generik lama
     * (created/updated/deleted) — jadi pencatatan Penjualan, Pengeluaran,
     * Dana Sukarela, Dana Hibah, HPP, dan Pendamping TIDAK berubah sama
     * sekali. Yang diberi nama spesifik hanyalah entitas yang sekarang bisa
     * diubah, dihapus, dan dipulihkan dari banyak tempat, sehingga log
     * "Updated Participant" saja tidak lagi cukup untuk ditelusuri.
     */
    private const AKSI_SPESIFIK = [
        Participant::class => 'participant',
        PendampinganRecord::class => 'mentoring',
        TrainingRecord::class => 'training',
        OmzetReport::class => 'omzet',
        PendampingTarget::class => 'target',
        KoperasiMember::class => 'koperasi_member',
        MemberSaving::class => 'koperasi_saving',
        BusinessUnit::class => 'business_unit',
        ParticipantMeasurement::class => 'measurement',
        // Dokumen legalitas diberi nama spesifik karena statusnya ikut
        // menentukan skor KPI pendamping — lihat alasan lengkapnya di
        // pendaftaran observer-nya (AppServiceProvider).
        LegalitasRecord::class => 'legalitas',
        // Tombol kebijakan — lihat penjelasannya di describe().
        SystemSetting::class => 'pengaturan_sistem',
        KoperasiSetting::class => 'pengaturan_koperasi',
        FinancialProxy::class => 'nilai_acuan',
    ];

    /**
     * KOLOM KEBIJAKAN — yang perubahannya menggeser arti seluruh angka.
     *
     * Berbeda dari nama lembaga atau logo: mengubah salah satu angka di
     * bawah langsung menggeser status SELURUH peserta dan skor SELURUH
     * pendamping, tanpa ada satu baris data pun yang berubah.
     *
     * Karena itu yang dicatat bukan sekadar "pengaturan diubah", melainkan
     * dari berapa menjadi berapa.
     */
    private const KOLOM_KEBIJAKAN = [
        'followup_kuning_hari' => 'ambang perlu perhatian',
        'followup_merah_hari' => 'ambang terlambat',
        'peserta_aktif_hari' => 'batas peserta aktif',
        'peserta_perlu_follow_up_hari' => 'batas peserta tidak aktif',
        'beban_ringan_maks' => 'batas beban ringan',
        'beban_normal_maks' => 'batas beban normal',
        'beban_tinggi_maks' => 'batas beban tinggi',
        'target_default_binaan' => 'target bawaan binaan',
        'target_default_kunjungan' => 'target bawaan kunjungan',
        'target_default_pendampingan' => 'target bawaan pendampingan',
        'target_default_follow_up' => 'target bawaan follow up',
    ];

    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->log('updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    /**
     * Soft delete yang dibatalkan. Hook ini hanya pernah terpanggil untuk
     * model ber-SoftDeletes; untuk model lain Eloquent tidak pernah
     * memicunya, jadi menambahkannya tidak mengubah perilaku yang sudah ada.
     */
    public function restored(Model $model): void
    {
        $this->log('restored', $model);
    }

    private function log(string $action, Model $model): void
    {
        // Jangan sampai proses logging sendiri menyebabkan error mengganggu
        // aksi utama (misal Simpan Peserta) — dibungkus try/catch, gagal
        // logging TIDAK boleh menggagalkan aksi aslinya.
        try {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $this->namaAksi($action, $model),
                'model_type' => class_basename($model),
                'model_id' => $model->getKey(),
                'description' => $this->describe($action, $model),
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            // Sengaja diam — logging tidak boleh menjatuhkan aksi utama.
        }
    }

    /**
     * participant_created, mentoring_updated, koperasi_member_deleted, ...
     * atau nama generik lama untuk model di luar daftar.
     */
    private function namaAksi(string $action, Model $model): string
    {
        $prefix = self::AKSI_SPESIFIK[$model::class] ?? null;

        return $prefix ? $prefix . '_' . $action : $action;
    }

    /**
     * Coba ambil nama/identitas yang enak dibaca dari model apapun —
     * tidak semua model punya field yang sama, jadi dicoba beberapa
     * kemungkinan umum, fallback ke ID kalau tidak ketemu.
     */
    private function describe(string $action, Model $model): string
    {
        // ============================================================
        // PENGATURAN — dicatat DARI BERAPA MENJADI BERAPA.
        //
        // "Pengaturan diubah" tidak menolong siapa pun. Yang ingin
        // diketahui orang saat menelusuri justru nilainya: kenapa bulan
        // lalu seluruh binaan mendadak berstatus merah? Jawabannya bisa
        // jadi bukan di data peserta, melainkan di ambang yang digeser.
        // ============================================================
        if ($model instanceof SystemSetting) {
            $berubah = [];

            foreach ($model->getChanges() as $kolom => $baru) {
                if (! array_key_exists($kolom, self::KOLOM_KEBIJAKAN)) {
                    continue;
                }

                $lama = $model->getOriginal($kolom);

                $berubah[] = self::KOLOM_KEBIJAKAN[$kolom] . ': '
                    . ($lama === null ? 'bawaan' : $lama)
                    . ' -> ' . ($baru === null ? 'bawaan' : $baru);
            }

            return $berubah === []
                ? ucfirst($action) . ' Pengaturan Sistem'
                : ucfirst($action) . ' Pengaturan Sistem — ' . implode(', ', $berubah);
        }

        if ($model instanceof FinancialProxy) {
            // Nilai acuan menentukan angka dampak yang dilaporkan ke pemberi
            // dana. Mengubahnya mengubah hasil SROI tanpa satu pun data
            // peserta bergerak.
            return ucfirst($action) . ' Nilai Acuan: ' . ($model->nama ?? '#' . $model->getKey())
                . ' = Rp' . number_format((float) ($model->nilai ?? 0), 0, ',', '.')
                . ($model->sumber ? ' (sumber: ' . $model->sumber . ')' : '');
        }

        if ($model instanceof PendampinganRecord) {
            return ucfirst($action) . ' Riwayat Pendampingan: '
                . optional($model->tanggal)->format('d-m-Y') . ' — ' . $model->jenis_kegiatan
                . ' (peserta: ' . ($model->participant?->nama_peserta ?? '#' . $model->participant_id) . ')';
        }

        if ($model instanceof TrainingRecord) {
            return ucfirst($action) . ' Pelatihan: ' . $model->nama_pelatihan
                . ' (' . $model->status . ', peserta: '
                . ($model->participant?->nama_peserta ?? '#' . $model->participant_id) . ')';
        }

        if ($model instanceof OmzetReport) {
            return ucfirst($action) . ' Laporan Omzet ' . $model->periodeLabel()
                . ': Rp' . number_format((float) $model->omzet, 0, ',', '.')
                . ' (peserta: ' . ($model->participant?->nama_peserta ?? '#' . $model->participant_id) . ')';
        }

        if ($model instanceof MemberSaving) {
            return ucfirst($action) . ' ' . $model->jenis_simpanan
                . ': Rp' . number_format((float) $model->nominal, 0, ',', '.')
                . ' (' . $model->status . ', anggota: '
                . ($model->koperasiMember?->namaTampil() ?? '#' . $model->koperasi_member_id) . ')';
        }

        if ($model instanceof PendampingTarget) {
            return ucfirst($action) . ' Target Pendamping: ' . ($model->pendamping?->nama ?? '#' . $model->pendamping_id)
                . ' — ' . $model->periodeLabel();
        }

        if ($model instanceof KoperasiMember) {
            return ucfirst($action) . ' Anggota Koperasi: ' . $model->namaTampil()
                . ' (' . $model->nomor_anggota . ')'
                . ($model->dariPeserta() ? ' — dari peserta Cahaya Preneur' : '');
        }

        $label = $model->nama
            ?? $model->name
            ?? $model->nama_peserta
            ?? $model->nama_produk
            ?? $model->nomor_transaksi
            ?? $model->nama_usaha
            ?? ('#' . $model->getKey());

        return ucfirst($action) . ' ' . class_basename($model) . ': ' . $label;
    }
}
