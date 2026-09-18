@extends('layouts.app')

@section('title', 'Edit Pendamping')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-6">Edit Pendamping — {{ $pendamping->nama }}</h2>

    <form method="POST" action="{{ route('pendampings.update', $pendamping) }}">
        @method('PUT')
        @include('pendampings._form')
    </form>
</div>
@endsection
