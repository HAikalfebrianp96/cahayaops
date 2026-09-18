{{-- ============================================================
     TOMBOL TINDAKAN UNTUK SATU PESERTA

     Dipakai daftar Prioritas dan daftar Follow Up. Keduanya menjawab
     pertanyaan yang sama, jadi keduanya harus menawarkan tombol yang sama
     untuk peserta yang sama — itulah alasan partial ini ada.

     Isi daftarnya ditentukan FollowUpService::tindakan(), bukan di sini.
     Yang diurus berkas ini hanya cara menampilkannya.

     Diperlukan:
       $tindakan — keluaran FollowUpService::tindakan()
       $peserta  — Participant
     ============================================================ --}}
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 sm:justify-end">
    @foreach ($tindakan as $t)
        <a href="{{ route($t['rute'], $peserta) }}{{ $t['fragmen'] }}"
           class="text-[11px] whitespace-nowrap hover:underline {{ $t['utama'] ? 'text-cahaya-primary font-medium' : 'text-text-secondary' }}">
            {{ $t['label'] }}
        </a>
    @endforeach

    <a href="{{ route('participants.show', $peserta) }}"
       class="text-[11px] text-status-info hover:underline whitespace-nowrap">Lihat Peserta</a>
</div>
