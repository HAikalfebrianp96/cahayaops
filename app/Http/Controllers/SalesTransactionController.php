<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AkunKas;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Services\PembayaranPenjualanService;
use App\Services\PenghapusanPermanenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesTransactionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView();

        // Relasi ...TermasukArsip, bukan relasi biasa.
        //
        // Sebelumnya daftar ini sengaja menampilkan "—" untuk unit atau
        // pelanggan yang diarsipkan: tanda itu berarti "datanya ada, lihat di
        // Arsip Data". Sejak halaman Arsip dilepas, tidak ada lagi tempat itu
        // — dan "—" berubah arti menjadi "tidak ada datanya".
        //
        // Akibatnya nyata di koperasi ini: ketiga transaksinya menampilkan
        // kolom pelanggan kosong, karena ketiga pelanggannya terarsip.
        // Petugas harus membuka detail satu per satu hanya untuk tahu siapa
        // yang membeli.
        $query = SalesTransaction::with(['businessUnitTermasukArsip', 'customerTermasukArsip', 'invoice']);

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->date('tanggal'));
        }
        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal', $request->integer('bulan'));
        }
        if ($request->filled('tahun')) {
            $query->whereYear('tanggal', $request->integer('tahun'));
        }
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->integer('unit_id'));
        }
        if ($request->filled('status_pembayaran')) {
            $query->where('status_pembayaran', $request->string('status_pembayaran'));
        }

        // SARINGAN KEADAAN TRANSAKSI.
        //
        // Bawaannya SEMUA, bukan hanya yang aktif. Daftar ini adalah layar
        // kerja, bukan laporan keuangan: operator yang mencari transaksi
        // tertentu justru sering mencari yang barusan dibatalkannya. Laporan
        // dan laba rugi punya penyaringnya sendiri dan tidak terpengaruh
        // pilihan di sini.
        $keadaan = $request->string('keadaan')->toString();
        if ($keadaan === 'aktif') {
            $query->where('status_transaksi', '!=', SalesTransaction::STATUS_DIBATALKAN);
        } elseif ($keadaan === 'dibatalkan') {
            $query->where('status_transaksi', SalesTransaction::STATUS_DIBATALKAN);
        }

        $transaksi = $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('koperasi.penjualan.index', [
            'transaksi' => $transaksi,
            'units' => BusinessUnit::orderBy('nama')->get(),
            'statusOptions' => SalesTransaction::STATUS_PEMBAYARAN_OPTIONS,
            'filters' => $request->only(['tanggal', 'bulan', 'tahun', 'unit_id', 'status_pembayaran', 'keadaan']),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeManage();

        if (! $request->filled('unit')) {
            return view('koperasi.penjualan.pilih-unit', [
                'units' => BusinessUnit::where('status', 'Aktif')->orderBy('nama')->get(),
            ]);
        }

        $unit = BusinessUnit::findOrFail($request->integer('unit'));

        return view('koperasi.penjualan.create', array_merge(
            $this->formData($unit),
            ['transaksi' => null]
        ));
    }

    public function store(Request $request, PembayaranPenjualanService $pembayaran)
    {
        $this->authorizeManage();

        $validated = $this->validatedRequest($request);
        $unit = BusinessUnit::findOrFail($validated['unit_id']);
        $itemsToSave = $this->buildItemsFromRequest($validated, $unit->id);
        [$subtotal, $diskon, $ongkir, $total] = $this->hitungTotal($validated, $itemsToSave);
        $dpNominal = $this->hitungDibayar($validated, $total);
        $buatInvoice = ($validated['action'] ?? 'simpan') === 'invoice';

        $transaksi = DB::transaction(function () use ($validated, $itemsToSave, $subtotal, $diskon, $ongkir, $total, $dpNominal, $buatInvoice, $pembayaran) {
            $customer = $this->resolveCustomer($validated);

            $trx = SalesTransaction::create([
                'unit_id' => $validated['unit_id'],
                'customer_id' => $customer->id,
                'tanggal' => $validated['tanggal'],
                'status_transaksi' => $validated['status_transaksi'],
                'subtotal' => $subtotal,
                'diskon' => $diskon,
                'ongkir' => $ongkir,
                'ongkir_ditanggung' => $validated['ongkir_ditanggung'] ?? 'Pelanggan',
                'total' => $total,
                'status_pembayaran' => $validated['status_pembayaran'],
                // Diisi PembayaranPenjualanService di bawah, dari riwayat pembayaran.
                'dp_nominal' => 0,
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'metode_pengiriman' => $validated['metode_pengiriman'],
                'nama_ekspedisi' => $validated['nama_ekspedisi'] ?? null,
                'nomor_resi' => $validated['nomor_resi'] ?? null,
                'alamat_pengiriman' => $validated['alamat_pengiriman'] ?? null,
                'hp_penerima' => $validated['hp_penerima'] ?? null,
                'catatan_pengiriman' => $validated['catatan_pengiriman'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($itemsToSave as $item) {
                $trx->items()->create($item);
            }

            // Items dulu, baru pembayaran: total transaksi sudah final di sini.
            $pembayaran->sesuaikan(
                $trx,
                $this->targetDibayar($validated, $total, $dpNominal),
                $this->akunPembayaran($validated, $pembayaran),
                $validated['metode_pembayaran'],
                $trx->tanggal->toDateString(),
                'Dibayar saat transaksi dicatat.',
            );

            if ($buatInvoice) {
                Invoice::create([
                    'sales_transaction_id' => $trx->id,
                    'tanggal_invoice' => $validated['tanggal'],
                ]);
            }

            return $trx;
        });

        $pesan = $buatInvoice
            ? 'Transaksi berhasil disimpan. Invoice ' . $transaksi->invoice->nomor_invoice . ' otomatis dibuat.'
            : 'Transaksi berhasil disimpan. Anda bisa buat invoice kapan saja dari halaman detail.';

        return redirect()->route('koperasi.penjualan.show', $transaksi)
            ->with('success', $pesan . $this->pesanKembalian($dpNominal, $total))
            ->with('warning', $this->peringatanHpp($itemsToSave));
    }

    public function edit(SalesTransaction $transaksi)
    {
        $this->authorizeManage();
        abort_if($transaksi->status_transaksi === 'Dibatalkan', 403, 'Transaksi yang sudah dibatalkan tidak dapat diedit.');

        // businessUnitTermasukArsip: transaksi lama tetap punya unitnya walau
        // unit itu sudah diarsipkan. Tanpa ini, mengarsipkan satu unit usaha
        // membuat halaman ini gagal dibuka untuk seluruh transaksi lamanya.
        $transaksi->load(['items', 'customer', 'businessUnitTermasukArsip']);

        return view('koperasi.penjualan.edit', array_merge(
            $this->formData($transaksi->businessUnitTermasukArsip, $transaksi),
            ['transaksi' => $transaksi]
        ));
    }

    public function update(Request $request, SalesTransaction $transaksi, PembayaranPenjualanService $pembayaran)
    {
        $this->authorizeManage();
        abort_if($transaksi->status_transaksi === 'Dibatalkan', 403, 'Transaksi yang sudah dibatalkan tidak dapat diedit.');

        $validated = $this->validatedRequest($request);
        $itemsToSave = $this->buildItemsFromRequest($validated, $transaksi->unit_id);
        [$subtotal, $diskon, $ongkir, $total] = $this->hitungTotal($validated, $itemsToSave);
        $dpNominal = $this->hitungDibayar($validated, $total);

        // Dibaca SEBELUM diganti — untuk jejak perubahan item di bawah.
        $itemLama = $transaksi->items()->orderBy('id')->get();

        DB::transaction(function () use ($transaksi, $validated, $itemsToSave, $subtotal, $diskon, $ongkir, $total, $dpNominal, $itemLama, $pembayaran) {
            // SEBELUM transaksinya diubah — lihat pastikanRiwayatAwal().
            $pembayaran->pastikanRiwayatAwal($transaksi);

            $customer = $this->resolveCustomer($validated);

            $transaksi->update([
                'customer_id' => $customer->id,
                'tanggal' => $validated['tanggal'],
                'status_transaksi' => $validated['status_transaksi'],
                'subtotal' => $subtotal,
                'diskon' => $diskon,
                'ongkir' => $ongkir,
                'ongkir_ditanggung' => $validated['ongkir_ditanggung'] ?? 'Pelanggan',
                'total' => $total,
                'status_pembayaran' => $validated['status_pembayaran'],
                // dp_nominal TIDAK diisi dari form — diturunkan dari riwayat
                // pembayaran lewat sesuaikan() di bawah.
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'metode_pengiriman' => $validated['metode_pengiriman'],
                'nama_ekspedisi' => $validated['nama_ekspedisi'] ?? null,
                'nomor_resi' => $validated['nomor_resi'] ?? null,
                'alamat_pengiriman' => $validated['alamat_pengiriman'] ?? null,
                'hp_penerima' => $validated['hp_penerima'] ?? null,
                'catatan_pengiriman' => $validated['catatan_pengiriman'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
            ]);

            // Item lama diganti bersih — invoice (kalau sudah ada) otomatis ikut ter-update
            // karena selalu membaca data transaksi yang hidup, bukan salinan.
            $transaksi->items()->delete();
            foreach ($itemsToSave as $item) {
                $transaksi->items()->create($item);
            }

            $this->catatPerubahanItem($transaksi, $itemLama, $itemsToSave);

            // Tambahan pembayaran lewat edit dicatat bertanggal HARI INI —
            // hari uangnya dicatat. Pelunasan dengan tanggal lain dicatat
            // lewat "Terima Pembayaran" di halaman detail.
            $pembayaran->sesuaikan(
                $transaksi,
                $this->targetDibayar($validated, $total, $dpNominal),
                $this->akunPembayaran($validated, $pembayaran),
                $validated['metode_pembayaran'],
                max(now()->toDateString(), $transaksi->tanggal->toDateString()),
                'Tambahan pembayaran lewat edit transaksi.',
            );
        });

        return redirect()
            ->route('koperasi.penjualan.show', $transaksi)
            ->with('success', 'Transaksi berhasil diperbarui.' . $this->pesanKembalian($dpNominal, $total))
            ->with('warning', $this->peringatanHpp($itemsToSave));
    }

    /**
     * Gabungan peringatan HPP > harga jual untuk seluruh baris — null kalau
     * semua baris wajar. Transaksinya TETAP tersimpan; ini hanya pengingat.
     */
    private function peringatanHpp(array $itemsToSave): ?string
    {
        $pesan = collect($itemsToSave)
            ->map(fn (array $item) => SalesTransactionItem::peringatanHpp(
                $item['nama_snapshot'],
                (float) $item['harga_modal_snapshot'],
                (float) $item['harga_jual_snapshot'],
            ))
            ->filter()
            ->values();

        return $pesan->isEmpty() ? null : $pesan->implode(' ');
    }

    /**
     * JEJAK PERUBAHAN ITEM — HPP, harga, qty dari berapa menjadi berapa.
     *
     * Item transaksi diganti bersih setiap kali disimpan, dan
     * SalesTransactionItem tidak diawasi Observer. Akibatnya, mengoreksi HPP
     * satu baris tanpa menyentuh kolom transaksi lain tidak meninggalkan
     * jejak APA PUN: Eloquent tidak memicu "updated" untuk baris induk yang
     * tidak berubah. Padahal HPP itulah yang menentukan laba rugi.
     *
     * Baris lama dipasangkan dengan baris baru berdasarkan produk + nama,
     * sesuai urutannya. Dicatat lewat ActivityLog::record() yang sudah ada.
     */
    private function catatPerubahanItem(SalesTransaction $transaksi, Collection $itemLama, array $itemBaru): void
    {
        $kolom = [
            'qty' => 'qty',
            'harga_jual_snapshot' => 'harga jual',
            'harga_modal_snapshot' => 'HPP',
            'diskon' => 'diskon',
            'hak_mitra_amount' => 'hak mitra',
        ];

        $kunci = fn ($item) => ($item['product_service_id'] ?? '') . '|' . $item['nama_snapshot'];
        $lama = $itemLama->map(fn ($item) => $item->only(array_merge(array_keys($kolom), ['product_service_id', 'nama_snapshot'])))
            ->groupBy($kunci);
        $baru = collect($itemBaru)->groupBy($kunci);

        $perubahan = [];

        foreach ($lama->keys()->merge($baru->keys())->unique() as $k) {
            $daftarLama = $lama->get($k, collect())->values();
            $daftarBaru = $baru->get($k, collect())->values();

            for ($i = 0; $i < max($daftarLama->count(), $daftarBaru->count()); $i++) {
                $l = $daftarLama->get($i);
                $b = $daftarBaru->get($i);
                $nama = ($b ?? $l)['nama_snapshot'];

                if ($l === null) {
                    $perubahan[] = $nama . ' ditambahkan';
                    continue;
                }

                if ($b === null) {
                    $perubahan[] = $nama . ' dihapus';
                    continue;
                }

                $bagian = [];
                foreach ($kolom as $field => $label) {
                    $nilaiLama = (float) ($l[$field] ?? 0);
                    $nilaiBaru = (float) ($b[$field] ?? 0);

                    if (abs($nilaiLama - $nilaiBaru) < 0.005) {
                        continue;
                    }

                    $bagian[] = $field === 'qty'
                        ? $label . ' ' . (int) $nilaiLama . ' -> ' . (int) $nilaiBaru
                        : $label . ' ' . rupiah($nilaiLama) . ' -> ' . rupiah($nilaiBaru);
                }

                if ($bagian !== []) {
                    $perubahan[] = $nama . ' ' . implode(', ', $bagian);
                }
            }
        }

        if ($perubahan === []) {
            return;
        }

        ActivityLog::record(
            action: 'sales_items_updated',
            modelType: 'SalesTransaction',
            modelId: $transaksi->id,
            description: Str::limit('Item penjualan ' . $transaksi->nomor_transaksi . ' diubah: ' . implode('; ', $perubahan), 250),
        );
    }

    /**
     * PEMBATALAN TRANSAKSI — bukan penghapusan.
     *
     * Penjualan adalah uang: barisnya sudah masuk laba rugi, laporan
     * penjualan, dan mungkin invoice yang sudah dicetak untuk pembeli.
     * Menghapusnya berarti mengubah semua itu tanpa jejak.
     *
     * Polanya SENGAJA disamakan dengan koreksi simpanan dan pembatalan
     * pengeluaran yang sudah ada: statusnya jadi "Dibatalkan", berhenti
     * dihitung ke laporan, tapi barisnya TETAP ADA lengkap dengan alasan,
     * waktu, dan pelakunya.
     */
    public function batalkan(Request $request, SalesTransaction $transaksi)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        abort_if(
            $transaksi->status_transaksi === SalesTransaction::STATUS_DIBATALKAN,
            403,
            'Transaksi ini sudah dibatalkan.'
        );

        $validated = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan_pembatalan.required' => 'Alasan pembatalan wajib diisi — inilah yang membuat pembatalan bisa dipertanggungjawabkan.',
        ]);

        $transaksi->forceFill([
            'status_transaksi' => SalesTransaction::STATUS_DIBATALKAN,
            'alasan_pembatalan' => $validated['alasan_pembatalan'],
            'dibatalkan_at' => now(),
            'dibatalkan_oleh' => Auth::id(),
        ])->save();

        ActivityLog::record(
            action: 'sales_transaction_cancelled',
            modelType: 'SalesTransaction',
            modelId: $transaksi->id,
            description: 'Transaksi ' . $transaksi->nomor_transaksi . ' dibatalkan. Alasan: ' . $validated['alasan_pembatalan'],
        );

        return redirect()
            ->route('koperasi.penjualan.show', $transaksi)
            ->with('success', 'Transaksi dibatalkan. Datanya tetap tersimpan sebagai jejak dan berhenti dihitung di laporan.');
    }

    /**
     * PULIHKAN transaksi yang dibatalkan.
     *
     * ======================================================================
     * KENAPA PEMULIHAN PERLU ADA, DAN KENAPA HARUS HATI-HATI
     * ======================================================================
     *
     * Pembatalan dipakai untuk dua hal yang berbeda: transaksi yang memang
     * batal, dan transaksi yang dibatalkan KARENA SALAH KLIK. Tanpa jalan
     * pulang, kesalahan tipe kedua hanya bisa diperbaiki dengan membuat
     * transaksi baru — dan itu menghasilkan nomor transaksi baru, tanggal
     * baru, serta satu baris tambahan di laporan yang sebenarnya tidak
     * pernah terjadi. Kerapian laporan justru rusak oleh perbaikannya.
     *
     * TIDAK ADA RISIKO PENGHITUNGAN GANDA di sini, dan itu bukan kebetulan:
     * status transaksi adalah SATU nilai, bukan penanda yang ditambahkan.
     * Memulihkan berarti mengembalikan nilai itu ke keadaan semula, jadi
     * satu transaksi tidak mungkin terhitung dua kali betapapun sering
     * tombolnya ditekan. Perlindungan sesungguhnya ada pada abort_if di
     * bawah: transaksi yang tidak sedang dibatalkan tidak bisa "dipulihkan"
     * lagi, sehingga statusnya tidak bisa tertimpa diam-diam.
     *
     * Status yang dikembalikan adalah 'Aktif', bukan status sebelum
     * pembatalan. Alasannya jujur: status lama tidak pernah disimpan, dan
     * menebaknya (Selesai? Draft?) lebih berbahaya daripada mengembalikan ke
     * keadaan yang paling netral lalu membiarkan operator menyesuaikannya.
     */
    public function pulihkan(SalesTransaction $transaksi)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        abort_unless(
            $transaksi->status_transaksi === SalesTransaction::STATUS_DIBATALKAN,
            403,
            'Transaksi ini tidak sedang dibatalkan, jadi tidak ada yang perlu dipulihkan.'
        );

        $alasanLama = $transaksi->alasan_pembatalan;

        $transaksi->forceFill([
            'status_transaksi' => 'Aktif',
            // Jejak pembatalan dibersihkan dari barisnya supaya laporan tidak
            // menampilkan transaksi aktif yang mencantumkan alasan pembatalan.
            // Riwayat lengkapnya TIDAK hilang — tersimpan di Activity Log,
            // termasuk alasan pembatalan lamanya yang ikut dicatat di bawah.
            'alasan_pembatalan' => null,
            'dibatalkan_at' => null,
            'dibatalkan_oleh' => null,
        ])->save();

        ActivityLog::record(
            action: 'sales_transaction_restored',
            modelType: 'SalesTransaction',
            modelId: $transaksi->id,
            description: 'Transaksi ' . $transaksi->nomor_transaksi . ' dipulihkan dan kembali dihitung di laporan.'
                . ($alasanLama ? ' Alasan pembatalan sebelumnya: ' . $alasanLama : ''),
        );

        return redirect()
            ->route('koperasi.penjualan.show', $transaksi)
            ->with('success', 'Transaksi dipulihkan dan kembali dihitung di laporan aktif.');
    }

    public function show(SalesTransaction $transaksi)
    {
        $this->authorizeView();

        // Relasi arsip-inklusif: halaman riwayat harus tetap menyebut nama
        // unit dan pelanggannya, bukan berubah jadi "—" begitu keduanya
        // diarsipkan. Kehilangan identitas di layar histori sama merugikannya
        // dengan halaman yang gagal terbuka, dan jauh lebih mudah lolos.
        $transaksi->load(['businessUnitTermasukArsip', 'customerTermasukArsip', 'items.productService', 'invoice', 'creator', 'pembayaran.akunKas', 'pembayaran.pembatal']);

        return view('koperasi.penjualan.show', [
            'transaksi' => $transaksi,
            'akunKasOptions' => AkunKas::aktif()->orderBy('jenis')->orderBy('nama')->get(),
            'metodeOptions' => SalesTransaction::METODE_PEMBAYARAN_OPTIONS,
            'akunBawaanId' => app(PembayaranPenjualanService::class)->akunBawaan($transaksi->metode_pembayaran)?->id,
        ]);
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    /**
     * Bahan untuk form Buat & Koreksi transaksi.
     *
     * $unit BOLEH null, dan itu bukan sekadar jaga-jaga: sebuah transaksi
     * lama bisa saja menunjuk unit usaha yang barisnya sudah benar-benar
     * hilang, dan halaman koreksinya tetap harus bisa dibuka — justru
     * transaksi seperti itulah yang paling perlu diperbaiki manusia.
     *
     * Yang TIDAK dilakukan di sini: menebak unit pengganti. Menautkan
     * transaksi ke unit lain diam-diam akan memindahkan angka penjualannya
     * ke laporan laba rugi unit yang salah.
     */
    private function formData(?BusinessUnit $unit, ?SalesTransaction $transaksi = null): array
    {
        // Tanpa unit tidak ada katalog produk yang bisa ditawarkan. Daftarnya
        // dimulai kosong lalu diisi produk yang MEMANG sudah ada di transaksi
        // ini (lihat perulangan di bawah), supaya barisnya tetap tampil utuh
        // dan tidak berubah jadi baris kosong saat disimpan ulang.
        $produkAktif = $unit
            ? $unit->productsServices()->where('status', 'Aktif')->orderBy('nama')->get()
            : collect();

        foreach ($transaksi->items ?? [] as $item) {
            if ($item->product_service_id && ! $produkAktif->contains('id', $item->product_service_id)) {
                // withTrashed: produk yang sudah diarsipkan tetap harus muncul
                // di barisnya sendiri, kalau tidak baris itu tampak kosong dan
                // ikut terhapus begitu form disimpan.
                $produkLama = ProductService::withTrashed()->find($item->product_service_id);
                if ($produkLama) {
                    $produkAktif->push($produkLama);
                }
            }
        }

        return [
            'unit' => $unit,
            'produkList' => $produkAktif,
            'customers' => Customer::orderBy('nama')->get(),
            'statusOptions' => SalesTransaction::STATUS_PEMBAYARAN_OPTIONS,
            'metodeOptions' => SalesTransaction::METODE_PEMBAYARAN_OPTIONS,
            'akunKasOptions' => AkunKas::aktif()->orderBy('jenis')->orderBy('nama')->get(),
            'statusTransaksiOptions' => $this->statusDariForm(),
            'metodePengirimanOptions' => SalesTransaction::METODE_PENGIRIMAN_OPTIONS,
            'ongkirDitanggungOptions' => SalesTransaction::ONGKIR_DITANGGUNG_OPTIONS,
        ];
    }

    /**
     * Status yang boleh dipilih lewat form Tambah/Edit — TANPA "Dibatalkan".
     *
     * Pembatalan punya jalurnya sendiri (batalkan()) yang MEWAJIBKAN alasan,
     * mencatat siapa & kapan, dan menulis Activity Log. Kalau "Dibatalkan"
     * bisa dipilih dari dropdown, seluruh pengaman itu terlewati — dan
     * transaksinya langsung memenuhi syarat Hapus Permanen tanpa satu pun
     * keterangan tentang uang yang hilang dari laporan.
     */
    private function statusDariForm(): array
    {
        return array_values(array_diff(
            SalesTransaction::STATUS_TRANSAKSI_OPTIONS,
            [SalesTransaction::STATUS_DIBATALKAN],
        ));
    }

    private function validatedRequest(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['required', 'exists:business_units,id'],
            'tanggal' => ['required', 'date'],
            'status_transaksi' => ['required', Rule::in($this->statusDariForm())],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_nama' => ['nullable', 'string', 'max:255'],
            'customer_hp' => ['nullable', 'string', 'max:30'],
            'customer_alamat' => ['nullable', 'string'],
            'customer_email' => ['nullable', 'email', 'max:255'],

            'items_master' => ['nullable', 'array'],
            'items_master.*.product_id' => ['nullable', 'exists:products_services,id'],
            'items_master.*.qty' => ['nullable', 'integer', 'min:0'],
            'items_master.*.harga_jual' => ['nullable', 'numeric', 'min:0'],
            'items_master.*.harga_modal' => ['nullable', 'numeric', 'min:0'],
            'items_master.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items_master.*.hak_mitra_per_unit' => ['nullable', 'numeric', 'min:0'],

            'items_manual' => ['nullable', 'array'],
            'items_manual.*.nama' => ['nullable', 'string', 'max:255'],
            'items_manual.*.jenis' => ['nullable', Rule::in(['Produk', 'Jasa'])],
            'items_manual.*.qty' => ['nullable', 'integer', 'min:0'],
            'items_manual.*.harga_jual' => ['nullable', 'numeric', 'min:0'],
            'items_manual.*.harga_modal' => ['nullable', 'numeric', 'min:0'],
            'items_manual.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items_manual.*.catatan' => ['nullable', 'string'],
            'items_manual.*.hak_mitra_per_unit' => ['nullable', 'numeric', 'min:0'],

            'diskon' => ['nullable', 'numeric', 'min:0'],
            'ongkir' => ['nullable', 'numeric', 'min:0'],
            'ongkir_ditanggung' => ['nullable', Rule::in(SalesTransaction::ONGKIR_DITANGGUNG_OPTIONS)],
            'metode_pengiriman' => ['required', Rule::in(SalesTransaction::METODE_PENGIRIMAN_OPTIONS)],
            'nama_ekspedisi' => ['nullable', 'string', 'max:255'],
            'nomor_resi' => ['nullable', 'string', 'max:255'],
            'alamat_pengiriman' => ['nullable', 'string'],
            'hp_penerima' => ['nullable', 'string', 'max:30'],
            'catatan_pengiriman' => ['nullable', 'string'],
            'status_pembayaran' => ['required', Rule::in(SalesTransaction::STATUS_PEMBAYARAN_OPTIONS)],
            'metode_pembayaran' => ['required', Rule::in(SalesTransaction::METODE_PEMBAYARAN_OPTIONS)],
            'nominal_dibayar' => ['nullable', 'numeric', 'min:0'],
            'akun_kas_id' => ['nullable', Rule::exists('akun_kas', 'id')->where('status', AkunKas::STATUS_AKTIF)],
            'catatan' => ['nullable', 'string'],
            'action' => ['sometimes', Rule::in(['simpan', 'invoice'])],
        ]);
    }

    /**
     * Hitung fee_rate/fee_amount/hak_mitra_amount SATU TEMPAT SAJA, dipakai
     * bareng utk item Master maupun item Manual — supaya logikanya konsisten
     * & tidak mudah "lupa" salah satu jalur (ini penyebab bug sebelumnya:
     * kolomnya sudah ada di database, tapi tidak pernah diisi).
     */
    /**
     * HAK MITRA — satu angka, diisi langsung per unit.
     *
     * =====================================================================
     * YANG DIGANTI
     * =====================================================================
     *
     * Dulu baris ini menuntut tiga keputusan untuk satu angka: pilih jenis
     * ("Milik Koperasi" atau "Titip Jual"), lalu isi persentase fee koperasi,
     * lalu hak mitra dihitung sebagai SISANYA (subtotal dikurangi fee).
     *
     * Akibat sampingnya tidak kelihatan dari form: item Titip Jual
     * dikeluarkan dari Subtotal dan HPP koperasi, sehingga dua transaksi
     * dengan barang dan harga yang sama bisa menghasilkan angka penjualan
     * yang berbeda hanya karena jenisnya dipilih berbeda.
     *
     * Sekarang cukup satu isian: berapa hak mitra per unit. Kosong berarti
     * tidak ada mitra.
     *
     * =====================================================================
     * TIDAK IKUT MENGURANGI PROFIT
     * =====================================================================
     *
     * Profit dihitung dari Harga Jual dikurangi Modal/HPP. Hak mitra dicatat
     * berdampingan, bukan dikurangkan lagi — kalau modalnya memang sudah
     * mewakili biaya yang sama, satu biaya akan terhitung dua kali.
     *
     * fee_rate dan fee_amount dikembalikan 0 dan kolomnya TIDAK dihapus:
     * membuang kolom berarti membuang angka transaksi lama yang mungkin
     * pernah memakainya.
     *
     * @return array{0: float, 1: float, 2: float} [fee_rate, fee_amount, hak_mitra_amount]
     */
    private function hitungKepemilikan(array $row, float $subtotalItem): array
    {
        $qty = max(0, (int) ($row['qty'] ?? 0));
        $perUnit = max(0, (float) ($row['hak_mitra_per_unit'] ?? 0));

        return [0.0, 0.0, round($qty * $perUnit, 2)];
    }

    private function buildItemsFromRequest(array $validated, int $unitId): array
    {
        $itemsToSave = [];

        // ITEM DARI MASTER — tiap baris punya product_id sendiri (bukan lagi keyed by id).
        $masterRows = collect($validated['items_master'] ?? [])
            ->filter(fn ($row) => ! empty($row['product_id']) && (int) ($row['qty'] ?? 0) > 0);

        if ($masterRows->isNotEmpty()) {
            $produkIds = $masterRows->pluck('product_id')->unique();
            $produkValid = ProductService::where('unit_id', $unitId)->whereIn('id', $produkIds)->get()->keyBy('id');

            if ($produkValid->count() !== $produkIds->count()) {
                throw ValidationException::withMessages([
                    'items_master' => 'Ada produk yang tidak valid untuk unit ini.',
                ]);
            }

            foreach ($masterRows as $row) {
                $produk = $produkValid[$row['product_id']];
                $qty = (int) $row['qty'];
                $hargaJual = isset($row['harga_jual']) && $row['harga_jual'] !== '' ? (float) $row['harga_jual'] : (float) $produk->harga_jual;
                $hargaModal = isset($row['harga_modal']) && $row['harga_modal'] !== '' ? (float) $row['harga_modal'] : (float) $produk->harga_modal;
                $diskonItem = (float) ($row['diskon'] ?? 0);
                $subtotalItem = max(0, ($qty * $hargaJual) - $diskonItem);

                [$feeRate, $feeAmount, $hakMitraAmount] = $this->hitungKepemilikan($row, $subtotalItem);

                $itemsToSave[] = [
                    'product_service_id' => $produk->id,
                    'nama_snapshot' => $produk->nama,
                    'jenis_snapshot' => $produk->jenis,
                    'qty' => $qty,
                    'harga_jual_snapshot' => $hargaJual,
                    'harga_modal_snapshot' => $hargaModal,
                    'diskon' => $diskonItem,
                    'fee_rate' => $feeRate,
                    'fee_amount' => $feeAmount,
                    'hak_mitra_amount' => $hakMitraAmount,
                    'catatan' => null,
                    'subtotal' => $subtotalItem,
                ];
            }
        }

        // ITEM MANUAL
        foreach (($validated['items_manual'] ?? []) as $row) {
            $nama = trim($row['nama'] ?? '');
            $qty = (int) ($row['qty'] ?? 0);

            if ($nama === '' || $qty <= 0) {
                continue;
            }

            $hargaJual = (float) ($row['harga_jual'] ?? 0);
            $hargaModal = (float) ($row['harga_modal'] ?? 0);
            $diskonItem = (float) ($row['diskon'] ?? 0);
            $subtotalItem = max(0, ($qty * $hargaJual) - $diskonItem);

            [$feeRate, $feeAmount, $hakMitraAmount] = $this->hitungKepemilikan($row, $subtotalItem);

            $itemsToSave[] = [
                'product_service_id' => null,
                'nama_snapshot' => $nama,
                'jenis_snapshot' => in_array($row['jenis'] ?? '', ['Produk', 'Jasa'], true) ? $row['jenis'] : 'Produk',
                'qty' => $qty,
                'harga_jual_snapshot' => $hargaJual,
                'harga_modal_snapshot' => $hargaModal,
                'diskon' => $diskonItem,
                'fee_rate' => $feeRate,
                'fee_amount' => $feeAmount,
                'hak_mitra_amount' => $hakMitraAmount,
                'catatan' => $row['catatan'] ?? null,
                'subtotal' => $subtotalItem,
            ];
        }

        if (empty($itemsToSave)) {
            throw ValidationException::withMessages([
                'items_master' => 'Tambahkan minimal satu item — dari Master Produk/Jasa, atau lewat Item Manual.',
            ]);
        }

        return $itemsToSave;
    }

    private function hitungTotal(array $validated, array $itemsToSave): array
    {
        $subtotal = array_sum(array_column($itemsToSave, 'subtotal'));
        $diskon = min((float) ($validated['diskon'] ?? 0), $subtotal);
        $ongkir = (float) ($validated['ongkir'] ?? 0);
        $ongkirDitanggung = $validated['ongkir_ditanggung'] ?? 'Pelanggan';

        // Ongkir cuma nambah Total Tagihan PELANGGAN kalau memang ditanggung
        // pelanggan. Kalau ditanggung Koperasi, nilainya TETAP tercatat di
        // kolom 'ongkir' (utk riwayat/rekap), tapi TIDAK membebani pelanggan.
        $ongkirKeTotal = $ongkirDitanggung === 'Koperasi' ? 0 : $ongkir;
        $total = $subtotal - $diskon + $ongkirKeTotal;

        return [$subtotal, $diskon, $ongkir, $total];
    }

    private function hitungDibayar(array $validated, float $total): float
    {
        return match ($validated['status_pembayaran']) {
            'Lunas' => isset($validated['nominal_dibayar']) && $validated['nominal_dibayar'] !== ''
                ? (float) $validated['nominal_dibayar']
                : $total,
            'DP' => (float) ($validated['nominal_dibayar'] ?? 0),
            default => 0,
        };
    }

    /**
     * Uang yang benar-benar tinggal di koperasi menurut isian form.
     *
     * "Lunas" berarti terbayar penuh, apa pun nominal yang diketik — aturan
     * yang sama dengan penjagaan di SalesTransaction::booted(). Nominal di
     * atas total adalah uang yang diserahkan pelanggan; selisihnya kembalian.
     */
    private function targetDibayar(array $validated, float $total, float $dpNominal): float
    {
        return $validated['status_pembayaran'] === 'Lunas' ? $total : min($dpNominal, $total);
    }

    /** Akun pilihan petugas, atau akun bawaan menurut metode pembayaran. */
    private function akunPembayaran(array $validated, PembayaranPenjualanService $pembayaran): ?int
    {
        return ! empty($validated['akun_kas_id'])
            ? (int) $validated['akun_kas_id']
            : $pembayaran->akunBawaan($validated['metode_pembayaran'])?->id;
    }

    private function pesanKembalian(float $dpNominal, float $total): string
    {
        return $dpNominal > $total && $total > 0
            ? ' Kelebihan ' . rupiah($dpNominal - $total) . ' dicatat sebagai kembalian, bukan uang masuk.'
            : '';
    }

    private function resolveCustomer(array $validated): Customer
    {
        if (! empty($validated['customer_id'])) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer) {
                return $customer;
            }
        }

        if (! empty($validated['customer_nama'])) {
            return Customer::firstOrCreate(
                [
                    'nama' => trim($validated['customer_nama']),
                    'nomor_hp' => $validated['customer_hp'] ?? null,
                ],
                [
                    'alamat' => $validated['customer_alamat'] ?? null,
                    'email' => $validated['customer_email'] ?? null,
                ]
            );
        }

        throw ValidationException::withMessages([
            'customer_nama' => 'Pilih customer yang sudah ada, atau isi nama customer baru.',
        ]);
    }

    /** Membuat/mengubah transaksi - Admin & Tim Koperasi. */
    /**
     * HAPUS PERMANEN satu transaksi penjualan.
     *
     * =====================================================================
     * HARUS DIBATALKAN DULU — DAN ITU BUKAN LANGKAH BIROKRATIS
     * =====================================================================
     *
     * Penjaganya (PenghapusanPermanenService) menolak transaksi yang masih
     * aktif, dengan alasan yang ditampilkan apa adanya ke petugas.
     *
     * Urutan itu disengaja: Batalkan menuntut ALASAN, dan alasan itu tercatat
     * di Activity Log. Kalau transaksi aktif boleh langsung dihapus, satu-
     * satunya keterangan yang tersisa tentang uang yang hilang dari laporan
     * adalah "dihapus" — tanpa siapa pun tahu kenapa.
     *
     * =====================================================================
     * ITEM DAN INVOICE IKUT TERHAPUS — DALAM SATU TRANSAKSI DATABASE
     * =====================================================================
     *
     * sales_transaction_items dan invoices ber-ON DELETE CASCADE ke
     * transaksi ini. Keduanya memang bagian dari transaksi yang sama, bukan
     * data berdiri sendiri — invoice tanpa transaksinya tidak berarti apa
     * pun. Dibungkus DB::transaction supaya tidak mungkin tersisa separuh:
     * baris induk hilang sementara item-itemnya tertinggal sebagai yatim.
     */
    public function destroy(SalesTransaction $transaksi, PenghapusanPermanenService $penghapusan)
    {
        $this->authorizeManage();

        $alasan = $penghapusan->alasanTertahan($transaksi);

        if ($alasan !== null) {
            return back()->withErrors(['hapus' => $alasan]);
        }

        // Diambil SEBELUM dihapus; sesudahnya tidak ada yang tersisa untuk
        // dirujuk. Hanya nomor, tanggal, dan total — tidak ada data rahasia.
        $nomor = $transaksi->nomor_transaksi;
        $id = $transaksi->id;
        $jumlahItem = $transaksi->items()->count();
        $punyaInvoice = $transaksi->invoice()->exists();

        DB::transaction(function () use ($transaksi, $penghapusan) {
            $penghapusan->hapus($transaksi);
        });

        ActivityLog::record(
            action: 'sale_permanently_deleted',
            modelType: 'SalesTransaction',
            modelId: $id,
            description: 'TRANSAKSI DIHAPUS PERMANEN: ' . $nomor
                . ' (' . $jumlahItem . ' item'
                . ($punyaInvoice ? ', beserta invoice-nya' : '')
                . '). Tindakan ini tidak dapat dibatalkan.',
        );

        return redirect()
            ->route('koperasi.penjualan.index')
            ->with('success', 'Transaksi ' . $nomor . ' dihapus permanen beserta seluruh itemnya.');
    }

    private function authorizeManage(): void
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);
    }

    /**
     * Melihat transaksi - Admin & Tim Koperasi (pengelola), plus Pimpinan
     * yang memang sudah read-only di sini sejak awal.
     */
    private function authorizeView(): void
    {
        $user = Auth::user();

        abort_unless($user->bolehKelolaKoperasi() || $user->isPimpinan(), 403);
    }
}
