@extends('layouts.app')

@section('title', 'Program Dampak')

@section('content')
@php
    $kartu = 'bg-white border border-border-soft rounded-2xl shadow-sm p-5';
@endphp

<div class="space-y-4">

    {{-- ================= KEPALA ================= --}}
    <div class="{{ $kartu }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg font-semibold text-text-main">{{ $program->nama }}</h2>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full
                        @if ($program->status === \App\Models\ImpactProgram::STATUS_FINAL) bg-status-success/10 text-status-success
                        @elseif ($program->status === \App\Models\ImpactProgram::STATUS_REVIEW) bg-gold-light text-cahaya-primary
                        @else bg-surface-bg text-text-secondary @endif">
                        {{ $program->status }}
                    </span>
                </div>
                <p class="text-xs text-text-secondary mt-1">
                    {{ $program->periodeLabel() }}
                    @if ($program->pendamping) &bull; Pendamping: {{ $program->pendamping->nama }} @endif
                </p>
                @if ($program->deskripsi)
                    <p class="text-sm text-text-secondary mt-2 max-w-2xl">{{ $program->deskripsi }}</p>
                @endif
            </div>

            <div class="flex gap-2">
                {{-- Export PDF tersedia untuk SIAPA PUN yang boleh membaca
                     program ini, termasuk Pimpinan — merekalah yang paling
                     sering membutuhkan berkasnya untuk dibawa keluar.
                     Dokumennya sendiri menolak mencetak rasio kalau datanya
                     belum lengkap, jadi membukanya lebih awal tidak berisiko. --}}
                <a href="{{ route('impact.export-pdf', $program) }}"
                   class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                    Export PDF
                </a>
                @if ($bisaKelola)
                    <a href="{{ route('impact.edit', $program) }}"
                       class="text-sm text-text-secondary border border-border-soft px-4 py-2 rounded-lg hover:text-text-main">Edit</a>
                @endif
                <a href="{{ route('impact.index') }}"
                   class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Kembali</a>
            </div>
        </div>

        @if ($program->terkunci())
            <div class="mt-4 rounded-xl bg-surface-bg border border-border-soft p-3">
                <p class="text-xs text-text-secondary">
                    <span class="font-medium text-text-main">Program ini terkunci.</span>
                    Angkanya sudah disetujui sebagai Final dan dipakai melapor keluar, jadi investasi dan
                    outcome-nya tidak bisa diubah lagi — termasuk oleh Admin. Untuk revisi, buat program
                    baru agar kedua versinya tetap bisa dibandingkan.
                </p>
            </div>
        @endif
    </div>

    @include('impact.partials.notifikasi')

    {{-- ================= ALUR PENYUSUNAN ================= --}}
    @include('impact.partials.langkah')

    {{-- ================= STATUS KESIAPAN ================= --}}
    <div class="{{ $kartu }}">
        <h3 class="text-sm font-semibold text-text-main mb-3">Status Data SROI</h3>

        @if ($kesiapan['siap'])
            <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3">
                <p class="text-sm font-semibold text-green-800">{{ $kesiapan['status'] }}</p>
                <p class="text-xs text-green-700 mt-1">
                    Investasi, outcome, dan proxy finansial sudah lengkap. SROI bisa dihitung.
                </p>
            </div>
        @else
            {{--
                Inilah jawaban sistem saat datanya belum cukup: DAFTAR APA YANG
                KURANG, bukan angka perkiraan. Rasio SROI dipakai melapor ke
                pemberi dana; angka yang tidak berdiri di atas investasi
                tercatat, outcome terukur, dan proxy bersumber bukan sekadar
                tidak akurat — ia menyesatkan orang yang memutuskan ke mana
                dana berikutnya pergi.
            --}}
            <div class="rounded-xl bg-gold-light border border-status-warning/40 px-4 py-3">
                <p class="text-sm font-semibold text-cahaya-primary">{{ $kesiapan['status'] }}</p>
                <p class="text-xs text-text-secondary mt-1 mb-2">
                    SROI belum bisa dihitung. Yang masih kurang:
                </p>
                <ul class="text-xs text-text-main space-y-1 list-disc list-inside">
                    @foreach ($kesiapan['kurang'] as $k)
                        <li>{{ $k }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($kesiapan['peringatan']))
            <div class="mt-3 rounded-xl border border-border-soft bg-surface-bg px-4 py-3">
                <p class="text-xs font-semibold text-text-main mb-2">Perlu ditinjau sebelum dilaporkan</p>
                <ul class="text-xs text-text-secondary space-y-1 list-disc list-inside">
                    @foreach ($kesiapan['peringatan'] as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($bisaKelola && $kesiapan['siap'])
            <form method="POST" action="{{ route('impact.hitung', $program) }}" class="mt-4">
                @csrf
                <button type="submit"
                        class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                    Hitung SROI
                </button>
            </form>
        @endif
    </div>

    {{-- ================= RANTAI DAMPAK ================= --}}
    <div class="{{ $kartu }}">
        <h3 class="text-sm font-semibold text-text-main mb-1">Rantai Dampak</h3>
        <p class="text-[11px] text-text-secondary mb-4">
            Seluruh angka di bawah ini diturunkan dari data operasional yang sudah ada — investasi yang
            dicatat, pendampingan &amp; pelatihan yang benar-benar terjadi, dan perubahan baseline&ndash;endline.
            Tidak ada yang perlu diinput ulang.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach (['input' => 'Input', 'activity' => 'Activity', 'output' => 'Output'] as $kunci => $judul)
                <div class="rounded-xl border border-border-soft p-4">
                    <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-2">{{ $judul }}</p>
                    <dl class="space-y-1 text-xs">
                        @foreach ($rantai[$kunci] as $label => $nilai)
                            <div class="flex justify-between gap-2">
                                <dt class="text-text-secondary">{{ $label }}</dt>
                                <dd class="text-text-main font-medium text-right">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-border-soft p-4 mt-4">
            <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-2">
                Outcome — perubahan terukur peserta
            </p>

            @if (empty($rantai['outcome']))
                <p class="text-xs text-text-secondary">
                    Belum ada peserta program ini yang punya baseline sekaligus endline/monitoring,
                    jadi belum ada perubahan yang bisa dinyatakan.
                </p>
            @else
                <div class="overflow-x-auto -mx-4 px-4">
                    <table class="w-full text-xs min-w-[480px]">
                        <thead>
                            <tr class="text-left text-text-secondary border-b border-border-soft">
                                <th class="py-2 font-medium">Indikator</th>
                                <th class="py-2 font-medium text-right">Peserta terukur</th>
                                <th class="py-2 font-medium text-right">Membaik</th>
                                <th class="py-2 font-medium text-right">Total perubahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-soft">
                            @foreach ($rantai['outcome'] as $ind)
                                <tr>
                                    <td class="py-2 text-text-main">{{ $ind['label'] }}</td>
                                    <td class="py-2 text-right text-text-secondary">{{ $ind['jumlah_peserta_terukur'] }}</td>
                                    <td class="py-2 text-right text-text-secondary">{{ $ind['jumlah_membaik'] }}</td>
                                    <td class="py-2 text-right font-medium text-text-main">
                                        {{ $ind['format'] === 'rupiah'
                                            ? rupiah((int) $ind['total_selisih'])
                                            : number_format((int) $ind['total_selisih'], 0, ',', '.') . ' ' . $ind['satuan'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-text-secondary mt-2">
                    Kolom &ldquo;peserta terukur&rdquo; ditampilkan bersama totalnya dengan sengaja: total kenaikan
                    dari 3 peserta dan dari 300 peserta adalah dua klaim yang sangat berbeda.
                </p>
            @endif
        </div>
    </div>

    {{-- ================= INVESTASI ================= --}}
    <div class="{{ $kartu }}">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-text-main">
                Investasi Program
                <span class="font-normal text-text-secondary">({{ rupiah($kesiapan['total_investasi']) }})</span>
            </h3>
            @if ($bisaKelola)
                <a href="{{ route('impact.investasi.create', $program) }}" class="text-sm text-cahaya-primary font-medium hover:underline">+ Tambah</a>
            @endif
        </div>

        @forelse ($program->investments as $inv)
            <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-border-soft last:border-0">
                <div class="min-w-0">
                    <p class="text-sm text-text-main">{{ $inv->uraian }}</p>
                    <p class="text-[11px] text-text-secondary">
                        {{ $inv->kategori }} &bull; {{ $inv->jenis }} &bull; {{ $inv->tanggal->format('d M Y') }}
                        @if ($inv->sumber_dana) &bull; {{ $inv->sumber_dana }} @endif
                        @if ($inv->punyaBukti())
                            &bull; <a href="{{ route('impact.investasi.bukti', $inv) }}"
                                      class="text-cahaya-secondary hover:underline">bukti</a>
                        @endif
                    </p>
                    {{-- Jejak audit: siapa mencatat pos ini, dan kapan. --}}
                    <p class="text-[11px] text-text-secondary">
                        Dicatat {{ $inv->pencatat->name ?? 'tidak tercatat' }}
                        &bull; {{ $inv->created_at?->translatedFormat('d M Y') ?? '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-text-main">{{ rupiah($inv->nominal) }}</span>
                    @if ($bisaKelola)
                        <a href="{{ route('impact.investasi.edit', $inv) }}" class="text-xs text-cahaya-secondary hover:underline">Edit</a>
                        <form method="POST" action="{{ route('impact.investasi.destroy', $inv) }}"
                              onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Pastikan data ini memang sudah tidak diperlukan.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-status-danger hover:underline">Hapus Permanen</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-text-secondary">
                Belum ada investasi tercatat. Tanpa ini, rasio SROI tidak punya penyebut dan tidak bisa dihitung.
            </p>
        @endforelse
    </div>

    {{-- ================= BASELINE & ENDLINE ================= --}}
    @include('impact.partials.pengukuran-peserta')

    {{-- ================= OUTCOME ================= --}}
    <div class="{{ $kartu }}">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-text-main">Outcome &amp; Faktor Penyesuai</h3>
            @if ($bisaKelola)
                <a href="{{ route('impact.outcome.create', $program) }}" class="text-sm text-cahaya-primary font-medium hover:underline">+ Tambah</a>
            @endif
        </div>

        @forelse ($program->outcomes as $o)
            <div class="border border-border-soft rounded-xl p-4 mb-3 last:mb-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-text-main">{{ $o->nama }}</p>
                        <p class="text-[11px] text-text-secondary mt-0.5">
                            {{ $o->otomatis() ? 'Otomatis — ' . ($o->indikatorLabel() ?? '-') : 'Manual' }}
                            &bull; Proxy:
                            @if ($o->proxy)
                                {{ $o->proxy->ringkas() }}
                            @else
                                <span class="text-status-danger">belum tersedia</span>
                            @endif
                        </p>
                    </div>
                    @if ($bisaKelola)
                        <div class="flex gap-3 text-xs">
                            <a href="{{ route('impact.outcome.edit', $o) }}" class="text-cahaya-secondary hover:underline">Edit</a>
                            <form method="POST" action="{{ route('impact.outcome.destroy', $o) }}"
                                  onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Pastikan data ini memang sudah tidak diperlukan.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-status-danger hover:underline">Hapus Permanen</button>
                            </form>
                        </div>
                    @endif
                </div>

                @php $persen = fn ($n) => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') . '%'; @endphp

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mt-3 text-xs">
                    <div class="rounded-lg bg-surface-bg px-2 py-1.5">
                        <p class="text-text-secondary text-[10px]">Deadweight</p>
                        <p class="text-text-main font-medium">{{ $persen($o->deadweight_persen) }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-bg px-2 py-1.5">
                        <p class="text-text-secondary text-[10px]">Attribution</p>
                        <p class="text-text-main font-medium">{{ $persen($o->attribution_persen) }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-bg px-2 py-1.5">
                        <p class="text-text-secondary text-[10px]">Displacement</p>
                        {{-- Tiga keadaan ditampilkan sebagai tiga kata yang
                             berbeda — "0%" tidak boleh menyamarkan "belum
                             ditentukan" maupun "tidak relevan". --}}
                        <p class="text-text-main font-medium">
                            @if ($o->displacementTidakRelevan())
                                <span class="text-text-secondary">Tidak relevan</span>
                            @elseif ($o->displacementBelumDitentukan())
                                <span class="text-status-warning">Belum ditentukan</span>
                            @else
                                {{ $persen($o->displacement_persen) }}
                            @endif
                        </p>
                    </div>
                    <div class="rounded-lg bg-surface-bg px-2 py-1.5">
                        <p class="text-text-secondary text-[10px]">Drop-off</p>
                        <p class="text-text-main font-medium">
                            @if ($o->dropOffRelevan())
                                {{ $persen($o->drop_off_persen) }}
                            @else
                                <span class="text-text-secondary">Tidak berlaku</span>
                            @endif
                        </p>
                    </div>
                    <div class="rounded-lg bg-surface-bg px-2 py-1.5">
                        <p class="text-text-secondary text-[10px]">Durasi</p>
                        <p class="text-text-main font-medium">{{ $o->durasi_tahun }} th</p>
                    </div>
                </div>

                @if ($o->penyesuaiMasihBawaan())
                    <p class="text-[11px] text-status-warning mt-2">
                        Faktor penyesuai masih bawaan — seluruh perubahan diklaim milik program.
                    </p>
                @endif

                @if ($o->attribution_catatan)
                    <p class="text-[11px] text-text-secondary mt-2">
                        <span class="font-medium text-text-main">Kontribusi pihak lain:</span> {{ $o->attribution_catatan }}
                    </p>
                @endif
                @if ($o->displacement_catatan)
                    <p class="text-[11px] text-text-secondary mt-1">
                        <span class="font-medium text-text-main">Displacement:</span> {{ $o->displacement_catatan }}
                    </p>
                @endif
                @if ($o->bukti)
                    <p class="text-[11px] text-text-secondary mt-2 pt-2 border-t border-border-soft">
                        Bukti: {{ $o->bukti }}
                    </p>
                @endif

                {{-- Jejak audit: siapa memasukkan angka ini, dan kapan.
                     Tanpa itu, outcome yang keliru tidak bisa ditanyakan ke
                     siapa pun. --}}
                <p class="text-[11px] text-text-secondary mt-1">
                    Dicatat {{ $o->pencatat->name ?? 'tidak tercatat' }}
                    &bull; {{ $o->created_at?->translatedFormat('d M Y') ?? '-' }}
                </p>
            </div>
        @empty
            <p class="text-sm text-text-secondary">Belum ada outcome. Outcome adalah perubahan yang diklaim terjadi karena program.</p>
        @endforelse
    </div>

    {{-- ================= HASIL PERHITUNGAN ================= --}}
    @if ($terakhir)
        @include('impact.partials.hasil', ['hasil' => $terakhir, 'program' => $program, 'bisaSetujui' => $bisaSetujui])
    @endif

    {{-- ================= RIWAYAT PERHITUNGAN ================= --}}
    @if ($program->calculations->count() > 1)
        <div class="{{ $kartu }}">
            <h3 class="text-sm font-semibold text-text-main mb-1">Riwayat Perhitungan</h3>
            <p class="text-[11px] text-text-secondary mb-3">
                Setiap perhitungan dibekukan beserta seluruh asumsinya dan tidak pernah dihitung ulang —
                jadi pertanyaan &ldquo;angka apa yang kita laporkan waktu itu, dan dari mana&rdquo; selalu punya jawaban.
            </p>
            <div class="overflow-x-auto -mx-5 px-5">
                <table class="w-full text-xs min-w-[480px]">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Waktu</th>
                            <th class="py-2 font-medium">Oleh</th>
                            <th class="py-2 font-medium text-right">Investasi</th>
                            <th class="py-2 font-medium text-right">Nilai dampak</th>
                            <th class="py-2 font-medium text-right">Rasio</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @foreach ($program->calculations as $c)
                            <tr>
                                <td class="py-2 text-text-main">{{ $c->dihitung_at->format('d M Y H:i') }}</td>
                                <td class="py-2 text-text-secondary">{{ $c->penghitung->name ?? 'Sistem' }}</td>
                                <td class="py-2 text-right text-text-secondary">{{ rupiah($c->total_investasi) }}</td>
                                <td class="py-2 text-right text-text-secondary">{{ rupiah($c->total_nilai_dampak) }}</td>
                                <td class="py-2 text-right font-medium text-text-main">
                                    {{ $c->rasio === null ? '—' : number_format($c->rasio, 2, ',', '.') }}
                                </td>
                                <td class="py-2 text-text-secondary">{{ $c->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
