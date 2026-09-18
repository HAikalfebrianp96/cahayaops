@extends('layouts.app')

@section('title', 'Tambah Pendamping')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-6">Tambah Pendamping</h2>

    <form method="POST" action="{{ route('pendampings.store') }}">
        @include('pendampings._form')
    </form>
</div>
@endsection
