@extends('layouts.app')

@section('title', 'KPI & Kinerja Pendamping')

@section('content')
{{-- ============================================================
     KPI & KINERJA PENDAMPING.

     Untuk Pendamping, halaman ini HANYA berisi barisnya sendiri —
     controller sudah menyaringnya di level query, dan filter ?pendamping_id
     dari URL diabaikan untuknya.

     Tabel dibungkus overflow-x-auto DAN diberi versi kartu untuk layar
     kecil: tabel 10 kolom tidak mungkin terbaca di ponsel.
     ============================================================ --}}
<div class="space-y-5">

    {{-- ============================================================
         PERINGATAN: SELURUH PENILAIAN DI BAWAH MASIH MEMAKAI ANGKA BAWAAN

         Kolom "Sumber target" di tabel sudah lama menyebutkan ini per baris,
         tapi per baris berarti baru ketahuan setelah dibaca satu per satu.
         Keadaan "belum ada target yang ditetapkan untuk SIAPA PUN" adalah
         keadaan yang berbeda sifatnya — itu bukan detail satu pendamping,
         melainkan pekerjaan yang belum dilakukan Admin.

         Hanya muncul kalau memang seluruh barisnya begitu. Peringatan yang
         tetap tampil setelah sebagian target diisi akan berhenti dibaca.
         ============================================================ --}}
    @if ($isAdmin && $jumlahDinilai > 0 && $jumlahPakaiDefault === $jumlahDinilai)
        <div class="rounded-xl bg-gold-light border border-status-warning/40 px-4 py-3">
            <p class="text-sm font-semibold text-cahaya-primary">
                Belum ada target yang ditetapkan untuk periode ini
            </p>
            <p class="text-xs text-text-secondary mt-1">
                Seluruh {{ $jumlahDinilai }} penilaian di bawah dihitung memakai target bawaan sistem,
                bukan target yang disepakati. Selama target belum ditetapkan, status seperti
                &ldquo;Rendah&rdquo; menilai pendamping terhadap angka yang belum tentu menjadi
                tanggung jawabnya.
                <a href="{{ route('target-pendamping.create') }}" class="text-cahaya-secondary hover:underline">Tetapkan target &rarr;</a>
            </p>
        </div>
    @endif

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                {{ $isAdmin ? 'KPI & Kinerja Pendamping' : 'KPI Saya' }}
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                Periode {{ $labelPeriode }} &bull; dihitung dari data pendampingan yang tersimpan.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('kpi-pendamping.export', request()->query()) }}"
               class="text-sm text-text-main border border-border-soft px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
                Export Excel
            </a>
            <a href="{{ route('kpi-pendamping.export-pdf', request()->query()) }}"
               class="text-sm bg-cahaya-primary text-white font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Download PDF
            </a>
            @if ($isAdmin)
                <a href="{{ route('target-pendamping.index') }}"
                   class="text-sm text-text-main border border-border-soft px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
                    Kelola Target
                </a>
            @endif
        </div>
    </div>

    {{-- FILTER --}}
    <form method="GET" action="{{ route('kpi-pendamping.index') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div>
            <label class="block text-[11px] text-text-secondary mb-1">Periode</label>
            <select name="periode" class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                @foreach (['Bulanan', 'Triwulan', 'Tahunan'] as $p)
                    <option value="{{ $p }}" @selected(($filters['periode'] ?? 'Bulanan') === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-text-secondary mb-1">Bulan</label>
            <select name="bulan" class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                @foreach ($bulanOptions as $nomor => $nama)
                    <option value="{{ $nomor }}" @selected((int) ($filters['bulan'] ?? now()->month) === $nomor)>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-text-secondary mb-1">Tahun</label>
            <select name="tahun" class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                @foreach ($tahunOptions as $t)
                    <option value="{{ $t }}" @selected((int) ($filters['tahun'] ?? now()->year) === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] text-text-secondary mb-1">Status Kinerja</label>
            <select name="status" class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                <option value="">Semua Status</option>
                @foreach ($statusOptions as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>

        @if ($isAdmin)
            <div>
                <label class="block text-[11px] text-text-secondary mb-1">Pendamping</label>
                <select name="pendamping_id" class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                    <option value="">Semua Pendamping</option>
                    @foreach ($pendampings as $p)
                        <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-text-secondary mb-1">Cari Nama</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama pendamping..."
                       class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
            </div>
        @endif

        <div class="sm:col-span-2 lg:col-span-6 flex gap-2">
            <button type="submit" class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Terapkan
            </button>
            <a href="{{ route('kpi-pendamping.index') }}" class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
        </div>
    </form>

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3">
        @foreach ([
            ['label' => 'Total Pendamping', 'nilai' => $ringkasan['total_pendamping'], 'warna' => 'text-text-main'],
            ['label' => 'Rata-rata KPI', 'nilai' => $ringkasan['rata_rata'] !== null ? $ringkasan['rata_rata'] . '%' : '—', 'warna' => 'text-cahaya-primary'],
            ['label' => 'Sangat Baik', 'nilai' => $ringkasan['sangat_baik'], 'warna' => 'text-status-success'],
            ['label' => 'Baik', 'nilai' => $ringkasan['baik'], 'warna' => 'text-status-info'],
            ['label' => 'Perlu Perhatian', 'nilai' => $ringkasan['perlu_perhatian'], 'warna' => 'text-status-warning'],
            ['label' => 'Rendah', 'nilai' => $ringkasan['rendah'], 'warna' => 'text-status-danger'],
            // Pendamping yang targetnya belum pernah diatur berdiri sendiri,
            // bukan dilebur ke salah satu dari empat penilaian di atas.
            ['label' => 'Target Belum Diatur', 'nilai' => $ringkasan['tanpa_target'] ?? 0, 'warna' => 'text-text-secondary'],
        ] as $kartu)
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1 leading-tight">{{ $kartu['label'] }}</p>
                <p class="text-xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($rapor->isEmpty())
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm text-center py-14">
            <p class="text-sm font-medium text-text-main mb-1">Tidak Ada Data</p>
            <p class="text-xs text-text-secondary">Tidak ada pendamping yang cocok dengan filter ini.</p>
        </div>
    @else
        {{-- TABEL — layar sedang ke atas --}}
        <div class="hidden md:block bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-5 py-3 font-medium">Pendamping</th>
                            <th class="px-5 py-3 font-medium">Binaan</th>
                            <th class="px-5 py-3 font-medium">Pendampingan</th>
                            <th class="px-5 py-3 font-medium">Follow Up</th>
                            <th class="px-5 py-3 font-medium">Kunjungan</th>
                            <th class="px-5 py-3 font-medium">Beban</th>
                            <th class="px-5 py-3 font-medium">Progress</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rapor as $r)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-5 py-3">
                                    <a href="{{ route('kpi-pendamping.show', $r['pendamping']) }}"
                                       class="font-medium text-text-main hover:underline">
                                        {{ $r['pendamping']->nama }}
                                    </a>
                                    <p class="text-[11px] text-text-secondary">
                                        {{ $r['pakai_default'] ? 'Target bawaan sistem' : 'Target individual' }}
                                    </p>
                                </td>
                                @foreach (['target_jumlah_binaan', 'target_pendampingan', 'target_follow_up', 'target_kunjungan'] as $kolom)
                                    <td class="px-5 py-3 text-text-secondary whitespace-nowrap">
                                        {{ $r['indikator'][$kolom]['aktual'] }}
                                        <span class="text-text-secondary">/ {{ $r['indikator'][$kolom]['target'] ?? '—' }}</span>
                                    </td>
                                @endforeach
                                <td class="px-5 py-3">
                                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $r['beban']['warna'] }}">
                                        {{ $r['beban']['kategori'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 min-w-[120px]">
                                    @if ($r['kpi'] === null)
                                        <span class="text-xs text-text-secondary italic">Tanpa target</span>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-2 rounded-full bg-surface-bg overflow-hidden">
                                                <div class="h-full rounded-full bg-cahaya-primary" style="width: {{ $r['kpi'] }}%"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-text-main">{{ $r['kpi'] }}%</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $r['warna'] }}">
                                        {{ $r['status'] ?? 'Belum ada target' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- KARTU — layar kecil. Tabel 8 kolom tidak mungkin terbaca di ponsel,
             jadi datanya disusun ulang, bukan sekadar digeser mendatar. --}}
        <div class="md:hidden space-y-3">
            @foreach ($rapor as $r)
                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0">
                            <a href="{{ route('kpi-pendamping.show', $r['pendamping']) }}"
                               class="font-semibold text-text-main hover:underline">{{ $r['pendamping']->nama }}</a>
                            <p class="text-[11px] text-text-secondary">
                                {{ $r['pakai_default'] ? 'Target bawaan' : 'Target individual' }}
                            </p>
                        </div>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full flex-shrink-0 {{ $r['warna'] }}">
                            {{ $r['status'] ?? 'Tanpa target' }}
                        </span>
                    </div>

                    @if ($r['kpi'] !== null)
                        <div class="flex items-center gap-2 mb-3">
                            <div class="flex-1 h-2.5 rounded-full bg-surface-bg overflow-hidden">
                                <div class="h-full rounded-full bg-cahaya-primary" style="width: {{ $r['kpi'] }}%"></div>
                            </div>
                            <span class="text-sm font-bold text-text-main">{{ $r['kpi'] }}%</span>
                        </div>
                    @endif

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        @foreach (['target_jumlah_binaan' => 'Binaan', 'target_pendampingan' => 'Pendampingan',
                                   'target_follow_up' => 'Follow Up', 'target_kunjungan' => 'Kunjungan'] as $kolom => $label)
                            <div>
                                <dt class="text-[11px] text-text-secondary">{{ $label }}</dt>
                                <dd class="text-text-main">
                                    {{ $r['indikator'][$kolom]['aktual'] }} / {{ $r['indikator'][$kolom]['target'] ?? '—' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    <p class="mt-3 pt-3 border-t border-border-soft text-[11px] text-text-secondary">
                        Beban binaan:
                        <span class="font-medium px-1.5 py-0.5 rounded-full {{ $r['beban']['warna'] }}">
                            {{ $r['beban']['kategori'] }} ({{ $r['beban']['jumlah'] }})
                        </span>
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-[11px] text-text-secondary">
        Indikator yang TIDAK punya target tidak ikut dihitung ke KPI — "tidak ditargetkan" berbeda
        dari "ditargetkan tapi belum tercapai". Ambang beban: Ringan &le; {{ $ambangBeban['ringan'] }},
        Normal &le; {{ $ambangBeban['normal'] }}, Tinggi &le; {{ $ambangBeban['tinggi'] }}, di atas itu Sangat Tinggi.
    </p>
</div>
@endsection
