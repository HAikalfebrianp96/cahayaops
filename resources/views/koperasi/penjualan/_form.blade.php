@php
    $isEdit = $transaksi !== null;
    $actionUrl = $isEdit ? route('koperasi.penjualan.update', $transaksi) : route('koperasi.penjualan.store');

    $produkListJson = $produkList->map(fn ($p) => [
        'id' => $p->id, 'nama' => $p->nama, 'jenis' => $p->jenis,
        'satuan' => $p->satuan ?: '-', 'harga_jual' => (float) $p->harga_jual, 'harga_modal' => (float) $p->harga_modal,
    ])->values();

    $existingMasterJson = $isEdit
        ? $transaksi->items->whereNotNull('product_service_id')->map(fn ($i) => [
            'product_service_id' => $i->product_service_id, 'qty' => $i->qty,
            'harga_jual_snapshot' => (float) $i->harga_jual_snapshot, 'harga_modal_snapshot' => (float) $i->harga_modal_snapshot,
            'diskon' => (float) $i->diskon,
            // Hak mitra disimpan sebagai TOTAL per baris; formnya meminta
            // per unit, jadi dibagi qty di sini. Transaksi lama seluruhnya
            // bernilai 0, jadi tidak ada angka yang bergeser.
            'hak_mitra_per_unit' => $i->qty > 0 ? round((float) $i->hak_mitra_amount / $i->qty, 2) : 0,
        ])->values() : collect();

    $existingManualJson = $isEdit
        ? $transaksi->items->whereNull('product_service_id')->map(fn ($i) => [
            'nama_snapshot' => $i->nama_snapshot, 'jenis_snapshot' => $i->jenis_snapshot, 'qty' => $i->qty,
            'harga_jual_snapshot' => (float) $i->harga_jual_snapshot, 'harga_modal_snapshot' => (float) $i->harga_modal_snapshot,
            'diskon' => (float) $i->diskon, 'catatan' => $i->catatan,
            'hak_mitra_per_unit' => $i->qty > 0 ? round((float) $i->hak_mitra_amount / $i->qty, 2) : 0,
        ])->values() : collect();
@endphp

