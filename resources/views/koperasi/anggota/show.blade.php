@extends('layouts.app')

@section('title', 'Detail Anggota')

@section('content')
<div class="space-y-4 max-w-3xl">

    {{-- INFORMASI ANGGOTA --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-semibold text-text-main">{{ $member->namaTampil() }}</h2>
                <p class="text-sm text-text-secondary">{{ $member->nomor_anggota }}</p>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                {{ $member->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                {{ $member->status }}
            </span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-text-secondary">Nomor HP</dt>
                <dd class="font-medium text-text-main">{{ $member->nomorHpTampil() ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Tanggal Bergabung</dt>
                <dd class="font-medium text-text-main">{{ optional($member->tanggal_bergabung)->format('d-m-Y') ?: '— (data belum lengkap)' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-text-secondary">Alamat</dt>
                <dd class="font-medium text-text-main">{{ $member->alamatTampil() ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Unit Usaha</dt>
                <dd class="font-medium text-text-main">{{ $member->unitUsaha->nama ?? '—' }}</dd>
            </div>
            @if ($member->dariPeserta())
                <div>
                    <dt class="text-text-secondary">Nama Usaha</dt>
                    <dd class="font-medium text-text-main">{{ $member->namaUsahaTampil() ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Jenis Usaha</dt>
                    <dd class="font-medium text-text-main">{{ $member->jenisUsahaTampil() ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-text-secondary">Email</dt>
                    <dd class="font-medium text-text-main">{{ $member->emailTampil() ?: '—' }}</dd>
                </div>
            @endif
            @if ($member->keterangan)
                <div class="sm:col-span-2">
                    <dt class="text-text-secondary">Keterangan</dt>
                    <dd class="font-medium text-text-main">{{ $member->keterangan }}</dd>
                </div>
            @endif
        </dl>

        {{-- HUBUNGAN DENGAN CAHAYA PRENEUR --}}
        @if ($member->participant)
            <div class="mt-4 pt-4 border-t border-border-soft">
                <p class="text-xs text-text-secondary mb-1">
                    Identitas anggota ini bersumber dari data peserta Cahaya Preneur — bukan salinan terpisah.
                </p>
                {{-- Tautannya hanya dirender untuk yang memang boleh membuka
                     Detail Peserta. Tim Koperasi boleh MEMAKAI data peserta,
                     tapi tidak diberi jalan masuk ke modul Cahaya Preneur —
                     kalau tetap dirender, tautannya cuma berujung 403. --}}
                @can('view', $member->participant)
                    <a href="{{ route('participants.show', $member->participant) }}" class="text-sm text-status-info hover:underline">
                        {{ $member->participant->nama_peserta }} — {{ $member->participant->nama_usaha }}
                    </a>
                @else
                    <p class="text-sm text-text-main">{{ $member->participant->nama_peserta }} — {{ $member->participant->nama_usaha }}</p>
                @endcan
            </div>
        @endif

        <div class="mt-6 flex gap-2 items-center flex-wrap">
            <a href="{{ route('koperasi.anggota.edit', $member) }}" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Edit
            </a>
            <form method="POST" action="{{ route('koperasi.anggota.destroy', $member) }}"
                  onsubmit="return confirm('Hapus Permanen?

Data anggota ini akan dihapus dan TIDAK dapat dipulihkan.

Anggota yang masih punya riwayat simpanan tidak bisa dihapus — sistem akan menolak dan menyebutkan alasannya.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="text-sm font-medium text-status-danger border border-status-danger/30 px-5 py-2.5 rounded-lg hover:bg-status-danger/5 transition">
                    Hapus Permanen
                </button>
            </form>
            <a href="{{ route('koperasi.anggota.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Kembali
            </a>
        </div>
    </div>

    {{-- AKSES ANGGOTA — Nomor Anggota + PIN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Akses "Cek Simpanan Saya"</h3>
        <p class="text-xs text-text-secondary mb-4">Anggota bisa cek simpanannya sendiri pakai Nomor Anggota + PIN, tanpa perlu akun login sistem.</p>

        @if (session('pin_baru'))
            <div class="mb-4 rounded-xl bg-gold-light border border-status-warning/30 px-4 py-3 text-sm">
                <p class="text-text-main font-medium mb-1">PIN Baru: <span class="text-lg tracking-widest font-bold">{{ session('pin_baru') }}</span></p>
                <p class="text-xs text-text-secondary">Catat/sampaikan ke {{ $member->namaTampil() }} SEKARANG — PIN ini tidak akan ditampilkan lagi setelah halaman ini ditinggalkan.</p>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-text-secondary">Nomor Anggota</p>
                <p class="text-sm font-semibold text-text-main">{{ $member->nomor_anggota }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-text-secondary mb-1">
                    Status PIN:
                    @if ($member->hasPin())
                        <span class="text-status-success font-medium">🟢 Sudah diatur</span>
                    @else
                        <span class="text-text-secondary font-medium">⚪ Belum diatur</span>
                    @endif
                </p>
                <form method="POST" action="{{ route('koperasi.anggota.generate-pin', $member) }}"
                      onsubmit="return confirm('{{ $member->hasPin() ? 'PIN lama akan tidak berlaku lagi. Yakin buat PIN baru?' : 'Buat PIN baru untuk anggota ini?' }}');">
                    @csrf
                    <button type="submit" class="text-xs bg-cahaya-accent text-white font-medium px-3 py-1.5 rounded-lg hover:opacity-90 transition">
                        {{ $member->hasPin() ? 'Generate PIN Baru' : 'Generate PIN' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- LINK CEK SIMPANAN

             URL-nya diambil dari route(), bukan ditulis tetap: alamat
             aplikasi berbeda antara komputer kantor, jaringan lokal, dan
             nanti server — link yang di-hard-code akan salah di salah
             satunya tanpa ada yang menyadarinya.

             Link ini SAMA untuk semua anggota dan tidak memuat rahasia apa
             pun. Yang membedakan siapa yang masuk adalah Nomor Anggota +
             PIN yang diketik di halaman itu — bukan alamatnya. Karena itu
             link ini aman dibagikan lewat WhatsApp, dan tidak ada nomor
             urut yang bisa ditebak orang lain. --}}
        <div class="mt-5 pt-5 border-t border-border-soft">
            <p class="text-xs text-text-secondary mb-2">
                Bagikan link ini ke {{ $member->namaTampil() }} lewat WhatsApp. Ia masuk dengan
                Nomor Anggota <span class="font-semibold text-text-main">{{ $member->nomor_anggota }}</span>
                dan PIN-nya sendiri.
            </p>

            <div class="flex flex-col sm:flex-row gap-2">
                <input id="linkCekSimpanan" type="text" readonly
                       value="{{ route('anggota-akses.login') }}"
                       onclick="this.select();"
                       class="flex-1 min-w-0 rounded-lg border border-border-soft px-3 py-2 text-sm bg-surface-bg text-text-secondary">
                <button type="button" onclick="salinLinkCekSimpanan()"
                        class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition whitespace-nowrap">
                    Salin Link Cek Simpanan
                </button>
                <a href="{{ route('anggota-akses.login') }}" target="_blank" rel="noopener"
                   class="border border-border-soft text-text-main text-sm font-medium px-4 py-2 rounded-lg hover:bg-surface-bg transition whitespace-nowrap text-center">
                    Buka Cek Simpanan
                </a>
            </div>

            <p id="salinCekSimpananBerhasil" class="text-xs text-status-success mt-2 hidden">Link berhasil disalin!</p>
            <p id="salinCekSimpananGagal" class="text-xs text-status-warning mt-2 hidden">
                Tidak bisa menyalin otomatis di browser ini — link sudah kami pilih (highlight),
                tekan lama lalu pilih "Salin"/"Copy" secara manual.
            </p>

            @unless ($member->hasPin())
                <p class="text-xs text-status-warning mt-2">
                    PIN belum diatur — link ini belum bisa dipakai {{ $member->namaTampil() }}.
                    Tekan "Generate PIN" di atas lebih dulu.
                </p>
            @endunless
        </div>
    </div>

    {{-- RINGKASAN SIMPANAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-4">Ringkasan Simpanan</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-text-secondary">Simpanan Pokok</p>
                <p class="text-lg font-bold text-cahaya-primary">{{ rupiah($ringkasanSimpanan['pokok']) }}</p>
            </div>
            <div>
                <p class="text-text-secondary">Simpanan Wajib</p>
                <p class="text-lg font-bold text-status-info">{{ rupiah($ringkasanSimpanan['wajib']) }}</p>
            </div>
            <div>
                <p class="text-text-secondary">Simpanan Sukarela</p>
                <p class="text-lg font-bold text-cahaya-primary">{{ rupiah($ringkasanSimpanan['sukarela']) }}</p>
            </div>
            <div>
                <p class="text-text-secondary">Total Simpanan</p>
                <p class="text-xl font-bold text-cahaya-accent">{{ rupiah($ringkasanSimpanan['total']) }}</p>
            </div>
        </div>
    </div>

    {{-- RIWAYAT SIMPANAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-4">Riwayat Simpanan</h3>

        @if ($member->savings->isEmpty())
            <p class="text-sm text-text-secondary italic mb-4">Belum ada riwayat simpanan.</p>
        @else
            <div class="overflow-x-auto -mx-1 px-1">
                <table class="w-full text-sm min-w-[620px] mb-5">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border-soft">
                        <th class="py-2 font-medium">Tanggal</th>
                        <th class="py-2 font-medium">Jenis</th>
                        <th class="py-2 font-medium">Nominal</th>
                        <th class="py-2 font-medium">Keterangan</th>
                        <th class="py-2 font-medium">Status</th>
                        <th class="py-2 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Entri yang DIBATALKAN tetap ditampilkan di sini beserta
                         alasannya — itulah jejak koreksinya. Yang berubah cuma
                         ia tidak lagi ikut menambah saldo di kartu ringkasan. --}}
                    @foreach ($member->savings as $s)
                        <tr class="border-b border-border-soft last:border-0 {{ $s->dibatalkan() ? 'bg-surface-bg/60' : '' }}">
                            <td class="py-2 text-text-secondary">{{ $s->tanggal->format('d-m-Y') }}</td>
                            <td class="py-2 text-text-main">{{ $s->jenis_simpanan }}</td>
                            <td class="py-2 font-semibold text-right {{ $s->dibatalkan() ? 'text-text-secondary line-through' : 'text-text-main' }}">
                                {{ rupiah($s->nominal) }}
                            </td>
                            <td class="py-2 text-text-secondary">
                                {{ $s->keterangan ?: '—' }}
                                @if ($s->dikoreksi_dari_id)
                                    <span class="block text-[11px] text-status-info">Pengganti dari entri #{{ $s->dikoreksi_dari_id }}</span>
                                @endif
                                @if ($s->dibatalkan() && $s->alasan_koreksi)
                                    <span class="block text-[11px] text-status-warning">Alasan: {{ $s->alasan_koreksi }}</span>
                                @endif
                            </td>
                            <td class="py-2">
                                @if ($s->dibatalkan())
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                @elseif ($s->terkunci())
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gold-light text-cahaya-accent">Terkunci</span>
                                @else
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary-light text-status-success">Tercatat</span>
                                @endif
                            </td>
                            <td class="py-2 space-x-2 whitespace-nowrap">
                                {{-- Kedua aksi dikendalikan MemberSavingPolicy, yang
                                     mencabut izinnya berdasarkan KEADAAN entri (terkunci /
                                     sudah dibatalkan), bukan berdasarkan role. --}}
                                @can('update', $s)
                                    <a href="{{ route('koperasi.savings.edit', $s) }}" class="text-xs text-status-info hover:underline">Perbaiki</a>
                                @endcan
                                @can('batalkan', $s)
                                    <button type="button" class="text-xs text-status-danger hover:underline"
                                            onclick="bukaFormKoreksi({{ $s->id }})">Batalkan</button>
                                @endcan
                                @can('pulihkan', $s)
                                    <form method="POST" action="{{ route('koperasi.savings.pulihkan', $s) }}" class="inline"
                                          onsubmit="return confirm('Pulihkan entri simpanan ini?

Nominalnya akan kembali dihitung ke total simpanan anggota.');">
                                        @csrf
                                        <button type="submit" class="text-xs text-cahaya-primary hover:underline">Pulihkan</button>
                                    </form>
                                @endcan
                                {{-- Entri dibatalkan yang TIDAK bisa dipulihkan sudah punya
                                     penggantinya sendiri. Alasannya disebutkan, bukan cuma
                                     tombolnya dihilangkan — supaya operator tahu apa yang
                                     harus dilakukan kalau memang perlu memulihkannya. --}}
                                @if ($s->dibatalkan() && $s->punyaPenggantiBerlaku())
                                    <span class="text-[11px] text-text-secondary" title="Batalkan dulu entri penggantinya kalau entri ini memang perlu dipulihkan.">
                                        sudah diganti
                                    </span>
                                @endif

                                {{-- HAPUS PERMANEN — hanya muncul untuk entri yang SUDAH
                                     dibatalkan, jadi membuangnya selalu keputusan KEDUA yang
                                     diambil pada waktu berbeda dari pembatalannya. Entri yang
                                     sudah jadi mata rantai koreksi entri lain tetap ditolak
                                     PenghapusanPermanenService, beserta alasannya.

                                     Saldo anggota tidak disimpan di kolom mana pun — selalu
                                     dijumlahkan dari entri-entri ini — jadi begitu barisnya
                                     hilang, saldonya ikut menyesuaikan sendiri. --}}
                                @if ($s->dibatalkan())
                                    @can('hapusPermanen', $s)
                                        <form method="POST" action="{{ route('koperasi.savings.destroy', $s) }}" class="inline"
                                              onsubmit="return confirm('Hapus Permanen?

Data yang dihapus tidak dapat dipulihkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-status-danger hover:underline">Hapus Permanen</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>

                        @can('batalkan', $s)
                            {{-- Form koreksi per entri, tersembunyi sampai dibutuhkan
                                 supaya tabelnya tetap bersih. --}}
                            <tr id="koreksi-{{ $s->id }}" class="hidden">
                                <td colspan="6" class="py-3">
                                    <form method="POST" action="{{ route('koperasi.savings.batalkan', $s) }}"
                                          class="bg-surface-bg border border-border-soft rounded-xl p-4 space-y-3">
                                        @csrf
                                        <p class="text-xs text-text-main font-medium">
                                            Batalkan {{ $s->jenis_simpanan }} {{ rupiah($s->nominal) }}
                                            tanggal {{ $s->tanggal->format('d-m-Y') }}
                                        </p>
                                        <p class="text-[11px] text-text-secondary">
                                            Entrinya TIDAK dihapus — hanya berhenti dihitung ke saldo, dan alasannya
                                            ikut tersimpan. Kalau angkanya cuma salah ketik, centang entri pengganti
                                            lalu isi nilai yang benar.
                                        </p>

                                        <div>
                                            <label class="block text-xs font-medium text-text-main mb-1">Alasan Koreksi</label>
                                            <input type="text" name="alasan_koreksi" required
                                                   placeholder="Contoh: salah ketik nominal, seharusnya Rp50.000"
                                                   class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                                        </div>

                                        <label class="flex items-center gap-2 text-xs text-text-main">
                                            <input type="checkbox" name="buat_pengganti" value="1" class="rounded border-border-soft"
                                                   onchange="document.getElementById('pengganti-{{ $s->id }}').classList.toggle('hidden', !this.checked)">
                                            Buat entri pengganti dengan nilai yang benar
                                        </label>

                                        <div id="pengganti-{{ $s->id }}" class="hidden grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-text-main mb-1">Nominal Pengganti (Rp)</label>
                                                <input type="number" name="nominal_pengganti" min="0" step="1000"
                                                       value="{{ (int) $s->nominal }}"
                                                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-text-main mb-1">Tanggal Pengganti</label>
                                                <input type="date" name="tanggal_pengganti"
                                                       value="{{ $s->tanggal->format('Y-m-d') }}"
                                                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                                            </div>
                                        </div>

                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-status-danger text-white text-xs font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                                                Batalkan Entri
                                            </button>
                                            <button type="button" class="text-xs text-text-secondary px-4 py-2 hover:text-text-main"
                                                    onclick="bukaFormKoreksi({{ $s->id }})">Tutup</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif

        {{-- TAMBAH SIMPANAN --}}
        <details class="border-t border-border-soft pt-4">
            <summary class="text-sm font-medium text-cahaya-primary cursor-pointer">+ Tambah Simpanan</summary>

            <form method="POST" action="{{ route('koperasi.savings.store', $member) }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Jenis Simpanan</label>
                        <select name="jenis_simpanan" required class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                            <option value="">— Pilih —</option>
                            @foreach ($jenisOptions as $jenis)
                                <option value="{{ $jenis }}">{{ $jenis }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Tanggal</label>
                        <input type="date" name="tanggal" required value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Nominal (Rp)</label>
                        <input type="number" min="0" step="1" name="nominal" required class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary">(boleh kosong)</span></label>
                    <input type="text" name="keterangan" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                </div>
                <button type="submit" class="bg-cahaya-accent text-white text-sm font-medium px-5 py-2 rounded-lg hover:opacity-90 transition">
                    Simpan Transaksi
                </button>
            </form>
        </details>
    </div>
</div>
<script>
    // Membuka/menutup form koreksi satu entri simpanan. Sengaja sesederhana
    // ini: tabelnya tetap bersih, dan form baru muncul saat benar-benar dipakai.
    // Ditaruh INLINE di dalam blok konten karena layout CahayaOS belum punya
    // stack script tersendiri — menambahkan stack baru bukan bagian scope ini.
    function bukaFormKoreksi(id) {
        document.getElementById('koreksi-' + id)?.classList.toggle('hidden');
    }

    // Menyalin link Cek Simpanan.
    //
    // Pola yang sama dengan Link Laporan Omzet di halaman detail peserta —
    // sengaja tidak dibuat cara kedua untuk pekerjaan yang sama.
    //
    // Fallback-nya BUKAN pelengkap: navigator.clipboard hanya tersedia pada
    // secure context. Begitu CahayaOS dibuka lewat alamat jaringan lokal
    // (http://192.168.x.x) alih-alih localhost, jalur utamanya mati — dan
    // justru itulah cara Tim Koperasi membukanya dari ponsel.
    function salinCekSimpananSukses() {
        const info = document.getElementById('salinCekSimpananBerhasil');
        info.classList.remove('hidden');
        setTimeout(() => info.classList.add('hidden'), 2500);
    }

    function salinCekSimpananFallback(input) {
        input.focus();
        input.select();
        input.setSelectionRange(0, 99999);

        try {
            if (document.execCommand('copy')) {
                salinCekSimpananSukses();
                return;
            }
        } catch (e) {
            // lanjut ke pesan manual
        }

        const gagal = document.getElementById('salinCekSimpananGagal');
        gagal.classList.remove('hidden');
        setTimeout(() => gagal.classList.add('hidden'), 4000);
    }

    function salinLinkCekSimpanan() {
        const input = document.getElementById('linkCekSimpanan');

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value)
                .then(salinCekSimpananSukses)
                .catch(() => salinCekSimpananFallback(input));
        } else {
            salinCekSimpananFallback(input);
        }
    }
</script>
@endsection
