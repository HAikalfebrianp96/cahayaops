@extends('layouts.app')

@section('title', 'Tambah Akun Kas & Bank')

@section('content')
<div class="max-w-xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Tambah Akun Kas & Bank</h2>
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('koperasi.kas-bank.store') }}">
            @include('koperasi.kas-bank._form')
        </form>
    </div>
</div>
@endsection
