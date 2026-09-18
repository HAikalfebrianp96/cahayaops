<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-cahayaos.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>Laporan Omzet — Cahaya Preneur</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cahaya-primary flex items-start sm:items-center justify-center px-4 py-8">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6 sm:p-8">

        <div class="text-center mb-6">
            <p class="text-xs font-semibold tracking-wide text-cahaya-secondary uppercase">Cahaya Preneur</p>
            <h1 class="text-xl font-bold text-cahaya-primary mt-1">Laporan Omzet Usaha</h1>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 mb-6 text-sm">
            <p class="text-gray-500">Nama Peserta</p>
            <p class="font-semibold text-gray-800 text-base">{{ $participant->nama_peserta }}</p>
            <p class="text-gray-500 mt-2">Nama Usaha</p>
            <p class="font-semibold text-gray-800 text-base">{{ $participant->nama_usaha }}</p>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-xl bg-green-50 border border-green-200 px-4 py-4 text-center">
                <p class="text-green-700 font-semibold">✓ {{ session('success') }}</p>
                <p class="text-xs text-green-600 mt-1">Terima kasih sudah mengisi laporan bulan ini.</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- enctype WAJIB — tanpa ini foto bukti tidak pernah sampai ke server,
             dan laporannya tetap tersimpan seolah tidak ada yang salah. --}}
        <form method="POST" action="{{ route('omzet.public.store', $participant->report_token) }}"
              enctype="multipart/form-data" class="space-y-5">
            @csrf

            @include('omzet.partials.pilih-periode', ['publik' => true])

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Omzet Periode Ini (Rp)</label>
                <input type="number" min="0" step="1" name="omzet" value="{{ old('omzet') }}" required
                       placeholder="Contoh: 1500000"
                       class="w-full rounded-xl border border-gray-300 px-3 py-3 text-lg font-semibold focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                <p class="text-xs text-gray-400 mt-1">Isi total penjualan usaha Ibu/Bapak pada periode yang dipilih di atas, dalam Rupiah.</p>
            </div>

            {{--
                MODAL & BIAYA — keduanya BOLEH KOSONG.

                Omzet saja tidak bisa membedakan usaha yang untung dari usaha
                yang ramai tapi rugi, jadi dua angka ini penting. Tapi form ini
                diisi peserta sendiri lewat ponsel, sering sambil melayani
                pembeli — mewajibkannya adalah cara tercepat membuat laporan
                omzet berhenti masuk sama sekali. Yang kosong disimpan sebagai
                NULL ("belum dilaporkan"), bukan 0.
            --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Modal / HPP (Rp) <span class="text-gray-400 font-normal">(boleh kosong)</span>
                </label>
                <input type="number" min="0" step="1" name="hpp" value="{{ old('hpp') }}"
                       placeholder="Contoh: 900000"
                       class="w-full rounded-xl border border-gray-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                <p class="text-xs text-gray-400 mt-1">Total belanja bahan/barang untuk semua yang terjual bulan ini.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Biaya Operasional (Rp) <span class="text-gray-400 font-normal">(boleh kosong)</span>
                </label>
                <input type="number" min="0" step="1" name="biaya_operasional" value="{{ old('biaya_operasional') }}"
                       placeholder="Contoh: 150000"
                       class="w-full rounded-xl border border-gray-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                <p class="text-xs text-gray-400 mt-1">Listrik, gas, transport, sewa, upah. Kosongkan kalau tidak ada.</p>
            </div>

            {{-- Perkiraan laba, muncul sendiri begitu Modal/HPP diisi. --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 hidden" data-laba="panel">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Perkiraan Laba</p>
                <dl class="grid grid-cols-2 gap-y-1 text-sm">
                    <dt class="text-gray-500">Laba kotor</dt>
                    <dd class="text-right font-medium text-gray-800" data-laba="kotor">-</dd>
                    <dt class="text-gray-500">Profit bersih</dt>
                    <dd class="text-right font-semibold text-gray-800" data-laba="bersih">-</dd>
                    <dt class="text-gray-500">Margin</dt>
                    <dd class="text-right text-gray-800" data-laba="margin">-</dd>
                </dl>
                <p class="text-xs text-status-danger mt-2" data-laba="peringatan"></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Catatan <span class="text-gray-400 font-normal">(boleh kosong)</span></label>
                <textarea name="catatan" rows="3" placeholder="Ada cerita atau kendala usaha bulan ini? Boleh ditulis di sini."
                          class="w-full rounded-xl border border-gray-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">{{ old('catatan') }}</textarea>
            </div>

            @include('omzet.partials.input-bukti', ['laporan' => null, 'publik' => true])

            <button type="submit"
                    class="w-full bg-cahaya-primary text-white font-bold text-lg py-4 rounded-xl hover:bg-cahaya-primary/90 transition">
                KIRIM LAPORAN
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-6">CahayaOS — YBM PLN UP3 Bengkulu</p>
    </div>

    @include('omzet.partials.skrip-laba')
</body>
</html>
