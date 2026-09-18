@if ($isEdit)
    <button type="submit" form="formPenjualan" class="w-full bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
        Simpan Perubahan
    </button>
@else
    <button type="submit" form="formPenjualan" name="action" value="simpan" class="w-full border-2 border-cahaya-primary text-cahaya-primary text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-cahaya-primary/5 transition">
        Simpan Transaksi
    </button>
    <button type="submit" form="formPenjualan" name="action" value="invoice" class="w-full bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
        Simpan &amp; Buat Invoice
    </button>
@endif
<a href="{{ $isEdit ? route('koperasi.penjualan.show', $transaksi) : route('koperasi.penjualan.index') }}" class="w-full text-center text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
    Batal
</a>
