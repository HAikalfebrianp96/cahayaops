{{--
    HASIL SROI — ditampilkan beserta seluruh langkah antaranya.

    Rasio telanjang tanpa langkah antaranya tidak bisa diperiksa siapa pun.
    Yang membuat angka ini layak dibawa keluar bukan besarnya, melainkan
    bahwa setiap pengurangnya terlihat: dari nilai kotor, dikurangi
    deadweight, dikali attribution, dikurangi displacement, lalu diluruhkan
    drop-off per tahun.
--}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h3 class="text-sm font-semibold text-text-main">Hasil Perhitungan SROI</h3>
            <p class="text-[11px] text-text-secondary mt-0.5">
                Dihitung {{ $hasil->dihitung_at->format('d M Y H:i') }}
                oleh {{ $hasil->penghitung->name ?? 'Sistem' }}
                &bull; Status: {{ $hasil->status }}
                @if ($hasil->sudahDisetujui())
                    &bull; disetujui {{ $hasil->penyetuju->name ?? '-' }}, {{ $hasil->disetujui_at->format('d M Y') }}
                @endif
            </p>
        </div>
    </div>

    {{-- ANGKA UTAMA --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="rounded-xl bg-surface-bg p-4">
            <p class="text-[11px] text-text-secondary">Total Investasi</p>
            <p class="text-base font-semibold text-text-main mt-0.5">{{ rupiah($hasil->total_investasi) }}</p>
        </div>
        <div class="rounded-xl bg-surface-bg p-4">
            <p class="text-[11px] text-text-secondary">Total Nilai Dampak</p>
            <p class="text-base font-semibold text-text-main mt-0.5">{{ rupiah($hasil->total_nilai_dampak) }}</p>
        </div>
        <div class="rounded-xl bg-surface-bg p-4">
            <p class="text-[11px] text-text-secondary">Nilai Sosial Bersih</p>
            <p class="text-base font-semibold mt-0.5 {{ $hasil->nilai_sosial_bersih < 0 ? 'text-status-danger' : 'text-text-main' }}">
                {{ rupiah($hasil->nilai_sosial_bersih) }}
            </p>
        </div>
        <div class="rounded-xl bg-primary-light p-4">
            <p class="text-[11px] text-cahaya-primary">Rasio SROI</p>
            <p class="text-xl font-bold text-cahaya-primary mt-0.5">
                {{ $hasil->rasio === null ? '—' : number_format($hasil->rasio, 2, ',', '.') }}
            </p>
        </div>
    </div>

    <p class="text-xs text-text-secondary mb-5">{{ $hasil->rasioKalimat() }}</p>

    {{-- RINCIAN PER OUTCOME --}}
    <h4 class="text-xs font-semibold text-text-main mb-2">Rincian per Outcome</h4>
    <div class="space-y-3 mb-5">
        @foreach ($hasil->rincian['outcomes'] ?? [] as $o)
            <div class="border border-border-soft rounded-xl p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-medium text-text-main">{{ $o['nama'] }}</p>
                    <p class="text-sm font-semibold text-text-main">{{ rupiah($o['nilai_bersih']) }}</p>
                </div>
                <p class="text-[11px] text-text-secondary mt-0.5">
                    Perubahan {{ number_format($o['jumlah_perubahan'], 0, ',', '.') }}
                    @if ($o['jumlah_peserta_terukur'] !== null)
                        dari {{ $o['jumlah_peserta_terukur'] }} peserta terukur
                    @endif
                    @if ($o['proxy'])
                        &bull; proxy {{ rupiah($o['proxy']['nilai']) }} {{ $o['proxy']['satuan'] }}
                        ({{ $o['proxy']['sumber'] }}, {{ $o['proxy']['tahun'] }})
                    @endif
                </p>

                {{-- Langkah antara: inilah yang membuat angkanya bisa diperiksa. --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 text-[11px]">
                    <div>
                        <p class="text-text-secondary">Nilai kotor</p>
                        <p class="text-text-main">{{ rupiah($o['langkah']['kotor']) }}</p>
                    </div>
                    <div>
                        <p class="text-text-secondary">− deadweight {{ $o['penyesuai']['deadweight_persen'] }}%</p>
                        <p class="text-text-main">{{ rupiah($o['langkah']['setelah_deadweight']) }}</p>
                    </div>
                    <div>
                        <p class="text-text-secondary">× attribution {{ $o['penyesuai']['attribution_persen'] }}%</p>
                        <p class="text-text-main">{{ rupiah($o['langkah']['setelah_attribution']) }}</p>
                    </div>
                    <div>
                        {{-- "Tidak relevan" dan "0%" adalah dua pernyataan yang
                             berbeda, dan laporan harus membedakannya. --}}
                        <p class="text-text-secondary">
                            @if (($o['penyesuai']['displacement_relevan'] ?? null) === false)
                                displacement tidak relevan
                            @else
                                − displacement {{ $o['penyesuai']['displacement_persen'] }}%
                            @endif
                        </p>
                        <p class="text-text-main">{{ rupiah($o['langkah']['setelah_displacement']) }}</p>
                    </div>
                </div>

                @if (count($o['per_tahun'] ?? []) > 1)
                    <p class="text-[11px] text-text-secondary mt-2">
                        Manfaat {{ $o['penyesuai']['durasi_tahun'] }} tahun, drop-off {{ $o['penyesuai']['drop_off_persen'] }}%/tahun:
                        @foreach ($o['per_tahun'] as $t)
                            Th{{ $t['tahun'] }} {{ rupiah($t['nilai']) }}@if (! $loop->last), @endif
                        @endforeach
                    </p>
                @endif

                @php
                    $catatanPenyesuai = array_filter([
                        'Dasar deadweight' => $o['penyesuai']['deadweight_catatan'] ?? null,
                        'Kontribusi pihak lain' => $o['penyesuai']['attribution_catatan'] ?? null,
                        'Displacement' => $o['penyesuai']['displacement_catatan'] ?? null,
                    ]);
                @endphp

                @if (! empty($catatanPenyesuai) || ! empty($o['bukti']))
                    <div class="mt-2 pt-2 border-t border-border-soft space-y-1">
                        @foreach ($catatanPenyesuai as $label => $isi)
                            <p class="text-[11px] text-text-secondary">
                                <span class="font-medium text-text-main">{{ $label }}:</span> {{ $isi }}
                            </p>
                        @endforeach
                        @if (! empty($o['bukti']))
                            <p class="text-[11px] text-text-secondary">
                                <span class="font-medium text-text-main">Bukti:</span> {{ $o['bukti'] }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- SENSITIVITAS --}}
    @if ($hasil->sensitivitas)
        <h4 class="text-xs font-semibold text-text-main mb-1">Analisis Sensitivitas</h4>
        <p class="text-[11px] text-text-secondary mb-2">
            Bukan tiga angka untuk dipilih yang paling bagus, melainkan ukuran seberapa rapuh rasio ini
            terhadap asumsi yang dipilih sendiri. Rasio yang berubah drastis hanya karena attribution
            digeser 20 poin tidak boleh dilaporkan sebagai angka tunggal. Proxy sengaja tidak ikut
            digeser — nilainya datang dari sumber luar.
        </p>
        <div class="overflow-x-auto -mx-5 px-5">
            <table class="w-full text-xs min-w-[420px]">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border-soft">
                        <th class="py-2 font-medium">Skenario</th>
                        <th class="py-2 font-medium text-right">Social Value</th>
                        <th class="py-2 font-medium text-right">Investasi</th>
                        <th class="py-2 font-medium text-right">Net Social Value</th>
                        <th class="py-2 font-medium text-right">Rasio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-soft">
                    @foreach ($hasil->sensitivitas as $kunci => $s)
                        <tr class="{{ $kunci === 'dasar' ? 'bg-surface-bg' : '' }}">
                            <td class="py-2 text-text-main">{{ $s['label'] }}</td>
                            <td class="py-2 text-right text-text-secondary">{{ rupiah($s['total_nilai_dampak']) }}</td>
                            {{-- Investasi sama di ketiga skenario, dan justru itu
                                 pesannya: yang bergeser hanyalah asumsi, bukan
                                 uang yang benar-benar dikeluarkan. --}}
                            <td class="py-2 text-right text-text-secondary">
                                {{ rupiah($s['total_investasi'] ?? $hasil->total_investasi) }}
                            </td>
                            <td class="py-2 text-right text-text-secondary">{{ rupiah($s['nilai_sosial_bersih']) }}</td>
                            <td class="py-2 text-right font-medium text-text-main">
                                {{ $s['rasio'] === null ? '—' : number_format($s['rasio'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- PERSETUJUAN --}}
    @if ($bisaSetujui && ! $hasil->sudahDisetujui())
        <form method="POST" action="{{ route('impact.setujui', [$program, $hasil]) }}"
              class="mt-5 pt-5 border-t border-border-soft"
              onsubmit="return confirm('Setujui hasil ini sebagai Final? Program akan terkunci dari perubahan.')">
            @csrf
            <label class="block text-xs font-medium text-text-main mb-1">
                Catatan Review <span class="text-text-secondary font-normal">(boleh kosong)</span>
            </label>
            <textarea name="catatan_review" rows="2" maxlength="2000"
                      placeholder="Apa yang diperiksa sebelum menyetujui angka ini?"
                      class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm"></textarea>
            <p class="text-[11px] text-text-secondary mt-1 mb-3">
                Menyetujui menandai hasil ini Final dan mengunci program dari perubahan. Keduanya tidak
                bisa dibatalkan lewat antarmuka — itulah arti &ldquo;sudah dilaporkan&rdquo;.
            </p>
            <button type="submit"
                    class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Setujui sebagai Final
            </button>
        </form>
    @endif

    @if ($hasil->catatan_review)
        <div class="mt-4 pt-4 border-t border-border-soft">
            <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-1">Catatan Review</p>
            <p class="text-xs text-text-main">{{ $hasil->catatan_review }}</p>
        </div>
    @endif
</div>
