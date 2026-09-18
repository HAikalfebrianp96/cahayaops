@extends('layouts.app')

@section('title', 'Edit Target Pendamping')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-5xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">
        Edit Target — {{ $target->pendamping->nama }}
    </h2>
    <p class="text-xs text-text-secondary mb-6">
        Periode saat ini: {{ $target->periodeLabel() }}. Mengubah target akan langsung mengubah
        perhitungan KPI periode tersebut — riwayatnya dihitung ulang, bukan disimpan.
    </p>

    <form method="POST" action="{{ route('target-pendamping.update', $target) }}">
        @method('PUT')
        @include('target-pendamping._form')
    </form>
</div>
@endsection