<form method="POST" action="{{ $actionUrl }}" class="space-y-5" id="formPenjualan">
    @csrf
    @if ($isEdit) @method('PUT') @endif
    {{-- unit_id diambil dari transaksinya kalau objek unitnya tidak bisa
         dimuat (unit sudah diarsipkan atau barisnya hilang). Kalau kolom ini
         sampai terkirim kosong, validasi menolak seluruh formulir dan
         transaksi lama jadi mustahil dikoreksi — persis masalah yang
         seharusnya diperbaiki halaman ini. --}}
    <input type="hidden" name="unit_id" value="{{ $unit?->id ?? $transaksi?->unit_id }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- KIRI: FORM --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- A. INFORMASI TRANSAKSI --}}
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-text-main mb-4">Informasi Transaksi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if ($isEdit)
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Nomor Transaksi</label>
                            <p class="font-medium text-text-main text-sm">{{ $transaksi->nomor_transaksi }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Nomor Invoice</label>
                            <p class="font-medium text-text-main text-sm">{{ $transaksi->invoice->nomor_invoice ?? '— (belum dibuat)' }}</p>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="{{ old('tanggal', $isEdit ? $transaksi->tanggal->format('Y-m-d') : now()->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                        @error('tanggal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Status Transaksi</label>
                        <select name="status_transaksi" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                            @foreach ($statusTransaksiOptions as $st)
                                <option value="{{ $st }}" @selected(old('status_transaksi', $isEdit ? $transaksi->status_transaksi : 'Aktif') === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Unit Usaha</label>
                        @if ($unit)
                            <p class="text-sm text-text-main py-2">
                                {{ $unit->nama }} <span class="text-text-secondary">({{ $unit->kode }})</span>
                                @if ($unit->trashed())
                                    {{-- Dikatakan terang-terangan. Operator perlu tahu kenapa
                                         katalog produknya tidak lagi bisa ditambah dari sini. --}}
                                    <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary">Unit diarsipkan</span>
                                @endif
                            </p>
                        @else
                            <p class="text-sm text-text-secondary py-2">Belum ditentukan</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-border-soft">
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Pilih Customer <span class="text-text-secondary">(kalau sudah pernah beli)</span></label>
                        <select name="customer_id" id="selectCustomer" onchange="onCustomerSelectChange()" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                            <option value="">— Belum dipilih —</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id', $isEdit ? $transaksi->customer_id : '') == $c->id)>{{ $c->nama }} @if($c->nomor_hp) ({{ $c->nomor_hp }}) @endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="toggleCustomerBaru()" class="text-sm text-cahaya-primary font-medium hover:underline">+ Customer Baru</button>
                    </div>
                </div>

                <div id="customerBaruFields" class="grid grid-cols-1 md:grid-cols-4 gap-2 mt-4 p-3 bg-surface-bg rounded-lg items-end" style="display: none;">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-text-secondary mb-1">Nama Customer Baru</label>
                        <input type="text" id="cbNama" class="w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-text-secondary mb-1">Nomor HP</label>
                        <input type="text" id="cbHp" class="w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <button type="button" onclick="simpanCustomerBaru()" class="w-full text-xs bg-cahaya-accent text-white px-3 py-2 rounded-lg hover:opacity-90">Simpan Customer</button>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-text-secondary mb-1">Alamat <span class="text-text-secondary">(boleh kosong)</span></label>
                        <input type="text" id="cbAlamat" class="w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                    </div>
                    <p id="cbStatus" class="text-xs md:col-span-4"></p>
                </div>
                @error('customer_nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- B. DETAIL ITEM (DINAMIS) --}}
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-text-main">Detail Item</h3>
                    <div class="flex gap-2">
                        <button type="button" onclick="addMasterRow()" class="text-xs bg-cahaya-primary text-white px-3 py-1.5 rounded-lg hover:opacity-90">+ Tambah Produk/Jasa</button>
                        <button type="button" onclick="addManualRow()" class="text-xs border-2 border-cahaya-primary text-cahaya-primary px-3 py-1.5 rounded-lg hover:bg-cahaya-primary/5">+ Item Manual</button>
                        <button type="button" onclick="toggleProdukBaru()" class="text-xs border-2 border-cahaya-accent text-cahaya-accent px-3 py-1.5 rounded-lg hover:bg-cahaya-accent/5">+ Produk Baru</button>
                    </div>
                </div>

                <div id="produkBaruForm" class="grid grid-cols-1 md:grid-cols-5 gap-2 mb-4 p-3 bg-surface-bg rounded-lg" style="display: none;">
                    <input type="text" id="pbNama" placeholder="Nama produk/jasa" class="rounded-lg border border-border-soft px-2 py-1.5 text-sm md:col-span-2">
                    <select id="pbJenis" class="rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                        <option value="Produk">Produk</option>
                        <option value="Jasa">Jasa</option>
                    </select>
                    <input type="number" id="pbHargaJual" placeholder="Harga Jual" min="0" class="rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                    <input type="number" id="pbHargaModal" placeholder="HPP" min="0" class="rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                    <input type="text" id="pbSatuan" placeholder="Satuan (boleh kosong)" class="rounded-lg border border-border-soft px-2 py-1.5 text-sm md:col-span-2">
                    <button type="button" onclick="simpanProdukBaru()" class="text-xs bg-cahaya-accent text-white px-3 py-1.5 rounded-lg hover:opacity-90 md:col-span-1">Simpan ke Master</button>
                    <p id="pbError" class="text-xs text-status-danger md:col-span-5"></p>
                </div>
                @error('items_master') <p class="text-xs text-status-danger mb-2">{{ $message }}</p> @enderror

                <div id="masterItemsContainer" class="space-y-3 mb-3"></div>
                <div id="manualItemsContainer" class="space-y-3"></div>

                <p id="noItemsHint" class="text-sm text-text-secondary italic">Belum ada item. Klik salah satu tombol di atas untuk mulai.</p>
            </div>

            {{-- C. DISKON TRANSAKSI & ONGKIR --}}
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-text-main mb-4">Diskon Transaksi &amp; Ongkir</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Diskon Tambahan (Rp)</label>
                        <input type="number" min="0" step="1" name="diskon" id="inputDiskon"
                               value="{{ old('diskon', $isEdit ? $transaksi->diskon : 0) }}"
                               oninput="recomputeSummary()" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Ongkir (Rp)</label>
                        <input type="number" min="0" step="1" name="ongkir" id="inputOngkir"
                               value="{{ old('ongkir', $isEdit ? $transaksi->ongkir : 0) }}"
                               oninput="recomputeSummary()" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-text-main mb-2">Ongkir Ditanggung</label>
                    <div class="flex gap-4">
                        @foreach ($ongkirDitanggungOptions as $od)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="ongkir_ditanggung" value="{{ $od }}" onchange="recomputeSummary()"
                                       @checked(old('ongkir_ditanggung', $isEdit ? $transaksi->ongkir_ditanggung : 'Pelanggan') === $od)>
                                {{ $od }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-text-secondary mt-1">Kalau ditanggung Koperasi, ongkir tidak dibebankan ke pelanggan (tidak masuk Grand Total), tapi tetap tercatat.</p>
                </div>
            </div>

            {{-- D. PENGIRIMAN / PENGAMBILAN --}}
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-text-main mb-4">Pengiriman / Pengambilan</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    @foreach ($metodePengirimanOptions as $mp)
                        <label class="flex items-center gap-2 text-sm border border-border-soft rounded-lg px-3 py-2 cursor-pointer">
                            <input type="radio" name="metode_pengiriman" value="{{ $mp }}" onchange="toggleShipping()"
                                   @checked(old('metode_pengiriman', $isEdit ? $transaksi->metode_pengiriman : 'Ambil di Tempat') === $mp)>
                            {{ $mp }}
                        </label>
                    @endforeach
                </div>
                @error('metode_pengiriman') <p class="text-xs text-status-danger mb-2">{{ $message }}</p> @enderror

                <div id="shippingFields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-text-main mb-1">Alamat Pengiriman</label>
                        <input type="text" name="alamat_pengiriman" value="{{ old('alamat_pengiriman', $isEdit ? $transaksi->alamat_pengiriman : '') }}" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Nomor HP Penerima</label>
                        <input type="text" name="hp_penerima" value="{{ old('hp_penerima', $isEdit ? $transaksi->hp_penerima : '') }}" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    </div>

                    <div id="ekspedisiFields" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Nama Ekspedisi</label>
                            <input type="text" name="nama_ekspedisi" value="{{ old('nama_ekspedisi', $isEdit ? $transaksi->nama_ekspedisi : '') }}" placeholder="Contoh: JNE, J&T, SiCepat" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Nomor Resi <span class="text-text-secondary">(boleh kosong)</span></label>
                            <input type="text" name="nomor_resi" value="{{ old('nomor_resi', $isEdit ? $transaksi->nomor_resi : '') }}" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-text-main mb-1">Catatan Pengiriman</label>
                        <input type="text" name="catatan_pengiriman" value="{{ old('catatan_pengiriman', $isEdit ? $transaksi->catatan_pengiriman : '') }}" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            {{-- E. PEMBAYARAN --}}
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-text-main mb-4">Pembayaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Status Pembayaran</label>
                        <select name="status_pembayaran" id="inputStatusBayar" onchange="onStatusBayarChange()" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}" @selected(old('status_pembayaran', $isEdit ? $transaksi->status_pembayaran : '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status_pembayaran') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Metode Pembayaran</label>
                        <select name="metode_pembayaran" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                            @foreach ($metodeOptions as $metode)
                                <option value="{{ $metode }}" @selected(old('metode_pembayaran', $isEdit ? $transaksi->metode_pembayaran : '') === $metode)>{{ $metode }}</option>
                            @endforeach
                        </select>
                        @error('metode_pembayaran') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Nominal Dibayar (Rp)</label>
                        <input type="number" min="0" step="1" name="nominal_dibayar" id="inputNominalDibayar"
                               value="{{ old('nominal_dibayar', $isEdit ? $transaksi->dp_nominal : 0) }}"
                               oninput="recomputeSummary()" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        <p class="text-xs text-text-secondary mt-1">Lunas & dikosongkan = otomatis Grand Total. DP = isi nominalnya. Sisa otomatis.</p>
                        @error('dp_nominal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- F. CATATAN --}}
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                <h3 class="text-sm font-semibold text-text-main mb-4">Catatan</h3>
                <textarea name="catatan" rows="2" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('catatan', $isEdit ? $transaksi->catatan : '') }}</textarea>
            </div>

            <div class="flex flex-wrap gap-2 lg:hidden">
                @include('koperasi.penjualan._tombol-aksi', ['isEdit' => $isEdit, 'transaksi' => $transaksi])
            </div>
        </div>

        {{-- KANAN: RINGKASAN (STICKY DI DESKTOP) --}}
        <div class="lg:col-span-1">
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 lg:sticky lg:top-4">
                <h3 class="text-sm font-semibold text-text-main mb-4">Ringkasan Transaksi</h3>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between text-text-secondary"><span>Total Item</span><span id="sumTotalItem">0</span></div>
                    <div class="flex justify-between text-text-secondary"><span>Total Qty</span><span id="sumTotalQty">0</span></div>
                    <div class="flex justify-between pt-2"><span class="text-text-secondary">Subtotal</span><span id="sumSubtotal">Rp0</span></div>
                    <div class="flex justify-between"><span class="text-text-secondary">Diskon</span><span id="sumDiskon">Rp0</span></div>
                    <div class="flex justify-between"><span class="text-text-secondary">Ongkir</span><span id="sumOngkir">Rp0</span></div>
                    <div class="flex justify-between font-bold text-cahaya-primary text-base border-t border-border-soft pt-2 mt-1"><span>Grand Total</span><span id="sumGrandTotal">Rp0</span></div>
                    <div class="flex justify-between pt-2"><span class="text-text-secondary">Dibayar</span><span id="sumDibayar">Rp0</span></div>
                    <div class="flex justify-between"><span class="text-text-secondary">Sisa</span><span id="sumSisa">Rp0</span></div>
                </div>

                {{-- Hak Mitra berdiri sendiri, TIDAK ikut mengurangi profit.

                     Profit sudah dihitung dari Harga Jual dikurangi Modal/HPP.
                     Kalau hak mitra ikut dikurangkan lagi sementara modalnya
                     memang mewakili biaya yang sama, satu biaya terhitung dua
                     kali dan profitnya jadi lebih kecil dari kenyataan. --}}
                <div class="border-t border-dashed border-border-soft mt-4 pt-4 space-y-1 text-sm">
                    <div class="flex justify-between text-text-secondary"><span>Hak Mitra</span><span id="sumHakMitra">Rp0</span></div>
                </div>

                <div class="border-t border-dashed border-border-soft mt-4 pt-4">
                    <p class="text-xs text-text-secondary mb-1">Internal (tidak tampil di invoice) — Estimasi, belum final</p>
                    <div class="flex justify-between text-sm text-text-secondary"><span>Total HPP</span><span id="sumTotalHpp">Rp0</span></div>
                    <div class="flex justify-between text-sm font-semibold text-cahaya-accent"><span>Estimasi Profit</span><span id="sumEstimasiProfit">Rp0</span></div>
                </div>

                <div class="hidden lg:flex flex-wrap gap-2 mt-6">
                    @include('koperasi.penjualan._tombol-aksi', ['isEdit' => $isEdit, 'transaksi' => $transaksi])
                </div>
            </div>
        </div>
    </div>
</form>

<template id="templateMasterRow">
    <div class="item-row border-b border-border-soft pb-3 space-y-1.5">
        <div class="grid grid-cols-12 gap-2 items-end">
            <div class="col-span-4">
                <select class="produk-select w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" onchange="onProdukChange(this)"></select>
                <p class="satuan-label text-xs text-text-secondary mt-0.5"></p>
            </div>
            <div class="col-span-2"><input type="number" min="0" step="1" placeholder="Qty" class="input-qty w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-2"><input type="number" min="0" step="1" placeholder="Harga" class="input-harga w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-2"><input type="number" min="0" step="1" placeholder="HPP" class="input-hpp w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-1"><input type="number" min="0" step="1" placeholder="Diskon" class="input-diskon w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-1 flex items-center justify-between gap-1">
                <span class="subtotal-label text-xs font-medium text-text-secondary">Rp0</span>
                <button type="button" onclick="removeRow(this)" class="text-status-danger text-xs hover:underline">Hapus</button>
            </div>
        </div>
        {{-- HAK MITRA — satu isian opsional, bukan jenis transaksi.

             Dulu baris ini memaksa memilih "Milik Koperasi" atau "Titip Jual"
             lebih dulu, lalu meminta persentase fee koperasi, dan hak mitra
             dihitung sebagai sisanya. Tiga keputusan untuk satu angka.

             Sekarang cukup satu: berapa hak mitra per unit. Kosong berarti
             tidak ada mitra — keadaan yang berlaku untuk seluruh transaksi
             yang sudah ada. --}}
        <div class="grid grid-cols-12 gap-2 items-center">
            <div class="col-span-4">
                <input type="number" min="0" step="1" placeholder="Hak Mitra / unit (opsional)"
                       class="input-hakmitra w-full rounded-lg border border-border-soft px-2 py-1 text-xs"
                       oninput="recomputeSummary()">
            </div>
            <div class="col-span-8 text-xs text-text-secondary">
                Hak Mitra baris ini: <span class="hakmitra-label font-medium">Rp0</span>
            </div>
        </div>
    </div>
</template>

<template id="templateManualRow">
    <div class="item-row border-b border-border-soft pb-3 space-y-1.5">
        <div class="grid grid-cols-12 gap-2 items-end">
            <div class="col-span-3"><input type="text" placeholder="Nama Item" class="input-nama w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-1">
                <select class="input-jenis w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm">
                    <option value="Produk">Produk</option>
                    <option value="Jasa">Jasa</option>
                </select>
            </div>
            <div class="col-span-1"><input type="number" min="0" step="1" placeholder="Qty" class="input-qty w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-2"><input type="number" min="0" step="1" placeholder="Harga" class="input-harga w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-2"><input type="number" min="0" step="1" placeholder="HPP" class="input-hpp w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-1"><input type="number" min="0" step="1" placeholder="Diskon" class="input-diskon w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
            <div class="col-span-2 flex items-center justify-between gap-1">
                <span class="subtotal-label text-xs font-medium text-text-secondary">Rp0</span>
                <button type="button" onclick="removeRow(this)" class="text-status-danger text-xs hover:underline">Hapus</button>
            </div>
        </div>
        {{-- HAK MITRA — satu isian opsional, bukan jenis transaksi.

             Dulu baris ini memaksa memilih "Milik Koperasi" atau "Titip Jual"
             lebih dulu, lalu meminta persentase fee koperasi, dan hak mitra
             dihitung sebagai sisanya. Tiga keputusan untuk satu angka.

             Sekarang cukup satu: berapa hak mitra per unit. Kosong berarti
             tidak ada mitra — keadaan yang berlaku untuk seluruh transaksi
             yang sudah ada. --}}
        <div class="grid grid-cols-12 gap-2 items-center">
            <div class="col-span-4">
                <input type="number" min="0" step="1" placeholder="Hak Mitra / unit (opsional)"
                       class="input-hakmitra w-full rounded-lg border border-border-soft px-2 py-1 text-xs"
                       oninput="recomputeSummary()">
            </div>
            <div class="col-span-8 text-xs text-text-secondary">
                Hak Mitra baris ini: <span class="hakmitra-label font-medium">Rp0</span>
            </div>
        </div>
        <input type="text" placeholder="Catatan (boleh kosong)" class="input-catatan w-full rounded-lg border border-border-soft px-2 py-1.5 text-sm">
    </div>
</template>

<script>
const PRODUK_LIST = @json($produkListJson);
// Kosong kalau unitnya tidak ada atau sudah diarsipkan: produk baru tidak
// boleh ditambahkan ke unit yang sudah berhenti beroperasi. Tombolnya
// memberi tahu alasannya, bukan gagal diam-diam (lihat tambahProdukCepat).
const QUICK_PRODUK_URL = @json($unit && ! $unit->trashed() ? route('koperasi.produk.quick-store', $unit) : null);
const QUICK_CUSTOMER_URL = "{{ route('koperasi.customer.quick-store') }}";
const EXISTING_MASTER = @json($existingMasterJson);
const EXISTING_MANUAL = @json($existingManualJson);
let masterCounter = 0;
let manualCounter = 0;

function toggleCustomerBaru() {
    const box = document.getElementById('customerBaruFields');
    box.style.display = (box.style.display === 'none') ? 'grid' : 'none';
}

function onCustomerSelectChange() {
    const select = document.getElementById('selectCustomer');
    if (select.value) {
        document.getElementById('customerBaruFields').style.display = 'none';
    }
}

function simpanCustomerBaru() {
    const nama = document.getElementById('cbNama').value.trim();
    const hp = document.getElementById('cbHp').value.trim();
    const alamat = document.getElementById('cbAlamat').value.trim();
    const statusEl = document.getElementById('cbStatus');
    statusEl.textContent = '';
    statusEl.className = 'text-xs md:col-span-4';

    if (! nama) {
        statusEl.textContent = 'Nama customer wajib diisi.';
        statusEl.className = 'text-xs md:col-span-4 text-status-danger';
        return;
    }

    fetch(QUICK_CUSTOMER_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ nama, nomor_hp: hp || null, alamat: alamat || null }),
    })
    .then(res => {
        if (! res.ok) throw new Error('Gagal menyimpan customer.');
        return res.json();
    })
    .then(customer => {
        const select = document.getElementById('selectCustomer');
        const opt = document.createElement('option');
        opt.value = customer.id;
        opt.textContent = customer.nama + (customer.nomor_hp ? ' (' + customer.nomor_hp + ')' : '');
        opt.selected = true;
        select.appendChild(opt);

        statusEl.textContent = '✓ Customer "' + customer.nama + '" berhasil disimpan & dipilih.';
        statusEl.className = 'text-xs md:col-span-4 text-status-success';

        document.getElementById('cbNama').value = '';
        document.getElementById('cbHp').value = '';
        document.getElementById('cbAlamat').value = '';
        setTimeout(() => { document.getElementById('customerBaruFields').style.display = 'none'; }, 1200);
    })
    .catch(() => {
        statusEl.textContent = 'Gagal menyimpan customer. Coba lagi.';
        statusEl.className = 'text-xs md:col-span-4 text-status-danger';
    });
}

