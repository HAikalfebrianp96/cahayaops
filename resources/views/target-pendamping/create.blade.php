@extends('layouts.app')

@section('title', 'Tetapkan Target Pendamping')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-5xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Tetapkan Target Pendamping</h2>
    <p class="text-xs text-text-secondary mb-6">
        Target individual selalu menang atas target bawaan sistem. Satu pendamping hanya boleh
        punya satu target per periode.
    </p>

    <form method="POST" action="{{ route('target-pendamping.store') }}">
        @include('target-pendamping._form')
    </form>
</div>
@endsection
