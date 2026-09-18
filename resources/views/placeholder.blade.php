@extends('layouts.app')

@section('title', $judul)

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
    <h2 class="text-lg font-semibold text-gray-700 mb-2">{{ $judul }}</h2>
    <p class="text-sm text-gray-500 max-w-md mx-auto">{{ $keterangan }}</p>
</div>
@endsection