function toggleProdukBaru() {
    const box = document.getElementById('produkBaruForm');
    box.style.display = (box.style.display === 'none') ? 'grid' : 'none';
}

function simpanProdukBaru() {
    const nama = document.getElementById('pbNama').value.trim();
    const jenis = document.getElementById('pbJenis').value;
    const hargaJual = document.getElementById('pbHargaJual').value;
    const hargaModal = document.getElementById('pbHargaModal').value;
    const satuan = document.getElementById('pbSatuan').value;
    const errorEl = document.getElementById('pbError');
    errorEl.textContent = '';

    if (! nama || hargaJual === '' || hargaModal === '') {
        errorEl.textContent = 'Nama, Harga Jual, dan HPP wajib diisi.';
        return;
    }

    if (! QUICK_PRODUK_URL) {
        alert('Unit usaha transaksi ini sudah diarsipkan, jadi produk baru tidak bisa ditambahkan ke dalamnya.\n\nPakai baris "Item Manual" untuk mencatat barang yang tidak ada di katalog.');
        return;
    }

    fetch(QUICK_PRODUK_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ nama, jenis, harga_jual: hargaJual, harga_modal: hargaModal, satuan }),
    })
    .then(res => {
        if (! res.ok) throw new Error('Gagal menyimpan produk.');
        return res.json();
    })
    .then(produk => {
        PRODUK_LIST.push(produk);
        addMasterRow({
            product_service_id: produk.id,
            qty: 1,
            harga_jual_snapshot: produk.harga_jual,
            harga_modal_snapshot: produk.harga_modal,
            diskon: 0,
        });
        document.getElementById('pbNama').value = '';
        document.getElementById('pbHargaJual').value = '';
        document.getElementById('pbHargaModal').value = '';
        document.getElementById('pbSatuan').value = '';
        document.getElementById('produkBaruForm').style.display = 'none';
    })
    .catch(() => {
        errorEl.textContent = 'Gagal menyimpan produk baru. Coba lagi.';
    });
}

