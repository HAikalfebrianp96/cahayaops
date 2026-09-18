<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-cahayaos.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>Simpanan Saya — Koperasi Cahaya</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50">
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-4">

        {{-- HEADER --}}
        <div class="text-center mb-2">
            <h1 class="text-lg font-bold text-koperasi-primary">Koperasi Cahaya</h1>
            <p class="text-xs text-gray-500">Koperasi Usaha Bersama</p>
            <p class="text-[11px] text-koperasi-accent font-medium">Dari Kita &bull; Untuk Kita &bull; Oleh Kita</p>
        </div>

        {{-- PROFIL --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-lg font-bold text-gray-800">{{ $member->namaTampil() }}</p>
                    <p class="text-sm text-gray-500">{{ $member->nomor_anggota }}</p>
                </div>
                <span class="px-2 py-1 rounded text-xs font-medium {{ $member->status === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $member->status }}
                </span>
            </div>
            <p class="text-xs text-gray-400 mt-2">Bergabung sejak {{ optional($member->tanggal_bergabung)->format('d F Y') ?: '—' }}</p>
        </div>

        {{-- RINGKASAN SIMPANAN --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Ringkasan Simpanan Saya</h3>
            {{--
                EMPAT kartu, bukan tiga. Simpanan Sukarela kini punya kartunya
                sendiri dan ikut dijumlahkan ke Total — sesuai ketetapan
                koperasi, dan sama persis dengan yang dilihat petugas di
                halaman Detail Anggota.

                Tiga jenis simpanan disusun sebaris di atas, Total diberi
                barisnya sendiri: ia bukan jenis keempat melainkan hasil
                penjumlahan ketiganya, dan tata letaknya harus mengatakan itu.
            --}}
            <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-base sm:text-lg font-bold text-koperasi-primary">{{ rupiah($ringkasan['pokok']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Simpanan Pokok</p>
                </div>
                <div>
                    <p class="text-base sm:text-lg font-bold text-koperasi-primary">{{ rupiah($ringkasan['wajib']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Simpanan Wajib</p>
                </div>
                <div>
                    <p class="text-base sm:text-lg font-bold text-koperasi-primary">{{ rupiah($ringkasan['sukarela']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Simpanan Sukarela</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-md py-3 px-4 mt-3 flex items-baseline justify-between">
                <p class="text-xs text-gray-500">Total Simpanan Saya</p>
                <p class="text-xl font-bold text-koperasi-accent">{{ rupiah($ringkasan['total']) }}</p>
            </div>
            <p class="text-xs text-gray-400 mt-2">Pokok + Wajib + Sukarela</p>
        </div>

        {{-- GRAFIK SEDERHANA --}}
        @if (count($chartData) > 1)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Perkembangan Simpanan Saya</h3>
                <canvas id="simpananChart" height="100"></canvas>
            </div>
        @endif

        {{-- RIWAYAT --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Riwayat Simpanan</h3>
            @if ($member->savings->isEmpty())
                <p class="text-sm text-gray-400 italic">Belum ada riwayat simpanan.</p>
            @else
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm min-w-[420px]">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="py-2">Tanggal</th>
                            <th class="py-2">Jenis</th>
                            <th class="py-2">Nominal</th>
                            <th class="py-2">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- ====================================================
                             Dua belas entri terbaru ditampilkan langsung,
                             sisanya di balik "Lihat Riwayat Lama".

                             Anggota yang menyetor Simpanan Wajib tiap bulan
                             menambah dua belas baris setiap tahun, dan daftar
                             ini tidak pernah menyusut. Halaman ini dibuka dari
                             ponsel untuk satu pertanyaan sederhana — "berapa
                             saldo saya?" — jadi jawaban itu tidak boleh makin
                             jauh dari layar tiap tahun keanggotaannya.

                             Tidak ada yang dihilangkan: seluruh entri tetap
                             dirender, hanya sebagiannya tertutup.
                             ==================================================== --}}
                        @foreach ($member->savings->take(12) as $s)
                            <tr class="border-b last:border-0">
                                <td class="py-2">{{ $s->tanggal->format('d-m-Y') }}</td>
                                <td class="py-2">{{ $s->jenis_simpanan }}</td>
                                <td class="py-2">{{ rupiah($s->nominal) }}</td>
                                <td class="py-2 text-gray-500">{{ $s->keterangan ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

                @if ($member->savings->count() > 12)
                    <details class="group mt-3">
                        <summary class="cursor-pointer list-none text-xs font-medium text-koperasi-primary">
                            <span class="group-open:hidden">
                                Lihat {{ $member->savings->count() - 12 }} riwayat lama &rarr;
                            </span>
                            <span class="hidden group-open:inline">Tutup riwayat lama</span>
                        </summary>

                        <div class="overflow-x-auto -mx-1 px-1 mt-2">
                            <table class="w-full text-sm min-w-[420px]">
                                <tbody>
                                    @foreach ($member->savings->skip(12) as $s)
                                        <tr class="border-b last:border-0">
                                            <td class="py-2">{{ $s->tanggal->format('d-m-Y') }}</td>
                                            <td class="py-2">{{ $s->jenis_simpanan }}</td>
                                            <td class="py-2">{{ rupiah($s->nominal) }}</td>
                                            <td class="py-2 text-gray-500">{{ $s->keterangan ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            @endif
        </div>

        {{-- DANA SOSIAL — SALDO BERSAMA, BUKAN PRIBADI --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-500 mb-1">Dana Sosial Koperasi Saat Ini <span class="italic">(dana bersama, bukan saldo pribadi Anda)</span></p>
            <p class="text-lg font-bold text-koperasi-accent">{{ rupiah($saldoDanaSosial) }}</p>
        </div>

        <form method="POST" action="{{ route('anggota-akses.logout') }}" class="text-center pt-2">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                Keluar
            </button>
        </form>
    </div>

    @if (count($chartData) > 1)
        @vite('resources/js/chart.js')
        <script>
            /* Chart.js kini dibundel Vite dan dimuat sebagai modul, yang selalu
               ditunda sampai halaman selesai diurai. Tanpa pembungkus ini, kode di
               bawah berjalan sebelum pustakanya ada dan grafiknya hilang tanpa
               pesan apa pun di halaman. */
            document.addEventListener('DOMContentLoaded', function () {

                new Chart(document.getElementById('simpananChart'), {
                    type: 'line',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [{
                            label: 'Total Simpanan (Rp)',
                            data: @json($chartData),
                            borderColor: '#0B4F63',
                            backgroundColor: 'rgba(11, 79, 99, 0.1)',
                            tension: 0.3,
                            fill: true,
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { ticks: { callback: v => 'Rp' + v.toLocaleString('id-ID') } } }
                    }
                });
            
            });
        </script>
    @endif
</body>
</html>
