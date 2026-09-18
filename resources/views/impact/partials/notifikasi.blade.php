{{--
    Pesan sukses & gagal untuk seluruh modul Dampak.

    Pesan gagal di modul ini hampir selalu berupa DAFTAR APA YANG KURANG
    (lihat SroiService::periksaKesiapan), bukan error teknis — jadi ia diberi
    ruang baca yang layak, bukan satu baris merah yang mudah diabaikan.
--}}
@if (session('success'))
    <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ session('error') }}
    </div>
@endif