function formatRp(n) {
    return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

function addMasterRow(prefill) {
    const tpl = document.getElementById('templateMasterRow').content.cloneNode(true);
    const row = tpl.querySelector('.item-row');
    const idx = masterCounter++;
    const select = row.querySelector('.produk-select');
    select.name = `items_master[${idx}][product_id]`;
    let opts = '<option value="">— Pilih Produk/Jasa —</option>';
    PRODUK_LIST.forEach(p => {
        opts += `<option value="${p.id}" data-jual="${p.harga_jual}" data-modal="${p.harga_modal}" data-satuan="${p.satuan}">${p.nama} (${p.jenis})</option>`;
    });
    select.innerHTML = opts;

    row.querySelector('.input-qty').name = `items_master[${idx}][qty]`;
    row.querySelector('.input-harga').name = `items_master[${idx}][harga_jual]`;
    row.querySelector('.input-hpp').name = `items_master[${idx}][harga_modal]`;
    row.querySelector('.input-diskon').name = `items_master[${idx}][diskon]`;
    row.querySelector('.input-hakmitra').name = `items_master[${idx}][hak_mitra_per_unit]`;

    document.getElementById('masterItemsContainer').appendChild(row);

    if (prefill) {
        const container = document.getElementById('masterItemsContainer');
        const lastRow = container.lastElementChild;
        lastRow.querySelector('.produk-select').value = prefill.product_service_id;
        lastRow.querySelector('.input-qty').value = prefill.qty;
        lastRow.querySelector('.input-harga').value = prefill.harga_jual_snapshot;
        lastRow.querySelector('.input-hpp').value = prefill.harga_modal_snapshot;
        lastRow.querySelector('.input-diskon').value = prefill.diskon;
        const opt = lastRow.querySelector('.produk-select').selectedOptions[0];
        if (opt) lastRow.querySelector('.satuan-label').textContent = 'Satuan: ' + (opt.dataset.satuan || '-');

        if (prefill.hak_mitra_per_unit > 0) {
            lastRow.querySelector('.input-hakmitra').value = prefill.hak_mitra_per_unit;
        }
    }

    toggleNoItemsHint();
    recomputeSummary();
}

function onProdukChange(select) {
    const row = select.closest('.item-row');
    const opt = select.selectedOptions[0];
    if (opt && opt.value) {
        row.querySelector('.input-harga').value = opt.dataset.jual;
        row.querySelector('.input-hpp').value = opt.dataset.modal;
        row.querySelector('.satuan-label').textContent = 'Satuan: ' + (opt.dataset.satuan || '-');
    }
    recomputeSummary();
}

function addManualRow(prefill) {
    const tpl = document.getElementById('templateManualRow').content.cloneNode(true);
    const row = tpl.querySelector('.item-row');
    const idx = manualCounter++;

    row.querySelector('.input-nama').name = `items_manual[${idx}][nama]`;
    row.querySelector('.input-jenis').name = `items_manual[${idx}][jenis]`;
    row.querySelector('.input-qty').name = `items_manual[${idx}][qty]`;
    row.querySelector('.input-harga').name = `items_manual[${idx}][harga_jual]`;
    row.querySelector('.input-hpp').name = `items_manual[${idx}][harga_modal]`;
    row.querySelector('.input-diskon').name = `items_manual[${idx}][diskon]`;
    row.querySelector('.input-catatan').name = `items_manual[${idx}][catatan]`;
    row.querySelector('.input-hakmitra').name = `items_manual[${idx}][hak_mitra_per_unit]`;

    document.getElementById('manualItemsContainer').appendChild(row);

    if (prefill) {
        const container = document.getElementById('manualItemsContainer');
        const lastRow = container.lastElementChild;
        lastRow.querySelector('.input-nama').value = prefill.nama_snapshot;
        lastRow.querySelector('.input-jenis').value = prefill.jenis_snapshot;
        lastRow.querySelector('.input-qty').value = prefill.qty;
        lastRow.querySelector('.input-harga').value = prefill.harga_jual_snapshot;
        lastRow.querySelector('.input-hpp').value = prefill.harga_modal_snapshot;
        lastRow.querySelector('.input-diskon').value = prefill.diskon;
        lastRow.querySelector('.input-catatan').value = prefill.catatan || '';

        if (prefill.hak_mitra_per_unit > 0) {
            lastRow.querySelector('.input-hakmitra').value = prefill.hak_mitra_per_unit;
        }
    }

    toggleNoItemsHint();
    recomputeSummary();
}

function removeRow(btn) {
    btn.closest('.item-row').remove();
    toggleNoItemsHint();
    recomputeSummary();
}

function toggleNoItemsHint() {
    const total = document.querySelectorAll('#masterItemsContainer .item-row, #manualItemsContainer .item-row').length;
    document.getElementById('noItemsHint').style.display = total === 0 ? 'block' : 'none';
}

function toggleShipping() {
    const metode = document.querySelector('input[name="metode_pengiriman"]:checked')?.value;
    document.getElementById('shippingFields').style.display = (metode === 'Ambil di Tempat') ? 'none' : 'grid';
    document.getElementById('ekspedisiFields').style.display = (metode === 'Ekspedisi') ? 'grid' : 'none';
}

function onStatusBayarChange() {
    recomputeSummary();
}

function recomputeSummary() {
    let totalItem = 0, totalQty = 0, subtotal = 0, totalHpp = 0;
    let totalHakMitra = 0;

    document.querySelectorAll('#masterItemsContainer .item-row, #manualItemsContainer .item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.input-qty')?.value || 0);
        const harga = parseFloat(row.querySelector('.input-harga')?.value || 0);
        const hpp = parseFloat(row.querySelector('.input-hpp')?.value || 0);
        const diskonItem = parseFloat(row.querySelector('.input-diskon')?.value || 0);
        const hakMitraPerUnit = parseFloat(row.querySelector('.input-hakmitra')?.value || 0);

        if (qty > 0) {
            totalItem++;
            totalQty += qty;
            const rowSubtotal = Math.max(0, (qty * harga) - diskonItem);
            const label = row.querySelector('.subtotal-label');
            if (label) label.textContent = formatRp(rowSubtotal);

            // SETIAP baris masuk Total Penjualan dan Total Modal — tidak ada
            // lagi item yang dikecualikan karena jenisnya.
            subtotal += rowSubtotal;
            totalHpp += qty * hpp;

            // Hak mitra dicatat terpisah dan TIDAK mengurangi profit di sini:
            // profit sudah dihitung dari Harga Jual dikurangi Modal/HPP.
            const hakMitra = Math.max(0, qty * hakMitraPerUnit);
            totalHakMitra += hakMitra;

            const hmLabel = row.querySelector('.hakmitra-label');
            if (hmLabel) hmLabel.textContent = formatRp(hakMitra);
        } else {
            const label = row.querySelector('.subtotal-label');
            if (label) label.textContent = 'Rp0';
        }
    });

    const diskonTrx = parseFloat(document.getElementById('inputDiskon')?.value || 0);
    const ongkir = parseFloat(document.getElementById('inputOngkir')?.value || 0);
    const ongkirDitanggung = document.querySelector('input[name="ongkir_ditanggung"]:checked')?.value || 'Pelanggan';
    const ongkirKeTotal = ongkirDitanggung === 'Koperasi' ? 0 : ongkir;

    const grandTotal = Math.max(0, subtotal - diskonTrx) + ongkirKeTotal;

    // Estimasi Profit = Total Penjualan - Total Modal/HPP. Titik.
    // Hak Mitra tidak ikut dikurangkan: kalau modalnya memang sudah mewakili
    // biaya yang sama, satu biaya akan terhitung dua kali.
    const estimasiProfit = subtotal - totalHpp;

    const statusBayar = document.getElementById('inputStatusBayar')?.value;
    let dibayar = 0;
    const nominalInput = document.getElementById('inputNominalDibayar');
    if (statusBayar === 'Lunas') {
        dibayar = nominalInput && nominalInput.value !== '' ? parseFloat(nominalInput.value) : grandTotal;
    } else if (statusBayar === 'DP') {
        dibayar = parseFloat(nominalInput?.value || 0);
    } else {
        dibayar = 0;
    }
    const sisa = Math.max(0, grandTotal - dibayar);

    document.getElementById('sumTotalItem').textContent = totalItem;
    document.getElementById('sumTotalQty').textContent = totalQty;
    document.getElementById('sumSubtotal').textContent = formatRp(subtotal);
    document.getElementById('sumDiskon').textContent = formatRp(diskonTrx);
    document.getElementById('sumOngkir').textContent = formatRp(ongkirKeTotal);
    document.getElementById('sumGrandTotal').textContent = formatRp(grandTotal);
    document.getElementById('sumDibayar').textContent = formatRp(dibayar);
    document.getElementById('sumSisa').textContent = formatRp(sisa);
    document.getElementById('sumHakMitra').textContent = formatRp(totalHakMitra);
    document.getElementById('sumTotalHpp').textContent = formatRp(totalHpp);
    document.getElementById('sumEstimasiProfit').textContent = formatRp(estimasiProfit);
}

// Muat ulang item yang sudah ada (mode Edit)
EXISTING_MASTER.forEach(item => addMasterRow(item));
EXISTING_MANUAL.forEach(item => addManualRow(item));
toggleShipping();
toggleNoItemsHint();
recomputeSummary();
</script>
