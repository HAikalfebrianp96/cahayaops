@extends('layouts.app')

@section('title', 'Edit Pengeluaran')

@section('content')
<div class="max-w-xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Edit Pengeluaran</h2>
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('koperasi.pengeluaran.update', $pengeluaran) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('koperasi.pengeluaran._form')
        </form>
    </div>
</div>
@endsection
